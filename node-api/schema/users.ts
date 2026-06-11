import { pgTable, text, serial, timestamp, integer, boolean } from "drizzle-orm/pg-core";
import { companiesTable } from "./companies";

export const usersTable = pgTable("users", {
  id:           serial("id").primaryKey(),
  companyId:    integer("company_id").notNull().references(() => companiesTable.id),
  name:         text("name").notNull(),
  email:        text("email").notNull().unique(),
  passwordHash: text("password_hash").notNull(),
  role: text("role", {
    enum: ["VIEWER", "COMMENTER", "CONTRIBUTOR", "OPERATOR", "COMPANY_ADMIN", "SUPER_ADMIN"],
  }).notNull().default("VIEWER"),
  isActive:  boolean("is_active").notNull().default(true),               // ← NUEVO
  createdAt: timestamp("created_at", { withTimezone: true }).notNull().defaultNow(),
  updatedAt: timestamp("updated_at", { withTimezone: true }).notNull().defaultNow(), // ← NUEVO
});

export type InsertUser = typeof usersTable.$inferInsert;
export type User       = typeof usersTable.$inferSelect;
