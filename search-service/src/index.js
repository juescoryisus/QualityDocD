'use strict';

const express  = require('express');
const mongoose = require('mongoose');

const app  = express();
const PORT = process.env.PORT || 3001;

app.use(express.json());

// ── Conexión MongoDB ──────────────────────────────────────────────────────────
const mongoUri = `mongodb://${process.env.MONGO_USER}:${process.env.MONGO_PASSWORD}` +
                 `@${process.env.MONGO_HOST || 'mongodb'}:27017/` +
                 `${process.env.MONGO_DB || 'qualitydoc_meta'}?authSource=admin`;

mongoose.connect(mongoUri)
  .then(() => console.log('MongoDB conectado'))
  .catch(err => { console.error('Error MongoDB:', err.message); process.exit(1); });

// ── Modelo ────────────────────────────────────────────────────────────────────
const docSchema = new mongoose.Schema({
  documentId:    { type: Number, required: true, unique: true },
  code:          String,
  title:         String,
  description:   String,
  category:      String,
  standard:      String,
  tags:          [String],
  fileExtension: String,
  status:        String,
  isPublic:      Boolean,
  updatedAt:     { type: Date, default: Date.now },
}, { collection: 'document_meta' });

docSchema.index({ title: 'text', description: 'text', tags: 'text', code: 'text' });
const Doc = mongoose.model('DocumentMeta', docSchema);

// ── Rutas ─────────────────────────────────────────────────────────────────────

// Health check
app.get('/health', async (req, res) => {
  const state = mongoose.connection.readyState;
  res.json({ ok: true, mongo: state === 1 ? 'connected' : 'disconnected', service: 'QualityDoc Search' });
});

// Buscar documentos
app.get('/api/search', async (req, res) => {
  try {
    const { q = '', category, status } = req.query;
    const filter = {};

    if (q.trim()) filter.$text = { $search: q };
    if (category)  filter.category = category;
    if (status)    filter.status   = status;

    const results = await Doc.find(filter)
      .sort(q.trim() ? { score: { $meta: 'textScore' } } : { updatedAt: -1 })
      .limit(50)
      .lean();

    res.json({ ok: true, total: results.length, query: q, results });
  } catch (err) {
    res.status(500).json({ ok: false, error: err.message });
  }
});

// Indexar / actualizar documento
app.post('/api/documents', async (req, res) => {
  try {
    const data = req.body;
    if (!data.documentId) return res.status(400).json({ ok: false, error: 'documentId requerido' });

    await Doc.findOneAndUpdate(
      { documentId: data.documentId },
      { ...data, updatedAt: new Date() },
      { upsert: true, new: true }
    );
    res.json({ ok: true });
  } catch (err) {
    res.status(500).json({ ok: false, error: err.message });
  }
});

// Eliminar documento del índice
app.delete('/api/documents/:id', async (req, res) => {
  try {
    await Doc.deleteOne({ documentId: parseInt(req.params.id) });
    res.json({ ok: true });
  } catch (err) {
    res.status(500).json({ ok: false, error: err.message });
  }
});

// Categorías disponibles
app.get('/api/categories', async (req, res) => {
  try {
    const cats = await Doc.distinct('category');
    res.json({ ok: true, categories: cats.filter(Boolean) });
  } catch (err) {
    res.status(500).json({ ok: false, error: err.message });
  }
});

// ── Arrancar servidor ─────────────────────────────────────────────────────────
app.listen(PORT, () => console.log(`Search service corriendo en puerto ${PORT}`));