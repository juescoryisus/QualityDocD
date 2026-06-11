// ★ NUEVO — categorías de documentos por empresa
import { pgTable, text, serial, timestamp, integer } from "drizzle-orm/pg-core";
import { companiesTable } from "./companies";

export const documentCategoriesTable = pgTable("document_categories", {
  id:          serial("id").primaryKey(),
  companyId:   integer("company_id").notNull().references(() => companiesTable.id),
  name:        text("name").notNull(),
  description: text("description"),
  createdAt:   timestamp("created_at", { withTimezone: true }).notNull().defaultNow(),
});

export type InsertDocumentCategory = typeof documentCategoriesTable.$inferInsert;
export type DocumentCategory       = typeof documentCategoriesTable.$inferSelect;
