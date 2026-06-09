import { Router, type IRouter } from "express";
import { requireAuth } from "../middleware/auth";
import { MODULE_ACCESS, MODULE_WRITE_ACCESS } from "../lib/schemas";

const router: IRouter = Router();

const MODULE_INFO: Record<string, { name: string; description: string }> = {
  MODULE_1: { name: "Gestión de Documentos",  description: "Aprobación, rechazo y ciclo de vida" },
  MODULE_2: { name: "Consulta de Documentos", description: "Visualización y búsqueda de documentos aprobados" },
  MODULE_3: { name: "Búsqueda Avanzada",      description: "Búsqueda por metadatos y contenido (solo OPERATOR+)" },
};

router.get("/modules", requireAuth, async (req, res): Promise<void> => {
  const role = req.auth!.role;
  const modules = Object.entries(MODULE_INFO).map(([id, info]) => ({
    id,
    name: info.name,
    description: info.description,
    canRead:      MODULE_ACCESS[id]?.includes(role)       ?? false,
    canWrite:     MODULE_WRITE_ACCESS[id]?.includes(role) ?? false,
    allowedRoles: MODULE_ACCESS[id],
    writeRoles:   MODULE_WRITE_ACCESS[id],
  }));
  res.json({ role, modules: modules.filter((m) => m.canRead) });
});

export default router;