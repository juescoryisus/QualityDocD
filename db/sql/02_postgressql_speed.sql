-- =============================================================================
-- QualityDocD — Seed de datos de prueba para PostgreSQL (audit_entries)
-- Ejecutar con: psql -h localhost -U qualitydoc -d qualitydoc_audit -f 02_postgresql_seed.sql
-- =============================================================================

-- Asegurarse de que la tabla exista (EF Core la crea, pero por si acaso)
CREATE TABLE IF NOT EXISTS audit_entries (
    "Id"             SERIAL PRIMARY KEY,
    "Action"         VARCHAR(100) NOT NULL,
    "DocumentId"     INTEGER,
    "DocumentCode"   VARCHAR(50),
    "DocumentTitle"  VARCHAR(500),
    "UserId"         INTEGER,
    "Username"       VARCHAR(100),
    "OldValue"       TEXT,
    "NewValue"       TEXT,
    "CreatedAt"      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

TRUNCATE TABLE audit_entries RESTART IDENTITY;

INSERT INTO audit_entries
    ("Action", "DocumentId", "DocumentCode", "DocumentTitle", "UserId", "Username", "OldValue", "NewValue", "CreatedAt")
VALUES
-- Creaciones
('Created',       5,  'MAN-000', 'Política de Calidad',                      1, 'admin',    NULL,          'Draft',       NOW() - INTERVAL '3 months'),
('Created',       6,  'PRO-001', 'Procedimiento de Compras',                 1, 'admin',    NULL,          'Draft',       NOW() - INTERVAL '2 months 15 days'),
('Created',       7,  'AUD-003', 'Informe de Auditoría Externa 2025',        2, 'gerente',  NULL,          'Draft',       NOW() - INTERVAL '1 month 20 days'),
('Created',       8,  'PRO-003', 'Procedimiento de Diseño y Desarrollo',     2, 'gerente',  NULL,          'Draft',       NOW() - INTERVAL '50 days'),
('Created',       9,  'MAN-001-V1', 'Manual de Calidad v1 (Obsoleto)',       1, 'admin',    NULL,          'Draft',       NOW() - INTERVAL '2 years'),
('Created',       1,  'MAN-001', 'Manual de Calidad v3',                     5, 'editor',   NULL,          'Draft',       NOW()),
('Created',       2,  'PRO-015', 'Procedimiento de Auditoría Interna',       5, 'editor',   NULL,          'Draft',       NOW() - INTERVAL '1 day'),
('Created',       3,  'PRO-008', 'Control de No Conformidades',              5, 'editor',   NULL,          'Draft',       NOW() - INTERVAL '3 days'),
('Created',       4,  'REC-022', 'Registro de Capacitaciones',               5, 'editor',   NULL,          'Draft',       NOW() - INTERVAL '5 days'),
('Created',       10, 'PRO-002-V1', 'Procedimiento de Ventas v1 (Obsoleto)', 2, 'gerente',  NULL,          'Draft',       NOW() - INTERVAL '1 year'),

-- Envíos a revisión
('StatusChanged', 5,  'MAN-000', 'Política de Calidad',                      1, 'admin',    'Draft',       'UnderReview', NOW() - INTERVAL '3 months' + INTERVAL '1 day'),
('StatusChanged', 6,  'PRO-001', 'Procedimiento de Compras',                 1, 'admin',    'Draft',       'UnderReview', NOW() - INTERVAL '2 months 13 days'),
('StatusChanged', 7,  'AUD-003', 'Informe de Auditoría Externa 2025',        2, 'gerente',  'Draft',       'UnderReview', NOW() - INTERVAL '1 month 18 days'),
('StatusChanged', 8,  'PRO-003', 'Procedimiento de Diseño y Desarrollo',     2, 'gerente',  'Draft',       'UnderReview', NOW() - INTERVAL '48 days'),
('StatusChanged', 3,  'PRO-008', 'Control de No Conformidades',              5, 'editor',   'Draft',       'UnderReview', NOW() - INTERVAL '2 days'),
('StatusChanged', 4,  'REC-022', 'Registro de Capacitaciones',               5, 'editor',   'Draft',       'UnderReview', NOW() - INTERVAL '4 days'),

-- Aprobaciones
('StatusChanged', 5,  'MAN-000', 'Política de Calidad',                      2, 'gerente',  'UnderReview', 'Approved',    NOW() - INTERVAL '3 months' + INTERVAL '3 days'),
('StatusChanged', 6,  'PRO-001', 'Procedimiento de Compras',                 2, 'gerente',  'UnderReview', 'Approved',    NOW() - INTERVAL '2 months 12 days'),
('StatusChanged', 7,  'AUD-003', 'Informe de Auditoría Externa 2025',        2, 'gerente',  'UnderReview', 'Approved',    NOW() - INTERVAL '1 month 16 days'),
('StatusChanged', 8,  'PRO-003', 'Procedimiento de Diseño y Desarrollo',     2, 'gerente',  'UnderReview', 'Approved',    NOW() - INTERVAL '40 days'),

-- Revisiones de aprobación individuales
('ApprovalAdded', 3,  'PRO-008', 'Control de No Conformidades',              3, 'revisor1', NULL,          'Pending',     NOW() - INTERVAL '2 days'),
('ApprovalReviewed', 3, 'PRO-008', 'Control de No Conformidades',            3, 'revisor1', 'Pending',     'Approved',    NOW() - INTERVAL '1 day'),
('ApprovalAdded', 4,  'REC-022', 'Registro de Capacitaciones',               3, 'revisor1', NULL,          'Pending',     NOW() - INTERVAL '3 days'),

-- Ediciones
('Updated',       3,  'PRO-008', 'Control de No Conformidades',              5, 'editor',   'v1',          'v2',          NOW() - INTERVAL '4 days'),
('Updated',       1,  'MAN-001', 'Manual de Calidad v3',                     5, 'editor',   NULL,          NULL,          NOW() - INTERVAL '2 hours'),

-- Obsolescencia
('StatusChanged', 9,  'MAN-001-V1', 'Manual de Calidad v1 (Obsoleto)',       1, 'admin',    'Approved',    'Obsolete',    NOW() - INTERVAL '1 year'),
('StatusChanged', 10, 'PRO-002-V1', 'Procedimiento de Ventas v1 (Obsoleto)', 1, 'admin',    'Approved',    'Obsolete',    NOW() - INTERVAL '6 months'),

-- Descargas / accesos
('Downloaded',    5,  'MAN-000', 'Política de Calidad',                      6, 'viewer',   NULL,          NULL,          NOW() - INTERVAL '2 days'),
('Downloaded',    6,  'PRO-001', 'Procedimiento de Compras',                 6, 'viewer',   NULL,          NULL,          NOW() - INTERVAL '1 day'),
('Downloaded',    7,  'AUD-003', 'Informe de Auditoría Externa 2025',        2, 'gerente',  NULL,          NULL,          NOW() - INTERVAL '5 hours'),
('Downloaded',    8,  'PRO-003', 'Procedimiento de Diseño y Desarrollo',     3, 'revisor1', NULL,          NULL,          NOW() - INTERVAL '3 hours');

SELECT COUNT(*) AS total_registros FROM audit_entries;
\echo '✔ PostgreSQL audit_entries seed completado correctamente.'
