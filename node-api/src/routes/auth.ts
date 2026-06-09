import { Router, type IRouter } from "express";
import bcrypt from "bcryptjs";
import { eq, and } from "drizzle-orm";
import { db, usersTable, companiesTable } from "../lib/db";
import { LoginUserBody, ValidateTokenBody, CreateUserBody, type UserRole, USER_ROLES, ROLE_WEIGHT } from "../lib/schemas";
import { signToken } from "../middleware/auth";
import { requireAuth } from "../middleware/auth";
import { requireMinRole } from "../middleware/roles";
import { logger } from "../lib/logger";

const router: IRouter = Router();

// ── POST /auth/login ──────────────────────────────────────────────────────────
router.post("/auth/login", async (req, res): Promise<void> => {
  const parsed = LoginUserBody.safeParse(req.body);
  if (!parsed.success) { res.status(400).json({ error: parsed.error.message }); return; }

  const { email, password, companySlug } = parsed.data;

  const [company] = await db.select().from(companiesTable).where(eq(companiesTable.slug, companySlug));
  if (!company) { res.status(401).json({ error: "Invalid credentials" }); return; }

  const [user] = await db.select().from(usersTable)
    .where(and(eq(usersTable.email, email), eq(usersTable.companyId, company.id)));
  if (!user) { res.status(401).json({ error: "Invalid credentials" }); return; }

  const valid = await bcrypt.compare(password, user.passwordHash);
  if (!valid) { res.status(401).json({ error: "Invalid credentials" }); return; }

  const token = signToken({
    userId:      user.id,
    companyId:   user.companyId,
    companySlug: company.slug,
    role:        user.role as UserRole,
  });

  res.json({
    token,
    user: { id: user.id, companyId: user.companyId, name: user.name, email: user.email, role: user.role },
  });
});

// ── POST /auth/validate ───────────────────────────────────────────────────────
router.post("/auth/validate", async (req, res): Promise<void> => {
  const parsed = ValidateTokenBody.safeParse(req.body);
  if (!parsed.success) { res.status(400).json({ error: parsed.error.message }); return; }

  const SESSION_SECRET = process.env.SESSION_SECRET ?? "dev-secret";
  try {
    const jwt = await import("jsonwebtoken");
    const payload = jwt.default.verify(parsed.data.token, SESSION_SECRET) as {
      userId: number; companyId: number; companySlug: string; role: string;
    };
    res.json({ valid: true, ...payload });
  } catch {
    res.json({ valid: false, userId: null, companyId: null, companySlug: null, role: null });
  }
});

// ── GET /users — listar usuarios ──────────────────────────────────────────────
// COMPANY_ADMIN: solo su empresa | SUPER_ADMIN: todos
router.get("/users", requireAuth, requireMinRole("COMPANY_ADMIN"), async (req, res): Promise<void> => {
  const isSuperAdmin = req.auth!.role === "SUPER_ADMIN";
  const companyId    = req.auth!.companyId;

  const rows = isSuperAdmin
    ? await db.select({
        id: usersTable.id,
        companyId: usersTable.companyId,
        name: usersTable.name,
        email: usersTable.email,
        role: usersTable.role,
        createdAt: usersTable.createdAt,
      }).from(usersTable)
    : await db.select({
        id: usersTable.id,
        companyId: usersTable.companyId,
        name: usersTable.name,
        email: usersTable.email,
        role: usersTable.role,
        createdAt: usersTable.createdAt,
      }).from(usersTable).where(eq(usersTable.companyId, companyId));

  res.json(rows);
});

// ── POST /users — crear usuario ───────────────────────────────────────────────
router.post("/users", requireAuth, requireMinRole("COMPANY_ADMIN"), async (req, res): Promise<void> => {
  const parsed = CreateUserBody.safeParse(req.body);
  if (!parsed.success) { res.status(400).json({ error: parsed.error.message }); return; }

  const { companyId, name, email, password, role } = parsed.data;

  // COMPANY_ADMIN no puede crear COMPANY_ADMIN ni SUPER_ADMIN
  if (req.auth!.role === "COMPANY_ADMIN") {
    const maxAllowed: UserRole = "OPERATOR";
    if (ROLE_WEIGHT[role as UserRole] > ROLE_WEIGHT[maxAllowed]) {
      res.status(403).json({ error: "No puedes crear usuarios con rol superior a OPERATOR" });
      return;
    }
    // Forzar companyId propio
    if (companyId !== req.auth!.companyId) {
      res.status(403).json({ error: "Solo puedes crear usuarios en tu empresa" });
      return;
    }
  }

  const passwordHash = await bcrypt.hash(password, 10);
  const [user] = await (db.insert(usersTable) as any)
    .values({ companyId, name, email, passwordHash, role })
    .returning();

  logger.info({ userId: user.id, role, companyId }, "User created");
  res.status(201).json({ id: user.id, companyId: user.companyId, name: user.name, email: user.email, role: user.role });
});

// ── PUT /users/:id/role — cambiar rol ─────────────────────────────────────────
router.put("/users/:id/role", requireAuth, requireMinRole("COMPANY_ADMIN"), async (req, res): Promise<void> => {
  const targetId  = parseInt(String(req.params.id), 10);
  const newRole   = req.body?.role as UserRole;

  if (!USER_ROLES.includes(newRole)) {
    res.status(400).json({ error: `Rol inválido. Válidos: ${USER_ROLES.join(", ")}` });
    return;
  }

  const [target] = await db.select().from(usersTable).where(eq(usersTable.id, targetId));
  if (!target) { res.status(404).json({ error: "Usuario no encontrado" }); return; }

  // COMPANY_ADMIN: solo usuarios de su empresa y no puede asignar su propio nivel o superior
  if (req.auth!.role === "COMPANY_ADMIN") {
    if (target.companyId !== req.auth!.companyId) {
      res.status(403).json({ error: "No puedes modificar usuarios de otra empresa" });
      return;
    }
    if (ROLE_WEIGHT[newRole] >= ROLE_WEIGHT["COMPANY_ADMIN"]) {
      res.status(403).json({ error: "No puedes asignar rol COMPANY_ADMIN o superior" });
      return;
    }
  }

  const [updated] = await (db.update(usersTable) as any)
    .set({ role: newRole })
    .where(eq(usersTable.id, targetId))
    .returning();

  logger.info({ targetUserId: targetId, newRole, changedBy: req.auth!.userId }, "User role updated");
  res.json({ id: updated.id, name: updated.name, email: updated.email, role: updated.role });
});

// ── DELETE /users/:id — eliminar usuario ──────────────────────────────────────
router.delete("/users/:id", requireAuth, requireMinRole("COMPANY_ADMIN"), async (req, res): Promise<void> => {
  const targetId = parseInt(String(req.params.id), 10);

  if (targetId === req.auth!.userId) {
    res.status(400).json({ error: "No puedes eliminarte a ti mismo" });
    return;
  }

  const [target] = await db.select().from(usersTable).where(eq(usersTable.id, targetId));
  if (!target) { res.status(404).json({ error: "Usuario no encontrado" }); return; }

  if (req.auth!.role === "COMPANY_ADMIN" && target.companyId !== req.auth!.companyId) {
    res.status(403).json({ error: "No puedes eliminar usuarios de otra empresa" });
    return;
  }

  await db.delete(usersTable).where(eq(usersTable.id, targetId));
  logger.info({ targetUserId: targetId, deletedBy: req.auth!.userId }, "User deleted");
  res.json({ deleted: true });
});

export default router;