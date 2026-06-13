using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using MongoDB.Bson;
using MongoDB.Driver;
using QualityDocD.Models.ViewModels;
using QualityDocD.Services;
using System.Security.Claims;

namespace QualityDocD.Controllers;

[Authorize]
public class DocumentsController : Controller
{
    private readonly DocumentService _svc;
    private readonly IWebHostEnvironment _env;
    private readonly IMongoDatabase _db;

    public DocumentsController(
        DocumentService svc,
        IWebHostEnvironment env,
        IMongoDatabase db)
    {
        _svc = svc;
        _env = env;
        _db = db;
    }

    // GET /Documents
    public async Task<IActionResult> Index(
        string? status, string? category, string? search)
    {
        var vm = await _svc.GetIndexAsync(status, category, search);
        return View(vm);
    }

    // GET /Documents/Details/5
    public async Task<IActionResult> Details(int id)
    {
        var vm = await _svc.GetDetailAsync(id);
        if (vm == null) return NotFound();
        return View(vm);
    }

    // GET /Documents/Create
    public IActionResult Create() => View(new DocumentFormViewModel());

    // POST /Documents/Create
    [HttpPost, ValidateAntiForgeryToken]
    public async Task<IActionResult> Create(DocumentFormViewModel model)
    {
        if (!ModelState.IsValid) return View(model);

        var (ok, error, docId) = await _svc.CreateAsync(model, GetUserId());
        if (!ok) { ModelState.AddModelError("", error!); return View(model); }

        TempData["Success"] = "Documento creado exitosamente.";
        return RedirectToAction(nameof(Details), new { id = docId });
    }

    // GET /Documents/Edit/5
    public async Task<IActionResult> Edit(int id)
    {
        var vm = await _svc.GetDetailAsync(id);
        if (vm == null) return NotFound();

        if (vm.Status is not ("Draft" or "PendingChanges"))
        {
            TempData["Error"] = "Solo se pueden editar documentos en estado Borrador o Cambios Pendientes.";
            return RedirectToAction(nameof(Details), new { id });
        }

        return View(new DocumentFormViewModel
        {
            Id = vm.Id,
            Title = vm.Title,
            Description = vm.Description,
            Category = vm.Category,
            Standard = vm.Standard,
            Tags = vm.Tags,
            IsPublic = vm.IsPublic,
            ExpiresAt = vm.ExpiresAt,
            ExistingFileName = vm.OriginalFileName,
            ExistingFileExt = vm.FileExtension,
            ExistingFileSize = vm.FileSizeBytes,
        });
    }

    // POST /Documents/Edit/5
    [HttpPost, ValidateAntiForgeryToken]
    public async Task<IActionResult> Edit(int id, DocumentFormViewModel model)
    {
        if (!ModelState.IsValid) return View(model);

        var (ok, error) = await _svc.UpdateAsync(id, model, GetUserId());
        if (!ok) { ModelState.AddModelError("", error!); return View(model); }

        TempData["Success"] = "Documento actualizado. Vuelve a estado Borrador.";
        return RedirectToAction(nameof(Details), new { id });
    }

    // POST /Documents/SubmitForReview
    [HttpPost, ValidateAntiForgeryToken]
    public async Task<IActionResult> SubmitForReview(SubmitReviewViewModel model)
    {
        var (ok, error) = await _svc.SubmitForReviewAsync(model, GetUserId());
        TempData[ok ? "Success" : "Error"] = ok
            ? "Documento enviado a revisión."
            : error;
        return RedirectToAction(nameof(Details), new { id = model.DocumentId });
    }

    // POST /Documents/Approve/5
    [HttpPost, ValidateAntiForgeryToken]
    [Authorize(Roles = "Admin,Manager,Reviewer")]
    public async Task<IActionResult> Approve(int id, string? comments)
    {
        var (ok, error) = await _svc.ApproveAsync(id, GetUserId(), comments);
        TempData[ok ? "Success" : "Error"] = ok
            ? "Revisión registrada. El documento avanza en el flujo."
            : error;
        return RedirectToAction(nameof(Details), new { id });
    }

    // POST /Documents/Reject/5
    [HttpPost, ValidateAntiForgeryToken]
    [Authorize(Roles = "Admin,Manager,Reviewer")]
    public async Task<IActionResult> Reject(int id, string? comments)
    {
        if (string.IsNullOrWhiteSpace(comments))
        {
            TempData["Error"] = "Debes indicar el motivo del rechazo.";
            return RedirectToAction(nameof(Details), new { id });
        }

        var (ok, error) = await _svc.RejectAsync(id, GetUserId(), comments);
        TempData[ok ? "Success" : "Error"] = ok
            ? "Documento rechazado definitivamente."
            : error;
        return RedirectToAction(nameof(Details), new { id });
    }

    // POST /Documents/RequestChanges/5
    [HttpPost, ValidateAntiForgeryToken]
    [Authorize(Roles = "Admin,Manager,Reviewer")]
    public async Task<IActionResult> RequestChanges(int id, string? comments)
    {
        if (string.IsNullOrWhiteSpace(comments))
        {
            TempData["Error"] = "Debes describir los cambios requeridos.";
            return RedirectToAction(nameof(Details), new { id });
        }

        var (ok, error) = await _svc.RequestChangesAsync(id, GetUserId(), comments);
        TempData[ok ? "Success" : "Error"] = ok
            ? "Se han solicitado cambios. El documento queda en estado 'Cambios Pendientes'."
            : error;
        return RedirectToAction(nameof(Details), new { id });
    }

    // POST /Documents/MarkObsolete/5
    [HttpPost, ValidateAntiForgeryToken]
    [Authorize(Roles = "Admin,Manager")]
    public async Task<IActionResult> MarkObsolete(int id)
    {
        var (ok, error) = await _svc.MarkObsoleteAsync(id, GetUserId());
        TempData[ok ? "Success" : "Error"] = ok
            ? "Documento marcado como Obsoleto."
            : error;
        return RedirectToAction(nameof(Details), new { id });
    }

    // GET /Documents/Download/5
    public async Task<IActionResult> Download(int id)
    {
        var result = await _svc.DownloadAsync(id, GetUserId());
        if (result == null) return NotFound();
        var (stream, fileName, contentType) = result.Value;
        return File(stream, contentType, fileName);
    }

    // GET /Documents/Preview/5
    [AllowAnonymous]
    public async Task<IActionResult> Preview(int id)
    {
        var vm = await _svc.PreviewAsync(id);
        if (vm == null) return NotFound();
        return View(vm);
    }

    // GET /Documents/ViewFile/5
    public async Task<IActionResult> ViewFile(int id)
    {
        var result = await _svc.GetFileStreamAsync(id);
        if (result == null) return NotFound();
        var (stream, contentType) = result.Value;
        Response.Headers["Content-Disposition"] = "inline";
        return File(stream, contentType);
    }

    // POST /Documents/UploadAttachment/5
    [HttpPost]
    public async Task<IActionResult> UploadAttachment(
        int documentId,
        IFormFile file,
        [FromServices] TextExtractionService extractor)
    {
        var uploadsPath = Path.Combine(_env.WebRootPath, "uploads");
        Directory.CreateDirectory(uploadsPath);

        var uniqueName = $"{Guid.NewGuid()}{Path.GetExtension(file.FileName)}";
        var filePath = Path.Combine(uploadsPath, uniqueName);

        using (var stream = System.IO.File.Create(filePath))
            await file.CopyToAsync(stream);

        using var extractStream = file.OpenReadStream();
        var textContent = await extractor.ExtractTextAsync(
            extractStream, file.FileName, file.ContentType);

        var attachment = new DocumentAttachment
        {
            DocumentId = documentId,
            FileName = uniqueName,
            OriginalName = file.FileName,
            MimeType = file.ContentType,
            FileSize = file.Length,
            TextContent = textContent,
            CreatedAt = DateTime.UtcNow
        };

        var collection = _db.GetCollection<DocumentAttachment>("attachments");
        await collection.InsertOneAsync(attachment);

        return Ok(attachment);
    }

    // GET /Documents/Search?q=texto
    [HttpGet]
    public async Task<IActionResult> Search([FromQuery] string q)
    {
        if (string.IsNullOrWhiteSpace(q))
            return Ok(new List<object>());

        var collection = _db.GetCollection<DocumentAttachment>("attachments");

        var filter = Builders<DocumentAttachment>.Filter.Or(
            Builders<DocumentAttachment>.Filter.Regex(
                "originalName", new BsonRegularExpression(q, "i")),
            Builders<DocumentAttachment>.Filter.Regex(
                "textContent", new BsonRegularExpression(q, "i"))
        );

        var matches = await collection.Find(filter).ToListAsync();
        return Ok(matches);
    }

    // ── Helper ────────────────────────────────────────────────────────────────
    private int GetUserId() =>
        int.Parse(User.FindFirstValue(ClaimTypes.NameIdentifier) ?? "0");



   

    // POST /Documents/Delete/5
    [HttpPost, ValidateAntiForgeryToken]
    [Authorize(Roles = "Admin,Manager,SuperAdmin")]
    public async Task<IActionResult> Delete(int id)
    {
        var (ok, error) = await _svc.DeleteAsync(id, GetUserId());

        if (!ok)
        {
            TempData["Error"] = error;
            return RedirectToAction(nameof(Details), new { id });
        }

        TempData["Success"] = "Documento eliminado correctamente.";
        return RedirectToAction(nameof(Index));
    }
}


