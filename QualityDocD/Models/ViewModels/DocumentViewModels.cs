using System.ComponentModel.DataAnnotations;

namespace QualityDocD.Models.ViewModels;

// ── Lista de documentos ───────────────────────────────────────────────────────
public class DocumentIndexViewModel
{
    public List<DocumentRowViewModel> Documents { get; set; } = new();
    public Dictionary<string, int> StatusCounts { get; set; } = new();
    public string? FilterStatus { get; set; }
    public string? FilterCategory { get; set; }
    public string? SearchQuery { get; set; }
}

public class DocumentRowViewModel
{
    public int Id { get; set; }
    public string Code { get; set; } = string.Empty;
    public string Title { get; set; } = string.Empty;
    public string Category { get; set; } = string.Empty;
    public string Standard { get; set; } = string.Empty;
    public int Version { get; set; }
    public string Status { get; set; } = string.Empty;
    public string FileExtension { get; set; } = string.Empty;
    public string CreatedBy { get; set; } = string.Empty;
    public DateTime CreatedAt { get; set; }
    public DateTime? ApprovedAt { get; set; }
    public bool IsPublic { get; set; }
}

// ── Detalle de documento ──────────────────────────────────────────────────────
public class DocumentDetailViewModel
{
    public int Id { get; set; }
    public string Code { get; set; } = string.Empty;
    public string Title { get; set; } = string.Empty;
    public string Description { get; set; } = string.Empty;
    public string Category { get; set; } = string.Empty;
    public string Standard { get; set; } = string.Empty;
    public int Version { get; set; }
    public string Status { get; set; } = string.Empty;
    public string Tags { get; set; } = string.Empty;
    public string OriginalFileName { get; set; } = string.Empty;
    public string FileExtension { get; set; } = string.Empty;
    public long FileSizeBytes { get; set; }
    public bool IsPublic { get; set; }
    public string CreatedBy { get; set; } = string.Empty;
    public DateTime CreatedAt { get; set; }
    public DateTime? UpdatedAt { get; set; }
    public DateTime? ApprovedAt { get; set; }
    public DateTime? ExpiresAt { get; set; }

    public List<ApprovalRowViewModel> Approvals { get; set; } = new();
    public List<AuditLogRowViewModel> AuditLogs { get; set; } = new();
    public List<UserSelectItem> AvailableReviewers { get; set; } = new();
    public SubmitReviewViewModel SubmitReview { get; set; } = new();
}

// ── Formulario crear/editar ───────────────────────────────────────────────────
public class DocumentFormViewModel
{
    public int Id { get; set; }

    [Required(ErrorMessage = "El título es obligatorio.")]
    [StringLength(500)]
    [Display(Name = "Título")]
    public string Title { get; set; } = string.Empty;

    [Display(Name = "Descripción")]
    public string Description { get; set; } = string.Empty;

    [Required(ErrorMessage = "La categoría es obligatoria.")]
    [Display(Name = "Categoría")]
    public string Category { get; set; } = string.Empty;

    [Display(Name = "Norma / Estándar")]
    public string Standard { get; set; } = string.Empty;

    [Display(Name = "Etiquetas (separadas por coma)")]
    public string Tags { get; set; } = string.Empty;

    [Display(Name = "Visible al público")]
    public bool IsPublic { get; set; }

    [Display(Name = "Fecha de vencimiento")]
    public DateTime? ExpiresAt { get; set; }

    [Display(Name = "Archivo adjunto")]
    public IFormFile? File { get; set; }

    // Datos del archivo existente (al editar)
    public string ExistingFileName { get; set; } = string.Empty;
    public string ExistingFileExt { get; set; } = string.Empty;
    public long ExistingFileSize { get; set; }
}

// ── Enviar a revisión ─────────────────────────────────────────────────────────
public class SubmitReviewViewModel
{
    public int DocumentId { get; set; }
    public List<int> ReviewerIds { get; set; } = new();
}

// ── Acción de aprobación ──────────────────────────────────────────────────────
public class ApprovalActionViewModel
{
    public int DocumentId { get; set; }
    public int ApprovalId { get; set; }
    public string Action { get; set; } = string.Empty;
    public string? Comments { get; set; }
}

// ── Filas de apoyo ────────────────────────────────────────────────────────────
public class ApprovalRowViewModel
{
    public int Id { get; set; }
    public string ReviewerName { get; set; } = string.Empty;
    public int Order { get; set; }
    public string Status { get; set; } = string.Empty;
    public string? Comments { get; set; }
    public DateTime? ReviewedAt { get; set; }
}

public class AuditLogRowViewModel
{
    public string Action { get; set; } = string.Empty;
    public string? Username { get; set; }
    public string? OldValue { get; set; }
    public string? NewValue { get; set; }
    public DateTime CreatedAt { get; set; }
}

public class UserSelectItem
{
    public int Id { get; set; }
    public string Username { get; set; } = string.Empty;
    public string Department { get; set; } = string.Empty;
}