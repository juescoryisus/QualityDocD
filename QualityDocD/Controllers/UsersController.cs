using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using QualityDocD.Data;
using QualityDocD.Services;
using System.Security.Claims;

namespace QualityDocD.Controllers;

[Authorize(Roles = "Admin,SuperAdmin")]
public class UsersController : Controller
{
    private readonly AppDbContext _db;
    private readonly CompanyService _svc;

    public UsersController(AppDbContext db, CompanyService svc)
    {
        _db = db;
        _svc = svc;
    }

    private int GetCompanyId() =>
        int.TryParse(User.FindFirstValue("company_id"), out var id) ? id : 0;

    private int GetUserId() =>
        int.TryParse(User.FindFirstValue(ClaimTypes.NameIdentifier), out var id) ? id : 0;

    private bool IsSuperAdmin() => User.IsInRole("SuperAdmin");

    // GET /Users
    public async Task<IActionResult> Index()
    {
        var users = await _db.Users
            .Where(u => u.CompanyId == GetCompanyId())
            .OrderBy(u => u.Role).ThenBy(u => u.Username)
            .ToListAsync();
        return View(users);
    }

    // POST /Users/ChangeRole
    [HttpPost, ValidateAntiForgeryToken]
    public async Task<IActionResult> ChangeRole(int userId, string role)
    {
        if (!IsSuperAdmin())
        {
            TempData["Error"] = "No tienes permiso para cambiar roles.";
            return RedirectToAction(nameof(Index));
        }

        var (ok, error) = await _svc.ChangeUserRoleAsync(
            userId, role,
            isSuperAdmin: IsSuperAdmin(),
            requestingUserId: GetUserId(),
            requestingUserCompanyId: GetCompanyId());

        TempData[ok ? "Success" : "Error"] = ok
            ? "Rol actualizado correctamente."
            : error;
        return RedirectToAction(nameof(Index));
    }

    // POST /Users/ToggleActive
    [HttpPost, ValidateAntiForgeryToken]
    public async Task<IActionResult> ToggleActive(int userId)
    {
        var user = await _db.Users.FindAsync(userId);
        if (user == null) return NotFound();

        if (!IsSuperAdmin() && user.CompanyId != GetCompanyId())
            return Forbid();

        if (user.Role == "SuperAdmin" && !IsSuperAdmin())
            return Forbid();

        user.IsActive = !user.IsActive;
        await _db.SaveChangesAsync();

        TempData["Success"] = $"Usuario {(user.IsActive ? "activado" : "desactivado")}.";
        return RedirectToAction(nameof(Index));
    }
}