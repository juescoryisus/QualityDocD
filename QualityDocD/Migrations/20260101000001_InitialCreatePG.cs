using System;
using Microsoft.EntityFrameworkCore.Migrations;
using Npgsql.EntityFrameworkCore.PostgreSQL.Metadata;

#nullable disable

namespace QualityDocD.Migrations.AuditDb;

public partial class InitialCreatePG : Migration
{
    protected override void Up(MigrationBuilder migrationBuilder)
    {
        migrationBuilder.CreateTable(
            name: "audit_entries",
            columns: table => new
            {
                Id = table.Column<int>(nullable: false)
                    .Annotation("Npgsql:ValueGenerationStrategy",
                        NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                DocumentId = table.Column<int>(nullable: false),
                DocumentCode = table.Column<string>(maxLength: 20, nullable: false,
                                    defaultValue: ""),
                UserId = table.Column<int>(nullable: true),
                Username = table.Column<string>(maxLength: 100, nullable: true),
                Action = table.Column<string>(maxLength: 100, nullable: false),
                OldValue = table.Column<string>(nullable: true),
                NewValue = table.Column<string>(nullable: true),
                IpAddress = table.Column<string>(maxLength: 45, nullable: true),
                CreatedAt = table.Column<DateTime>(nullable: false)
            },
            constraints: t => t.PrimaryKey("PK_audit_entries", x => x.Id));

        migrationBuilder.CreateIndex("idx_ae_doc", "audit_entries", "DocumentId");
        migrationBuilder.CreateIndex("idx_ae_date", "audit_entries", "CreatedAt");

        migrationBuilder.CreateTable(
            name: "compliance_records",
            columns: table => new
            {
                Id = table.Column<int>(nullable: false)
                    .Annotation("Npgsql:ValueGenerationStrategy",
                        NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                Category = table.Column<string>(maxLength: 100, nullable: false),
                Standard = table.Column<string>(maxLength: 100, nullable: false),
                Approved = table.Column<int>(nullable: false, defaultValue: 0),
                Draft = table.Column<int>(nullable: false, defaultValue: 0),
                UnderReview = table.Column<int>(nullable: false, defaultValue: 0),
                Obsolete = table.Column<int>(nullable: false, defaultValue: 0),
                Total = table.Column<int>(nullable: false, defaultValue: 0),
                LastUpdated = table.Column<DateTime>(nullable: false)
            },
            constraints: t => t.PrimaryKey("PK_compliance_records", x => x.Id));

        migrationBuilder.AddUniqueConstraint(
            "uq_compliance_cat_std", "compliance_records",
            new[] { "Category", "Standard" });

        migrationBuilder.CreateTable(
            name: "access_logs",
            columns: table => new
            {
                Id = table.Column<int>(nullable: false)
                    .Annotation("Npgsql:ValueGenerationStrategy",
                        NpgsqlValueGenerationStrategy.IdentityByDefaultColumn),
                DocumentId = table.Column<int>(nullable: false),
                Username = table.Column<string>(maxLength: 100, nullable: true),
                IpAddress = table.Column<string>(maxLength: 45, nullable: true),
                Action = table.Column<string>(maxLength: 50, nullable: false,
                                 defaultValue: "view"),
                AccessedAt = table.Column<DateTime>(nullable: false)
            },
            constraints: t => t.PrimaryKey("PK_access_logs", x => x.Id));
    }

    protected override void Down(MigrationBuilder migrationBuilder)
    {
        migrationBuilder.DropTable("access_logs");
    }
}