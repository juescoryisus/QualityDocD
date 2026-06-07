'use strict';

const mongoose = require('mongoose');

const documentMetaSchema = new mongoose.Schema(
  {
    documentId:    { type: Number,   required: true, unique: true, index: true },
    code:          { type: String,   required: true, unique: true },
    title:         { type: String,   required: true },
    description:   { type: String,   default: '' },
    category:      { type: String,   default: '', index: true },
    standard:      { type: String,   default: '' },
    tags:          { type: [String], default: [], index: true },
    fileExtension: { type: String,   default: '' },
    status:        { type: String,   default: 'Draft', index: true },
    isPublic:      { type: Boolean,  default: false },
    createdAt:     { type: Date,     default: Date.now },
    updatedAt:     { type: Date,     default: Date.now },
  },
  { collection: 'document_metadata' }
);

// Índice full-text con pesos por campo
documentMetaSchema.index(
  { title: 'text', description: 'text', tags: 'text', standard: 'text' },
  {
    weights: { title: 10, tags: 5, standard: 3, description: 1 },
    name: 'idx_fulltext',
  }
);

module.exports = mongoose.model('DocumentMeta', documentMetaSchema);
