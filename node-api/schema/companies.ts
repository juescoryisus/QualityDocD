import { pgTable, text, serial, timestamp, boolean } from "drizzle-orm/pg-core";

export const companiesTable = pgTable("companies", {
  id:        serial("id").primaryKey(),
  name:      text("name").notNull(),
  slug:      text("slug").notNull().unique(),
  logoUrl:   text("logo_url"),                                           // ← NUEVO
  isActive:  boolean("is_active").notNull().default(true),               // ← NUEVO
  createdAt: timestamp("created_at", { withTimezone: true }).notNull().defaultNow(),
  updatedAt: timestamp("updated_at", { withTimezone: true }).notNull().defaultNow(), // ← NUEVO
});

export type InsertCompany = typeof companiesTable.$inferInsert;
export type Company       = typeof companiesTable.$inferSelect;
