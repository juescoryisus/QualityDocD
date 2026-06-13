'use strict';

require('dotenv').config();

const express  = require('express');
const mongoose = require('mongoose');

const searchRouter = require('./routes/search');

const app  = express();
const PORT = process.env.PORT || 3001;

app.use(express.json());

// ── Conexión MongoDB ──────────────────────────────────────────────────────────
const MONGO_HOST = process.env.MONGO_HOST     || 'localhost';
const MONGO_PORT = process.env.MONGO_PORT     || '27017';
const MONGO_DB   = process.env.MONGO_DB       || 'qualitydoc_meta';
const MONGO_USER = process.env.MONGO_USER;
const MONGO_PASS = process.env.MONGO_PASSWORD;

let mongoUri;
if (MONGO_USER && MONGO_PASS) {
  mongoUri = `mongodb://${MONGO_USER}:${MONGO_PASS}@${MONGO_HOST}:${MONGO_PORT}/${MONGO_DB}?authSource=admin`;
} else {
  // Sin autenticación (desarrollo local sin Docker)
  mongoUri = `mongodb://${MONGO_HOST}:${MONGO_PORT}/${MONGO_DB}`;
}

mongoose
  .connect(mongoUri)
  .then(() => console.log(`[MongoDB] Conectado a ${MONGO_DB} en ${MONGO_HOST}:${MONGO_PORT}`))
  .catch(err => {
    console.error('[MongoDB] Error de conexión:', err.message);
    process.exit(1);
  });

// ── Rutas ─────────────────────────────────────────────────────────────────────

// Health check
app.get('/health', async (_req, res) => {
  const state = mongoose.connection.readyState;
  res.json({
    ok:      true,
    mongo:   state === 1 ? 'connected' : 'disconnected',
    service: 'QualityDoc Search Service',
  });
});

// Rutas de búsqueda y documentos
app.use('/api', searchRouter);

const uploadRouter = require('./routes/upload');  // ← agregar
// ...
app.use('/api', searchRouter);
app.use('/api', uploadRouter);   // ← agregar

// ── Inicio ────────────────────────────────────────────────────────────────────
app.listen(PORT, () => {
  console.log(`[Search Service] Escuchando en puerto ${PORT}`);
});
