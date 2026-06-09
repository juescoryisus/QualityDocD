import { Router, type IRouter } from "express";
import { desc, eq } from "drizzle-orm";
import { db, documentsTable, documentVersionsTable } from "../lib/db";
import { requireAuth } from "../middleware/auth";

const router: IRouter = Router();

router.get("/dashboard/summary", requireAuth, async (req, res): Promise<void> => {
  const companyId    = req.auth!.companyId;
  const isSuperAdmin = req.auth!.role === "SUPER_ADMIN";

  const docs = isSuperAdmin
    ? await db.select().from(documentsTable)
    : await db.select().from(documentsTable).where(eq(documentsTable.companyId, companyId));

  const total  = docs.length;
  const drafts = await (async () => {
    const where = isSuperAdmin
      ? eq(documentVersionsTable.status, "draft")
      : eq(documentVersionsTable.companyId, companyId);
    const rows = await db.select().from(documentVersionsTable).where(where);
    return {
      draft:    rows.filter((v) => v.status === "draft").length,
      current:  rows.filter((v) => v.status === "current").length,
      obsolete: rows.filter((v) => v.status === "obsolete").length,
    };
  })();

  res.json({ total, byStatus: drafts });
});

router.get("/dashboard/recent", requireAuth, async (req, res): Promise<void> => {
  const companyId    = req.auth!.companyId;
  const isSuperAdmin = req.auth!.role === "SUPER_ADMIN";

  const docs = isSuperAdmin
    ? await db.select().from(documentsTable).orderBy(desc(documentsTable.createdAt)).limit(10)
    : await db.select().from(documentsTable)
        .where(eq(documentsTable.companyId, companyId))
        .orderBy(desc(documentsTable.createdAt))
        .limit(10);

  res.json(docs.map((d) => ({ ...d, createdAt: d.createdAt.toISOString() })));
});

export default router;