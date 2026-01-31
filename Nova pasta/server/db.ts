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

  const result = await db.insert(appointments).values({
    userId: data.userId,
    appointmentDate: data.appointmentDate,
    startTime: data.startTime,
    endTime: data.endTime,
    reason: data.reason,
    notes: data.notes,
    status: "confirmed",
  });

  return result;
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

  const now = new Date();
  return await db
    .select()
    .from(appointments)
    .where(
      and(
        eq(appointments.userId, userId),
        gte(appointments.appointmentDate, now),
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

  return await db
    .update(appointments)
    .set({
      status: "cancelled",
      cancelledAt: new Date(),
      cancellationReason: reason,
    })
    .where(eq(appointments.id, appointmentId));
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
