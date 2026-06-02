namespace QualityDocD.Models.Domain;

/// <summary>
/// Estado de cada aprobación individual de un revisor.
/// </summary>
public enum ApprovalStatus
{
    /// <summary>El revisor aún no ha tomado ninguna decisión.</summary>
    Pending,

    /// <summary>El revisor aprobó el documento sin observaciones.</summary>
    Approved,

    /// <summary>El revisor rechazó el documento definitivamente.</summary>
    Rejected,

    /// <summary>El revisor solicita cambios antes de aprobar (estado intermedio).</summary>
    RequestChanges
}

public class DocumentApproval
{
    public int Id { get; set; }
    public int DocumentId { get; set; }
    public Document Document { get; set; } = null!;
    public int ReviewerId { get; set; }
    public User Reviewer { get; set; } = null!;

    /// <summary>Orden de revisión (1 = primero, 2 = segundo, etc.).</summary>
    public int ApprovalOrder { get; set; } = 1;

    public ApprovalStatus Status { get; set; } = ApprovalStatus.Pending;

    /// <summary>Comentarios del revisor (obligatorio si Status = RequestChanges o Rejected).</summary>
    public string? Comments { get; set; }

    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime? ReviewedAt { get; set; }
}
