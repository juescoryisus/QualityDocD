import express from "express";
import cors from "cors";
import router from "./routes/index";
import { requireAuth } from "./middleware/auth"; // ← ya existía
import { tenantMiddleware } from "./middleware/tenant"; // ← NUEVO

const app = express();

app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// ── Middlewares globales (orden importante) ──────────────────────────────────
//
//  1. requireAuth  → verifica el JWT y llena req.auth
//                   (solo actúa si hay header Authorization: Bearer ...)
//  2. tenantMiddleware → usa req.auth.companyId para cargar la empresa
//                        e inyectar req.company y req.companyId
//
// Las rutas públicas (login, healthz) no se ven afectadas porque
// requireAuth llama next() si no hay token, y tenantMiddleware también
// llama next() si no hay companyId ni X-Company-Slug.
// ────────────────────────────────────────────────────────────────────────────
app.use(requireAuth); // ← NUEVO (mover aquí desde las rutas individuales)
app.use(tenantMiddleware); // ← NUEVO

app.use("/api", router);

export default app;
