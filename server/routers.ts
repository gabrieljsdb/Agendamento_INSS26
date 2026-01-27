import { z } from "zod";
import { TRPCError } from "@trpc/server";
import { publicProcedure, protectedProcedure, router } from "./_core/trpc";
import { COOKIE_NAME } from "@shared/const";
import { getSessionCookieOptions } from "./_core/cookies";
import { systemRouter } from "./_core/systemRouter";
import {
  getUserByCPF,
  createAppointment,
  getUserAppointments,
  getUpcomingAppointments,
  cancelAppointment,
  getAppointmentsByDate,
  getOrCreateAppointmentLimit,
  incrementAppointmentCount,
  updateLastCancellation,
  createBlockedSlot,
  getBlockedSlotsForDate,
  deleteBlockedSlot,
  logAuditAction,
} from "./db";
import { soapAuthService } from "./services/soapAuthService";
import { appointmentValidationService } from "./services/appointmentValidationService";
import { emailService } from "./services/emailService";

export const appRouter = router({
  system: systemRouter,

  auth: router({
    me: publicProcedure.query((opts) => opts.ctx.user),

    logout: publicProcedure.mutation(({ ctx }) => {
      const cookieOptions = getSessionCookieOptions(ctx.req);
      ctx.res.clearCookie(COOKIE_NAME, { ...cookieOptions, maxAge: -1 });
      return {
        success: true,
      } as const;
    }),

    /**
     * Login com CPF e senha via SOAP OAB/SC
     */
    loginWithSOAP: publicProcedure
      .input(
        z.object({
          cpf: z.string().min(11, "CPF inválido"),
          password: z.string().min(1, "Senha obrigatória"),
        })
      )
      .mutation(async ({ input, ctx }) => {
        try {
          // Autentica contra SOAP
          const soapResult = await soapAuthService.authenticate(input.cpf, input.password);

          if (!soapResult.success) {
            throw new TRPCError({
              code: "UNAUTHORIZED",
              message: soapResult.message || "Credenciais inválidas",
            });
          }

          // Busca ou cria usuário no banco
          let user = await getUserByCPF(soapResult.cpf);

          if (!user) {
            // Cria novo usuário
            const { upsertUser } = await import("./db");
            await upsertUser({
              openId: `soap_${soapResult.cpf}`,
              cpf: soapResult.cpf,
              oab: soapResult.oab,
              name: soapResult.name,
              email: soapResult.email,
              phone: soapResult.phone,
              loginMethod: "soap",
            });

            user = await getUserByCPF(soapResult.cpf);
          } else {
            // Atualiza dados do usuário
            const { upsertUser } = await import("./db");
            await upsertUser({
              openId: user.openId,
              cpf: soapResult.cpf,
              oab: soapResult.oab,
              name: soapResult.name,
              email: soapResult.email,
              phone: soapResult.phone,
              lastSignedIn: new Date(),
            });
          }

          if (!user) {
            throw new TRPCError({
              code: "INTERNAL_SERVER_ERROR",
              message: "Erro ao criar usuário",
            });
          }

          // Cria sessão
          const { sdk } = await import("./_core/sdk");
          const sessionToken = await sdk.createSessionToken(user.openId, {
            name: user.name,
            expiresInMs: 365 * 24 * 60 * 60 * 1000, // 1 ano
          });

          const cookieOptions = getSessionCookieOptions(ctx.req);
          ctx.res.cookie(COOKIE_NAME, sessionToken, {
            ...cookieOptions,
            maxAge: 365 * 24 * 60 * 60 * 1000,
          });

          // Log auditoria
          await logAuditAction({
            userId: user.id,
            action: "LOGIN_SOAP",
            entityType: "user",
            entityId: user.id,
            ipAddress: ctx.req.ip,
          });

          return {
            success: true,
            user: {
              id: user.id,
              name: user.name,
              email: user.email,
              oab: user.oab,
              role: user.role,
            },
          };
        } catch (error) {
          console.error("[Auth] Erro ao fazer login SOAP:", error);
          throw new TRPCError({
            code: "INTERNAL_SERVER_ERROR",
            message: "Erro ao processar login",
          });
        }
      }),
  }),

  /**
   * Procedures de Agendamento
   */
  appointments: router({
    /**
     * Obtém horários disponíveis para uma data
     */
    getAvailableSlots: protectedProcedure
      .input(z.object({ date: z.date() }))
      .query(async ({ input }) => {
        const slots = await appointmentValidationService.getAvailableSlots(input.date);
        return { slots };
      }),

    /**
     * Valida se um agendamento é possível
     */
    validate: protectedProcedure
      .input(
        z.object({
          appointmentDate: z.date(),
          startTime: z.string(),
        })
      )
      .query(async ({ input, ctx }) => {
        const result = await appointmentValidationService.validateAppointment(
          input.appointmentDate,
          input.startTime,
          ctx.user.id
        );

        return result;
      }),

    /**
     * Cria um novo agendamento
     */
    create: protectedProcedure
      .input(
        z.object({
          appointmentDate: z.date(),
          startTime: z.string(),
          reason: z.string().min(1, "Motivo obrigatório"),
          notes: z.string().optional(),
        })
      )
      .mutation(async ({ input, ctx }) => {
        try {
          // Valida agendamento
          const validation = await appointmentValidationService.validateAppointment(
            input.appointmentDate,
            input.startTime,
            ctx.user.id
          );

          if (!validation.valid) {
            throw new TRPCError({
              code: "BAD_REQUEST",
              message: validation.message,
            });
          }

          // Calcula hora de término
          const endTime = appointmentValidationService.calculateEndTime(input.startTime);

          // Cria agendamento
          const result = await createAppointment({
            userId: ctx.user.id,
            appointmentDate: input.appointmentDate,
            startTime: input.startTime,
            endTime: endTime,
            reason: input.reason,
            notes: input.notes,
          });

          // Incrementa contador mensal
          await incrementAppointmentCount(ctx.user.id);

          // Envia email de confirmação
          const appointmentDateStr = input.appointmentDate.toLocaleDateString("pt-BR");
          await emailService.sendAppointmentConfirmation({
            toEmail: ctx.user.email,
            userName: ctx.user.name,
            appointmentDate: appointmentDateStr,
            startTime: input.startTime,
            endTime: endTime,
            reason: input.reason,
            userId: ctx.user.id,
          });

          // Log auditoria
          await logAuditAction({
            userId: ctx.user.id,
            action: "CREATE_APPOINTMENT",
            entityType: "appointment",
            details: `Agendamento para ${appointmentDateStr} às ${input.startTime}`,
            ipAddress: ctx.req.ip,
          });

          return {
            success: true,
            message: "Agendamento realizado com sucesso",
          };
        } catch (error) {
          console.error("[Appointments] Erro ao criar agendamento:", error);
          if (error instanceof TRPCError) throw error;
          throw new TRPCError({
            code: "INTERNAL_SERVER_ERROR",
            message: "Erro ao criar agendamento",
          });
        }
      }),

    /**
     * Lista próximos agendamentos do usuário
     */
    getUpcoming: protectedProcedure.query(async ({ ctx }) => {
      const appointments = await getUpcomingAppointments(ctx.user.id);
      return {
        appointments: appointments.map((apt) => ({
          id: apt.id,
          date: apt.appointmentDate.toLocaleDateString("pt-BR"),
          time: apt.startTime,
          reason: apt.reason,
          status: apt.status,
        })),
      };
    }),

    /**
     * Lista histórico completo de agendamentos
     */
    getHistory: protectedProcedure
      .input(z.object({ limit: z.number().default(50) }).optional())
      .query(async ({ input, ctx }) => {
        const appointments = await getUserAppointments(ctx.user.id, input?.limit);
        return {
          appointments: appointments.map((apt) => ({
            id: apt.id,
            date: apt.appointmentDate.toLocaleDateString("pt-BR"),
            time: apt.startTime,
            reason: apt.reason,
            status: apt.status,
            createdAt: apt.createdAt.toLocaleDateString("pt-BR"),
            cancelledAt: apt.cancelledAt?.toLocaleDateString("pt-BR"),
          })),
        };
      }),

    /**
     * Cancela um agendamento
     */
    cancel: protectedProcedure
      .input(
        z.object({
          appointmentId: z.number(),
          reason: z.string().optional(),
        })
      )
      .mutation(async ({ input, ctx }) => {
        try {
          // Cancela agendamento
          await cancelAppointment(input.appointmentId, input.reason || "Cancelado pelo usuário");

          // Atualiza bloqueio de cancelamento
          await updateLastCancellation(ctx.user.id);

          // Envia email de cancelamento
          await emailService.sendAppointmentCancellation({
            toEmail: ctx.user.email,
            userName: ctx.user.name,
            appointmentDate: new Date().toLocaleDateString("pt-BR"),
            startTime: new Date().toLocaleTimeString("pt-BR"),
            reason: input.reason,
            appointmentId: input.appointmentId,
            userId: ctx.user.id,
          });

          // Log auditoria
          await logAuditAction({
            userId: ctx.user.id,
            action: "CANCEL_APPOINTMENT",
            entityType: "appointment",
            entityId: input.appointmentId,
            details: input.reason,
            ipAddress: ctx.req.ip,
          });

          return {
            success: true,
            message: "Agendamento cancelado com sucesso",
          };
        } catch (error) {
          console.error("[Appointments] Erro ao cancelar agendamento:", error);
          throw new TRPCError({
            code: "INTERNAL_SERVER_ERROR",
            message: "Erro ao cancelar agendamento",
          });
        }
      }),
  }),

  /**
   * Procedures Administrativas
   */
  admin: router({
    /**
     * Bloqueia um horário ou dia inteiro
     */
    blockSlot: protectedProcedure
      .input(
        z.object({
          blockedDate: z.date(),
          startTime: z.string(),
          endTime: z.string(),
          blockType: z.enum(["full_day", "time_slot"]),
          reason: z.string(),
        })
      )
      .mutation(async ({ input, ctx }) => {
        // Verifica se é admin
        if (ctx.user.role !== "admin") {
          throw new TRPCError({
            code: "FORBIDDEN",
            message: "Acesso negado",
          });
        }

        try {
          await createBlockedSlot({
            blockedDate: input.blockedDate,
            startTime: input.startTime,
            endTime: input.endTime,
            blockType: input.blockType,
            reason: input.reason,
            createdBy: ctx.user.id,
          });

          // Log auditoria
          await logAuditAction({
            userId: ctx.user.id,
            action: "CREATE_BLOCKED_SLOT",
            entityType: "blocked_slot",
            details: `${input.blockType}: ${input.reason}`,
            ipAddress: ctx.req.ip,
          });

          return {
            success: true,
            message: "Bloqueio criado com sucesso",
          };
        } catch (error) {
          console.error("[Admin] Erro ao criar bloqueio:", error);
          throw new TRPCError({
            code: "INTERNAL_SERVER_ERROR",
            message: "Erro ao criar bloqueio",
          });
        }
      }),

    /**
     * Remove um bloqueio
     */
    removeBlock: protectedProcedure
      .input(z.object({ blockId: z.number() }))
      .mutation(async ({ input, ctx }) => {
        if (ctx.user.role !== "admin") {
          throw new TRPCError({
            code: "FORBIDDEN",
            message: "Acesso negado",
          });
        }

        try {
          await deleteBlockedSlot(input.blockId);

          // Log auditoria
          await logAuditAction({
            userId: ctx.user.id,
            action: "DELETE_BLOCKED_SLOT",
            entityType: "blocked_slot",
            entityId: input.blockId,
            ipAddress: ctx.req.ip,
          });

          return {
            success: true,
            message: "Bloqueio removido com sucesso",
          };
        } catch (error) {
          console.error("[Admin] Erro ao remover bloqueio:", error);
          throw new TRPCError({
            code: "INTERNAL_SERVER_ERROR",
            message: "Erro ao remover bloqueio",
          });
        }
      }),

    /**
     * Lista agendamentos de uma data (para admin)
     */
    getAppointmentsByDate: protectedProcedure
      .input(z.object({ date: z.date() }))
      .query(async ({ input, ctx }) => {
        if (ctx.user.role !== "admin") {
          throw new TRPCError({
            code: "FORBIDDEN",
            message: "Acesso negado",
          });
        }

        const appointments = await getAppointmentsByDate(input.date);
        return {
          appointments: appointments.map((apt) => ({
            id: apt.id,
            userName: apt.userId.toString(), // Será preenchido com join
            time: apt.startTime,
            reason: apt.reason,
            status: apt.status,
          })),
        };
      }),
  }),
});

export type AppRouter = typeof appRouter;
