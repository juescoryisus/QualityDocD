using Microsoft.EntityFrameworkCore.Migrations;

#nullable disable

namespace QualityDocD.Migrations;

/// <summary>
/// Migración que añade los nuevos estados intermedios al flujo de aprobación:
/// - DocumentStatus: PendingChanges, UnderSecondReview, Rejected
/// - DocumentApproval: RejectedAt (fecha de rechazo del documento)
///
/// No requiere cambios en la DB cuando Status se guarda como string (HasConversion).
/// Solo añade la columna RejectedAt al documento.
/// </summary>
public partial class AddIntermediateDocumentStatuses : Migration
{
    protected override void Up(MigrationBuilder migrationBuilder)
    {
        // Añadir columna RejectedAt a Documents
        migrationBuilder.AddColumn<DateTime>(
            name: "RejectedAt",
            table: "Documents",
            type: "datetime2",
            nullable: true);
    }

    protected override void Down(MigrationBuilder migrationBuilder)
    {
        migrationBuilder.DropColumn(
            name: "RejectedAt",
            table: "Documents");
    }
}
