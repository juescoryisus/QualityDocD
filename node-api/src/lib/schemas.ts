import { z } from "zod";

// ── 6 roles según el profesor ─────────────────────────────────────────────────
export const USER_ROLES = [
  "VIEWER",        // Usuario normal – solo consulta
  "COMMENTER",     // Usuario normal – consulta + comenta
  "CONTRIBUTOR",   // Usuario normal – puede subir documentos (quedan en DRAFT)
  "OPERATOR",      // Operador – gestiona documentos en su módulo
  "COMPANY_ADMIN", // Admin de empresa – control total en SU empresa
  "SUPER_ADMIN",   // Super admin – control total en TODAS las empresas
] as const;

export type UserRole = (typeof USER_ROLES)[number];

// Peso numérico para comparar niveles de rol
export const ROLE_WEIGHT: Record<UserRole, number> = {
  VIEWER: 1,
  COMMENTER: 2,
  CONTRIBUTOR: 3,
  OPERATOR: 4,
  COMPANY_ADMIN: 5,
  SUPER_ADMIN: 6,
};

export function hasMinRole(userRole: UserRole, minRole: UserRole): boolean {
  return ROLE_WEIGHT[userRole] >= ROLE_WEIGHT[minRole];
}

// ── Módulos y permisos ────────────────────────────────────────────────────────
// MODULE_1 — Gestión (aprobar/rechazar): todos menos usuarios normales
// MODULE_2 — Consulta de documentos: todos
// MODULE_3 — Búsqueda avanzada / metadatos: solo OPERATOR+
export const MODULE_ACCESS: Record<string, UserRole[]> = {
  MODULE_1: ["OPERATOR", "COMPANY_ADMIN", "SUPER_ADMIN"],
  MODULE_2: ["VIEWER", "COMMENTER", "CONTRIBUTOR", "OPERATOR", "COMPANY_ADMIN", "SUPER_ADMIN"],
  MODULE_3: ["OPERATOR", "COMPANY_ADMIN", "SUPER_ADMIN"],
};

export const MODULE_WRITE_ACCESS: Record<string, UserRole[]> = {
  MODULE_1: ["OPERATOR", "COMPANY_ADMIN", "SUPER_ADMIN"],
  MODULE_2: ["COMPANY_ADMIN", "SUPER_ADMIN"],
  MODULE_3: ["SUPER_ADMIN"],
};

// ── Zod schemas ───────────────────────────────────────────────────────────────
export const LoginUserBody = z.object({
  email: z.string(),
  password: z.string(),
  companySlug: z.string(),
});

export const ValidateTokenBody = z.object({
  token: z.string(),
});

export const CreateUserBody = z.object({
  companyId: z.number(),
  name: z.string(),
  email: z.string(),
  password: z.string(),
  role: z.enum(USER_ROLES).optional().default("VIEWER"),
});

export const CreateCompanyBody = z.object({
  name: z.string().min(1),
  slug: z.string().min(1),
});

export const CreateDocumentBody = z.object({
  title: z.string().min(1),
  format: z.string().optional(),
  contentUrl: z.string().nullable().optional(),
  contentText: z.string(),
  keywords: z.array(z.string()).optional().default([]),
});

export const CreateDocumentVersionBody = z.object({
  bumpMajor: z.boolean().optional(),
  contentUrl: z.string().nullable().optional(),
  contentText: z.string(),
});

export const IndexDocumentBody = z.object({
  versionId: z.number(),
});

export const ReceiveDocumentApprovedEventBody = z.object({
  documentId: z.number(),
  versionId: z.number(),
  companyId: z.number(),
  approvedAt: z.string(),
});