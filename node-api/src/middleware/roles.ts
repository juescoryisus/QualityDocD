import { Request, Response, NextFunction } from "express";
import {
  type UserRole,
  MODULE_ACCESS,
  MODULE_WRITE_ACCESS,
  ROLE_WEIGHT,
} from "../lib/schemas";

export function requireMinRole(minRole: UserRole) {
  return (req: Request, res: Response, next: NextFunction): void => {
    if (!req.auth) { res.status(401).json({ error: "Not authenticated" }); return; }
    if (ROLE_WEIGHT[req.auth.role] < ROLE_WEIGHT[minRole]) {
      res.status(403).json({ error: `Se requiere rol mínimo: ${minRole}` });
      return;
    }
    next();
  };
}

export function requireModuleAccess(module: string, write = false) {
  return (req: Request, res: Response, next: NextFunction): void => {
    if (!req.auth) { res.status(401).json({ error: "Not authenticated" }); return; }
    const allowed = write ? MODULE_WRITE_ACCESS[module] : MODULE_ACCESS[module];
    if (!allowed?.includes(req.auth.role)) {
      res.status(403).json({ error: `Acceso denegado al ${module}` });
      return;
    }
    next();
  };
}