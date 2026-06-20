using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using MongoDB.Driver;
using QualityDocD.Data;
using QualityDocD.Extensions;
using QualityDocD.Models.ViewModels;
using QualityDocD.Services;

namespace QualityDocD.Controllers;

[Authorize(Roles = "Admin,Manager,SuperAdmin")]
public class ReportsController : Controller
{
    private readonly DocumentService _svc;
    private readonly MongoDbContext _mongo;

    public ReportsController(DocumentService svc, MongoDbContext mongo)
    {
        _svc = svc;
        _mongo = mongo;
    }

    // GET /Reports/Compliance
    public async Task<IActionResult> Compliance(string? dateFrom, string? dateTo)
    {
        var vm = await _svc.GetComplianceReportAsync(dateFrom ?? "", dateTo ?? "");
        return View(vm);
    }

    // GET /Reports/Audit
    public async Task<IActionResult> Audit(
      int page = 1,
      int pageSize = 25,
      string? filterAction = null,
      string? filterUser = null,
      string? filterDocument = null,
      string? filterDateFrom = null,
      string? filterDateTo = null)
    {
        var vm = await _svc.GetAuditReportAsync(
            page, pageSize,
            filterAction, filterUser, filterDocument,
            filterDateFrom, filterDateTo);
        return View(vm);
    }

    // GET /Reports/Search
    public async Task<IActionResult> Search(string? q, string? category, string? status)
    {
        if (string.IsNullOrWhiteSpace(q) && string.IsNullOrWhiteSpace(category)
                                         && string.IsNullOrWhiteSpace(status))
            return View(new SearchResultViewModel { Query = "" });

        var vm = await _svc.SearchAsync(q ?? "", category, status);
        return View(vm);
    }

    // GET /Reports/MongoJson
    [Authorize(Roles = "Admin")]
    public async Task<IActionResult> MongoJson()
    {
        var docs = await _mongo.DocumentMetas
            .Find(_ => true)
            .ToListAsync();

        var json = System.Text.Json.JsonSerializer.Serialize(docs,
            new System.Text.Json.JsonSerializerOptions
            {
                WriteIndented = true,
                PropertyNamingPolicy = System.Text.Json.JsonNamingPolicy.CamelCase
            });

        ViewData["Title"] = "Metadatos MongoDB";
        ViewBag.Json = json;
        ViewBag.Count = docs.Count;
        return View();
    }

    // GET /Reports/MongoJson/raw
    [Authorize(Roles = "Admin")]
    [Route("Reports/MongoJson/raw")]
    public async Task<IActionResult> MongoJsonRaw()
    {
        var docs = await _mongo.DocumentMetas.Find(_ => true).ToListAsync();
        var json = System.Text.Json.JsonSerializer.Serialize(docs,
            new System.Text.Json.JsonSerializerOptions
            {
                WriteIndented = true,
                PropertyNamingPolicy = System.Text.Json.JsonNamingPolicy.CamelCase
            });
        return File(System.Text.Encoding.UTF8.GetBytes(json),
                    "application/json",
                    $"mongodb-metadata-{DateTime.Now:yyyyMMdd-HHmm}.json");
    }

    // ── Re-indexación masiva ──────────────────────────────────────────────────

    // GET /Reports/ReIndex  — muestra la página de confirmación
    [Authorize(Roles = "Admin")]
    public IActionResult ReIndex()
    {
        return View();
    }

    // POST /Reports/ReIndex  — ejecuta la re-indexación
    [Authorize(Roles = "Admin")]
    [HttpPost, ActionName("ReIndex")]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> ReIndexConfirmed()
    {
        var result = await _svc.ReIndexAllAsync();
        return View("ReIndexResult", result);
    }

    // GET /Reports/ExportAudit  — descarga CSV con los filtros activos
    [Authorize(Roles = "Admin,Manager")]
    public async Task<IActionResult> ExportAudit(
        string? filterAction = null,
        string? filterUser = null,
        string? filterDocument = null,
        string? filterDateFrom = null,
        string? filterDateTo = null)
    {
        var rows = await _svc.ExportAuditAsync(
            filterAction, filterUser, filterDocument,
            filterDateFrom, filterDateTo);

        var csv = BuildCsv(rows);
        var bytes = System.Text.Encoding.UTF8.GetPreamble()  // BOM para que Excel muestre acentos
            .Concat(System.Text.Encoding.UTF8.GetBytes(csv))
            .ToArray();

        var fileName = $"auditoria_{DateTime.Now:yyyyMMdd_HHmm}.csv";
        return File(bytes, "text/csv; charset=utf-8", fileName);
    }

    // ── Genera el contenido CSV ───────────────────────────────────────────────
    private static string BuildCsv(List<AuditReportRow> rows)
    {
        var sb = new System.Text.StringBuilder();

        // Encabezados
        sb.AppendLine("Fecha/Hora,Acción,Código,Documento,Usuario,Antes,Después,IP");

        foreach (var r in rows)
        {
            // Convierte a hora México antes de exportar
            var fechaMx = r.CreatedAt.ToMexico().ToString("dd/MM/yyyy HH:mm");

            sb.AppendLine(string.Join(",",
                CsvField(fechaMx),
                CsvField(r.Action),
                CsvField(r.DocumentCode),
                CsvField(r.DocumentTitle),
                CsvField(r.Username ?? ""),
                CsvField(r.OldValue ?? ""),
                CsvField(r.NewValue ?? ""),
                CsvField(r.IpAddress ?? "")
            ));
        }

        return sb.ToString();
    }

    // Escapa un campo CSV (envuelve en comillas si tiene coma, comilla o salto de línea)
    private static string CsvField(string value)
    {
        if (value.Contains(',') || value.Contains('"') || value.Contains('\n'))
            return $"\"{value.Replace("\"", "\"\"")}\"";
        return value;
    }

}