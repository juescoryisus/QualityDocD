// ★ NUEVO — middleware de tenant (multiempresa)
//
// Resuelve la empresa actual en la petición.
// Fuentes de resolución (en orden de prioridad):
//   1. req.auth.companyId  — ya viene en el JWT después de login
//   2. X-Company-Slug      — header para peticiones pre-autenticación
//
// Después de ejecutarse inyecta:
//   req.company   → objeto completo de la empresa
//   req.companyId → id numérico de la empresa

import { Request, Response, NextFunction } from "express";
import { db } from "../lib/db"; 
import { companiesTable } from "../../schema";
import { eq } from "drizzle-orm";
import type { Company } from "../../schema";

declare global {
  namespace Express {
    interface Request {
      company?: Company;
      companyId?: number;
    }
  }
}

export async function tenantMiddleware(
  req: Request,
  res: Response,
  next: NextFunction,
): Promise<void> {
  // Si el JWT ya fue verificado, tomamos companyId de ahí
  const idFromJwt = req.auth?.companyId;
  const slugFromHdr = req.headers["x-company-slug"] as string | undefined;

  if (!idFromJwt && !slugFromHdr) {
    return next(); // ruta pública sin contexto de empresa
  }

  try {
    let rows: Company[];

    if (idFromJwt) {
      rows = await db
        .select()
        .from(companiesTable)
        .where(eq(companiesTable.id, idFromJwt))
        .limit(1);
    } else {
      rows = await db
        .select()
        .from(companiesTable)
        .where(eq(companiesTable.slug, slugFromHdr!))
        .limit(1);
    }

    const company = rows[0];

    if (!company) {
      res.status(404).json({ error: "Empresa no encontrada" });
      return;
    }

    if (!company.isActive) {
      res.status(403).json({ error: "La empresa está inactiva" });
      return;
    }

    req.company = company;
    req.companyId = company.id;
    next();
  } catch (err) {
    next(err);
  }
}

/**
 * Usar en rutas que NECESITAN contexto de empresa.
 * Va después de requireAuth para que req.auth ya esté disponible.
 */
export function requireTenant(
  req: Request,
  res: Response,
  next: NextFunction,
): void {
  if (!req.companyId) {
    res.status(400).json({ error: "Contexto de empresa requerido" });
    return;
  }
  next();
}
