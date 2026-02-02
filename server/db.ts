import { eq, and, gte, lte, desc, asc, sql } from "drizzle-orm";
import { drizzle } from "drizzle-orm/mysql2";
import {
  InsertUser,
  users,
  appointments,
  blockedSlots,
  appointmentLimits,
  auditLogs,
  emailQueue,
  systemSettings,
  emailTemplates,
  appointmentMessages,
} from "../drizzle/schema";
import { ENV } from "./_core/env";

let _db: ReturnType<typeof drizzle> | null = null;

// Lazily create the drizzle instance so local tooling can run without a DB.
export async function getDb() {
  if (!_db && process.env.DATABASE_URL) {
    try {
      _db = drizzle(process.env.DATABASE_URL);
    } catch (error) {
      console.warn("[Database] Failed to connect:", error);
      _db = null;
    }
  }
  return _db;
}

/**
 * USERS - Gerenciamento de usuários
 */

export async function upsertUser(user: Omit<InsertUser, 'id'>): Promise<void> {
  if (!user.openId) {
    throw new Error("User openId is required for upsert");
  }

  const db = await getDb();
  if (!db) {
    console.warn("[Database] Cannot upsert user: database not available");
    return;
  }

  try {
    const values: Omit<InsertUser, 'id'> = {
      openId: user.openId,
      cpf: user.cpf,
      oab: user.oab,
      name: user.name,
      email: user.email,
      phone: user.phone,
      cep: user.cep,
      endereco: user.endereco,
      bairro: user.bairro,
      cidade: user.cidade,
      estado: user.estado,
      nomeMae: user.nomeMae,
      nomePai: user.nomePai,
      rg: user.rg,
      orgaoRg: user.orgaoRg,
      dataExpedicaoRg: user.dataExpedicaoRg,
      loginMethod: user.loginMethod,
    };

    const updateSet: Record<string, unknown> = {
      cpf: user.cpf,
      oab: user.oab,
      name: user.name,
      email: user.email,
      phone: user.phone,
      cep: user.cep,
      endereco: user.endereco,
      bairro: user.bairro,
      cidade: user.cidade,
      estado: user.estado,
      nomeMae: user.nomeMae,
      nomePai: user.nomePai,
      rg: user.rg,
      orgaoRg: user.orgaoRg,
      dataExpedicaoRg: user.dataExpedicaoRg,
    };

    if (user.lastSignedIn !== undefined) {
      values.lastSignedIn = user.lastSignedIn;
      updateSet.lastSignedIn = user.lastSignedIn;
    }

    if (user.role !== undefined) {
      values.role = user.role;
      updateSet.role = user.role;
    } else if (user.openId === ENV.ownerOpenId) {
      values.role = "admin";
      updateSet.role = "admin";
    }

    if (!values.lastSignedIn) {
      values.lastSignedIn = new Date();
    }

    await db.insert(users).values(values).onDuplicateKeyUpdate({
      set: updateSet,
    });
  } catch (error) {
    console.error("[Database] Failed to upsert user:", error);
    throw error;
  }
}

export async function getUserByOpenId(openId: string) {
  const db = await getDb();
  if (!db) {
    console.warn("[Database] Cannot get user: database not available");
    return undefined;
  }

  const result = await db.select().from(users).where(eq(users.openId, openId)).limit(1);
  return result.length > 0 ? result[0] : undefined;
}

export async function getUserByCPF(cpf: string) {
  const db = await getDb();
  if (!db) return undefined;

  const result = await db.select().from(users).where(eq(users.cpf, cpf)).limit(1);
  return result.length > 0 ? result[0] : undefined;
}

export async function getUserByOAB(oab: string) {
  const db = await getDb();
  if (!db) return undefined;

  const result = await db.select().from(users).where(eq(users.oab, oab)).limit(1);
  return result.length > 0 ? result[0] : undefined;
}

export async function updateUserPhone(userId: number, phone: string) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");

  return await db
    .update(users)
    .set({ phone })
    .where(eq(users.id, userId));
}

/**
 * APPOINTMENTS - Gerenciamento de agendamentos
 */

export async function createAppointment(data: {
  userId: number;
  appointmentDate: Date;
  startTime: string;
  endTime: string;
  reason: string;
  notes?: string;
}) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");

  const [result] = await db.insert(appointments).values({
    userId: data.userId,
    appointmentDate: data.appointmentDate,
    startTime: data.startTime,
    endTime: data.endTime,
    reason: data.reason,
    notes: data.notes,
    status: "confirmed",
  });

  // Retorna apenas o ID inserido (insertId) para evitar problemas de serialização
  return (result as any).insertId as number;
}

export async function getUserAppointments(userId: number, limit = 50) {
  const db = await getDb();
  if (!db) return [];

  return await db
    .select()
    .from(appointments)
    .where(eq(appointments.userId, userId))
    .orderBy(desc(appointments.appointmentDate))
    .limit(limit);
}

export async function getUpcomingAppointments(userId: number) {
  const db = await getDb();
  if (!db) return [];

  // Usamos uma data de ontem para garantir que agendamentos de hoje 
  // apareçam mesmo com pequenas diferenças de fuso horário no servidor
  const yesterday = new Date();
  yesterday.setDate(yesterday.getDate() - 1);
  yesterday.setHours(0, 0, 0, 0);

  return await db
    .select()
    .from(appointments)
    .where(
      and(
        eq(appointments.userId, userId),
        gte(appointments.appointmentDate, yesterday),
        eq(appointments.status, "confirmed")
      )
    )
    .orderBy(asc(appointments.appointmentDate))
    .limit(10);
}

export async function getAppointmentsByDate(date: Date) {
  const db = await getDb();
  if (!db) return [];

  const startOfDay = new Date(date);
  startOfDay.setHours(0, 0, 0, 0);

  const endOfDay = new Date(date);
  endOfDay.setHours(23, 59, 59, 999);

  return await db
    .select()
    .from(appointments)
    .where(
      and(
        gte(appointments.appointmentDate, startOfDay),
        lte(appointments.appointmentDate, endOfDay),
        eq(appointments.status, "confirmed")
      )
    )
    .orderBy(asc(appointments.startTime));
}

export async function cancelAppointment(appointmentId: number, reason: string) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");

  // Busca o userId do agendamento antes de cancelar
  const apt = await db
    .select({ userId: appointments.userId })
    .from(appointments)
    .where(eq(appointments.id, appointmentId))
    .limit(1);

  const result = await db
    .update(appointments)
    .set({
      status: "cancelled",
      cancelledAt: new Date(),
      cancellationReason: reason,
    })
    .where(eq(appointments.id, appointmentId));

  // Decrementa o contador de agendamentos do mês para o usuário
  if (apt.length > 0) {
    await db
      .update(appointmentLimits)
      .set({
        appointmentsThisMonth: sql`GREATEST(0, appointmentsThisMonth - 1)`,
      })
      .where(eq(appointmentLimits.userId, apt[0].userId));
  }

  return result;
}

/**
 * BLOCKED SLOTS - Gerenciamento de bloqueios
 */

export async function createBlockedSlot(data: {
  blockedDate: Date;
  startTime: string;
  endTime: string;
  blockType: "full_day" | "time_slot";
  reason: string;
  createdBy: number;
}) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");

  return await db.insert(blockedSlots).values(data);
}

export async function getBlockedSlotsForDate(date: Date) {
  const db = await getDb();
  if (!db) return [];

  const startOfDay = new Date(date);
  startOfDay.setHours(0, 0, 0, 0);

  const endOfDay = new Date(date);
  endOfDay.setHours(23, 59, 59, 999);

  return await db
    .select()
    .from(blockedSlots)
    .where(
      and(
        gte(blockedSlots.blockedDate, startOfDay),
        lte(blockedSlots.blockedDate, endOfDay)
      )
    );
}

export async function deleteBlockedSlot(slotId: number) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");

  return await db.delete(blockedSlots).where(eq(blockedSlots.id, slotId));
}

/**
 * APPOINTMENT LIMITS - Controle de limite mensal
 */

export async function getOrCreateAppointmentLimit(userId: number) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");

  const currentMonth = new Date().toISOString().substring(0, 7); // YYYY-MM

  const existing = await db
    .select()
    .from(appointmentLimits)
    .where(eq(appointmentLimits.userId, userId))
    .limit(1);

  if (existing.length > 0) {
    const limit = existing[0];
    // Reset if month changed
    if (limit.currentMonth !== currentMonth) {
      await db
        .update(appointmentLimits)
        .set({
          currentMonth,
          appointmentsThisMonth: 0,
        })
        .where(eq(appointmentLimits.userId, userId));

      return { ...limit, currentMonth, appointmentsThisMonth: 0 };
    }
    return limit;
  }

  // Create new limit
  await db.insert(appointmentLimits).values({
    userId,
    currentMonth,
    appointmentsThisMonth: 0,
  });

  return {
    id: 0,
    userId,
    monthlyLimit: 2,
    currentMonth,
    appointmentsThisMonth: 0,
    lastCancellationAt: null,
    createdAt: new Date(),
    updatedAt: new Date(),
  };
}

export async function incrementAppointmentCount(userId: number) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");

  return await db
    .update(appointmentLimits)
    .set({
      appointmentsThisMonth: sql`appointmentsThisMonth + 1`,
    })
    .where(eq(appointmentLimits.userId, userId));
}

export async function updateLastCancellation(userId: number) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");

  return await db
    .update(appointmentLimits)
    .set({
      lastCancellationAt: new Date(),
    })
    .where(eq(appointmentLimits.userId, userId));
}

/**
 * EMAIL QUEUE - Gerenciamento de fila de emails
 */

export async function addEmailToQueue(data: {
  toEmail: string;
  toName?: string;
  subject: string;
  body: string;
  emailType: string;
  appointmentId?: number;
  userId?: number;
}) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");

  return await db.insert(emailQueue).values({
    ...data,
    status: "pending",
  });
}

export async function getPendingEmails(limit = 50) {
  const db = await getDb();
  if (!db) return [];

  return await db
    .select()
    .from(emailQueue)
    .where(eq(emailQueue.status, "pending"))
    .orderBy(asc(emailQueue.createdAt))
    .limit(limit);
}

export async function markEmailAsSent(emailId: number) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");

  return await db
    .update(emailQueue)
    .set({
      status: "sent",
      sentAt: new Date(),
    })
    .where(eq(emailQueue.id, emailId));
}

export async function markEmailAsFailed(emailId: number, reason: string) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");

  return await db
    .update(emailQueue)
    .set({
      status: "failed",
      failureReason: reason,
      retryCount: sql`retryCount + 1`,
    })
    .where(eq(emailQueue.id, emailId));
}

/**
 * SYSTEM SETTINGS - Configurações do sistema
 */

export async function getSystemSettings() {
  const db = await getDb();
  if (!db) return null;

  const result = await db.select().from(systemSettings).limit(1);
  return result.length > 0 ? result[0] : null;
}

export async function updateSystemSettings(data: Partial<typeof systemSettings.$inferInsert>) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");

  return await db.update(systemSettings).set(data);
}

/**
 * AUDIT LOG - Log de auditoria
 */

export async function logAuditAction(data: {
  userId?: number;
  action: string;
  entityType: string;
  entityId?: number;
  details?: string;
  ipAddress?: string;
}) {
  const db = await getDb();
  if (!db) return;

  try {
    await db.insert(auditLogs).values(data);
  } catch (error) {
    console.error("[Database] Failed to log audit action:", error);
  }
}

/**
 * EMAIL TEMPLATES - Gerenciamento de modelos de e-mail
 */
export async function getEmailTemplates() {
  const db = await getDb();
  if (!db) return [];
  return await db.select().from(emailTemplates);
}

export async function getEmailTemplateBySlug(slug: string) {
  const db = await getDb();
  if (!db) return null;
  const result = await db
    .select()
    .from(emailTemplates)
    .where(eq(emailTemplates.slug, slug))
    .limit(1);
  return result[0] || null;
}

export async function upsertEmailTemplate(data: {
  slug: string;
  name: string;
  subject: string;
  body: string;
  variables?: string;
}) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");

  const existing = await getEmailTemplateBySlug(data.slug);

  if (existing) {
    return await db
      .update(emailTemplates)
      .set({
        name: data.name,
        subject: data.subject,
        body: data.body,
        variables: data.variables,
        updatedAt: new Date(),
      })
      .where(eq(emailTemplates.slug, data.slug));
  } else {
    return await db.insert(emailTemplates).values(data);
  }
}

/**
 * Inicializa templates padrão se não existirem
 */
export async function seedEmailTemplates() {
  const templates = [
    {
      slug: "appointment_confirmation",
      name: "Confirmação de Agendamento",
      subject: "Agendamento Confirmado - Sistema de Agendamento INSS",
      body: "<h1>Olá {userName}</h1><p>Seu agendamento para o dia {appointmentDate} às {startTime} foi confirmado.</p>",
      variables: "{userName}, {appointmentDate}, {startTime}, {endTime}, {reason}"
    },
    {
      slug: "appointment_cancellation",
      name: "Cancelamento de Agendamento",
      subject: "Agendamento Cancelado - Sistema de Agendamento INSS",
      body: "<h1>Olá {userName}</h1><p>Seu agendamento para o dia {appointmentDate} às {startTime} foi cancelado.</p>",
      variables: "{userName}, {appointmentDate}, {startTime}, {reason}"
    },
    {
      slug: "custom_notification",
      name: "Notificação Customizada",
      subject: "Aviso Importante - Sistema de Agendamento INSS",
      body: "<h1>Olá {userName}</h1><p>{message}</p>",
      variables: "{userName}, {message}"
    }
  ];

  for (const t of templates) {
    const existing = await getEmailTemplateBySlug(t.slug);
    if (!existing) {
      await upsertEmailTemplate(t);
    }
  }
}
