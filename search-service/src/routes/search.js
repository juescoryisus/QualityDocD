'use strict';

const express      = require('express');
const router       = express.Router();
const DocumentMeta = require('../models/DocumentMeta');

// ── GET /api/search?q=&category=&status=&limit= ───────────────────────────────
router.get('/search', async (req, res) => {
  try {
    const { q, category, status, limit = 50 } = req.query;
    const filter = {};

    if (q && q.trim()) filter.$text = { $search: q.trim() };
    if (category)       filter.category = category;
    if (status)         filter.status   = status;

    const projection = q && q.trim() ? { score: { $meta: 'textScore' } } : {};
    const sort       = q && q.trim() ? { score: { $meta: 'textScore' } } : { updatedAt: -1 };

    const docs = await DocumentMeta
      .find(filter, projection)
      .sort(sort)
      .limit(Number(limit));

    res.json({ ok: true, total: docs.length, query: q || '', results: docs });
  } catch (err) {
    res.status(500).json({ ok: false, error: err.message });
  }
});

// ── GET /api/categories — lista de categorías únicas ─────────────────────────
router.get('/categories', async (_req, res) => {
  try {
    const categories = await DocumentMeta.distinct('category');
    res.json({ ok: true, categories: categories.filter(Boolean).sort() });
  } catch (err) {
    res.status(500).json({ ok: false, error: err.message });
  }
});

// ── GET /api/documents/:documentId — obtener metadatos de un documento ────────
router.get('/documents/:documentId', async (req, res) => {
  try {
    const doc = await DocumentMeta.findOne({ documentId: Number(req.params.documentId) });
    if (!doc) return res.status(404).json({ ok: false, error: 'Not found' });
    res.json({ ok: true, document: doc });
  } catch (err) {
    res.status(500).json({ ok: false, error: err.message });
  }
});

// ── POST /api/documents — indexar o actualizar un documento ───────────────────
router.post('/documents', async (req, res) => {
  try {
    const {
      documentId, code, title, description,
      category, standard, tags, fileExtension,
      status, isPublic,
    } = req.body;

    if (!documentId || !code || !title) {
      return res.status(400).json({ ok: false, error: 'documentId, code y title son obligatorios.' });
    }

    const parsedTags = Array.isArray(tags)
      ? tags
      : (tags || '').split(',').map(t => t.trim()).filter(Boolean);

    const meta = await DocumentMeta.findOneAndUpdate(
      { documentId },
      {
        documentId,
        code,
        title,
        description:   description   || '',
        category:      category       || '',
        standard:      standard       || '',
        tags:          parsedTags,
        fileExtension: fileExtension  || '',
        status:        status         || 'Draft',
        isPublic:      !!isPublic,
        updatedAt:     new Date(),
      },
      { upsert: true, new: true }
    );

    res.status(201).json({ ok: true, id: meta._id });
  } catch (err) {
    res.status(500).json({ ok: false, error: err.message });
  }
});

// ── DELETE /api/documents/:documentId — eliminar del índice ──────────────────
router.delete('/documents/:documentId', async (req, res) => {
  try {
    await DocumentMeta.deleteOne({ documentId: Number(req.params.documentId) });
    res.json({ ok: true });
  } catch (err) {
    res.status(500).json({ ok: false, error: err.message });
  }
});

module.exports = router;
