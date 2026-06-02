import { z } from "zod";

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
  role: z.enum(["admin", "editor", "viewer"]).optional(),
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