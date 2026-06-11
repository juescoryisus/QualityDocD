using System;
using Microsoft.EntityFrameworkCore.Migrations;

#nullable disable

namespace QualityDocD.Migrations.SqlServer
{
    public partial class AddMultiCompanySupport : Migration
    {
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.AddColumn<int>(
                name: "CompanyId",
                table: "Users",
                type: "int",
                nullable: false,
                defaultValue: 0);

            migrationBuilder.AddColumn<int>(
                name: "CompanyId",
                table: "Documents",
                type: "int",
                nullable: false,
                defaultValue: 0);

            migrationBuilder.CreateTable(
                name: "Companies",
                columns: table => new
                {
                    Id = table.Column<int>(type: "int", nullable: false)
                        .Annotation("SqlServer:Identity", "1, 1"),
                    Name = table.Column<string>(type: "nvarchar(max)", nullable: false),
                    Slug = table.Column<string>(type: "nvarchar(450)", nullable: false),
                    Email = table.Column<string>(type: "nvarchar(450)", nullable: false),
                    IsActive = table.Column<bool>(type: "bit", nullable: false, defaultValue: true),
                    CreatedAt = table.Column<DateTime>(type: "datetime2", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_Companies", x => x.Id);
                });

            // ★ LÍNEA 1 — crear empresa por defecto
            migrationBuilder.Sql(@"
                INSERT INTO Companies (Name, Slug, Email, IsActive, CreatedAt)
                VALUES ('Default', 'default', 'admin@default.local', 1, GETUTCDATE())
            ");

            // ★ LÍNEA 2 — asignar empresa a usuarios existentes
            migrationBuilder.Sql(
                "UPDATE Users SET CompanyId = (SELECT TOP 1 Id FROM Companies WHERE Slug = 'default')");

            // ★ LÍNEA 3 — asignar empresa a documentos existentes
            migrationBuilder.Sql(
                "UPDATE Documents SET CompanyId = (SELECT TOP 1 Id FROM Companies WHERE Slug = 'default')");

            migrationBuilder.CreateIndex(
                name: "IX_Users_CompanyId",
                table: "Users",
                column: "CompanyId");

            migrationBuilder.CreateIndex(
                name: "IX_Documents_CompanyId",
                table: "Documents",
                column: "CompanyId");

            migrationBuilder.CreateIndex(
                name: "IX_Companies_Email",
                table: "Companies",
                column: "Email",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_Companies_Slug",
                table: "Companies",
                column: "Slug",
                unique: true);

            migrationBuilder.AddForeignKey(
                name: "FK_Documents_Companies_CompanyId",
                table: "Documents",
                column: "CompanyId",
                principalTable: "Companies",
                principalColumn: "Id",
                onDelete: ReferentialAction.Restrict);

            migrationBuilder.AddForeignKey(
                name: "FK_Users_Companies_CompanyId",
                table: "Users",
                column: "CompanyId",
                principalTable: "Companies",
                principalColumn: "Id",
                onDelete: ReferentialAction.Restrict);
        }

        protected override void Down(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropForeignKey(name: "FK_Documents_Companies_CompanyId", table: "Documents");
            migrationBuilder.DropForeignKey(name: "FK_Users_Companies_CompanyId", table: "Users");
            migrationBuilder.DropTable(name: "Companies");
            migrationBuilder.DropIndex(name: "IX_Users_CompanyId", table: "Users");
            migrationBuilder.DropIndex(name: "IX_Documents_CompanyId", table: "Documents");
            migrationBuilder.DropColumn(name: "CompanyId", table: "Users");
            migrationBuilder.DropColumn(name: "CompanyId", table: "Documents");
        }
    }
}