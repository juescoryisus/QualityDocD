import { Router, type IRouter } from "express";
import { eq, and } from "drizzle-orm";
import { db, documentsTable, documentVersionsTable, searchIndexTable } from "../lib/db";
import { CreateDocumentBody, CreateDocumentVersionBody } from "../lib/schemas";
import { requireAuth } from "../middleware/auth";
import { requireModuleAccess } from "../middleware/roles";
import { tokenize } from "../lib/tokenizer";
import { logger } from "../lib/logger";

const router: IRouter = Router();

// ── GET /documents ────────────────────────────────────────────────────────────
router.get("/documents", requireAuth, requireModuleAccess("MODULE_2"), async (req, res): Promise<void> => {
  const companyId    = req.auth!.companyId;
  const isSuperAdmin = req.auth!.role === "SUPER_ADMIN";

  const docs = isSuperAdmin
    ? await db.select().from(documentsTable)
    : await db.select().from(documentsTable).where(eq(documentsTable.companyId, companyId));

  const result = await Promise.all(
    docs.map(async (doc) => {
      const [currentVersion] = await db
        .select()
        .from(documentVersionsTable)
        .where(and(
          eq(documentVersionsTable.documentId, doc.id),
          eq(documentVersionsTable.status, "current")
        ));
      return { ...doc, currentVersion: currentVersion ?? null };
    })
  );

  res.json(result);
});

// ── POST /documents ───────────────────────────────────────────────────────────
router.post("/documents", requireAuth, requireModuleAccess("MODULE_2"), async (req, res): Promise<void> => {
  const parsed = CreateDocumentBody.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }

  const companyId = req.auth!.companyId;
  const userId    = req.auth!.userId;

  const [doc] = await (db.insert(documentsTable) as any)
    .values({
      companyId,
      title: parsed.data.title,
      format: parsed.data.format ?? "pdf",
      createdBy: userId,
    })
    .returning();

  // Las versiones arrancan en 0.1 (borrador inicial)
  // Al aprobarse → número entero: 0.x → 1, 1.x → 2, 2.x → 3...
  const [version] = await (db.insert(documentVersionsTable) as any)
    .values({
      documentId: doc.id,
      companyId,
      majorVersion:  0,
      minorVersion:  1,
      versionNumber: "0.1",
      status: "draft",
      contentUrl:  parsed.data.contentUrl ?? null,
      contentText: parsed.data.contentText,
      createdBy:   userId,
    })
    .returning();

  res.status(201).json({ ...doc, currentVersion: version });
});

// ── GET /documents/:id ────────────────────────────────────────────────────────
router.get("/documents/:id", requireAuth, requireModuleAccess("MODULE_2"), async (req, res): Promise<void> => {
  const id           = parseInt(String(req.params.id), 10);
  const companyId    = req.auth!.companyId;
  const isSuperAdmin = req.auth!.role === "SUPER_ADMIN";

  const [doc] = isSuperAdmin
    ? await db.select().from(documentsTable).where(eq(documentsTable.id, id))
    : await db.select().from(documentsTable).where(and(eq(documentsTable.id, id), eq(documentsTable.companyId, companyId)));

  if (!doc) { res.status(404).json({ error: "Document not found" }); return; }

  const [currentVersion] = await db
    .select()
    .from(documentVersionsTable)
    .where(and(eq(documentVersionsTable.documentId, id), eq(documentVersionsTable.status, "current")));

  res.json({ ...doc, currentVersion: currentVersion ?? null });
});

// ── GET /documents/:id/history ────────────────────────────────────────────────
router.get("/documents/:id/history", requireAuth, requireModuleAccess("MODULE_2"), async (req, res): Promise<void> => {
  const id           = parseInt(String(req.params.id), 10);
  const companyId    = req.auth!.companyId;
  const isSuperAdmin = req.auth!.role === "SUPER_ADMIN";

  const [doc] = isSuperAdmin
    ? await db.select().from(documentsTable).where(eq(documentsTable.id, id))
    : await db.select().from(documentsTable).where(and(eq(documentsTable.id, id), eq(documentsTable.companyId, companyId)));

  if (!doc) { res.status(404).json({ error: "Document not found" }); return; }

  const versions = await db
    .select()
    .from(documentVersionsTable)
    .where(eq(documentVersionsTable.documentId, id));

  res.json(versions);
});

// ── POST /documents/:id/versions ──────────────────────────────────────────────
router.post("/documents/:id/versions", requireAuth, requireModuleAccess("MODULE_1"), async (req, res): Promise<void> => {
  const id        = parseInt(String(req.params.id), 10);
  const companyId = req.auth!.companyId;
  const userId    = req.auth!.userId;

  const parsed = CreateDocumentVersionBody.safeParse(req.body);
  if (!parsed.success) { res.status(400).json({ error: parsed.error.message }); return; }

  const [doc] = await db.select().from(documentsTable)
    .where(and(eq(documentsTable.id, id), eq(documentsTable.companyId, companyId)));
  if (!doc) { res.status(404).json({ error: "Document not found" }); return; }

  const allVersions = await db.select().from(documentVersionsTable).where(eq(documentVersionsTable.documentId, id));

  // Versionado: borradores en decimal (0.1, 1.1, 2.1...), aprobados en entero (1, 2, 3...)
  // El Math.max base es 0 (no 1) para no saltar el rango 0.x
  const latestMajor = Math.max(...allVersions.map((v) => v.majorVersion), 0);
  const latestMinor = allVersions
    .filter((v) => v.majorVersion === latestMajor)
    .reduce((m, v) => Math.max(m, v.minorVersion), 0);

  const bumpMajor = parsed.data.bumpMajor ?? false;
  const newMajor  = bumpMajor ? latestMajor + 1 : latestMajor;
  const newMinor  = bumpMajor ? 0              : latestMinor + 1;

  const [version] = await (db.insert(documentVersionsTable) as any)
    .values({
      documentId:    id,
      companyId,
      majorVersion:  newMajor,
      minorVersion:  newMinor,
      versionNumber: `${newMajor}.${newMinor}`,
      status: "draft",
      contentUrl:  parsed.data.contentUrl ?? null,
      contentText: parsed.data.contentText,
      createdBy:   userId,
    })
    .returning();

  res.status(201).json(version);
});

// ── POST /documents/:id/versions/:versionId/approve — MODULE_1 (OPERATOR+) ───
router.post(
  "/documents/:id/versions/:versionId/approve",
  requireAuth,
  requireModuleAccess("MODULE_1", true),
  async (req, res): Promise<void> => {
    const docId     = parseInt(String(req.params.id), 10);
    const versionId = parseInt(String(req.params.versionId), 10);
    const companyId = req.auth!.companyId;
    const userId    = req.auth!.userId;

    const [version] = await db
      .select()
      .from(documentVersionsTable)
      .where(and(
        eq(documentVersionsTable.id, versionId),
        eq(documentVersionsTable.documentId, docId),
        eq(documentVersionsTable.companyId, companyId)
      ));

    if (!version) { res.status(404).json({ error: "Version not found" }); return; }

    // Marcar versión actual como obsoleta
    await (db.update(documentVersionsTable) as any)
      .set({ status: "obsolete" })
      .where(and(eq(documentVersionsTable.documentId, docId), eq(documentVersionsTable.status, "current")));

    // Al aprobar cualquier versión decimal X.Y → número entero X+1
    // Ejemplos: 0.1 → 1, 0.3 → 1, 1.1 → 2, 1.4 → 2, 2.1 → 3
    const promotedMajor  = version.majorVersion + 1;
    const promotedMinor  = 0;
    const promotedNumber = String(promotedMajor);

    const [approved] = await (db.update(documentVersionsTable) as any)
      .set({
        status:        "current",
        majorVersion:  promotedMajor,
        minorVersion:  promotedMinor,
        versionNumber: promotedNumber,
        approvedBy:    userId,
        approvedAt:    new Date(),
      })
      .where(eq(documentVersionsTable.id, versionId))
      .returning();

    // Indexar en PostgreSQL
    const titleTokens = tokenize(approved.versionNumber ?? "");
    const bodyTokens  = tokenize(version.contentText ?? "");
    const tokens      = [...new Set([...titleTokens, ...bodyTokens])];

    const existing = await db.select().from(searchIndexTable).where(eq(searchIndexTable.versionId, versionId));
    if (existing.length === 0) {
      await (db.insert(searchIndexTable) as any)
        .values({ documentId: docId, versionId, companyId, titleTokens, bodyTokens, tokens });
    }

    // Sincronizar a MongoDB (sin bloquear si falla)
    (async () => {
      try {
        const { getMongoDb } = await import("../lib/mongo");
        const mongo = await getMongoDb();
        const [doc] = await db.select().from(documentsTable).where(eq(documentsTable.id, docId));
        await mongo.collection("document_metadata").updateOne(
          { documentId: docId },
          { $set: {
            documentId:  docId,
            versionId,
            companyId,
            title:       doc?.title ?? "",
            format:      doc?.format ?? "pdf",
            version:     approved.versionNumber,
            status:      "Approved",
            contentText: version.contentText ?? "",
            tags:        (doc?.title ?? "").toLowerCase().split(" ").filter(Boolean),
            approvedAt:  new Date(),
          }},
          { upsert: true }
        );
      } catch (err) {
        logger.warn({ err }, "MongoDB sync failed after approval");
      }
    })();

    logger.info({ documentId: docId, versionId, companyId, version: promotedNumber }, "Document version approved");
    res.json(approved);
  }
);

export default router;
