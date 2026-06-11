using Microsoft.EntityFrameworkCore;
using System.Text;
using System.Text.Json;
using MongoDB.Driver;
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
    private readonly MongoDbContext _mongo;        // ← NUEVO

    private static readonly HashSet<string> AllowedExtensions =
        new(StringComparer.OrdinalIgnoreCase)
        {
            ".pdf", ".docx", ".doc", ".xlsx", ".xls", ".pptx", ".ppt",
            ".png", ".jpg", ".jpeg", ".gif", ".tif", ".tiff",
            ".dwg", ".dxf", ".step", ".stp", ".iges",
            ".txt", ".csv", ".zip", ".7z"
        };

    public DocumentService(
        AppDbContext sql,
        AuditDbContext pg,
        IWebHostEnvironment env,
        IHttpContextAccessor http,
        IHttpClientFactory httpFactory,
        ILogger<DocumentService> log,
        MongoDbContext mongo)                                  // ← NUEVO
    {
        _sql = sql;
        _pg = pg;
        _env = env;
        _http = http;
        _httpFactory = httpFactory;
        _log = log;
        _mongo = mongo;                                   // ← NUEVO
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
            .Select(u => new UserSelectItem
            {
                Id = u.Id,
                Username = u.Username,
                Department = u.Department
            })
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
                    ReviewerId = a.ReviewerId,
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
        await SyncSearchServiceAsync(doc);

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
        await SyncSearchServiceAsync(doc);

        return (true, null);
    }

    // ── Enviar a revisión ─────────────────────────────────────────────────────

    public async Task<(bool ok, string? error)> SubmitForReviewAsync(
        SubmitReviewViewModel form, int userId)
    {
        var doc = await _sql.Documents
            .Include(d => d.Approvals)
            .FirstOrDefaultAsync(d => d.Id == form.DocumentId);

        if (doc == null) return (false, "Documento no encontrado.");

        if (!doc.CanSubmitForReview())
            return (false, "El documento debe estar en Borrador o Cambios Pendientes para enviarse a revisión.");

        if (form.ReviewerIds == null || form.ReviewerIds.Count == 0)
            return (false, "Selecciona al menos un revisor.");

        var oldStatus = doc.Status.ToString();
        var isSecondRound = doc.Status == DocumentStatus.PendingChanges;

        if (isSecondRound)
            _sql.DocumentApprovals.RemoveRange(doc.Approvals);

        for (int i = 0; i < form.ReviewerIds.Count; i++)
            _sql.DocumentApprovals.Add(new DocumentApproval
            {
                DocumentId = doc.Id,
                ReviewerId = form.ReviewerIds[i],
                ApprovalOrder = i + 1,
            });

        doc.Status = isSecondRound ? DocumentStatus.UnderSecondReview : DocumentStatus.UnderReview;
        doc.UpdatedAt = DateTime.UtcNow;

        await _sql.SaveChangesAsync();
        await AddAuditAsync(doc, userId, "StatusChange", oldStatus, doc.Status.ToString());
        await SyncPgAuditAsync(doc, userId, "StatusChange", oldStatus, doc.Status.ToString());
        await SyncSearchServiceAsync(doc);

        return (true, null);
    }

    // ── Aprobar ───────────────────────────────────────────────────────────────

    public async Task<(bool ok, string? error)> ApproveAsync(
        int documentId, int reviewerId, string? comments)
    {
        var doc = await _sql.Documents
            .Include(d => d.Approvals)
            .FirstOrDefaultAsync(d => d.Id == documentId);

        if (doc == null) return (false, "Documento no encontrado.");
        if (!doc.IsInReview()) return (false, "El documento no está en revisión.");

        var approval = doc.Approvals
            .Where(a => a.ReviewerId == reviewerId && a.Status == ApprovalStatus.Pending)
            .OrderBy(a => a.ApprovalOrder)
            .FirstOrDefault();

        if (approval == null)
            return (false, "No tienes una revisión pendiente asignada para este documento.");

        approval.Status = ApprovalStatus.Approved;
        approval.Comments = comments;
        approval.ReviewedAt = DateTime.UtcNow;

        var oldStatus = doc.Status.ToString();
        var allApproved = doc.Approvals
            .Where(a => a.Id != approval.Id)
            .All(a => a.Status == ApprovalStatus.Approved);

        if (allApproved)
        {
            doc.Status = DocumentStatus.Approved;
            doc.ApprovedAt = DateTime.UtcNow;
            doc.UpdatedAt = DateTime.UtcNow;
            await _sql.SaveChangesAsync();
            await AddAuditAsync(doc, reviewerId, "StatusChange", oldStatus, "Approved");
            await SyncPgAuditAsync(doc, reviewerId, "Approved", oldStatus, "Approved");
            await SyncSearchServiceAsync(doc);
        }
        else
        {
            await _sql.SaveChangesAsync();
            await AddAuditAsync(doc, reviewerId, "ApprovalReviewed", "Pending", "Approved");
        }

        return (true, null);
    }

    // ── Solicitar cambios ─────────────────────────────────────────────────────

    public async Task<(bool ok, string? error)> RequestChangesAsync(
        int documentId, int reviewerId, string comments)
    {
        var doc = await _sql.Documents
            .Include(d => d.Approvals)
            .FirstOrDefaultAsync(d => d.Id == documentId);

        if (doc == null) return (false, "Documento no encontrado.");
        if (!doc.IsInReview())
            return (false, "Solo se pueden solicitar cambios cuando el documento está en revisión.");

        var approval = doc.Approvals
            .Where(a => a.ReviewerId == reviewerId && a.Status == ApprovalStatus.Pending)
            .OrderBy(a => a.ApprovalOrder)
            .FirstOrDefault();

        if (approval == null)
            return (false, "No tienes una revisión pendiente asignada para este documento.");

        approval.Status = ApprovalStatus.RequestChanges;
        approval.Comments = comments;
        approval.ReviewedAt = DateTime.UtcNow;

        var oldStatus = doc.Status.ToString();
        doc.Status = DocumentStatus.PendingChanges;
        doc.UpdatedAt = DateTime.UtcNow;

        await _sql.SaveChangesAsync();
        await AddAuditAsync(doc, reviewerId, "StatusChange", oldStatus, "PendingChanges");
        await SyncPgAuditAsync(doc, reviewerId, "StatusChange", oldStatus, "PendingChanges");
        await SyncSearchServiceAsync(doc);

        return (true, null);
    }

    // ── Rechazar ──────────────────────────────────────────────────────────────

    public async Task<(bool ok, string? error)> RejectAsync(
        int documentId, int reviewerId, string comments)
    {
        var doc = await _sql.Documents
            .Include(d => d.Approvals)
            .FirstOrDefaultAsync(d => d.Id == documentId);

        if (doc == null) return (false, "Documento no encontrado.");
        if (!doc.IsInReview())
            return (false, "Solo se pueden rechazar documentos en revisión.");

        var approval = doc.Approvals
            .Where(a => a.ReviewerId == reviewerId && a.Status == ApprovalStatus.Pending)
            .OrderBy(a => a.ApprovalOrder)
            .FirstOrDefault();

        if (approval == null)
            return (false, "No tienes una revisión pendiente asignada para este documento.");

        approval.Status = ApprovalStatus.Rejected;
        approval.Comments = comments;
        approval.ReviewedAt = DateTime.UtcNow;

        var oldStatus = doc.Status.ToString();
        doc.Status = DocumentStatus.Rejected;
        doc.RejectedAt = DateTime.UtcNow;
        doc.UpdatedAt = DateTime.UtcNow;

        await _sql.SaveChangesAsync();
        await AddAuditAsync(doc, reviewerId, "StatusChange", oldStatus, "Rejected");
        await SyncPgAuditAsync(doc, reviewerId, "Rejected", oldStatus, "Rejected");
        await SyncSearchServiceAsync(doc);

        return (true, null);
    }

    // ── Marcar obsoleto ───────────────────────────────────────────────────────

    public async Task<(bool ok, string? error)> MarkObsoleteAsync(int id, int userId)
    {
        var doc = await _sql.Documents.FindAsync(id);
        if (doc == null) return (false, "Documento no encontrado.");
        if (doc.Status == DocumentStatus.Obsolete)
            return (false, "El documento ya está marcado como Obsoleto.");

        var old = doc.Status.ToString();
        doc.Status = DocumentStatus.Obsolete;
        doc.UpdatedAt = DateTime.UtcNow;

        await _sql.SaveChangesAsync();
        await AddAuditAsync(doc, userId, "StatusChange", old, "Obsolete");
        await SyncPgAuditAsync(doc, userId, "Obsolete", old, "Obsolete");
        await SyncSearchServiceAsync(doc);

        return (true, null);
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


    // ── Vista previa de archivo ────────────────────────────────────────────────
    public async Task<FilePreviewViewModel?> PreviewAsync(int id)
    {
        var doc = await _sql.Documents.FindAsync(id);
        if (doc == null || string.IsNullOrEmpty(doc.StoredFileName)) return null;

        var path = Path.Combine(GetUploadPath(), doc.StoredFileName);
        if (!File.Exists(path)) return null;

        var ext = doc.FileExtension?.ToLowerInvariant() ?? string.Empty;

        // PDF — devolver stream para iframe
        if (ext == ".pdf")
        {
            return new FilePreviewViewModel
            {
                DocumentId = doc.Id,
                Title = doc.Title,
                OriginalFileName = doc.OriginalFileName,
                FileExtension = ext,
                PreviewType = "pdf",
            };
        }

        // TXT / CSV — leer texto
        if (ext is ".txt" or ".csv")
        {
            var text = await File.ReadAllTextAsync(path);
            return new FilePreviewViewModel
            {
                DocumentId = doc.Id,
                Title = doc.Title,
                OriginalFileName = doc.OriginalFileName,
                FileExtension = ext,
                PreviewType = "text",
                TextContent = text,
            };
        }

        // DOCX — convertir a HTML básico
        if (ext == ".docx")
        {
            var html = ConvertDocxToHtml(path);
            return new FilePreviewViewModel
            {
                DocumentId = doc.Id,
                Title = doc.Title,
                OriginalFileName = doc.OriginalFileName,
                FileExtension = ext,
                PreviewType = "docx",
                HtmlContent = html,
            };
        }

        // Formato sin vista previa
        return new FilePreviewViewModel
        {
            DocumentId = doc.Id,
            Title = doc.Title,
            OriginalFileName = doc.OriginalFileName,
            FileExtension = ext,
            PreviewType = "unsupported",
        };
    }

    // ── Sirve el archivo raw para el <iframe> del PDF ─────────────────────────
    public async Task<(Stream stream, string contentType)?> GetFileStreamAsync(int id)
    {
        var doc = await _sql.Documents.FindAsync(id);
        if (doc == null || string.IsNullOrEmpty(doc.StoredFileName)) return null;

        var path = Path.Combine(GetUploadPath(), doc.StoredFileName);
        if (!File.Exists(path)) return null;

        var ct = string.IsNullOrEmpty(doc.ContentType) ? "application/octet-stream" : doc.ContentType;
        return (new FileStream(path, FileMode.Open, FileAccess.Read, FileShare.Read), ct);
    }

    // ── Convierte DOCX a HTML legible (párrafos, negrita, listas) ─────────────
    private static string ConvertDocxToHtml(string path)
    {
        var sb = new StringBuilder();
        sb.Append("<div class=\"docx-preview\">");

        using var wordDoc = DocumentFormat.OpenXml.Packaging
            .WordprocessingDocument.Open(path, false);
        var body = wordDoc.MainDocumentPart?.Document?.Body;
        if (body == null) return "<p class=\"text-muted\">No se pudo leer el documento.</p>";

        foreach (var element in body.ChildElements)
        {
            if (element is DocumentFormat.OpenXml.Wordprocessing.Paragraph para)
            {
                // Estilo del párrafo (headings)
                var style = para.ParagraphProperties?.ParagraphStyleId?.Val?.Value ?? "";
                var tag = style switch
                {
                    "Heading1" or "1" => "h4",
                    "Heading2" or "2" => "h5",
                    "Heading3" or "3" => "h6",
                    _ => "p"
                };

                var lineHtml = new StringBuilder();
                foreach (var run in para.Descendants<DocumentFormat.OpenXml.Wordprocessing.Run>())
                {
                    var bold = run.RunProperties?.Bold != null;
                    var italic = run.RunProperties?.Italic != null;
                    var text = System.Web.HttpUtility.HtmlEncode(
                        string.Concat(run.Descendants<DocumentFormat.OpenXml.Wordprocessing.Text>()
                            .Select(t => t.Text)));

                    if (string.IsNullOrEmpty(text)) continue;
                    if (bold && italic) lineHtml.Append($"<strong><em>{text}</em></strong>");
                    else if (bold) lineHtml.Append($"<strong>{text}</strong>");
                    else if (italic) lineHtml.Append($"<em>{text}</em>");
                    else lineHtml.Append(text);
                }

                var content = lineHtml.ToString();
                if (string.IsNullOrWhiteSpace(content))
                    sb.Append("<br/>");
                else
                    sb.Append($"<{tag} class=\"docx-para\">{content}</{tag}>");
            }
            else if (element is DocumentFormat.OpenXml.Wordprocessing.Table table)
            {
                sb.Append("<table class=\"table table-bordered table-sm docx-table\">");
                foreach (var row in table.Descendants<DocumentFormat.OpenXml.Wordprocessing.TableRow>())
                {
                    sb.Append("<tr>");
                    foreach (var cell in row.Descendants<DocumentFormat.OpenXml.Wordprocessing.TableCell>())
                    {
                        var cellText = System.Web.HttpUtility.HtmlEncode(
                            string.Concat(cell.Descendants<DocumentFormat.OpenXml.Wordprocessing.Text>()
                                .Select(t => t.Text)));
                        sb.Append($"<td>{cellText}</td>");
                    }
                    sb.Append("</tr>");
                }
                sb.Append("</table>");
            }
        }

        sb.Append("</div>");
        return sb.ToString();
    }


    // ── Búsqueda ──────────────────────────────────────────────────────────────

    public async Task<SearchResultViewModel> SearchAsync(
        string query, string? category, string? status)
    {
        // Intentar búsqueda MongoDB directa (incluye fileContent)
        try
        {
            var filter = string.IsNullOrWhiteSpace(query)
                ? Builders<DocumentMeta>.Filter.Empty
                : Builders<DocumentMeta>.Filter.Text(query);

            if (!string.IsNullOrWhiteSpace(category))
                filter &= Builders<DocumentMeta>.Filter.Eq(d => d.Category, category);

            if (!string.IsNullOrWhiteSpace(status))
                filter &= Builders<DocumentMeta>.Filter.Eq(d => d.Status, status);

            var mongoDocs = await _mongo.DocumentMetas
                .Find(filter)
                .Limit(50)
                .ToListAsync();

            if (mongoDocs.Count > 0)
            {
                return new SearchResultViewModel
                {
                    Query = query ?? string.Empty,
                    Total = mongoDocs.Count,
                    Results = mongoDocs.Select(d => new SearchResultItem
                    {
                        DocumentId = d.DocumentId,
                        Code = d.Code,
                        Title = d.Title,
                        Category = d.Category,
                        Standard = d.Standard,
                        Tags = d.Tags,
                        Status = d.Status,
                        FileExtension = d.FileExtension,
                    }).ToList(),
                };
            }
        }
        catch (Exception ex)
        {
            _log.LogWarning("MongoDB search falló: {Message}. Usando fallback.", ex.Message);
        }

        // Fallback: Node.js microservicio
        try
        {
            var client = _httpFactory.CreateClient("SearchService");
            var url = $"/api/search?q={Uri.EscapeDataString(query ?? "")}&mode=all";
            if (!string.IsNullOrWhiteSpace(category))
                url += $"&category={Uri.EscapeDataString(category)}";
            if (!string.IsNullOrWhiteSpace(status))
                url += $"&status={Uri.EscapeDataString(status)}";

            var response = await client.GetAsync(url);
            if (response.IsSuccessStatusCode)
            {
                var json = await response.Content.ReadAsStringAsync();
                var result = JsonSerializer.Deserialize<SearchServiceResponse>(json,
                    new JsonSerializerOptions { PropertyNameCaseInsensitive = true });

                if (result?.Results?.Count > 0)
                    return new SearchResultViewModel
                    {
                        Query = query ?? string.Empty,
                        Total = result.Total,
                        Results = result.Results.Select(r => new SearchResultItem
                        {
                            DocumentId = r.DocumentId,
                            Code = r.Code,
                            Title = r.Title,
                            Category = r.Category,
                            Standard = r.Standard,
                            Tags = r.Tags ?? new(),
                            Status = r.Status,
                            FileExtension = r.FileExtension,
                        }).ToList(),
                    };
            }
        }
        catch (Exception ex)
        {
            _log.LogWarning("Search service no disponible: {Message}.", ex.Message);
        }

        // Último fallback: SQL Server
        return await FallbackSearchAsync(query, category, status);
    }

    // ── Búsqueda fallback SQL Server ──────────────────────────────────────────

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

    // ── Re-indexación masiva ───────────────────────────────────────────────────────
    // Recorre todos los documentos en SQL Server, extrae el texto de cada archivo
    // y actualiza (o crea) su entrada en MongoDB con el campo fileContent.
    // Solo accesible para Admin. Progreso reportado por retorno.

    public async Task<ReIndexResultViewModel> ReIndexAllAsync(
        IProgress<string>? progress = null)
    {
        var docs = await _sql.Documents
            .OrderBy(d => d.Id)
            .ToListAsync();

        int ok = 0, skipped = 0, failed = 0;
        var errors = new List<string>();

        foreach (var doc in docs)
        {
            try
            {
                var fileContent = await ExtractFileTextAsync(doc.StoredFileName);
                var hasFile = !string.IsNullOrEmpty(fileContent);

                var filter = Builders<DocumentMeta>.Filter.Eq(d => d.DocumentId, doc.Id);
                var update = Builders<DocumentMeta>.Update
                    .Set(d => d.DocumentId, doc.Id)
                    .Set(d => d.Code, doc.Code)
                    .Set(d => d.Title, doc.Title)
                    .Set(d => d.Description, doc.Description)
                    .Set(d => d.Category, doc.Category)
                    .Set(d => d.Standard, doc.Standard)
                    .Set(d => d.Tags, doc.Tags
                        .Split(',', StringSplitOptions.RemoveEmptyEntries)
                        .Select(t => t.Trim()).ToList())
                    .Set(d => d.FileExtension, doc.FileExtension)
                    .Set(d => d.Status, doc.Status.ToString())
                    .Set(d => d.IsPublic, doc.IsPublic)
                    .Set(d => d.FileContent, fileContent)
                    .Set(d => d.UpdatedAt, DateTime.UtcNow);

                await _mongo.DocumentMetas.UpdateOneAsync(
                    filter, update, new UpdateOptions { IsUpsert = true });

                if (hasFile) ok++;
                else skipped++;

                progress?.Report($"[{doc.Code}] {doc.Title} — {(hasFile ? "texto extraído" : "sin archivo")}");
            }
            catch (Exception ex)
            {
                failed++;
                var msg = $"[{doc.Code}] ERROR: {ex.Message}";
                errors.Add(msg);
                progress?.Report(msg);
                _log.LogWarning("ReIndex falló en {Code}: {Msg}", doc.Code, ex.Message);
            }
        }

        return new ReIndexResultViewModel
        {
            Total = docs.Count,
            Ok = ok,
            Skipped = skipped,
            Failed = failed,
            Errors = errors,
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
                UnderReview = g.Count(d => d.Status == DocumentStatus.UnderReview
                                        || d.Status == DocumentStatus.UnderSecondReview),
                Obsolete = g.Count(d => d.Status == DocumentStatus.Obsolete
                                        || d.Status == DocumentStatus.Rejected),
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

    public async Task<AuditReportViewModel> GetAuditReportAsync(
    int page, int pageSize,
    string? filterAction = null,
    string? filterUser = null,
    string? filterDocument = null,
    string? filterDateFrom = null,
    string? filterDateTo = null)
    {
        page = Math.Max(1, page);
        pageSize = Math.Clamp(pageSize, 10, 100);

        var query = _sql.AuditLogs
            .Include(l => l.Document)
            .Include(l => l.User)
            .AsQueryable();

        // ── Filtros ───────────────────────────────────────────────────────────
        if (!string.IsNullOrWhiteSpace(filterAction))
            query = query.Where(l => l.Action == filterAction);

        if (!string.IsNullOrWhiteSpace(filterUser))
            query = query.Where(l => l.User != null &&
                l.User.Username.Contains(filterUser));

        if (!string.IsNullOrWhiteSpace(filterDocument))
            query = query.Where(l => l.Document.Code.Contains(filterDocument) ||
                                     l.Document.Title.Contains(filterDocument));

        if (!string.IsNullOrWhiteSpace(filterDateFrom) &&
            DateTime.TryParse(filterDateFrom, out var dateFrom))
            query = query.Where(l => l.CreatedAt >= dateFrom.ToUniversalTime());

        if (!string.IsNullOrWhiteSpace(filterDateTo) &&
            DateTime.TryParse(filterDateTo, out var dateTo))
            query = query.Where(l => l.CreatedAt <= dateTo.AddDays(1).ToUniversalTime());

        // ── Paginación ────────────────────────────────────────────────────────
        var total = await query.CountAsync();
        var logs = await query
            .OrderByDescending(l => l.CreatedAt)
            .Skip((page - 1) * pageSize)
            .Take(pageSize)
            .ToListAsync();

        return new AuditReportViewModel
        {
            TotalCount = total,
            Page = page,
            PageSize = pageSize,
            FilterAction = filterAction,
            FilterUser = filterUser,
            FilterDocument = filterDocument,
            FilterDateFrom = filterDateFrom,
            FilterDateTo = filterDateTo,
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

    // ── Extrae texto de archivos adjuntos para búsqueda ───────────────────────

    private async Task<string> ExtractFileTextAsync(string storedFileName)
    {
        if (string.IsNullOrEmpty(storedFileName)) return string.Empty;
        var path = Path.Combine(GetUploadPath(), storedFileName);
        if (!File.Exists(path)) return string.Empty;

        var ext = Path.GetExtension(storedFileName).ToLowerInvariant();
        try
        {
            // Texto plano y CSV — lectura directa
            if (ext is ".txt" or ".csv")
                return await File.ReadAllTextAsync(path);

            // PDF — requiere NuGet: UglyToad.PdfPig
            if (ext == ".pdf")
            {
                var sb = new StringBuilder();
                using var pdf = UglyToad.PdfPig.PdfDocument.Open(path);
                foreach (var page in pdf.GetPages())
                    sb.AppendLine(string.Concat(page.GetWords().Select(w => w.Text + " ")));
                return sb.ToString();
            }

            // DOCX — requiere NuGet: DocumentFormat.OpenXml
            if (ext == ".docx")
            {
                var sb = new StringBuilder();
                using var docx = DocumentFormat.OpenXml.Packaging
                    .WordprocessingDocument.Open(path, false);
                var body = docx.MainDocumentPart?.Document?.Body;
                if (body != null)
                    foreach (var text in body.Descendants<DocumentFormat.OpenXml.Wordprocessing.Text>())
                        sb.Append(text.Text).Append(' ');
                return sb.ToString();
            }
        }
        catch (Exception ex)
        {
            _log.LogWarning("No se pudo extraer texto de {File}: {Msg}", storedFileName, ex.Message);
        }

        return string.Empty;
    }

    // ── Sincroniza con MongoDB + microservicio Node.js ────────────────────────

    private async Task SyncSearchServiceAsync(Document doc)
    {
        // 1) Node.js microservicio (como antes)
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
            var content = new StringContent(
                JsonSerializer.Serialize(payload), Encoding.UTF8, "application/json");
            await client.PostAsync("/api/documents", content);
        }
        catch (Exception ex)
        {
            _log.LogWarning("No se pudo sincronizar con Search Service: {Message}", ex.Message);
        }

        // 2) MongoDB directo — incluye el contenido extraído del archivo
        try
        {
            var fileContent = await ExtractFileTextAsync(doc.StoredFileName);

            var filter = Builders<DocumentMeta>.Filter.Eq(d => d.DocumentId, doc.Id);
            var update = Builders<DocumentMeta>.Update
                .Set(d => d.DocumentId, doc.Id)
                .Set(d => d.Code, doc.Code)
                .Set(d => d.Title, doc.Title)
                .Set(d => d.Description, doc.Description)
                .Set(d => d.Category, doc.Category)
                .Set(d => d.Standard, doc.Standard)
                .Set(d => d.Tags, doc.Tags
                    .Split(',', StringSplitOptions.RemoveEmptyEntries)
                    .Select(t => t.Trim()).ToList())
                .Set(d => d.FileExtension, doc.FileExtension)
                .Set(d => d.Status, doc.Status.ToString())
                .Set(d => d.IsPublic, doc.IsPublic)
                .Set(d => d.FileContent, fileContent)
                .Set(d => d.UpdatedAt, DateTime.UtcNow);

            await _mongo.DocumentMetas.UpdateOneAsync(
                filter, update, new UpdateOptions { IsUpsert = true });
        }
        catch (Exception ex)
        {
            _log.LogWarning("No se pudo sincronizar con MongoDB: {Message}", ex.Message);
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
        catch { /* PostgreSQL no disponible — continúa sin bloquear */ }
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