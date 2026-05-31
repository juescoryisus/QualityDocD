using Microsoft.EntityFrameworkCore;
using System.Text;
using System.Text.Json;
using QualityDocD.Data;
using QualityDocD.Models.Domain;
using QualityDocD.Models.ViewModels;

namespace QualityDocD.Services;

public class DocumentService
{
    private readonly AppDbContext _sql;
    private readonly AuditDbContext _pg;
    private readonly IWebHostEnvironment _env;
    private readonly IHttpContextAccessor _http;
    private readonly IHttpClientFactory _httpFactory;
    private readonly ILogger<DocumentService> _log;

    private static readonly HashSet<string> AllowedExtensions =
        new(StringComparer.OrdinalIgnoreCase)
        {
            ".pdf",".docx",".doc",".xlsx",".xls",".pptx",".ppt",
            ".png",".jpg",".jpeg",".gif",".tif",".tiff",
            ".dwg",".dxf",".step",".stp",".iges",
            ".txt",".csv",".zip",".7z"
        };

    public DocumentService(AppDbContext sql, AuditDbContext pg,
        IWebHostEnvironment env, IHttpContextAccessor http,
        IHttpClientFactory httpFactory, ILogger<DocumentService> log)
    {
        _sql = sql;
        _pg = pg;
        _env = env;
        _http = http;
        _httpFactory = httpFactory;
        _log = log;
    }

    // ── Consultas ─────────────────────────────────────────────────────────────

    public async Task<DocumentIndexViewModel> GetIndexAsync(
        string? status, string? category, string? search)
    {
        var query = _sql.Documents.Include(d => d.CreatedByUser).AsQueryable();

        if (!string.IsNullOrWhiteSpace(status) &&
            Enum.TryParse<DocumentStatus>(status, out var s))
            query = query.Where(d => d.Status == s);

        if (!string.IsNullOrWhiteSpace(category))
            query = query.Where(d => d.Category == category);

        if (!string.IsNullOrWhiteSpace(search))
            query = query.Where(d => d.Title.Contains(search)
                                  || d.Description.Contains(search)
                                  || d.Code.Contains(search));

        var docs = await query.OrderByDescending(d => d.CreatedAt).ToListAsync();

        var counts = await _sql.Documents
            .GroupBy(d => d.Status)
            .Select(g => new { Status = g.Key.ToString(), Count = g.Count() })
            .ToDictionaryAsync(x => x.Status, x => x.Count);

        return new DocumentIndexViewModel
        {
            Documents = docs.Select(ToRow).ToList(),
            FilterStatus = status,
            FilterCategory = category,
            SearchQuery = search,
            StatusCounts = counts,
        };
    }

    public async Task<DocumentDetailViewModel?> GetDetailAsync(int id)
    {
        var doc = await _sql.Documents
            .Include(d => d.CreatedByUser)
            .Include(d => d.Approvals).ThenInclude(a => a.Reviewer)
            .Include(d => d.AuditLogs).ThenInclude(l => l.User)
            .FirstOrDefaultAsync(d => d.Id == id);

        if (doc == null) return null;

        var reviewers = await _sql.Users
            .Where(u => u.IsActive
                     && (u.Role == "Reviewer" || u.Role == "Manager")
                     && u.Id != doc.CreatedByUserId)
            .Select(u => new UserSelectItem { Id = u.Id, Username = u.Username, Department = u.Department })
            .ToListAsync();

        return new DocumentDetailViewModel
        {
            Id = doc.Id,
            Code = doc.Code,
            Title = doc.Title,
            Description = doc.Description,
            Category = doc.Category,
            Standard = doc.Standard,
            Version = doc.Version,
            Status = doc.Status.ToString(),
            Tags = doc.Tags,
            OriginalFileName = doc.OriginalFileName,
            FileExtension = doc.FileExtension,
            FileSizeBytes = doc.FileSizeBytes,
            IsPublic = doc.IsPublic,
            CreatedBy = doc.CreatedByUser.Username,
            CreatedAt = doc.CreatedAt,
            UpdatedAt = doc.UpdatedAt,
            ApprovedAt = doc.ApprovedAt,
            ExpiresAt = doc.ExpiresAt,
            Approvals = doc.Approvals.OrderBy(a => a.ApprovalOrder)
                .Select(a => new ApprovalRowViewModel
                {
                    Id = a.Id,
                    ReviewerName = a.Reviewer.Username,
                    Order = a.ApprovalOrder,
                    Status = a.Status.ToString(),
                    Comments = a.Comments,
                    ReviewedAt = a.ReviewedAt,
                }).ToList(),
            AuditLogs = doc.AuditLogs.OrderByDescending(l => l.CreatedAt).Take(20)
                .Select(l => new AuditLogRowViewModel
                {
                    Action = l.Action,
                    Username = l.User?.Username,
                    OldValue = l.OldValue,
                    NewValue = l.NewValue,
                    CreatedAt = l.CreatedAt,
                }).ToList(),
            AvailableReviewers = reviewers,
            SubmitReview = new SubmitReviewViewModel { DocumentId = doc.Id },
        };
    }

    // ── Crear ─────────────────────────────────────────────────────────────────

    public async Task<(bool ok, string? error, int docId)> CreateAsync(
        DocumentFormViewModel form, int userId)
    {
        var uploadPath = GetUploadPath();
        string stored = "", original = "", ext = "", ct = "";
        long size = 0;

        if (form.File != null)
        {
            var (valid, err, s, o, e, c, sz) = await SaveFileAsync(form.File, uploadPath);
            if (!valid) return (false, err, 0);
            (stored, original, ext, ct, size) = (s, o, e, c, sz);
        }

        var count = await _sql.Documents.CountAsync();
        var code = $"QD-{count + 1:D4}";

        var doc = new Document
        {
            Code = code,
            Title = form.Title,
            Description = form.Description,
            Category = form.Category,
            Standard = form.Standard,
            Tags = form.Tags,
            IsPublic = form.IsPublic,
            ExpiresAt = form.ExpiresAt,
            StoredFileName = stored,
            OriginalFileName = original,
            FileExtension = ext,
            ContentType = ct,
            FileSizeBytes = size,
            CreatedByUserId = userId,
        };

        _sql.Documents.Add(doc);
        await _sql.SaveChangesAsync();

        await AddAuditAsync(doc, userId, "Created", null, "Draft");
        await SyncPgAuditAsync(doc, userId, "Created", null, "Draft");
        await SyncSearchServiceAsync(doc);   // ← indexa en MongoDB via Node.js

        return (true, null, doc.Id);
    }

    // ── Actualizar ────────────────────────────────────────────────────────────

    public async Task<(bool ok, string? error)> UpdateAsync(
        int id, DocumentFormViewModel form, int userId)
    {
        var doc = await _sql.Documents.FindAsync(id);
        if (doc == null) return (false, "Documento no encontrado.");

        if (form.File != null)
        {
            var (valid, err, s, o, e, c, sz) = await SaveFileAsync(form.File, GetUploadPath());
            if (!valid) return (false, err);
            DeleteFile(doc.StoredFileName);
            (doc.StoredFileName, doc.OriginalFileName,
             doc.FileExtension, doc.ContentType, doc.FileSizeBytes) = (s, o, e, c, sz);
            doc.Version++;
        }

        doc.Title = form.Title;
        doc.Description = form.Description;
        doc.Category = form.Category;
        doc.Standard = form.Standard;
        doc.Tags = form.Tags;
        doc.IsPublic = form.IsPublic;
        doc.ExpiresAt = form.ExpiresAt;
        doc.Status = DocumentStatus.Draft;
        doc.UpdatedAt = DateTime.UtcNow;

        await _sql.SaveChangesAsync();
        await AddAuditAsync(doc, userId, "Updated", null, null);
        await SyncSearchServiceAsync(doc);   // ← actualiza índice en MongoDB

        return (true, null);
    }

    // ── Enviar a revisión ─────────────────────────────────────────────────────

    public async Task<(bool ok, string? error)> SubmitForReviewAsync(
        SubmitReviewViewModel form, int userId)
    {
        var doc = await _sql.Documents.FindAsync(form.DocumentId);
        if (doc == null) return (false, "Documento no encontrado.");
        if (doc.Status != DocumentStatus.Draft)
            return (false, "Solo se pueden enviar documentos en estado Borrador.");
        if (form.ReviewerIds.Count == 0)
            return (false, "Seleccione al menos un revisor.");

        var old = doc.Status.ToString();
        doc.Status = DocumentStatus.UnderReview;

        var existing = _sql.DocumentApprovals.Where(a => a.DocumentId == doc.Id);
        _sql.DocumentApprovals.RemoveRange(existing);

        for (int i = 0; i < form.ReviewerIds.Count; i++)
            _sql.DocumentApprovals.Add(new DocumentApproval
            {
                DocumentId = doc.Id,
                ReviewerId = form.ReviewerIds[i],
                ApprovalOrder = i + 1,
            });

        await _sql.SaveChangesAsync();
        await AddAuditAsync(doc, userId, "StatusChange", old, "UnderReview");
        await SyncPgAuditAsync(doc, userId, "StatusChange", old, "UnderReview");
        await SyncSearchServiceAsync(doc);   // ← actualiza estado en MongoDB

        return (true, null);
    }

    // ── Procesar aprobación ───────────────────────────────────────────────────

    public async Task<(bool ok, string? error)> ProcessApprovalAsync(
        ApprovalActionViewModel form, int userId)
    {
        var approval = await _sql.DocumentApprovals
            .Include(a => a.Document)
            .FirstOrDefaultAsync(a => a.Id == form.ApprovalId
                                   && a.DocumentId == form.DocumentId
                                   && a.ReviewerId == userId
                                   && a.Status == ApprovalStatus.Pending);

        if (approval == null)
            return (false, "Aprobación no encontrada o ya procesada.");

        approval.Comments = form.Comments;
        approval.ReviewedAt = DateTime.UtcNow;
        var doc = approval.Document;

        if (form.Action.Equals("approve", StringComparison.OrdinalIgnoreCase))
        {
            approval.Status = ApprovalStatus.Approved;
            var all = await _sql.DocumentApprovals
                .Where(a => a.DocumentId == doc.Id).ToListAsync();
            if (all.All(a => a.Status == ApprovalStatus.Approved))
            {
                doc.Status = DocumentStatus.Approved;
                doc.ApprovedAt = DateTime.UtcNow;
                await AddAuditAsync(doc, userId, "StatusChange", "UnderReview", "Approved");
                await SyncPgAuditAsync(doc, userId, "Approved", "UnderReview", "Approved");
            }
        }
        else
        {
            approval.Status = ApprovalStatus.Rejected;
            doc.Status = DocumentStatus.Draft;
            await AddAuditAsync(doc, userId, "StatusChange", "UnderReview", "Draft");
            await SyncPgAuditAsync(doc, userId, "Rejected", "UnderReview", "Draft");
        }

        await _sql.SaveChangesAsync();
        await SyncSearchServiceAsync(doc);   // ← actualiza estado en MongoDB
        return (true, null);
    }

    // ── Marcar obsoleto ───────────────────────────────────────────────────────

    public async Task<bool> MarkObsoleteAsync(int id, int userId)
    {
        var doc = await _sql.Documents.FindAsync(id);
        if (doc == null) return false;
        var old = doc.Status.ToString();
        doc.Status = DocumentStatus.Obsolete;
        await _sql.SaveChangesAsync();
        await AddAuditAsync(doc, userId, "StatusChange", old, "Obsolete");
        await SyncPgAuditAsync(doc, userId, "Obsolete", old, "Obsolete");
        await SyncSearchServiceAsync(doc);   // ← actualiza estado en MongoDB
        return true;
    }

    // ── Descargar archivo ─────────────────────────────────────────────────────

    public async Task<(Stream stream, string fileName, string contentType)?> DownloadAsync(
        int id, int userId)
    {
        var doc = await _sql.Documents.FindAsync(id);
        if (doc == null || string.IsNullOrEmpty(doc.StoredFileName)) return null;
        var path = Path.Combine(GetUploadPath(), doc.StoredFileName);
        if (!File.Exists(path)) return null;
        await AddAuditAsync(doc, userId, "Downloaded", null, null);
        var ct = string.IsNullOrEmpty(doc.ContentType) ? "application/octet-stream" : doc.ContentType;
        return (new FileStream(path, FileMode.Open, FileAccess.Read), doc.OriginalFileName, ct);
    }

    // ── Búsqueda — llama al microservicio Node.js ─────────────────────────────

    public async Task<SearchResultViewModel> SearchAsync(
        string query, string? category, string? status)
    {
        try
        {
            var client = _httpFactory.CreateClient("SearchService");
            var url = $"/api/search?q={Uri.EscapeDataString(query ?? "")}";
            if (!string.IsNullOrWhiteSpace(category))
                url += $"&category={Uri.EscapeDataString(category)}";
            if (!string.IsNullOrWhiteSpace(status))
                url += $"&status={Uri.EscapeDataString(status)}";

            var response = await client.GetAsync(url);
            if (!response.IsSuccessStatusCode)
                return FallbackSearchAsync(query, category, status).Result;

            var json = await response.Content.ReadAsStringAsync();
            var result = JsonSerializer.Deserialize<SearchServiceResponse>(json,
                new JsonSerializerOptions { PropertyNameCaseInsensitive = true });

            return new SearchResultViewModel
            {
                Query = query ?? string.Empty,
                Total = result?.Total ?? 0,
                Results = result?.Results?.Select(r => new SearchResultItem
                {
                    DocumentId = r.DocumentId,
                    Code = r.Code,
                    Title = r.Title,
                    Category = r.Category,
                    Standard = r.Standard,
                    Tags = r.Tags ?? new(),
                    Status = r.Status,
                    FileExtension = r.FileExtension,
                }).ToList() ?? new(),
            };
        }
        catch (Exception ex)
        {
            _log.LogWarning("Search service no disponible: {Message}. Usando SQL Server.", ex.Message);
            return await FallbackSearchAsync(query, category, status);
        }
    }

    // ── Búsqueda fallback con SQL Server (si Node.js no está disponible) ──────

    private async Task<SearchResultViewModel> FallbackSearchAsync(
        string? query, string? category, string? status)
    {
        var q = _sql.Documents.AsQueryable();

        if (!string.IsNullOrWhiteSpace(query))
            q = q.Where(d => d.Title.Contains(query)
                           || d.Description.Contains(query)
                           || d.Code.Contains(query)
                           || d.Tags.Contains(query));

        if (!string.IsNullOrWhiteSpace(category))
            q = q.Where(d => d.Category == category);

        if (!string.IsNullOrWhiteSpace(status) &&
            Enum.TryParse<DocumentStatus>(status, out var s))
            q = q.Where(d => d.Status == s);

        var docs = await q.OrderByDescending(d => d.CreatedAt).Take(50).ToListAsync();

        return new SearchResultViewModel
        {
            Query = query ?? string.Empty,
            Total = docs.Count,
            Results = docs.Select(d => new SearchResultItem
            {
                DocumentId = d.Id,
                Code = d.Code,
                Title = d.Title,
                Category = d.Category,
                Standard = d.Standard,
                Tags = d.Tags.Split(',', StringSplitOptions.RemoveEmptyEntries)
                                  .Select(t => t.Trim()).ToList(),
                Status = d.Status.ToString(),
                FileExtension = d.FileExtension,
            }).ToList(),
        };
    }

    // ── Reporte de cumplimiento ────────────────────────────────────────────────

    public async Task<ComplianceReportViewModel> GetComplianceReportAsync(
        string dateFrom, string dateTo)
    {
        var rows = await _sql.Documents
            .GroupBy(d => new { d.Category, d.Standard })
            .Select(g => new ComplianceRow
            {
                Category = g.Key.Category,
                Standard = g.Key.Standard,
                Approved = g.Count(d => d.Status == DocumentStatus.Approved),
                Draft = g.Count(d => d.Status == DocumentStatus.Draft),
                UnderReview = g.Count(d => d.Status == DocumentStatus.UnderReview),
                Obsolete = g.Count(d => d.Status == DocumentStatus.Obsolete),
                Total = g.Count(),
                LastApproved = g.Max(d => d.ApprovedAt),
            })
            .OrderBy(r => r.Category).ThenBy(r => r.Standard)
            .ToListAsync();

        return new ComplianceReportViewModel
        {
            Rows = rows,
            DateFrom = dateFrom,
            DateTo = dateTo,
            TotalApproved = rows.Sum(r => r.Approved),
            TotalDocuments = rows.Sum(r => r.Total),
        };
    }

    // ── Reporte de auditoría ──────────────────────────────────────────────────

    public async Task<AuditReportViewModel> GetAuditReportAsync(int page, int pageSize)
    {
        page = Math.Max(1, page);
        pageSize = Math.Clamp(pageSize, 10, 100);

        var query = _sql.AuditLogs
            .Include(l => l.Document)
            .Include(l => l.User)
            .OrderByDescending(l => l.CreatedAt);

        var total = await query.CountAsync();
        var logs = await query.Skip((page - 1) * pageSize).Take(pageSize).ToListAsync();

        return new AuditReportViewModel
        {
            TotalCount = total,
            Page = page,
            PageSize = pageSize,
            Logs = logs.Select(l => new AuditReportRow
            {
                Action = l.Action,
                DocumentCode = l.Document.Code,
                DocumentTitle = l.Document.Title,
                Username = l.User?.Username,
                OldValue = l.OldValue,
                NewValue = l.NewValue,
                IpAddress = l.IpAddress,
                CreatedAt = l.CreatedAt,
            }).ToList(),
        };
    }

    // ── Sincroniza con microservicio Node.js → MongoDB ────────────────────────

    private async Task SyncSearchServiceAsync(Document doc)
    {
        try
        {
            var client = _httpFactory.CreateClient("SearchService");
            var payload = new
            {
                documentId = doc.Id,
                code = doc.Code,
                title = doc.Title,
                description = doc.Description,
                category = doc.Category,
                standard = doc.Standard,
                tags = doc.Tags,
                fileExtension = doc.FileExtension,
                status = doc.Status.ToString(),
                isPublic = doc.IsPublic,
            };
            var json = JsonSerializer.Serialize(payload);
            var content = new StringContent(json, Encoding.UTF8, "application/json");
            await client.PostAsync("/api/documents", content);
        }
        catch (Exception ex)
        {
            _log.LogWarning("No se pudo sincronizar con Search Service: {Message}", ex.Message);
        }
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    private async Task AddAuditAsync(Document doc, int userId,
        string action, string? old, string? @new)
    {
        _sql.AuditLogs.Add(new AuditLog
        {
            DocumentId = doc.Id,
            UserId = userId,
            Action = action,
            OldValue = old,
            NewValue = @new,
            IpAddress = _http.HttpContext?.Connection.RemoteIpAddress?.ToString(),
        });
        await _sql.SaveChangesAsync();
    }

    private async Task SyncPgAuditAsync(Document doc, int userId,
        string action, string? old, string? @new)
    {
        try
        {
            var user = await _sql.Users.FindAsync(userId);
            _pg.AuditEntries.Add(new AuditEntry
            {
                DocumentId = doc.Id,
                DocumentCode = doc.Code,
                UserId = userId,
                Username = user?.Username,
                Action = action,
                OldValue = old,
                NewValue = @new,
                IpAddress = _http.HttpContext?.Connection.RemoteIpAddress?.ToString(),
            });
            await _pg.SaveChangesAsync();
        }
        catch { /* PostgreSQL no disponible — continúa */ }
    }

    private static async Task<(bool ok, string? err, string stored,
        string original, string ext, string ct, long size)>
        SaveFileAsync(IFormFile file, string uploadPath)
    {
        var ext = Path.GetExtension(file.FileName).ToLowerInvariant();
        if (!AllowedExtensions.Contains(ext))
            return (false, $"Extensión '{ext}' no permitida.", "", "", "", "", 0);

        Directory.CreateDirectory(uploadPath);
        var stored = $"{Guid.NewGuid()}{ext}";
        var full = Path.Combine(uploadPath, stored);
        await using var fs = new FileStream(full, FileMode.Create);
        await file.CopyToAsync(fs);
        return (true, null, stored, file.FileName, ext, file.ContentType, file.Length);
    }

    private void DeleteFile(string stored)
    {
        if (string.IsNullOrEmpty(stored)) return;
        var path = Path.Combine(GetUploadPath(), stored);
        if (File.Exists(path)) File.Delete(path);
    }

    private string GetUploadPath() =>
        Path.Combine(_env.ContentRootPath, "wwwroot", "uploads");

    private static DocumentRowViewModel ToRow(Document d) => new()
    {
        Id = d.Id,
        Code = d.Code,
        Title = d.Title,
        Category = d.Category,
        Standard = d.Standard,
        Version = d.Version,
        Status = d.Status.ToString(),
        FileExtension = d.FileExtension,
        CreatedBy = d.CreatedByUser.Username,
        CreatedAt = d.CreatedAt,
        ApprovedAt = d.ApprovedAt,
        IsPublic = d.IsPublic,
    };
}

// ── DTOs para deserializar respuesta del Search Service ──────────────────────

internal class SearchServiceResponse
{
    public bool Ok { get; set; }
    public int Total { get; set; }
    public string Query { get; set; } = string.Empty;
    public List<SearchServiceItem> Results { get; set; } = new();
}

internal class SearchServiceItem
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