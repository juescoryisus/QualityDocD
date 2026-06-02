import { Router, type IRouter } from "express";
import { eq, and } from "drizzle-orm";
import { db, documentsTable, documentVersionsTable, searchIndexTable } from "../lib/db";
import { CreateDocumentBody, CreateDocumentVersionBody } from "../lib/schemas";
import { requireAuth } from "../middleware/auth";
import { tokenize } from "../lib/tokenizer";
import { logger } from "../lib/logger";

const router: IRouter = Router();

router.get("/documents", requireAuth, async (req, res): Promise<void> => {
  const companyId = req.auth!.companyId;

  const docs = await db
    .select()
    .from(documentsTable)
    .where(eq(documentsTable.companyId, companyId));

  const result = await Promise.all(
    docs.map(async (doc) => {
      const [currentVersion] = await db
        .select()
        .from(documentVersionsTable)
        .where(
          and(
            eq(documentVersionsTable.documentId, doc.id),
            eq(documentVersionsTable.status, "current")
          )
        );
      return { ...doc, currentVersion: currentVersion ?? null };
    })
  );

  res.json(result);
});

router.post("/documents", requireAuth, async (req, res): Promise<void> => {
  const parsed = CreateDocumentBody.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }

  const companyId = req.auth!.companyId;
  const userId = req.auth!.userId;

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const [doc] = await (db.insert(documentsTable) as any)
    .values({
      companyId,
      title: parsed.data.title,
      format: parsed.data.format ?? "pdf",
      createdBy: userId,
    })
    .returning();

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const [version] = await (db.insert(documentVersionsTable) as any)
    .values({
      documentId: doc.id,
      companyId,
      majorVersion: 1,
      minorVersion: 0,
      versionNumber: "1.0",
      status: "draft",
      contentUrl: parsed.data.contentUrl ?? null,
      contentText: parsed.data.contentText,
      createdBy: userId,
    })
    .returning();

  res.status(201).json({ ...doc, currentVersion: version });
});

router.get("/documents/:id", requireAuth, async (req, res): Promise<void> => {
  const id = parseInt(String(req.params.id), 10);
  const companyId = req.auth!.companyId;

  const [doc] = await db
    .select()
    .from(documentsTable)
    .where(and(eq(documentsTable.id, id), eq(documentsTable.companyId, companyId)));

  if (!doc) {
    res.status(404).json({ error: "Document not found" });
    return;
  }

  const [currentVersion] = await db
    .select()
    .from(documentVersionsTable)
    .where(
      and(
        eq(documentVersionsTable.documentId, id),
        eq(documentVersionsTable.status, "current")
      )
    );

  res.json({ ...doc, currentVersion: currentVersion ?? null });
});

router.get("/documents/:id/history", requireAuth, async (req, res): Promise<void> => {
  const id = parseInt(String(req.params.id), 10);
  const companyId = req.auth!.companyId;

  const [doc] = await db
    .select()
    .from(documentsTable)
    .where(and(eq(documentsTable.id, id), eq(documentsTable.companyId, companyId)));

  if (!doc) {
    res.status(404).json({ error: "Document not found" });
    return;
  }

  const versions = await db
    .select()
    .from(documentVersionsTable)
    .where(eq(documentVersionsTable.documentId, id));

  res.json(versions);
});

router.post("/documents/:id/versions", requireAuth, async (req, res): Promise<void> => {
  const id = parseInt(String(req.params.id), 10);
  const companyId = req.auth!.companyId;
  const userId = req.auth!.userId;

  const parsed = CreateDocumentVersionBody.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }

  const [doc] = await db
    .select()
    .from(documentsTable)
    .where(and(eq(documentsTable.id, id), eq(documentsTable.companyId, companyId)));

  if (!doc) {
    res.status(404).json({ error: "Document not found" });
    return;
  }

  const allVersions = await db
    .select()
    .from(documentVersionsTable)
    .where(eq(documentVersionsTable.documentId, id));

  const latestMajor = Math.max(...allVersions.map((v) => v.majorVersion), 1);
  const latestMinorForMajor = allVersions
    .filter((v) => v.majorVersion === latestMajor)
    .reduce((max, v) => Math.max(max, v.minorVersion), 0);

  const bumpMajor = parsed.data.bumpMajor ?? false;
  const newMajor = bumpMajor ? latestMajor + 1 : latestMajor;
  const newMinor = bumpMajor ? 0 : latestMinorForMajor + 1;

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const [version] = await (db.insert(documentVersionsTable) as any)
    .values({
      documentId: id,
      companyId,
      majorVersion: newMajor,
      minorVersion: newMinor,
      versionNumber: `${newMajor}.${newMinor}`,
      status: "draft",
      contentUrl: parsed.data.contentUrl ?? null,
      contentText: parsed.data.contentText,
      createdBy: userId,
    })
    .returning();

  res.status(201).json(version);
});

router.post(
  "/documents/:id/versions/:versionId/approve",
  requireAuth,
  async (req, res): Promise<void> => {
    const docId = parseInt(String(req.params.id), 10);
    const versionId = parseInt(String(req.params.versionId), 10);
    const companyId = req.auth!.companyId;
    const userId = req.auth!.userId;

    const [version] = await db
      .select()
      .from(documentVersionsTable)
      .where(
        and(
          eq(documentVersionsTable.id, versionId),
          eq(documentVersionsTable.documentId, docId),
          eq(documentVersionsTable.companyId, companyId)
        )
      );

    if (!version) {
      res.status(404).json({ error: "Version not found" });
      return;
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    await (db.update(documentVersionsTable) as any)
      .set({ status: "obsolete" })
      .where(
        and(
          eq(documentVersionsTable.documentId, docId),
          eq(documentVersionsTable.status, "current")
        )
      );

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const [approved] = await (db.update(documentVersionsTable) as any)
      .set({ status: "current", approvedBy: userId, approvedAt: new Date() })
      .where(eq(documentVersionsTable.id, versionId))
      .returning();

    const titleTokens = tokenize(version.contentText ? approved.versionNumber : "");
    const bodyTokens = tokenize(version.contentText ?? "");
    const tokens = [...new Set([...titleTokens, ...bodyTokens])];

    const existing = await db
      .select()
      .from(searchIndexTable)
      .where(eq(searchIndexTable.versionId, versionId));

    if (existing.length === 0) {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      await (db.insert(searchIndexTable) as any).values({
        documentId: docId,
        versionId,
        companyId,
        titleTokens,
        bodyTokens,
        tokens,
      });
    }

    logger.info({ documentId: docId, versionId, companyId }, "Document version approved");

    res.json(approved);
  }
);

export default router;
