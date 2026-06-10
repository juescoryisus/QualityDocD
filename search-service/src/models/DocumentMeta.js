'use strict';

const mongoose = require('mongoose');

const documentMetaSchema = new mongoose.Schema(
  {
    documentId:    { type: Number,   required: true, unique: true, index: true },
    versionId:     { type: Number,   default: null  },
    companyId:     { type: Number,   default: null,  index: true },
    code:          { type: String,   default: ''    },
    title:         { type: String,   required: true },
    description:   { type: String,   default: ''    },
    category:      { type: String,   default: '',    index: true },
    standard:      { type: String,   default: ''    },
    tags:          { type: [String], default: [],    index: true },
    fileExtension: { type: String,   default: ''    },
    format:        { type: String,   default: ''    },
    version:       { type: String,   default: ''    },
    // Texto completo extraído del documento (PDF u otro formato)
    // Incluido en el índice full-text para búsqueda por contenido
    contentText:   { type: String,   default: ''    },
    status:        { type: String,   default: 'Draft', index: true },
    isPublic:      { type: Boolean,  default: false },
    approvedAt:    { type: Date,     default: null  },
    createdAt:     { type: Date,     default: Date.now },
    updatedAt:     { type: Date,     default: Date.now },
  },
  { collection: 'document_metadata' }
);

// Índice full-text con pesos por campo
// contentText (peso 8) permite buscar por el texto dentro del documento PDF/Word
documentMetaSchema.index(
  {
    title:       'text',
    contentText: 'text',
    tags:        'text',
    description: 'text',
    standard:    'text',
  },
  {
    weights: {
      title:       10,
      tags:         5,
      contentText:  8,
      standard:     3,
      description:  1,
    },
    name: 'idx_fulltext',
  }
);

module.exports = mongoose.model('DocumentMeta', documentMetaSchema);
s