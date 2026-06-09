import { pgTable, text, serial, timestamp, integer } from "drizzle-orm/pg-core";
import { companiesTable } from "./companies";

export const usersTable = pgTable("users", {
  id: serial("id").primaryKey(),
  companyId: integer("company_id").notNull().references(() => companiesTable.id),
  name: text("name").notNull(),
  email: text("email").notNull().unique(),
  passwordHash: text("password_hash").notNull(),
  role: text("role", {
    enum: ["VIEWER", "COMMENTER", "CONTRIBUTOR", "OPERATOR", "COMPANY_ADMIN", "SUPER_ADMIN"],
  }).notNull().default("VIEWER"),
  createdAt: timestamp("created_at", { withTimezone: true }).notNull().defaultNow(),
});

export type InsertUser = typeof usersTable.$inferInsert;
export type User      = typeof usersTable.$inferSelect;