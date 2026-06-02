-- =============================================================================
-- QualityDocD — Seed de datos de prueba para SQL Server
-- Ejecutar con: sqlcmd -S localhost,1433 -U sa -P "QualityDoc2026!" -d QualityDocDB -i 01_sqlserver_seed.sql
-- =============================================================================

USE QualityDocDB;
GO

-- ─── Usuarios de prueba ───────────────────────────────────────────────────────
-- Contraseñas: Admin123!, Gerente123!, Revisor123!, Editor123!, Viewer123!
-- (hashes BCrypt generados con cost=11)

DELETE FROM AuditLogs;
DELETE FROM DocumentApprovals;
DELETE FROM Documents;
DELETE FROM Users;
GO

SET IDENTITY_INSERT Users ON;

INSERT INTO Users (Id, Username, Email, PasswordHash, Role, Department, IsActive, CreatedAt)
VALUES
(1,  'admin',    'admin@qualitydoc.local',    '$2a$11$fYZVXrqG5i3p8rQ/S9cxzO4PFBzPbpWN6v5JQRBqVlpVbEHBvKU9W', 'Admin',   'TI',         1, GETUTCDATE()),
(2,  'gerente',  'gerente@qualitydoc.local',  '$2a$11$T9Km9pEzT8fGq7p.vBhpGuD7SJoKi/cg3dqRlPO4MAtBHYDRfkxmq', 'Manager', 'Calidad',    1, GETUTCDATE()),
(3,  'revisor1', 'revisor1@qualitydoc.local', '$2a$11$X3nFVJkT1Q5l6kSM2bGcyuPRJCfJV2G.BLFTa6JyJLqSLbj8nBGiC', 'Reviewer','Producción', 1, GETUTCDATE()),
(4,  'revisor2', 'revisor2@qualitydoc.local', '$2a$11$X3nFVJkT1Q5l6kSM2bGcyuPRJCfJV2G.BLFTa6JyJLqSLbj8nBGiC', 'Reviewer','Calidad',    1, GETUTCDATE()),
(5,  'editor',   'editor@qualitydoc.local',   '$2a$11$rZlV6X0i.mF7bByJJCJ9JuT/OBivjLXxzptf9lXxkVuvL4GcqfAim', 'Editor',  'Operaciones',1, GETUTCDATE()),
(6,  'viewer',   'viewer@qualitydoc.local',   '$2a$11$dGg.3q0kSL0PpGN5KWpXouZcEqxjqb7d1Fq09d0LJRCbWK3BuMWqy', 'Viewer',  'Logística',  1, GETUTCDATE());

SET IDENTITY_INSERT Users OFF;
GO

-- ─── Documentos de prueba ─────────────────────────────────────────────────────
SET IDENTITY_INSERT Documents ON;

INSERT INTO Documents
    (Id, Code, Title, Description, Category, Standard, Version, Status,
     StoredFileName, OriginalFileName, FileExtension, ContentType, FileSizeBytes,
     Tags, IsPublic, CreatedAt, CreatedByUserId)
VALUES
-- Borradores
(1,  'MAN-001', 'Manual de Calidad v3',
     'Manual principal del sistema de gestión de calidad ISO 9001.',
     'Manual', 'ISO 9001:2015', 1, 'Draft',
     'a1b2c3d4-0001-0000-0000-000000000001.pdf', 'Manual_Calidad_v3.pdf',
     '.pdf', 'application/pdf', 204800,
     'calidad,ISO9001,manual', 0, GETUTCDATE(), 5),

(2,  'PRO-015', 'Procedimiento de Auditoría Interna',
     'Describe el proceso completo para realizar auditorías internas.',
     'Procedure', 'ISO 9001:2015', 2, 'Draft',
     'a1b2c3d4-0002-0000-0000-000000000002.docx', 'Auditoria_Interna_v2.docx',
     '.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 98304,
     'auditoria,interna,proceso', 0, GETUTCDATE(), 5),

-- En revisión
(3,  'PRO-008', 'Control de No Conformidades',
     'Procedimiento para identificar, registrar y gestionar no conformidades.',
     'Procedure', 'ISO 9001:2015', 1, 'UnderReview',
     'a1b2c3d4-0003-0000-0000-000000000003.pdf', 'Control_NC.pdf',
     '.pdf', 'application/pdf', 153600,
     'no conformidad,control,gestion', 0, DATEADD(DAY,-3,GETUTCDATE()), 5),

(4,  'REC-022', 'Registro de Capacitaciones',
     'Formato estándar para el registro de actividades de formación del personal.',
     'Record', 'ISO 9001:2015', 3, 'UnderReview',
     'a1b2c3d4-0004-0000-0000-000000000004.xlsx', 'Registro_Capacitaciones.xlsx',
     '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 45056,
     'capacitacion,rrhh,registro', 1, DATEADD(DAY,-5,GETUTCDATE()), 5),

-- Aprobados
(5,  'MAN-000', 'Política de Calidad',
     'Declaración formal de la política de calidad de la organización.',
     'Manual', 'ISO 9001:2015', 4, 'Approved',
     'a1b2c3d4-0005-0000-0000-000000000005.pdf', 'Politica_Calidad.pdf',
     '.pdf', 'application/pdf', 81920,
     'politica,calidad,ISO9001', 1, DATEADD(MONTH,-3,GETUTCDATE()), 1),

(6,  'PRO-001', 'Procedimiento de Compras',
     'Define el proceso de evaluación, selección y seguimiento de proveedores.',
     'Procedure', 'ISO 9001:2015', 2, 'Approved',
     'a1b2c3d4-0006-0000-0000-000000000006.pdf', 'Procedimiento_Compras.pdf',
     '.pdf', 'application/pdf', 122880,
     'compras,proveedores,evaluacion', 1, DATEADD(MONTH,-2,GETUTCDATE()), 1),

(7,  'AUD-003', 'Informe de Auditoría Externa 2025',
     'Resultados y hallazgos de la auditoría de certificación realizada en 2025.',
     'Audit', 'ISO 9001:2015', 1, 'Approved',
     'a1b2c3d4-0007-0000-0000-000000000007.pdf', 'Auditoria_Externa_2025.pdf',
     '.pdf', 'application/pdf', 512000,
     'auditoria,externa,2025,certificacion', 0, DATEADD(MONTH,-1,GETUTCDATE()), 2),

(8,  'PRO-003', 'Procedimiento de Diseño y Desarrollo',
     'Controla las etapas de diseño y desarrollo de nuevos productos y servicios.',
     'Procedure', 'ISO 9001:2015', 1, 'Approved',
     'a1b2c3d4-0008-0000-0000-000000000008.pdf', 'Diseno_Desarrollo.pdf',
     '.pdf', 'application/pdf', 167936,
     'diseño,desarrollo,producto', 0, DATEADD(DAY,-45,GETUTCDATE()), 2),

-- Obsoletos
(9,  'MAN-001-V1', 'Manual de Calidad v1 (Obsoleto)',
     'Primera versión del manual de calidad. Reemplazada por MAN-001.',
     'Manual', 'ISO 9001:2008', 1, 'Obsolete',
     'a1b2c3d4-0009-0000-0000-000000000009.pdf', 'Manual_Calidad_v1.pdf',
     '.pdf', 'application/pdf', 180224,
     'calidad,manual,obsoleto,v1', 0, DATEADD(YEAR,-2,GETUTCDATE()), 1),

(10, 'PRO-002-V1', 'Procedimiento de Ventas v1 (Obsoleto)',
     'Versión anterior del proceso de ventas.',
     'Procedure', 'ISO 9001:2008', 1, 'Obsolete',
     'a1b2c3d4-0010-0000-0000-000000000010.pdf', 'Ventas_v1.pdf',
     '.pdf', 'application/pdf', 77824,
     'ventas,obsoleto,v1', 0, DATEADD(YEAR,-1,GETUTCDATE()), 2);

SET IDENTITY_INSERT Documents OFF;
GO

-- Marcar fechas de aprobación en documentos aprobados
UPDATE Documents SET ApprovedAt = DATEADD(DAY,-7,GETUTCDATE())  WHERE Id = 5;
UPDATE Documents SET ApprovedAt = DATEADD(DAY,-14,GETUTCDATE()) WHERE Id = 6;
UPDATE Documents SET ApprovedAt = DATEADD(DAY,-10,GETUTCDATE()) WHERE Id = 7;
UPDATE Documents SET ApprovedAt = DATEADD(DAY,-20,GETUTCDATE()) WHERE Id = 8;
GO

-- ─── Aprobaciones de prueba ───────────────────────────────────────────────────
SET IDENTITY_INSERT DocumentApprovals ON;

INSERT INTO DocumentApprovals (Id, DocumentId, ReviewerId, ApprovalOrder, Status, Comments, CreatedAt, ReviewedAt)
VALUES
-- Doc 3 (UnderReview) — primer revisor aprobó, segundo pendiente
(1, 3, 3, 1, 'Approved',  'Primera revisión aprobada. Buena estructura.', DATEADD(DAY,-2,GETUTCDATE()), DATEADD(DAY,-1,GETUTCDATE())),
(2, 3, 4, 2, 'Pending',   NULL,                                            DATEADD(DAY,-1,GETUTCDATE()), NULL),

-- Doc 4 (UnderReview) — pendiente de primera revisión
(3, 4, 3, 1, 'Pending',   NULL,                                            DATEADD(DAY,-3,GETUTCDATE()), NULL),

-- Doc 5 (Approved) — aprobado por ambos revisores
(4, 5, 3, 1, 'Approved',  'Cumple con todos los requisitos de la norma.',  DATEADD(MONTH,-3,GETUTCDATE()), DATEADD(MONTH,-3,DATEADD(DAY,2,GETUTCDATE()))),
(5, 5, 4, 2, 'Approved',  'Aprobado. Sin observaciones.',                  DATEADD(MONTH,-3,GETUTCDATE()), DATEADD(MONTH,-3,DATEADD(DAY,3,GETUTCDATE()))),

-- Doc 6 (Approved)
(6, 6, 2, 1, 'Approved',  'Proceso bien documentado y claro.',             DATEADD(MONTH,-2,GETUTCDATE()), DATEADD(MONTH,-2,DATEADD(DAY,2,GETUTCDATE()))),

-- Doc 7 (Approved)
(7, 7, 2, 1, 'Approved',  'Informe completo y detallado.',                 DATEADD(MONTH,-1,GETUTCDATE()), DATEADD(MONTH,-1,DATEADD(DAY,1,GETUTCDATE()))),

-- Doc 8 (Approved)
(8, 8, 3, 1, 'Approved',  'Excelente documentación del proceso.',          DATEADD(DAY,-45,GETUTCDATE()), DATEADD(DAY,-40,GETUTCDATE())),
(9, 8, 4, 2, 'Approved',  'Sin observaciones. Aprobado.',                  DATEADD(DAY,-45,GETUTCDATE()), DATEADD(DAY,-38,GETUTCDATE()));

SET IDENTITY_INSERT DocumentApprovals OFF;
GO

-- ─── Audit Logs de prueba ─────────────────────────────────────────────────────
SET IDENTITY_INSERT AuditLogs ON;

INSERT INTO AuditLogs (Id, DocumentId, UserId, Action, OldValue, NewValue, CreatedAt)
VALUES
(1,  5, 1, 'StatusChanged',   'Draft',       'Approved',    DATEADD(MONTH,-3,DATEADD(DAY,3,GETUTCDATE()))),
(2,  6, 2, 'StatusChanged',   'Draft',       'Approved',    DATEADD(MONTH,-2,DATEADD(DAY,2,GETUTCDATE()))),
(3,  7, 2, 'StatusChanged',   'UnderReview', 'Approved',    DATEADD(MONTH,-1,DATEADD(DAY,1,GETUTCDATE()))),
(4,  8, 3, 'StatusChanged',   'UnderReview', 'Approved',    DATEADD(DAY,-38,GETUTCDATE())),
(5,  3, 5, 'Created',         NULL,          'Draft',       DATEADD(DAY,-3,GETUTCDATE())),
(6,  3, 5, 'StatusChanged',   'Draft',       'UnderReview', DATEADD(DAY,-2,GETUTCDATE())),
(7,  4, 5, 'Created',         NULL,          'Draft',       DATEADD(DAY,-5,GETUTCDATE())),
(8,  4, 5, 'StatusChanged',   'Draft',       'UnderReview', DATEADD(DAY,-4,GETUTCDATE())),
(9,  9, 1, 'StatusChanged',   'Approved',    'Obsolete',    DATEADD(YEAR,-1,GETUTCDATE())),
(10, 1, 5, 'Created',         NULL,          'Draft',       GETUTCDATE());

SET IDENTITY_INSERT AuditLogs OFF;
GO

PRINT '✔ SQL Server seed completado correctamente.';
PRINT '  Usuarios: 6  (admin, gerente, revisor1, revisor2, editor, viewer)';
PRINT '  Documentos: 10  (2 Draft, 2 UnderReview, 4 Approved, 2 Obsolete)';
PRINT '  Aprobaciones: 9  |  Audit Logs: 10';
GO
