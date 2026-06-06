import { Router, type IRouter } from "express";
import { desc, sql } from "drizzle-orm";
import { db, documentsTable } from "@workspace/db";
import {
  GetDashboardSummaryResponse,
  GetRecentDocumentsResponse,
} from "@workspace/api-zod";

const router: IRouter = Router();

router.get("/dashboard/summary", async (_req, res): Promise<void> => {
  const rows = await db
    .select({
      status: documentsTable.status,
      count: sql<number>`count(*)::int`,
    })
    .from(documentsTable)
    .groupBy(documentsTable.status);

  const byStatus = { draft: 0, review: 0, approved: 0, rejected: 0 };
  let total = 0;
  for (const row of rows) {
    byStatus[row.status] = row.count;
    total += row.count;
  }

  const categoryRows = await db
    .select({
      category: documentsTable.category,
      count: sql<number>`count(*)::int`,
    })
    .from(documentsTable)
    .groupBy(documentsTable.category);

  const byCategory = categoryRows.map((r) => ({
    category: r.category,
    count: r.count,
  }));

  res.json(GetDashboardSummaryResponse.parse({ total, byStatus, byCategory }));
});

router.get("/dashboard/recent", async (_req, res): Promise<void> => {
  const docs = await db
    .select()
    .from(documentsTable)
    .orderBy(desc(documentsTable.updatedAt))
    .limit(10);

  res.json(
    GetRecentDocumentsResponse.parse(
      docs.map((d) => ({
        ...d,
        approvedAt: d.approvedAt ? d.approvedAt.toISOString() : null,
        createdAt: d.createdAt.toISOString(),
        updatedAt: d.updatedAt.toISOString(),
      }))
    )
  );
});

export default router;
