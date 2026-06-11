namespace QualityDocD.Models.ViewModels;

// ── Reporte de cumplimiento ───────────────────────────────────────────────────
public class ComplianceReportViewModel
{
    public List<ComplianceRow> Rows { get; set; } = new();
    public string DateFrom { get; set; } = string.Empty;
    public string DateTo { get; set; } = string.Empty;
    public int TotalApproved { get; set; }
    public int TotalDocuments { get; set; }
}

public class ComplianceRow
{
    public string Category { get; set; } = string.Empty;
    public string Standard { get; set; } = string.Empty;
    public int Approved { get; set; }
    public int Draft { get; set; }
    public int UnderReview { get; set; }
    public int Obsolete { get; set; }
    public int Total { get; set; }
    public DateTime? LastApproved { get; set; }
}

public class AuditReportViewModel
{
    // Paginación
    public int TotalCount { get; set; }
    public int Page { get; set; }
    public int PageSize { get; set; }

    // Filtros activos
    public string? FilterAction { get; set; }
    public string? FilterUser { get; set; }
    public string? FilterDocument { get; set; }
    public string? FilterDateFrom { get; set; }
    public string? FilterDateTo { get; set; }

    public List<AuditReportRow> Logs { get; set; } = new();
}

public class AuditReportRow
{
    public string Action { get; set; } = string.Empty;
    public string DocumentCode { get; set; } = string.Empty;
    public string DocumentTitle { get; set; } = string.Empty;
    public string? Username { get; set; }
    public string? OldValue { get; set; }
    public string? NewValue { get; set; }
    public string? IpAddress { get; set; }
    public DateTime CreatedAt { get; set; }
}


// ── Búsqueda MongoDB ──────────────────────────────────────────────────────────
public class SearchResultViewModel
{
    public string Query { get; set; } = string.Empty;
    public int Total { get; set; }
    public List<SearchResultItem> Results { get; set; } = new();
}

public class SearchResultItem
{
    public int DocumentId { get; set; }
    public string Code { get; set; } = string.Empty;
    public string Title { get; set; } = string.Empty;
    public string Category { get; set; } = string.Empty;
    public string Standard { get; set; } = string.Empty;
    public List<string> Tags { get; set; } = new();
    public string Status { get; set; } = string.Empty;
    public string FileExtension { get; set; } = string.Empty;
}

// ── Re-indexación masiva ──────────────────────────────────────────────────────
public class ReIndexResultViewModel
{
    public int Total { get; set; }
    public int Ok { get; set; }   // documentos con texto extraído
    public int Skipped { get; set; }   // documentos sin archivo o formato no soportado
    public int Failed { get; set; }   // errores
    public List<string> Errors { get; set; } = new();
}

// ── Vista previa de archivo ───────────────────────────────────────────────
public class FilePreviewViewModel
{
    public int DocumentId { get; set; }
    public string Title { get; set; } = string.Empty;
    public string OriginalFileName { get; set; } = string.Empty;
    public string FileExtension { get; set; } = string.Empty;

    /// <summary>pdf | text | docx | unsupported</summary>
    public string PreviewType { get; set; } = string.Empty;
    public string TextContent { get; set; } = string.Empty;
    public string HtmlContent { get; set; } = string.Empty;
}