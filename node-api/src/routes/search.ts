import { Router, type IRouter } from "express";
import { eq, and } from "drizzle-orm";
import { db, documentVersionsTable, searchIndexTable, documentsTable } from "../lib/db";
import { requireAuth } from "../middleware/auth";
import { requireModuleAccess } from "../middleware/roles";
import { tokenize, extractSnippet } from "../lib/tokenizer";
import { logger } from "../lib/logger";

const router: IRouter = Router();

// ── POST /search/index ────────────────────────────────────────────────────────
router.post("/search/index", requireAuth, requireModuleAccess("MODULE_1"), async (req, res): Promise<void> => {
  const versionId = parseInt(String(req.body?.versionId), 10);
  if (isNaN(versionId)) { res.status(400).json({ error: "versionId requerido" }); return; }

  const companyId = req.auth!.companyId;
  const [version] = await db
    .select()
    .from(documentVersionsTable)
    .where(and(eq(documentVersionsTable.id, versionId), eq(documentVersionsTable.companyId, companyId)));

  if (!version) { res.status(404).json({ error: "Version not found" }); return; }

  const [doc] = await db.select().from(documentsTable).where(eq(documentsTable.id, version.documentId));
  const titleTokens = tokenize(doc?.title ?? "");
  const bodyTokens  = tokenize(version.contentText ?? "");
  const tokens      = [...new Set([...titleTokens, ...bodyTokens])];

  await db.delete(searchIndexTable).where(eq(searchIndexTable.versionId, versionId));
  await (db.insert(searchIndexTable) as any).values({
    documentId: version.documentId,
    versionId: version.id,
    companyId,
    titleTokens,
    bodyTokens,
    tokens,
  });

  res.json({ indexed: true, tokenCount: tokens.length });
});

// ── GET /search — BÚSQUEDA DUAL (MODULE_2 = todos) ───────────────────────────
router.get("/search", requireAuth, requireModuleAccess("MODULE_2"), async (req, res): Promise<void> => {
  const q    = typeof req.query.q    === "string" ? req.query.q.trim() : "";
  const mode = typeof req.query.mode === "string" ? req.query.mode     : "all";
  const field= typeof req.query.field=== "string" ? req.query.field    : "all";

  const companyId    = req.auth!.companyId;
  const isSuperAdmin = req.auth!.role === "SUPER_ADMIN";

  const results: {
    source: string;
    documentId: number;
    versionId: number;
    title: string;
    versionNumber: string;
    contentUrl: string | null;
    matchedIn: string;
    snippet: string;
  }[] = [];

  // ── MODO METADATA (MongoDB o fallback PostgreSQL) ─────────────────────────
  if (mode === "metadata" || mode === "all") {
    let mongoOk = false;

    if (q !== "") {
      try {
        const { getMongoDb } = await import("../lib/mongo");
        const mongo = await getMongoDb();
        const filter: Record<string, unknown> = { $text: { $search: q } };
        if (!isSuperAdmin) filter.companyId = companyId;

        const mongoDocs = await mongo
          .collection("document_metadata")
          .find(filter, { projection: { score: { $meta: "textScore" } } })
          .sort({ score: { $meta: "textScore" } })
          .limit(30)
          .toArray();

        for (const d of mongoDocs) {
          results.push({
            source: "mongodb-metadata",
            documentId:    d.documentId as number,
            versionId:     d.versionId  as number,
            title:         d.title      as string,
            versionNumber: d.version    as string,
            contentUrl:    null,
            matchedIn:     "metadata",
            snippet:       extractSnippet((d.contentText as string) ?? "", q),
          });
        }
        mongoOk = true;
      } catch (err) {
        logger.warn({ err }, "MongoDB metadata search failed, using PostgreSQL fallback");
      }
    }

    // Fallback PostgreSQL
    if (!mongoOk) {
      const queryTokens = tokenize(q);
      const whereClause = isSuperAdmin ? undefined : eq(searchIndexTable.companyId, companyId);
      const allIndexed  = whereClause
        ? await db.select().from(searchIndexTable).where(whereClause)
        : await db.select().from(searchIndexTable);

      for (const entry of allIndexed) {
        const titleTokens = entry.titleTokens as string[];
        const bodyTokens  = entry.bodyTokens  as string[];
        const inTitle = q === "" || queryTokens.some((t) => titleTokens.includes(t));
        const inBody  = q === "" || queryTokens.some((t) => bodyTokens.includes(t));
        const matchTitle = field === "all" || field === "title" ? inTitle : false;
        const matchBody  = field === "all" || field === "body"  ? inBody  : false;
        if (!matchTitle && !matchBody) continue;

        const [version] = await db.select().from(documentVersionsTable).where(eq(documentVersionsTable.id, entry.versionId));
        const [doc]     = await db.select().from(documentsTable).where(eq(documentsTable.id, entry.documentId));
        if (!version || !doc) continue;

        results.push({
          source: "postgres-index",
          documentId:    entry.documentId,
          versionId:     entry.versionId,
          title:         doc.title,
          versionNumber: version.versionNumber,
          contentUrl:    version.contentUrl ?? null,
          matchedIn:     matchTitle && matchBody ? "both" : matchTitle ? "title" : "body",
          snippet:       extractSnippet(version.contentText ?? doc.title, q),
        });
      }
    }
  }

  // ── MODO CONTENT (MongoDB full-text en contentText) ───────────────────────
  if ((mode === "content" || mode === "all") && q !== "") {
    try {
      const { getMongoDb } = await import("../lib/mongo");
      const mongo = await getMongoDb();
      const filter: Record<string, unknown> = { contentText: { $regex: q, $options: "i" } };
      if (!isSuperAdmin) filter.companyId = companyId;

      const contentDocs = await mongo.collection("document_metadata").find(filter).limit(30).toArray();
      const existingIds = new Set(results.map((r) => r.documentId));

      for (const d of contentDocs) {
        const docId = d.documentId as number;
        if (!existingIds.has(docId)) {
          results.push({
            source: "mongodb-content",
            documentId:    docId,
            versionId:     d.versionId  as number,
            title:         d.title      as string,
            versionNumber: d.version    as string,
            contentUrl:    null,
            matchedIn:     "content",
            snippet:       extractSnippet((d.contentText as string) ?? "", q),
          });
        } else {
          const r = results.find((r) => r.documentId === docId);
          if (r) r.matchedIn = "metadata+content";
        }
      }
    } catch (err) {
      logger.warn({ err }, "MongoDB content search failed");
    }
  }

  res.json({ query: q || null, mode, total: results.length, results });
});

// ── GET /search/suggest — autocompletado (MODULE_3 = OPERATOR+) ──────────────
router.get("/search/suggest", requireAuth, requireModuleAccess("MODULE_3"), async (req, res): Promise<void> => {
  const q = typeof req.query.q === "string" ? req.query.q.trim() : "";
  if (q.length < 2) { res.json({ suggestions: [] }); return; }

  try {
    const { getMongoDb } = await import("../lib/mongo");
    const mongo = await getMongoDb();
    const filter: Record<string, unknown> = { title: { $regex: q, $options: "i" } };
    if (req.auth!.role !== "SUPER_ADMIN") filter.companyId = req.auth!.companyId;

    const docs = await mongo.collection("document_metadata")
      .find(filter, { projection: { documentId: 1, title: 1 } })
      .limit(10)
      .toArray();

    res.json({ suggestions: docs.map((d) => ({ id: d.documentId, title: d.title })) });
  } catch {
    res.json({ suggestions: [] });
  }
});

export default router;