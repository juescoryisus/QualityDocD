using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using QualityDocD.Models.ViewModels;
using QualityDocD.Services;
using System.Security.Claims;

namespace QualityDocD.Controllers;

[Authorize]
public class DocumentsController : Controller
{
    private readonly DocumentService _svc;

    public DocumentsController(DocumentService svc) => _svc = svc;

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
            ? "Documento enviado a revisión." : error;
        return RedirectToAction(nameof(Details), new { id = model.DocumentId });
    }

    // POST /Documents/ProcessApproval
    [HttpPost, ValidateAntiForgeryToken]
    public async Task<IActionResult> ProcessApproval(ApprovalActionViewModel model)
    {
        var (ok, error) = await _svc.ProcessApprovalAsync(model, GetUserId());
        TempData[ok ? "Success" : "Error"] = ok
            ? (model.Action == "approve" ? "Documento aprobado." : "Documento rechazado.")
            : error;
        return RedirectToAction(nameof(Details), new { id = model.DocumentId });
    }

    // POST /Documents/MarkObsolete/5
    [HttpPost, ValidateAntiForgeryToken]
    [Authorize(Roles = "Admin,Manager")]
    public async Task<IActionResult> MarkObsolete(int id)
    {
        var ok = await _svc.MarkObsoleteAsync(id, GetUserId());
        TempData[ok ? "Success" : "Error"] = ok
            ? "Documento marcado como Obsoleto."
            : "No se encontró el documento.";
        return RedirectToAction(nameof(Details), new { id });
    }

    // GET /Documents/Download/5
    public async Task<IActionResult> Download(int id)
    {
        var result = await _svc.DownloadAsync(id, GetUserId());
        if (result == null) return NotFound();
        var (stream, fileName, ct) = result.Value;
        return File(stream, ct, fileName);
    }

    private int GetUserId() =>
        int.Parse(User.FindFirstValue(ClaimTypes.NameIdentifier) ?? "0");
}
