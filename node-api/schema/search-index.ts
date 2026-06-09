import { pgTable, serial, timestamp, integer, text, jsonb } from "drizzle-orm/pg-core";
import { companiesTable } from "./companies";
import { documentsTable, documentVersionsTable } from "./documents";

export const searchIndexTable = pgTable("search_index", {
  id:          serial("id").primaryKey(),
  documentId:  integer("document_id").notNull().references(() => documentsTable.id),
  versionId:   integer("version_id").notNull().references(() => documentVersionsTable.id),
  companyId:   integer("company_id").notNull().references(() => companiesTable.id),
  titleTokens: text("title_tokens").array().notNull().default([]),
  bodyTokens:  text("body_tokens").array().notNull().default([]),
  tokens:      jsonb("tokens").notNull().default([]),
  indexedAt:   timestamp("indexed_at", { withTimezone: true }).notNull().defaultNow(),
});

export type InsertSearchIndex = typeof searchIndexTable.$inferInsert;
export type SearchIndex       = typeof searchIndexTable.$inferSelect;