import { pgTable, text, serial, timestamp, integer } from "drizzle-orm/pg-core";
import { companiesTable } from "./companies";
import { usersTable } from "./users";

export const documentsTable = pgTable("documents", {
  id:        serial("id").primaryKey(),
  companyId: integer("company_id").notNull().references(() => companiesTable.id),
  title:     text("title").notNull(),
  format:    text("format").notNull().default("pdf"),
  createdBy: integer("created_by").notNull().references(() => usersTable.id),
  createdAt: timestamp("created_at", { withTimezone: true }).notNull().defaultNow(),
});

export const documentVersionsTable = pgTable("document_versions", {
  id:            serial("id").primaryKey(),
  documentId:    integer("document_id").notNull().references(() => documentsTable.id),
  companyId:     integer("company_id").notNull().references(() => companiesTable.id),
  majorVersion:  integer("major_version").notNull().default(1),
  minorVersion:  integer("minor_version").notNull().default(0),
  versionNumber: text("version_number").notNull(),
  status:        text("status", { enum: ["draft", "current", "obsolete"] }).notNull().default("draft"),
  contentUrl:    text("content_url"),
  contentText:   text("content_text"),
  approvedBy:    integer("approved_by").references(() => usersTable.id),
  approvedAt:    timestamp("approved_at", { withTimezone: true }),
  createdBy:     integer("created_by").notNull().references(() => usersTable.id),
  createdAt:     timestamp("created_at", { withTimezone: true }).notNull().defaultNow(),
});

export type InsertDocument        = typeof documentsTable.$inferInsert;
export type Document              = typeof documentsTable.$inferSelect;
export type InsertDocumentVersion = typeof documentVersionsTable.$inferInsert;
export type DocumentVersion       = typeof documentVersionsTable.$inferSelect;