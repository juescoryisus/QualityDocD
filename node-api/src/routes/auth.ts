import { Router, type IRouter } from "express";
import bcrypt from "bcryptjs";
import { eq, and } from "drizzle-orm";
import { db, usersTable, companiesTable } from "../lib/db";
import { LoginUserBody, ValidateTokenBody, CreateUserBody } from "../lib/schemas";
import { signToken } from "../middleware/auth";

const router: IRouter = Router();

router.post("/auth/login", async (req, res): Promise<void> => {
  const parsed = LoginUserBody.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }
  const { email, password, companySlug } = parsed.data;

  const [company] = await db
    .select()
    .from(companiesTable)
    .where(eq(companiesTable.slug, companySlug));

  if (!company) {
    res.status(401).json({ error: "Invalid credentials" });
    return;
  }

  const [user] = await db
    .select()
    .from(usersTable)
    .where(and(eq(usersTable.email, email), eq(usersTable.companyId, company.id)));

  if (!user) {
    res.status(401).json({ error: "Invalid credentials" });
    return;
  }

  const valid = await bcrypt.compare(password, user.passwordHash);
  if (!valid) {
    res.status(401).json({ error: "Invalid credentials" });
    return;
  }

  const token = signToken({
    userId: user.id,
    companyId: user.companyId,
    companySlug: company.slug,
    role: user.role,
  });

  res.json({
    token,
    user: {
      id: user.id,
      companyId: user.companyId,
      name: user.name,
      email: user.email,
      role: user.role,
    },
  });
});

router.post("/auth/validate", async (req, res): Promise<void> => {
  const parsed = ValidateTokenBody.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }

const SESSION_SECRET = process.env.SESSION_SECRET ?? "dev-secret";  try {
    const jwt = await import("jsonwebtoken");
    const payload = jwt.default.verify(parsed.data.token, SESSION_SECRET) as {
      userId: number;
      companyId: number;
      companySlug: string;
      role: string;
    };
    res.json({
      valid: true,
      userId: payload.userId,
      companyId: payload.companyId,
      companySlug: payload.companySlug,
      role: payload.role,
    });
  } catch {
    res.json({ valid: false, userId: null, companyId: null, companySlug: null, role: null });
  }
});

router.post("/users", async (req, res): Promise<void> => {
  const parsed = CreateUserBody.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }

  const { password, ...rest } = parsed.data;
  const passwordHash = await bcrypt.hash(password, 10);

  const [user] = await db
    .insert(usersTable)
    .values({ ...rest, passwordHash })
    .returning();

  res.status(201).json({
    id: user.id,
    companyId: user.companyId,
    name: user.name,
    email: user.email,
    role: user.role,
  });
});

export default router;
