import { Router, type IRouter } from "express";
import { eq, and } from "drizzle-orm";
import { db, documentVersionsTable, searchIndexTable, documentsTable } from "../lib/db";
import { IndexDocumentBody } from "../lib/schemas";
import { requireAuth } from "../middleware/auth";
import { tokenize, extractSnippet } from "../lib/tokenizer";

const router: IRouter = Router();

router.post("/search/index", requireAuth, async (req, res): Promise<void> => {
  const parsed = IndexDocumentBody.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }

  const companyId = req.auth!.companyId;

  const [version] = await db
    .select()
    .from(documentVersionsTable)
    .where(
      and(
        eq(documentVersionsTable.id, parsed.data.versionId),
        eq(documentVersionsTable.companyId, companyId)
      )
    );

  if (!version) {
    res.status(404).json({ error: "Version not found" });
    return;
  }

  const [doc] = await db
    .select()
    .from(documentsTable)
    .where(eq(documentsTable.id, version.documentId));

  const titleTokens = tokenize(doc?.title ?? "");
  const bodyTokens = tokenize(version.contentText ?? "");
  const tokens = [...new Set([...titleTokens, ...bodyTokens])];

  await db
    .delete(searchIndexTable)
    .where(eq(searchIndexTable.versionId, parsed.data.versionId));

  await db.insert(searchIndexTable).values({
    documentId: version.documentId,
    versionId: version.id,
    companyId,
    titleTokens,
    bodyTokens,
    tokens,
  });

  res.json({ indexed: true, tokenCount: tokens.length });
});

router.get("/search", requireAuth, async (req, res): Promise<void> => {
  const q = typeof req.query.q === "string" ? req.query.q : null;
  if (!q || q.trim() === "") {
    res.status(400).json({ error: "Query parameter 'q' is required" });
    return;
  }

  const field = typeof req.query.field === "string" ? req.query.field : "all";
  const companyId = req.auth!.companyId;
  const queryTokens = tokenize(q);

  const allIndexed = await db
    .select()
    .from(searchIndexTable)
    .where(eq(searchIndexTable.companyId, companyId));

  const hits = [];

  for (const entry of allIndexed) {
    const titleTokens = entry.titleTokens as string[];
    const bodyTokens = entry.bodyTokens as string[];

    const inTitle = queryTokens.some((t) => titleTokens.includes(t));
    const inBody = queryTokens.some((t) => bodyTokens.includes(t));

    const matchTitle = field === "all" || field === "title" ? inTitle : false;
    const matchBody = field === "all" || field === "body" ? inBody : false;

    if (!matchTitle && !matchBody) continue;

    const [version] = await db
      .select()
      .from(documentVersionsTable)
      .where(eq(documentVersionsTable.id, entry.versionId));

    const [doc] = await db
      .select()
      .from(documentsTable)
      .where(eq(documentsTable.id, entry.documentId));

    if (!version || !doc) continue;

    const matchedIn = matchTitle && matchBody ? "both" : matchTitle ? "title" : "body";
    const snippet = extractSnippet(version.contentText ?? doc.title, q);

    hits.push({
      documentId: entry.documentId,
      versionId: entry.versionId,
      title: doc.title,
      versionNumber: version.versionNumber,
      contentUrl: version.contentUrl ?? null,
      matchedIn,
      snippet,
    });
  }

  res.json(hits);
});

export default router;
