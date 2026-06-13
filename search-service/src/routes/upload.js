"use strict";

const express  = require("express");
const multer   = require("multer");
const pdfParse = require("pdf-parse");
const mammoth  = require("mammoth");
const path     = require("path");
const DocumentMeta = require("../models/DocumentMeta");

const router  = express.Router();
const storage = multer.memoryStorage();           // archivo en RAM, no en disco
const upload  = multer({
  storage,
  limits: { fileSize: 20 * 1024 * 1024 },        // máx 20 MB
  fileFilter: (_req, file, cb) => {
    const allowed = [".pdf", ".docx", ".doc", ".txt"];
    const ext = path.extname(file.originalname).toLowerCase();
    if (allowed.includes(ext)) cb(null, true);
    else cb(new Error(`Tipo no soportado: ${ext}. Permitidos: ${allowed.join(", ")}`));
  },
});

// ── Extrae texto según tipo de archivo ────────────────────────────────────────
async function extractText(file) {
  const ext = path.extname(file.originalname).toLowerCase();

  if (ext === ".pdf") {
    const data = await pdfParse(file.buffer);
    return data.text || "";
  }

  if (ext === ".docx" || ext === ".doc") {
    const result = await mammoth.extractRawText({ buffer: file.buffer });
    return result.value || "";
  }

  if (ext === ".txt") {
    return file.buffer.toString("utf-8");
  }

  return "";
}

// ── POST /api/upload ─────────────────────────────────────────────────────────
// Campos del form-data:
//   file          — archivo (PDF, DOCX, DOC, TXT)
//   documentId    — requerido
//   title         — requerido
//   versionId, companyId, code, description, category,
//   standard, tags (coma-separado), version, status, isPublic — opcionales
router.post("/upload", upload.single("file"), async (req, res) => {
  try {
    if (!req.file) {
      return res.status(400).json({ ok: false, error: "No se recibió ningún archivo." });
    }

    const {
      documentId, versionId, companyId, code, title,
      description, category, standard, tags,
      version, status, isPublic,
    } = req.body;

    if (!documentId || !title) {
      return res.status(400).json({ ok: false, error: "documentId y title son obligatorios." });
    }

    // Extraer texto del archivo
    const contentText = await extractText(req.file);

    const ext = path.extname(req.file.originalname).toLowerCase().replace(".", "");

    const parsedTags = Array.isArray(tags)
      ? tags
      : (tags || "").split(",").map((t) => t.trim()).filter(Boolean);

    const meta = await DocumentMeta.findOneAndUpdate(
      { documentId: Number(documentId) },
      {
        documentId:    Number(documentId),
        versionId:     versionId  ? Number(versionId)  : null,
        companyId:     companyId  ? Number(companyId)  : null,
        code:          code          || "",
        title,
        description:   description   || "",
        category:      category      || "",
        standard:      standard      || "",
        tags:          parsedTags,
        fileExtension: ext,
        format:        ext.toUpperCase(),
        version:       version       || "",
        contentText,
        status:        status        || "Draft",
        isPublic:      isPublic === "true" || isPublic === true,
        updatedAt:     new Date(),
      },
      { upsert: true, new: true }
    );

    res.status(201).json({
      ok:          true,
      id:          meta._id,
      fileType:    ext,
      textLength:  contentText.length,
      preview:     contentText.slice(0, 200),
    });

  } catch (err) {
    res.status(500).json({ ok: false, error: err.message });
  }
});

module.exports = router;