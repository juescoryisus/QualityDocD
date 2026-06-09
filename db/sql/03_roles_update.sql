-- Migración: expandir roles de 3 a 6 tipos
-- Ejecutar: sqlcmd -S localhost,1433 -U sa -P "QualityDoc2026!" -d QualityDocDB -i 03_roles_update.sql

USE QualityDocDB;
GO

-- 1. Actualizar constraint de roles
ALTER TABLE Users DROP CONSTRAINT IF EXISTS CK_Users_Role;
GO

ALTER TABLE Users ADD CONSTRAINT CK_Users_Role
  CHECK (Role IN ('VIEWER','COMMENTER','CONTRIBUTOR','OPERATOR','COMPANY_ADMIN','SUPER_ADMIN'));
GO

-- 2. Migrar roles existentes al nuevo esquema
UPDATE Users SET Role = 'SUPER_ADMIN'    WHERE Role = 'Admin';
UPDATE Users SET Role = 'COMPANY_ADMIN'  WHERE Role = 'Manager';
UPDATE Users SET Role = 'OPERATOR'       WHERE Role = 'Reviewer';
UPDATE Users SET Role = 'CONTRIBUTOR'    WHERE Role = 'Editor';
UPDATE Users SET Role = 'VIEWER'         WHERE Role = 'Viewer';
GO

-- 3. Verificar
SELECT Id, Username, Role FROM Users;
GO