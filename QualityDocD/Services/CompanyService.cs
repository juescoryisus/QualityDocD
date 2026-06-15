using Microsoft.EntityFrameworkCore;
using QualityDocD.Data;
using QualityDocD.Models.Domain;

namespace QualityDocD.Services;

public class CompanyService
{
    // Agregar al final de la clase CompanyService, antes del último }

    public async Task<(bool ok, string? error)> ChangeUserRoleAsync(
        int userId, string newRole, bool isSuperAdmin,
        int requestingUserId, int requestingUserCompanyId)
    {
        var baseRoles = new[] { "Viewer", "Reviewer", "Manager", "Admin" };
        var allRoles = new[] { "Viewer", "Reviewer", "Manager", "Admin", "SuperAdmin" };

        var allowed = isSuperAdmin ? allRoles : baseRoles;
        if (!allowed.Contains(newRole))
            return (false, "Rol no válido.");

        var user = await _sql.Users.FindAsync(userId);
        if (user == null) return (false, "Usuario no encontrado.");

        if (!isSuperAdmin)
        {
            if (user.CompanyId != requestingUserCompanyId)
                return (false, "No tienes permiso para modificar este usuario.");
            if (user.Role == "SuperAdmin")
                return (false, "No puedes modificar el rol de un SuperAdmin.");
            if (user.Role == "Admin" && userId != requestingUserId)
                return (false, "No puedes modificar el rol de otro Admin.");
        }

        user.Role = newRole;
        await _sql.SaveChangesAsync();
        return (true, null);
    }






    private readonly AppDbContext _sql;

    public CompanyService(AppDbContext sql) => _sql = sql;

    public async Task<List<Company>> GetAllAsync() =>
        await _sql.Companies.OrderBy(c => c.Name).ToListAsync();

    public async Task<Company?> GetByIdAsync(int id) =>
        await _sql.Companies.FindAsync(id);

    public async Task<Company?> GetBySlugAsync(string slug) =>
        await _sql.Companies.FirstOrDefaultAsync(c => c.Slug == slug && c.IsActive);

    public async Task<(bool ok, string? error, Company? company)> CreateAsync(
        string name, string slug, string email)
    {
        slug = slug.Trim().ToLowerInvariant();

        if (await _sql.Companies.AnyAsync(c => c.Slug == slug))
            return (false, "El identificador (slug) ya está en uso.", null);

        if (await _sql.Companies.AnyAsync(c => c.Email == email))
            return (false, "El correo ya está registrado en otra empresa.", null);

        var company = new Company
        {
            Name = name.Trim(),
            Slug = slug,
            Email = email.Trim(),
        };

        _sql.Companies.Add(company);
        await _sql.SaveChangesAsync();
        return (true, null, company);
    }

    public async Task<(bool ok, string? error)> UpdateAsync(
        int id, string name, string email, bool isActive)
    {
        var company = await _sql.Companies.FindAsync(id);
        if (company == null) return (false, "Empresa no encontrada.");

        if (await _sql.Companies.AnyAsync(c => c.Email == email && c.Id != id))
            return (false, "El correo ya está registrado en otra empresa.");

        company.Name = name.Trim();
        company.Email = email.Trim();
        company.IsActive = isActive;

        await _sql.SaveChangesAsync();
        return (true, null);
    }

    public async Task<(bool ok, string? error)> ToggleActiveAsync(int id)
    {
        var company = await _sql.Companies.FindAsync(id);
        if (company == null) return (false, "Empresa no encontrada.");

        company.IsActive = !company.IsActive;
        await _sql.SaveChangesAsync();
        return (true, null);
    }

    public async Task<CompanyStats> GetStatsAsync(int companyId)
    {
        var users = await _sql.Users.CountAsync(u => u.CompanyId == companyId);
        var docs = await _sql.Documents.CountAsync(d => d.CompanyId == companyId);
        var approved = await _sql.Documents.CountAsync(
            d => d.CompanyId == companyId && d.Status == DocumentStatus.Approved);

        return new CompanyStats
        {
            TotalUsers = users,
            TotalDocuments = docs,
            ApprovedDocuments = approved,
        };
    }
}

public class CompanyStats
{
    public int TotalUsers { get; set; }
    public int TotalDocuments { get; set; }
    public int ApprovedDocuments { get; set; }
}
