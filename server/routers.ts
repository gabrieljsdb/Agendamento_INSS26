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
  incrementAppointmentCount,
  updateLastCancellation,
  logAuditAction,
} from "./db";
import { soapAuthService } from "./services/soapAuthService";
import { appointmentValidationService } from "./services/appointmentValidationService";
import { emailService } from "./services/emailService";
import { documentService } from "./services/documentService";

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
     * Login com CPF e senha via SOAP OAB/SC (Integrado com GeracaoDocumentoINSS)
     */
    loginWithSOAP: publicProcedure
      .input(
        z.object({
          cpf: z.string().min(1, "CPF obrigatório"),
          password: z.string().min(1, "Senha obrigatória"),
        })
      )
      .mutation(async ({ input, ctx }) => {
        try {
          // Autentica contra SOAP
          const soapResult = await soapAuthService.authenticate(input.cpf, input.password);

          // DEBUG: Ver o que chegou do SOAP antes de verificar erro
          // console.log('DEBUG SOAP RAW:', JSON.stringify(soapResult, null, 2));

          if (!soapResult.success || !soapResult.userData) {
            throw new TRPCError({
              code: "UNAUTHORIZED",
              message: soapResult.message || "Credenciais inválidas",
            });
          }

          const userData = soapResult.userData;

          // --- BLOCO DE VERIFICAÇÃO DE INADIMPLÊNCIA CORRIGIDO ---
          // Acessamos como 'any' caso a tipagem do userData ainda não tenha esse campo definido
          const statusInadimplente = (userData as any).Inadimplente;

          console.log('DEBUG LOGIN OAB:', {
              nome: userData.nome,
              statusInadimplente: statusInadimplente,
              tamanho: statusInadimplente?.length,
              ehSim: statusInadimplente?.trim() === 'Sim'
          });

          // Verifica se é estritamente "Sim" (ignorando espaços)
          if (statusInadimplente && statusInadimplente.trim() === 'Sim') {
              throw new TRPCError({
                  code: 'UNAUTHORIZED',
                  message: 'Acesso negado: Regularize sua situação com a OAB'
              });
          }
          // --- FIM DO BLOCO CORRIGIDO ---

          // Busca ou cria usuário no banco com todos os campos extras
          let user = await getUserByCPF(userData.cpf);

          const { upsertUser } = await import("./db");
          
          // Mapeamento dos dados
          const userPayload = {
            openId: `soap_${userData.cpf}`,
            cpf: userData.cpf,
            oab: userData.oab,
            name: userData.nome,
            email: userData.email,
            cep: userData.cep,
            endereco: userData.endereco,
            bairro: userData.bairro,
            cidade: userData.cidade,
            estado: userData.estado,
            nomeMae: userData.nome_mae, // Certifique-se que o snake_case bate com o retorno do service
            nomePai: userData.nome_pai,
            rg: userData.rg,
            orgaoRg: userData.orgao_rg,
            dataExpedicaoRg: userData.data_expedicao_rg,
            loginMethod: "soap",
            lastSignedIn: new Date(),
          };

          await upsertUser(userPayload);
          user = await getUserByCPF(userData.cpf);

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
          if (error instanceof TRPCError) throw error;
          throw new TRPCError({
            code: "INTERNAL_SERVER_ERROR",
            message: "Erro ao processar login",
          });
        }
      }),
  }),

  /**
   * Procedures de Documentos
   */
  documents: router({
    generateMyDocument: protectedProcedure.mutation(async ({ ctx }) => {
      try {
        const user = ctx.user;
        if (!user) throw new TRPCError({ code: "UNAUTHORIZED" });

        const fullUser = await getUserByCPF(user.cpf);
        if (!fullUser) throw new TRPCError({ code: "NOT_FOUND", message: "Usuário não encontrado" });

        const soapUserData = {
          nome: fullUser.name,
          email: fullUser.email,
          cep: fullUser.cep || '',
          endereco: fullUser.endereco || '',
          bairro: fullUser.bairro || '',
          cidade: fullUser.cidade || '',
          estado: fullUser.estado || '',
          nome_mae: fullUser.nomeMae || '',
          nome_pai: fullUser.nomePai || '',
          cpf: fullUser.cpf,
          rg: fullUser.rg || '',
          oab: fullUser.oab,
          orgao_rg: fullUser.orgaoRg || '',
          data_expedicao_rg: fullUser.dataExpedicaoRg || '',
        };

        const buffer = await documentService.generateUserDocument(soapUserData);
        
        return {
          filename: `Documento_${fullUser.name.replace(/\s+/g, '_')}.docx`,
          content: buffer.toString('base64'),
          contentType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        };
      } catch (error) {
        console.error("[Documents] Erro ao gerar documento:", error);
        throw new TRPCError({
          code: "INTERNAL_SERVER_ERROR",
          message: "Erro ao gerar documento Word",
        });
      }
    })
  }),

  /**
   * Procedures de Agendamento
   */
  appointments: router({
    getAvailableSlots: protectedProcedure
      .input(z.object({ date: z.date() }))
      .query(async ({ input }) => {
        const slots = await appointmentValidationService.getAvailableSlots(input.date);
        return { slots };
      }),

    validate: protectedProcedure
      .input(z.object({ appointmentDate: z.date(), startTime: z.string() }))
      .query(async ({ input, ctx }) => {
        return await appointmentValidationService.validateAppointment(
          input.appointmentDate,
          input.startTime,
          ctx.user.id
        );
      }),

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
          const validation = await appointmentValidationService.validateAppointment(
            input.appointmentDate,
            input.startTime,
            ctx.user.id
          );

          if (!validation.valid) {
            throw new TRPCError({ code: "BAD_REQUEST", message: validation.message });
          }

          const endTime = appointmentValidationService.calculateEndTime(input.startTime);
          await createAppointment({
            userId: ctx.user.id,
            appointmentDate: input.appointmentDate,
            startTime: input.startTime,
            endTime: endTime,
            reason: input.reason,
            notes: input.notes,
          });

          await incrementAppointmentCount(ctx.user.id);

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

          await logAuditAction({
            userId: ctx.user.id,
            action: "CREATE_APPOINTMENT",
            entityType: "appointment",
            details: `Agendamento para ${appointmentDateStr} às ${input.startTime}`,
            ipAddress: ctx.req.ip,
          });

          return { success: true, message: "Agendamento realizado com sucesso" };
        } catch (error) {
          console.error("[Appointments] Erro ao criar agendamento:", error);
          if (error instanceof TRPCError) throw error;
          throw new TRPCError({ code: "INTERNAL_SERVER_ERROR", message: "Erro ao criar agendamento" });
        }
      }),

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

    cancel: protectedProcedure
      .input(z.object({ appointmentId: z.number(), reason: z.string().optional() }))
      .mutation(async ({ input, ctx }) => {
        try {
          await cancelAppointment(input.appointmentId, input.reason || "Cancelado pelo usuário");
          await updateLastCancellation(ctx.user.id);

          await emailService.sendAppointmentCancellation({
            toEmail: ctx.user.email,
            userName: ctx.user.name,
            appointmentDate: new Date().toLocaleDateString("pt-BR"),
            startTime: new Date().toLocaleTimeString("pt-BR"),
            reason: input.reason,
            appointmentId: input.appointmentId,
            userId: ctx.user.id,
          });

          await logAuditAction({
            userId: ctx.user.id,
            action: "CANCEL_APPOINTMENT",
            entityType: "appointment",
            entityId: input.appointmentId,
            details: input.reason,
            ipAddress: ctx.req.ip,
          });

          return { success: true, message: "Agendamento cancelado com sucesso" };
        } catch (error) {
          console.error("[Appointments] Erro ao cancelar agendamento:", error);
          throw new TRPCError({ code: "INTERNAL_SERVER_ERROR", message: "Erro ao cancelar agendamento" });
        }
      }),
  }),
});

export type AppRouter = typeof appRouter;