// =============================================================================
//  QualityDoc — Migración MongoDB: actualizar índice full-text
//  Ejecutar UNA VEZ en despliegues existentes donde ya existe idx_fulltext.
//
//  Uso:
//    mongosh "mongodb://mongoadmin:<password>@localhost:27017" \
//      --authenticationDatabase admin \
//      db/mongodb/migrate_add_contentText_index.js
// =============================================================================

db = db.getSiblingDB('qualitydoc_meta');

print('▶ Eliminando índice full-text anterior (idx_fulltext)...');
try {
  db.document_metadata.dropIndex('idx_fulltext');
  print('  ✔ Índice anterior eliminado.');
} catch (e) {
  print('  ℹ Índice anterior no encontrado, continuando...');
}

print('▶ Creando nuevo índice full-text con contentText y companyId...');
db.document_metadata.createIndex(
  {
    title:       'text',
    contentText: 'text',
    tags:        'text',
    description: 'text',
    standard:    'text',
  },
  {
    name:    'idx_fulltext',
    weights: {
      title:       10,
      contentText:  8,
      tags:         5,
      standard:     3,
      description:  1,
    }
  }
);
print('  ✔ Nuevo índice full-text creado con campo contentText (peso 8).');

print('▶ Creando índice companyId (multiempresa)...');
try {
  db.document_metadata.createIndex({ companyId: 1 }, { name: 'idx_companyId' });
  print('  ✔ Índice companyId creado.');
} catch (e) {
  print('  ℹ Índice companyId ya existe, omitiendo.');
}

print('✅ Migración completada.');
