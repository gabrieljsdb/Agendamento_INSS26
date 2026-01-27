import {
  int,
  mysqlEnum,
  mysqlTable,
  text,
  timestamp,
  varchar,
  boolean,
  datetime,
  decimal,
  index,
  unique,
  tinyint,
} from "drizzle-orm/mysql-core";

/**
 * USERS TABLE - Sincronizado com autenticação SOAP OAB/SC
 */
export const users = mysqlTable(
  "users",
  {
    id: int("id").autoincrement().primaryKey(),
    openId: varchar("openId", { length: 64 }).notNull().unique(),
    
    // Dados sincronizados do SOAP OAB/SC
    cpf: varchar("cpf", { length: 14 }).notNull().unique(),
    oab: varchar("oab", { length: 20 }).notNull().unique(),
    name: text("name").notNull(),
    email: varchar("email", { length: 320 }).notNull(),
    phone: varchar("phone", { length: 20 }),
    
    // Controle de acesso
    role: mysqlEnum("role", ["user", "admin"]).default("user").notNull(),
    isActive: boolean("isActive").default(true).notNull(),
    
    // Auditoria
    loginMethod: varchar("loginMethod", { length: 64 }),
    createdAt: timestamp("createdAt").defaultNow().notNull(),
    updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
    lastSignedIn: timestamp("lastSignedIn").defaultNow().notNull(),
  },
  (table) => ({
    cpfIdx: index("cpf_idx").on(table.cpf),
    oabIdx: index("oab_idx").on(table.oab),
    emailIdx: index("email_idx").on(table.email),
  })
);

export type User = typeof users.$inferSelect;
export type InsertUser = typeof users.$inferInsert;

/**
 * APPOINTMENTS TABLE - Agendamentos do sistema
 */
export const appointments = mysqlTable(
  "appointments",
  {
    id: int("id").autoincrement().primaryKey(),
    userId: int("userId").notNull(),
    
    // Dados do agendamento
    appointmentDate: datetime("appointmentDate").notNull(),
    startTime: varchar("startTime", { length: 8 }).notNull(), // HH:MM:SS
    endTime: varchar("endTime", { length: 8 }).notNull(), // HH:MM:SS
    
    // Motivo e detalhes
    reason: varchar("reason", { length: 100 }).notNull(),
    notes: text("notes"),
    
    // Status
    status: mysqlEnum("status", [
      "pending",
      "confirmed",
      "completed",
      "cancelled",
      "no_show",
    ])
      .default("pending")
      .notNull(),
    
    // Controle de cancelamento
    cancelledAt: timestamp("cancelledAt"),
    cancellationReason: text("cancellationReason"),
    
    // Auditoria
    createdAt: timestamp("createdAt").defaultNow().notNull(),
    updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
  },
  (table) => ({
    userIdIdx: index("userId_idx").on(table.userId),
    appointmentDateIdx: index("appointmentDate_idx").on(table.appointmentDate),
    statusIdx: index("status_idx").on(table.status),
  })
);

export type Appointment = typeof appointments.$inferSelect;
export type InsertAppointment = typeof appointments.$inferInsert;

/**
 * BLOCKED_SLOTS TABLE - Bloqueios de horários específicos
 */
export const blockedSlots = mysqlTable(
  "blocked_slots",
  {
    id: int("id").autoincrement().primaryKey(),
    
    // Data e hora bloqueada
    blockedDate: datetime("blockedDate").notNull(),
    startTime: varchar("startTime", { length: 8 }).notNull(),
    endTime: varchar("endTime", { length: 8 }).notNull(),
    
    // Tipo de bloqueio
    blockType: mysqlEnum("blockType", ["full_day", "time_slot"]).notNull(),
    
    // Motivo
    reason: text("reason").notNull(),
    
    // Quem criou
    createdBy: int("createdBy").notNull(),
    
    // Auditoria
    createdAt: timestamp("createdAt").defaultNow().notNull(),
    updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
  },
  (table) => ({
    blockedDateIdx: index("blockedDate_idx").on(table.blockedDate),
    blockTypeIdx: index("blockType_idx").on(table.blockType),
  })
);

export type BlockedSlot = typeof blockedSlots.$inferSelect;
export type InsertBlockedSlot = typeof blockedSlots.$inferInsert;

/**
 * APPOINTMENT_LIMITS TABLE - Controle de limite mensal por usuário
 */
export const appointmentLimits = mysqlTable(
  "appointment_limits",
  {
    id: int("id").autoincrement().primaryKey(),
    userId: int("userId").notNull().unique(),
    
    // Limite mensal
    monthlyLimit: int("monthlyLimit").default(2).notNull(),
    currentMonth: varchar("currentMonth", { length: 7 }).notNull(), // YYYY-MM
    appointmentsThisMonth: int("appointmentsThisMonth").default(0).notNull(),
    
    // Último cancelamento (para bloqueio de 2h)
    lastCancellationAt: timestamp("lastCancellationAt"),
    
    // Auditoria
    createdAt: timestamp("createdAt").defaultNow().notNull(),
    updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
  },
  (table) => ({
    userIdIdx: index("userId_idx").on(table.userId),
    currentMonthIdx: index("currentMonth_idx").on(table.currentMonth),
  })
);

export type AppointmentLimit = typeof appointmentLimits.$inferSelect;
export type InsertAppointmentLimit = typeof appointmentLimits.$inferInsert;

/**
 * AUDIT_LOG TABLE - Log de todas as ações do sistema
 */
export const auditLogs = mysqlTable(
  "audit_logs",
  {
    id: int("id").autoincrement().primaryKey(),
    userId: int("userId"),
    
    // Ação realizada
    action: varchar("action", { length: 50 }).notNull(),
    entityType: varchar("entityType", { length: 50 }).notNull(),
    entityId: int("entityId"),
    
    // Detalhes
    details: text("details"),
    ipAddress: varchar("ipAddress", { length: 45 }),
    
    // Auditoria
    createdAt: timestamp("createdAt").defaultNow().notNull(),
  },
  (table) => ({
    userIdIdx: index("userId_idx").on(table.userId),
    actionIdx: index("action_idx").on(table.action),
    createdAtIdx: index("createdAt_idx").on(table.createdAt),
  })
);

export type AuditLog = typeof auditLogs.$inferSelect;
export type InsertAuditLog = typeof auditLogs.$inferInsert;

/**
 * EMAIL_QUEUE TABLE - Fila de emails para envio
 */
export const emailQueue = mysqlTable(
  "email_queue",
  {
    id: int("id").autoincrement().primaryKey(),
    
    // Destinatário
    toEmail: varchar("toEmail", { length: 320 }).notNull(),
    toName: varchar("toName", { length: 255 }),
    
    // Conteúdo
    subject: varchar("subject", { length: 255 }).notNull(),
    body: text("body").notNull(),
    emailType: varchar("emailType", { length: 50 }).notNull(),
    
    // Referência
    appointmentId: int("appointmentId"),
    userId: int("userId"),
    
    // Status
    status: mysqlEnum("status", ["pending", "sent", "failed"]).default("pending").notNull(),
    sentAt: timestamp("sentAt"),
    failureReason: text("failureReason"),
    retryCount: int("retryCount").default(0).notNull(),
    
    // Auditoria
    createdAt: timestamp("createdAt").defaultNow().notNull(),
    updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
  },
  (table) => ({
    statusIdx: index("status_idx").on(table.status),
    appointmentIdIdx: index("appointmentId_idx").on(table.appointmentId),
    createdAtIdx: index("createdAt_idx").on(table.createdAt),
  })
);

export type EmailQueue = typeof emailQueue.$inferSelect;
export type InsertEmailQueue = typeof emailQueue.$inferInsert;

/**
 * DAILY_REPORT_LOG TABLE - Log de relatórios diários enviados
 */
export const dailyReportLogs = mysqlTable(
  "daily_report_logs",
  {
    id: int("id").autoincrement().primaryKey(),
    
    // Data do relatório
    reportDate: datetime("reportDate").notNull().unique(),
    
    // Dados do relatório
    appointmentCount: int("appointmentCount").notNull(),
    adminEmailsSent: int("adminEmailsSent").notNull(),
    
    // Status
    status: mysqlEnum("status", ["success", "failed"]).notNull(),
    errorMessage: text("errorMessage"),
    
    // Auditoria
    createdAt: timestamp("createdAt").defaultNow().notNull(),
  },
  (table) => ({
    reportDateIdx: index("reportDate_idx").on(table.reportDate),
    statusIdx: index("status_idx").on(table.status),
  })
);

export type DailyReportLog = typeof dailyReportLogs.$inferSelect;
export type InsertDailyReportLog = typeof dailyReportLogs.$inferInsert;

/**
 * SYSTEM_SETTINGS TABLE - Configurações do sistema
 */
export const systemSettings = mysqlTable("system_settings", {
  id: int("id").autoincrement().primaryKey(),
  
  // Configurações de agendamento
  workingHoursStart: varchar("workingHoursStart", { length: 8 }).default("08:00:00").notNull(),
  workingHoursEnd: varchar("workingHoursEnd", { length: 8 }).default("12:00:00").notNull(),
  appointmentDurationMinutes: int("appointmentDurationMinutes").default(30).notNull(),
  monthlyLimitPerUser: int("monthlyLimitPerUser").default(2).notNull(),
  cancellationBlockingHours: int("cancellationBlockingHours").default(2).notNull(),
  maxAdvancedBookingDays: int("maxAdvancedBookingDays").default(30).notNull(),
  blockingTimeAfterHours: varchar("blockingTimeAfterHours", { length: 8 }).default("19:00:00").notNull(),
  
  // Informações da instituição
  institutionName: varchar("institutionName", { length: 255 }).default("OAB/SC").notNull(),
  institutionAddress: text("institutionAddress"),
  institutionPhone: varchar("institutionPhone", { length: 20 }),
  
  // Email
  senderEmail: varchar("senderEmail", { length: 320 }).notNull(),
  senderName: varchar("senderName", { length: 255 }).notNull(),
  adminEmails: text("adminEmails").notNull(), // JSON array
  
  // Auditoria
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type SystemSettings = typeof systemSettings.$inferSelect;
export type InsertSystemSettings = typeof systemSettings.$inferInsert;
