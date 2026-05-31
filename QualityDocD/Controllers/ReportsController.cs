using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using QualityDocD.Models.ViewModels;
using QualityDocD.Services;

namespace QualityDocD.Controllers;

[Authorize(Roles = "Admin,Manager,Reviewer")]
public class ReportsController : Controller
{
    private readonly DocumentService _svc;

    public ReportsController(DocumentService svc) => _svc = svc;

    // GET /Reports/Compliance
    public async Task<IActionResult> Compliance(string? dateFrom, string? dateTo)
    {
        var vm = await _svc.GetComplianceReportAsync(dateFrom ?? "", dateTo ?? "");
        return View(vm);
    }

    // GET /Reports/Audit
    public async Task<IActionResult> Audit(int page = 1, int pageSize = 25)
    {
        var vm = await _svc.GetAuditReportAsync(page, pageSize);
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
}
