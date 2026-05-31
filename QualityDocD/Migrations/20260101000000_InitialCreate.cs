using System;
using Microsoft.EntityFrameworkCore.Migrations;

#nullable disable

namespace QualityDocD.Migrations;

public partial class InitialCreate : Migration
{
    protected override void Up(MigrationBuilder migrationBuilder)
    {
        migrationBuilder.CreateTable(
            name: "Users",
            columns: table => new
            {
                Id = table.Column<int>(nullable: false)
                                    .Annotation("SqlServer:Identity", "1, 1"),
                Username = table.Column<string>(maxLength: 100, nullable: false),
                Email = table.Column<string>(maxLength: 255, nullable: false),
                PasswordHash = table.Column<string>(maxLength: 500, nullable: false),
                Role = table.Column<string>(maxLength: 50, nullable: false,
                                    defaultValue: "Viewer"),
                Department = table.Column<string>(maxLength: 100, nullable: false,
                                    defaultValue: ""),
                IsActive = table.Column<bool>(nullable: false, defaultValue: true),
                CreatedAt = table.Column<DateTime>(nullable: false),
                LastLoginAt = table.Column<DateTime>(nullable: true)
            },
            constraints: t => t.PrimaryKey("PK_Users", x => x.Id));

        migrationBuilder.CreateIndex("IX_Users_Username", "Users", "Username", unique: true);
        migrationBuilder.CreateIndex("IX_Users_Email", "Users", "Email", unique: true);

        migrationBuilder.CreateTable(
            name: "Documents",
            columns: table => new
            {
                Id = table.Column<int>(nullable: false)
                                        .Annotation("SqlServer:Identity", "1, 1"),
                Code = table.Column<string>(maxLength: 20, nullable: false),
                Title = table.Column<string>(maxLength: 500, nullable: false),
                Description = table.Column<string>(nullable: false, defaultValue: ""),
                Category = table.Column<string>(maxLength: 100, nullable: false,
                                        defaultValue: ""),
                Standard = table.Column<string>(maxLength: 100, nullable: false,
                                        defaultValue: ""),
                Version = table.Column<int>(nullable: false, defaultValue: 1),
                Status = table.Column<string>(maxLength: 50, nullable: false,
                                        defaultValue: "Draft"),
                StoredFileName = table.Column<string>(maxLength: 500, nullable: false,
                                        defaultValue: ""),
                OriginalFileName = table.Column<string>(maxLength: 500, nullable: false,
                                        defaultValue: ""),
                FileExtension = table.Column<string>(maxLength: 20, nullable: false,
                                        defaultValue: ""),
                ContentType = table.Column<string>(maxLength: 200, nullable: false,
                                        defaultValue: ""),
                FileSizeBytes = table.Column<long>(nullable: false, defaultValue: 0L),
                Tags = table.Column<string>(maxLength: 1000, nullable: false,
                                        defaultValue: ""),
                IsPublic = table.Column<bool>(nullable: false, defaultValue: false),
                CreatedByUserId = table.Column<int>(nullable: false),
                CreatedAt = table.Column<DateTime>(nullable: false),
                UpdatedAt = table.Column<DateTime>(nullable: true),
                ApprovedAt = table.Column<DateTime>(nullable: true),
                ExpiresAt = table.Column<DateTime>(nullable: true),
                MongoMetadataId = table.Column<string>(maxLength: 100, nullable: true)
            },
            constraints: t =>
            {
                t.PrimaryKey("PK_Documents", x => x.Id);
                t.ForeignKey("FK_Documents_Users_CreatedByUserId",
                    x => x.CreatedByUserId, "Users", "Id",
                    onDelete: ReferentialAction.Restrict);
            });

        migrationBuilder.CreateIndex("IX_Documents_Code", "Documents", "Code",
            unique: true);
        migrationBuilder.CreateIndex("IX_Documents_Status", "Documents", "Status");
        migrationBuilder.CreateIndex("IX_Documents_Category", "Documents", "Category");

        migrationBuilder.CreateTable(
            name: "DocumentApprovals",
            columns: table => new
            {
                Id = table.Column<int>(nullable: false)
                                     .Annotation("SqlServer:Identity", "1, 1"),
                DocumentId = table.Column<int>(nullable: false),
                ReviewerId = table.Column<int>(nullable: false),
                ApprovalOrder = table.Column<int>(nullable: false, defaultValue: 1),
                Status = table.Column<string>(maxLength: 50, nullable: false,
                                     defaultValue: "Pending"),
                Comments = table.Column<string>(maxLength: 1000, nullable: true),
                CreatedAt = table.Column<DateTime>(nullable: false),
                ReviewedAt = table.Column<DateTime>(nullable: true)
            },
            constraints: t =>
            {
                t.PrimaryKey("PK_DocumentApprovals", x => x.Id);
                t.ForeignKey("FK_DocumentApprovals_Documents_DocumentId",
                    x => x.DocumentId, "Documents", "Id",
                    onDelete: ReferentialAction.Cascade);
                t.ForeignKey("FK_DocumentApprovals_Users_ReviewerId",
                    x => x.ReviewerId, "Users", "Id",
                    onDelete: ReferentialAction.Restrict);
            });

        migrationBuilder.CreateTable(
            name: "AuditLogs",
            columns: table => new
            {
                Id = table.Column<int>(nullable: false)
                                  .Annotation("SqlServer:Identity", "1, 1"),
                DocumentId = table.Column<int>(nullable: false),
                UserId = table.Column<int>(nullable: true),
                Action = table.Column<string>(maxLength: 100, nullable: false),
                OldValue = table.Column<string>(nullable: true),
                NewValue = table.Column<string>(nullable: true),
                IpAddress = table.Column<string>(maxLength: 45, nullable: true),
                CreatedAt = table.Column<DateTime>(nullable: false)
            },
            constraints: t =>
            {
                t.PrimaryKey("PK_AuditLogs", x => x.Id);
                t.ForeignKey("FK_AuditLogs_Documents_DocumentId",
                    x => x.DocumentId, "Documents", "Id",
                    onDelete: ReferentialAction.Cascade);
                t.ForeignKey("FK_AuditLogs_Users_UserId",
                    x => x.UserId, "Users", "Id",
                    onDelete: ReferentialAction.SetNull);
            });

        // Usuarios semilla con hashes BCrypt reales
        migrationBuilder.InsertData(
            table: "Users",
            columns: new[]
            {
                "Id","Username","Email","PasswordHash",
                "Role","Department","IsActive","CreatedAt"
            },
            values: new object[,]
            {
                { 1, "admin",    "admin@qualitydoc.local",
                  BCrypt.Net.BCrypt.HashPassword("Admin123!"),
                  "Admin",    "TI",           true,
                  new DateTime(2026,1,1,0,0,0,DateTimeKind.Utc) },
                { 2, "gerente",  "gerente@qualitydoc.local",
                  BCrypt.Net.BCrypt.HashPassword("Gerente123!"),
                  "Manager",  "Calidad",      true,
                  new DateTime(2026,1,1,0,0,0,DateTimeKind.Utc) },
                { 3, "revisor",  "revisor@qualitydoc.local",
                  BCrypt.Net.BCrypt.HashPassword("Revisor123!"),
                  "Reviewer", "Operaciones",  true,
                  new DateTime(2026,1,1,0,0,0,DateTimeKind.Utc) },
                { 4, "operario", "operario@qualitydoc.local",
                  BCrypt.Net.BCrypt.HashPassword("Operario123!"),
                  "Viewer",   "Producción",   true,
                  new DateTime(2026,1,1,0,0,0,DateTimeKind.Utc) },
            });
    }

    protected override void Down(MigrationBuilder migrationBuilder)
    {
        migrationBuilder.DropTable("AuditLogs");
    }
}
