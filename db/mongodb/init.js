// ─────────────────────────────────────────────────────────────────────────────
//  QualityDoc — Inicialización MongoDB
//  Se ejecuta automáticamente la primera vez que el contenedor arranca.
// ─────────────────────────────────────────────────────────────────────────────

// Cambiar a la base de datos del proyecto
db = db.getSiblingDB('qualitydoc_meta');

// Crear usuario con permisos solo sobre esta base de datos
db.createUser({
  user: 'qualitydoc',
  pwd:  'QualityDoc_Mongo_2026!',
  roles: [{ role: 'readWrite', db: 'qualitydoc_meta' }]
});

// Crear colección e índices iniciales
db.createCollection('document_metadata');

db.document_metadata.createIndex({ documentId: 1 }, { unique: true, name: 'idx_documentId' });
db.document_metadata.createIndex({ code:        1 }, { unique: true, name: 'idx_code'       });
db.document_metadata.createIndex({ category:    1 },                { name: 'idx_category'  });
db.document_metadata.createIndex({ tags:        1 },                { name: 'idx_tags'      });
db.document_metadata.createIndex({ status:      1 },                { name: 'idx_status'    });

// Índice full-text con pesos por campo
db.document_metadata.createIndex(
  { title: 'text', description: 'text', tags: 'text', standard: 'text' },
  {
    name:    'idx_fulltext',
    weights: { title: 10, tags: 5, standard: 3, description: 1 }
  }
);

print('✔ MongoDB: base de datos qualitydoc_meta inicializada correctamente.');