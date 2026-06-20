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

    public async Task<IActionResult> Index()
    {
        var users = await _db.Users
            .Include(u => u.Role)
            .Include(u => u.Department)
            .Where(u => u.Department.CompanyId == GetCompanyId())
            .OrderBy(u => u.Role.Name).ThenBy(u => u.Username)
            .ToListAsync();
        return View(users);
    }

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

        TempData[ok ? "Success" : "Error"] = ok ? "Rol actualizado correctamente." : error;
        return RedirectToAction(nameof(Index));
    }

    [HttpPost, ValidateAntiForgeryToken]
    public async Task<IActionResult> ToggleActive(int userId)
    {
        var user = await _db.Users
            .Include(u => u.Role)
            .Include(u => u.Department)
            .FirstOrDefaultAsync(u => u.Id == userId);
        if (user == null) return NotFound();

        if (!IsSuperAdmin() && user.Department.CompanyId != GetCompanyId())
            return Forbid();

        if (user.Role.Name == "SuperAdmin" && !IsSuperAdmin())
            return Forbid();

        user.IsActive = !user.IsActive;
        await _db.SaveChangesAsync();

        TempData["Success"] = $"Usuario {(user.IsActive ? "activado" : "desactivado")}.";
        return RedirectToAction(nameof(Index));
    }
}