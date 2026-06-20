IF OBJECT_ID(N'[__EFMigrationsHistory]') IS NULL
BEGIN
    CREATE TABLE [__EFMigrationsHistory] (
        [MigrationId] nvarchar(150) NOT NULL,
        [ProductVersion] nvarchar(32) NOT NULL,
        CONSTRAINT [PK___EFMigrationsHistory] PRIMARY KEY ([MigrationId])
    );
END;
GO

BEGIN TRANSACTION;
CREATE TABLE [Users] (
    [Id] int NOT NULL IDENTITY,
    [Username] nvarchar(450) NOT NULL,
    [Email] nvarchar(450) NOT NULL,
    [PasswordHash] nvarchar(max) NOT NULL,
    [Role] nvarchar(max) NOT NULL DEFAULT N'Viewer',
    [Department] nvarchar(max) NOT NULL,
    [IsActive] bit NOT NULL DEFAULT CAST(1 AS bit),
    [CreatedAt] datetime2 NOT NULL,
    [LastLoginAt] datetime2 NULL,
    CONSTRAINT [PK_Users] PRIMARY KEY ([Id])
);

CREATE TABLE [Documents] (
    [Id] int NOT NULL IDENTITY,
    [Code] nvarchar(450) NOT NULL,
    [Title] nvarchar(max) NOT NULL,
    [Description] nvarchar(max) NOT NULL,
    [Category] nvarchar(max) NOT NULL,
    [Standard] nvarchar(max) NOT NULL,
    [Version] int NOT NULL,
    [Status] nvarchar(max) NOT NULL,
    [StoredFileName] nvarchar(max) NOT NULL,
    [OriginalFileName] nvarchar(max) NOT NULL,
    [FileExtension] nvarchar(max) NOT NULL,
    [ContentType] nvarchar(max) NOT NULL,
    [FileSizeBytes] bigint NOT NULL,
    [Tags] nvarchar(max) NOT NULL,
    [IsPublic] bit NOT NULL,
    [MongoMetadataId] nvarchar(max) NULL,
    [CreatedAt] datetime2 NOT NULL,
    [UpdatedAt] datetime2 NULL,
    [ApprovedAt] datetime2 NULL,
    [RejectedAt] datetime2 NULL,
    [ExpiresAt] datetime2 NULL,
    [CreatedByUserId] int NOT NULL,
    CONSTRAINT [PK_Documents] PRIMARY KEY ([Id]),
    CONSTRAINT [FK_Documents_Users_CreatedByUserId] FOREIGN KEY ([CreatedByUserId]) REFERENCES [Users] ([Id]) ON DELETE NO ACTION
);

CREATE TABLE [AuditLogs] (
    [Id] int NOT NULL IDENTITY,
    [DocumentId] int NOT NULL,
    [UserId] int NULL,
    [Action] nvarchar(max) NOT NULL,
    [OldValue] nvarchar(max) NULL,
    [NewValue] nvarchar(max) NULL,
    [IpAddress] nvarchar(max) NULL,
    [CreatedAt] datetime2 NOT NULL,
    CONSTRAINT [PK_AuditLogs] PRIMARY KEY ([Id]),
    CONSTRAINT [FK_AuditLogs_Documents_DocumentId] FOREIGN KEY ([DocumentId]) REFERENCES [Documents] ([Id]) ON DELETE CASCADE,
    CONSTRAINT [FK_AuditLogs_Users_UserId] FOREIGN KEY ([UserId]) REFERENCES [Users] ([Id]) ON DELETE SET NULL
);

CREATE TABLE [DocumentApprovals] (
    [Id] int NOT NULL IDENTITY,
    [DocumentId] int NOT NULL,
    [ReviewerId] int NOT NULL,
    [ApprovalOrder] int NOT NULL,
    [Status] nvarchar(max) NOT NULL,
    [Comments] nvarchar(max) NULL,
    [CreatedAt] datetime2 NOT NULL,
    [ReviewedAt] datetime2 NULL,
    CONSTRAINT [PK_DocumentApprovals] PRIMARY KEY ([Id]),
    CONSTRAINT [FK_DocumentApprovals_Documents_DocumentId] FOREIGN KEY ([DocumentId]) REFERENCES [Documents] ([Id]) ON DELETE CASCADE,
    CONSTRAINT [FK_DocumentApprovals_Users_ReviewerId] FOREIGN KEY ([ReviewerId]) REFERENCES [Users] ([Id]) ON DELETE NO ACTION
);

CREATE INDEX [IX_AuditLogs_DocumentId] ON [AuditLogs] ([DocumentId]);

CREATE INDEX [IX_AuditLogs_UserId] ON [AuditLogs] ([UserId]);

CREATE INDEX [IX_DocumentApprovals_DocumentId] ON [DocumentApprovals] ([DocumentId]);

CREATE INDEX [IX_DocumentApprovals_ReviewerId] ON [DocumentApprovals] ([ReviewerId]);

CREATE UNIQUE INDEX [IX_Documents_Code] ON [Documents] ([Code]);

CREATE INDEX [IX_Documents_CreatedByUserId] ON [Documents] ([CreatedByUserId]);

CREATE UNIQUE INDEX [IX_Users_Email] ON [Users] ([Email]);

CREATE UNIQUE INDEX [IX_Users_Username] ON [Users] ([Username]);

INSERT INTO [__EFMigrationsHistory] ([MigrationId], [ProductVersion])
VALUES (N'20260606202353_InitialSqlServer', N'9.0.0');

ALTER TABLE [Users] ADD [CompanyId] int NOT NULL DEFAULT 0;

ALTER TABLE [Documents] ADD [CompanyId] int NOT NULL DEFAULT 0;

CREATE TABLE [Companies] (
    [Id] int NOT NULL IDENTITY,
    [Name] nvarchar(max) NOT NULL,
    [Slug] nvarchar(450) NOT NULL,
    [Email] nvarchar(450) NOT NULL,
    [IsActive] bit NOT NULL DEFAULT CAST(1 AS bit),
    [CreatedAt] datetime2 NOT NULL,
    CONSTRAINT [PK_Companies] PRIMARY KEY ([Id])
);


                INSERT INTO Companies (Name, Slug, Email, IsActive, CreatedAt)
                VALUES ('Default', 'default', 'admin@default.local', 1, GETUTCDATE())
            

UPDATE Users SET CompanyId = (SELECT TOP 1 Id FROM Companies WHERE Slug = 'default')

UPDATE Documents SET CompanyId = (SELECT TOP 1 Id FROM Companies WHERE Slug = 'default')

CREATE INDEX [IX_Users_CompanyId] ON [Users] ([CompanyId]);

CREATE INDEX [IX_Documents_CompanyId] ON [Documents] ([CompanyId]);

CREATE UNIQUE INDEX [IX_Companies_Email] ON [Companies] ([Email]);

CREATE UNIQUE INDEX [IX_Companies_Slug] ON [Companies] ([Slug]);

ALTER TABLE [Documents] ADD CONSTRAINT [FK_Documents_Companies_CompanyId] FOREIGN KEY ([CompanyId]) REFERENCES [Companies] ([Id]) ON DELETE NO ACTION;

ALTER TABLE [Users] ADD CONSTRAINT [FK_Users_Companies_CompanyId] FOREIGN KEY ([CompanyId]) REFERENCES [Companies] ([Id]) ON DELETE NO ACTION;

INSERT INTO [__EFMigrationsHistory] ([MigrationId], [ProductVersion])
VALUES (N'20260611072242_AddMultiCompanySupport', N'9.0.0');

ALTER TABLE [Users] DROP CONSTRAINT [FK_Users_Companies_CompanyId];

DECLARE @var0 sysname;
SELECT @var0 = [d].[name]
FROM [sys].[default_constraints] [d]
INNER JOIN [sys].[columns] [c] ON [d].[parent_column_id] = [c].[column_id] AND [d].[parent_object_id] = [c].[object_id]
WHERE ([d].[parent_object_id] = OBJECT_ID(N'[Users]') AND [c].[name] = N'Department');
IF @var0 IS NOT NULL EXEC(N'ALTER TABLE [Users] DROP CONSTRAINT [' + @var0 + '];');
ALTER TABLE [Users] DROP COLUMN [Department];

DECLARE @var1 sysname;
SELECT @var1 = [d].[name]
FROM [sys].[default_constraints] [d]
INNER JOIN [sys].[columns] [c] ON [d].[parent_column_id] = [c].[column_id] AND [d].[parent_object_id] = [c].[object_id]
WHERE ([d].[parent_object_id] = OBJECT_ID(N'[Users]') AND [c].[name] = N'Role');
IF @var1 IS NOT NULL EXEC(N'ALTER TABLE [Users] DROP CONSTRAINT [' + @var1 + '];');
ALTER TABLE [Users] DROP COLUMN [Role];

DROP INDEX [IX_Users_Username] ON [Users];
DECLARE @var2 sysname;
SELECT @var2 = [d].[name]
FROM [sys].[default_constraints] [d]
INNER JOIN [sys].[columns] [c] ON [d].[parent_column_id] = [c].[column_id] AND [d].[parent_object_id] = [c].[object_id]
WHERE ([d].[parent_object_id] = OBJECT_ID(N'[Users]') AND [c].[name] = N'Username');
IF @var2 IS NOT NULL EXEC(N'ALTER TABLE [Users] DROP CONSTRAINT [' + @var2 + '];');
ALTER TABLE [Users] ALTER COLUMN [Username] nvarchar(100) NOT NULL;
CREATE UNIQUE INDEX [IX_Users_Username] ON [Users] ([Username]);

DROP INDEX [IX_Users_Email] ON [Users];
DECLARE @var3 sysname;
SELECT @var3 = [d].[name]
FROM [sys].[default_constraints] [d]
INNER JOIN [sys].[columns] [c] ON [d].[parent_column_id] = [c].[column_id] AND [d].[parent_object_id] = [c].[object_id]
WHERE ([d].[parent_object_id] = OBJECT_ID(N'[Users]') AND [c].[name] = N'Email');
IF @var3 IS NOT NULL EXEC(N'ALTER TABLE [Users] DROP CONSTRAINT [' + @var3 + '];');
ALTER TABLE [Users] ALTER COLUMN [Email] nvarchar(200) NOT NULL;
CREATE UNIQUE INDEX [IX_Users_Email] ON [Users] ([Email]);

DECLARE @var4 sysname;
SELECT @var4 = [d].[name]
FROM [sys].[default_constraints] [d]
INNER JOIN [sys].[columns] [c] ON [d].[parent_column_id] = [c].[column_id] AND [d].[parent_object_id] = [c].[object_id]
WHERE ([d].[parent_object_id] = OBJECT_ID(N'[Users]') AND [c].[name] = N'CompanyId');
IF @var4 IS NOT NULL EXEC(N'ALTER TABLE [Users] DROP CONSTRAINT [' + @var4 + '];');
ALTER TABLE [Users] ALTER COLUMN [CompanyId] int NULL;

ALTER TABLE [Users] ADD [DepartmentId] int NOT NULL DEFAULT 0;

ALTER TABLE [Users] ADD [RoleId] int NOT NULL DEFAULT 0;

CREATE TABLE [Departments] (
    [Id] int NOT NULL IDENTITY,
    [Name] nvarchar(100) NOT NULL,
    [CompanyId] int NOT NULL,
    [IsActive] bit NOT NULL DEFAULT CAST(1 AS bit),
    [CreatedAt] datetime2 NOT NULL,
    CONSTRAINT [PK_Departments] PRIMARY KEY ([Id]),
    CONSTRAINT [FK_Departments_Companies_CompanyId] FOREIGN KEY ([CompanyId]) REFERENCES [Companies] ([Id]) ON DELETE NO ACTION
);

CREATE TABLE [Roles] (
    [Id] int NOT NULL IDENTITY,
    [Name] nvarchar(50) NOT NULL,
    [Description] nvarchar(200) NULL,
    [IsActive] bit NOT NULL DEFAULT CAST(1 AS bit),
    [CreatedAt] datetime2 NOT NULL,
    CONSTRAINT [PK_Roles] PRIMARY KEY ([Id])
);

CREATE INDEX [IX_Users_DepartmentId] ON [Users] ([DepartmentId]);

CREATE INDEX [IX_Users_RoleId] ON [Users] ([RoleId]);

CREATE INDEX [IX_Departments_CompanyId] ON [Departments] ([CompanyId]);

CREATE UNIQUE INDEX [IX_Roles_Name] ON [Roles] ([Name]);

ALTER TABLE [Users] ADD CONSTRAINT [FK_Users_Companies_CompanyId] FOREIGN KEY ([CompanyId]) REFERENCES [Companies] ([Id]);

ALTER TABLE [Users] ADD CONSTRAINT [FK_Users_Departments_DepartmentId] FOREIGN KEY ([DepartmentId]) REFERENCES [Departments] ([Id]) ON DELETE NO ACTION;

ALTER TABLE [Users] ADD CONSTRAINT [FK_Users_Roles_RoleId] FOREIGN KEY ([RoleId]) REFERENCES [Roles] ([Id]) ON DELETE NO ACTION;

INSERT INTO [__EFMigrationsHistory] ([MigrationId], [ProductVersion])
VALUES (N'20260618061125_AddRolesAndDepartments', N'9.0.0');

ALTER TABLE [Users] ADD [CompanyId] int NULL;

CREATE INDEX [IX_Users_CompanyId] ON [Users] ([CompanyId]);

ALTER TABLE [Users] ADD CONSTRAINT [FK_Users_Companies_CompanyId] FOREIGN KEY ([CompanyId]) REFERENCES [Companies] ([Id]);

INSERT INTO [__EFMigrationsHistory] ([MigrationId], [ProductVersion])
VALUES (N'20260618065202_SyncModel', N'9.0.0');

ALTER TABLE [Users] DROP CONSTRAINT [FK_Users_Companies_CompanyId];

DROP INDEX [IX_Users_CompanyId] ON [Users];

DECLARE @var5 sysname;
SELECT @var5 = [d].[name]
FROM [sys].[default_constraints] [d]
INNER JOIN [sys].[columns] [c] ON [d].[parent_column_id] = [c].[column_id] AND [d].[parent_object_id] = [c].[object_id]
WHERE ([d].[parent_object_id] = OBJECT_ID(N'[Users]') AND [c].[name] = N'CompanyId');
IF @var5 IS NOT NULL EXEC(N'ALTER TABLE [Users] DROP CONSTRAINT [' + @var5 + '];');
ALTER TABLE [Users] DROP COLUMN [CompanyId];

INSERT INTO [__EFMigrationsHistory] ([MigrationId], [ProductVersion])
VALUES (N'20260618070412_RemoveCompanyUsers', N'9.0.0');

INSERT INTO [__EFMigrationsHistory] ([MigrationId], [ProductVersion])
VALUES (N'20260619041311_InitialCreate', N'9.0.0');

COMMIT;
GO

