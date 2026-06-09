import { pgTable, text, serial, timestamp } from "drizzle-orm/pg-core";

export const companiesTable = pgTable("companies", {
  id:        serial("id").primaryKey(),
  name:      text("name").notNull(),
  slug:      text("slug").notNull().unique(),
  createdAt: timestamp("created_at", { withTimezone: true }).notNull().defaultNow(),
});

export type InsertCompany = typeof companiesTable.$inferInsert;
export type Company       = typeof companiesTable.$inferSelect;