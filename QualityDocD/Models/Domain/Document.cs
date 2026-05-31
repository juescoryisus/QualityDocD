namespace QualityDocD.Models.Domain;

public enum DocumentStatus { Draft, UnderReview, Approved, Obsolete }

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
    public DateTime? ExpiresAt { get; set; }

    // FK y navegación
    public int CreatedByUserId { get; set; }
    public User CreatedByUser { get; set; } = null!;

    public ICollection<DocumentApproval> Approvals { get; set; } = new List<DocumentApproval>();
    public ICollection<AuditLog> AuditLogs { get; set; } = new List<AuditLog>();
}
