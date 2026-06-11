namespace QualityDocD.Models.Domain;

/// <summary>
/// Estados del documento en el flujo de aprobación.
/// Draft → UnderReview → PendingChanges ↔ UnderReview → Approved → Obsolete
///                     ↘ Rejected
/// </summary>
public enum DocumentStatus
{
    /// <summary>Borrador inicial, aún no enviado a revisión.</summary>
    Draft,

    /// <summary>Enviado a revisores, esperando aprobaciones.</summary>
    UnderReview,

    /// <summary>Un revisor solicitó cambios; el autor debe corregir y reenviar.</summary>
    PendingChanges,

    /// <summary>En segunda ronda de revisión tras aplicar los cambios solicitados.</summary>
    UnderSecondReview,

    /// <summary>Aprobado por todos los revisores requeridos.</summary>
    Approved,

    /// <summary>Rechazado definitivamente por los revisores.</summary>
    Rejected,

    /// <summary>Versión obsoleta, reemplazada por una versión más reciente.</summary>
    Obsolete
}

public class Document
{
    public int Id { get; set; }
    public string Code { get; set; } = string.Empty;
    public string Title { get; set; } = string.Empty;
    public string Description { get; set; } = string.Empty;
    public string Category { get; set; } = string.Empty;
    public string Standard { get; set; } = string.Empty;
    public int Version { get; set; } = 1;
    public DocumentStatus Status { get; set; } = DocumentStatus.Draft;

    // Archivo adjunto
    public string StoredFileName { get; set; } = string.Empty;
    public string OriginalFileName { get; set; } = string.Empty;
    public string FileExtension { get; set; } = string.Empty;
    public string ContentType { get; set; } = string.Empty;
    public long FileSizeBytes { get; set; }

    // Metadatos
    public string Tags { get; set; } = string.Empty;
    public bool IsPublic { get; set; }
    public string? MongoMetadataId { get; set; }

    // Fechas
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime? UpdatedAt { get; set; }
    public DateTime? ApprovedAt { get; set; }
    public DateTime? RejectedAt { get; set; }
    public DateTime? ExpiresAt { get; set; }

    // Multi-empresa
    public int CompanyId { get; set; }
    public Company Company { get; set; } = null!;

    // FK y navegación
    public int CreatedByUserId { get; set; }
    public User CreatedByUser { get; set; } = null!;

    public ICollection<DocumentApproval> Approvals { get; set; } = new List<DocumentApproval>();
    public ICollection<AuditLog> AuditLogs { get; set; } = new List<AuditLog>();

    // ── Helpers de dominio ────────────────────────────────────────────────────

    /// <summary>Retorna true si el documento puede ser enviado a revisión.</summary>
    public bool CanSubmitForReview() =>
        Status is DocumentStatus.Draft or DocumentStatus.PendingChanges;

    /// <summary>Retorna true si el documento está en algún estado de revisión activa.</summary>
    public bool IsInReview() =>
        Status is DocumentStatus.UnderReview or DocumentStatus.UnderSecondReview;

    /// <summary>Retorna true si el documento tiene un estado final (no puede cambiar a menos que se cree nueva versión).</summary>
    public bool IsFinal() =>
        Status is DocumentStatus.Approved or DocumentStatus.Rejected or DocumentStatus.Obsolete;
}
