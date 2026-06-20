using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using QualityDocD.Data;
using QualityDocD.Services;

namespace QualityDocD.Controllers;

[Authorize(Roles = "SuperAdmin")]
public class CompaniesController : Controller
{
    private readonly CompanyService _svc;
    public CompaniesController(CompanyService svc) => _svc = svc;

    public async Task<IActionResult> Index()
    {
        var companies = await _svc.GetAllAsync();
        return View(companies);
    }

    public async Task<IActionResult> Details(int id, [FromServices] AppDbContext db)
    {
        var company = await _svc.GetByIdAsync(id);
        if (company == null) return NotFound();
        ViewBag.Stats = await _svc.GetStatsAsync(id);
        ViewBag.Users = await db.Users
            .Include(u => u.Role)
            .Include(u => u.Department)
            .Where(u => u.Department.CompanyId == id)
            .OrderBy(u => u.Role.Name).ThenBy(u => u.Username)
            .ToListAsync();
        return View(company);
    }

    public IActionResult Create() => View();

    [HttpPost, ValidateAntiForgeryToken]
    public async Task<IActionResult> Create(string name, string slug, string email)
    {
        var (ok, error, company) = await _svc.CreateAsync(name, slug, email);
        if (!ok) { ModelState.AddModelError("", error!); return View(); }
        TempData["Success"] = $"Empresa '{company!.Name}' creada. Slug: {company.Slug}";
        return RedirectToAction(nameof(Index));
    }

    public async Task<IActionResult> Edit(int id)
    {
        var company = await _svc.GetByIdAsync(id);
        if (company == null) return NotFound();
        return View(company);
    }

    [HttpPost, ValidateAntiForgeryToken]
    public async Task<IActionResult> Edit(int id, string name, string email, bool isActive)
    {
        var (ok, error) = await _svc.UpdateAsync(id, name, email, isActive);
        if (!ok) { ModelState.AddModelError("", error!); return View(await _svc.GetByIdAsync(id)); }
        TempData["Success"] = "Empresa actualizada correctamente.";
        return RedirectToAction(nameof(Index));
    }

    [HttpPost, ValidateAntiForgeryToken]
    public async Task<IActionResult> ToggleActive(int id)
    {
        var (ok, error) = await _svc.ToggleActiveAsync(id);
        TempData[ok ? "Success" : "Error"] = ok ? "Estado actualizado." : error;
        return RedirectToAction(nameof(Index));
    }

    [HttpPost, ValidateAntiForgeryToken]
    public async Task<IActionResult> ChangeUserRole(int userId, string role, int companyId)
    {
        var (ok, error) = await _svc.ChangeUserRoleAsync(
            userId, role,
            isSuperAdmin: true,
            requestingUserId: 0,
            requestingUserCompanyId: 0);

        TempData[ok ? "Success" : "Error"] = ok
            ? "Rol actualizado correctamente."
            : error;
        return RedirectToAction(nameof(Details), new { id = companyId });
    }

}