using Microsoft.EntityFrameworkCore;
using QualityDocD.Data;
using QualityDocD.Models.Domain;

namespace QualityDocD.Services;

public class AuthService
{
    private readonly AppDbContext _sql;

    public AuthService(AppDbContext sql) => _sql = sql;

    public async Task<User?> ValidateAsync(string username, string password)
    {
        var user = await _sql.Users
            .Include(u => u.Role)
            .Include(u => u.Department)
                .ThenInclude(d => d.Company)
            .FirstOrDefaultAsync(u => u.Username == username && u.IsActive);

        if (user == null || !BCrypt.Net.BCrypt.Verify(password, user.PasswordHash))
            return null;

        if (!user.Department.Company.IsActive)
            return null;

        user.LastLoginAt = DateTime.UtcNow;
        await _sql.SaveChangesAsync();
        return user;
    }

    public async Task<(bool ok, string? error)> RegisterAsync(
        string username, string email, string password,
        string roleName, string deptName, string companySlug)
    {
        var company = await _sql.Companies
            .FirstOrDefaultAsync(c => c.Slug == companySlug && c.IsActive);

        if (company == null)
            return (false, "La empresa indicada no existe o está inactiva.");

        if (await _sql.Users.AnyAsync(u => u.Username == username))
            return (false, "El nombre de usuario ya está en uso.");

        if (await _sql.Users.AnyAsync(u => u.Email == email))
            return (false, "El correo electrónico ya está registrado.");

        var role = await _sql.Roles.FirstOrDefaultAsync(r => r.Name == roleName)
                   ?? await _sql.Roles.FirstAsync(r => r.Name == "Viewer");

        var dept = await _sql.Departments
            .FirstOrDefaultAsync(d => d.Name == deptName && d.CompanyId == company.Id);

        if (dept == null)
        {
            dept = new Department { Name = deptName, CompanyId = company.Id };
            _sql.Departments.Add(dept);
            await _sql.SaveChangesAsync();
        }

        _sql.Users.Add(new User
        {
            Username = username,
            Email = email,
            PasswordHash = BCrypt.Net.BCrypt.HashPassword(password),
            RoleId = role.Id,
            DepartmentId = dept.Id,
        });

        await _sql.SaveChangesAsync();
        return (true, null);
    }

    public async Task<(bool ok, string? error)> RegisterSuperAdminAsync(
        string username, string email, string password, int companyId)
    {
        if (await _sql.Users.AnyAsync(u => u.Username == username))
            return (false, "El nombre de usuario ya está en uso.");

        if (await _sql.Users.AnyAsync(u => u.Email == email))
            return (false, "El correo electrónico ya está registrado.");

        var role = await _sql.Roles.FirstAsync(r => r.Name == "SuperAdmin");

        var dept = await _sql.Departments
            .FirstOrDefaultAsync(d => d.Name == "Administración" && d.CompanyId == companyId);

        if (dept == null)
        {
            dept = new Department { Name = "Administración", CompanyId = companyId };
            _sql.Departments.Add(dept);
            await _sql.SaveChangesAsync();
        }

        _sql.Users.Add(new User
        {
            Username = username,
            Email = email,
            PasswordHash = BCrypt.Net.BCrypt.HashPassword(password),
            RoleId = role.Id,
            DepartmentId = dept.Id,
        });

        await _sql.SaveChangesAsync();
        return (true, null);
    }
}