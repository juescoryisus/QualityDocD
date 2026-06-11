using Microsoft.EntityFrameworkCore;
using QualityDocD.Data;
using QualityDocD.Models.Domain;

namespace QualityDocD.Services;

public class AuthService
{
    private readonly AppDbContext _sql;

    public AuthService(AppDbContext sql) => _sql = sql;

    /// <summary>Valida credenciales y retorna el usuario con su empresa cargada.</summary>
    public async Task<User?> ValidateAsync(string username, string password)
    {
        var user = await _sql.Users
            .Include(u => u.Company)
            .FirstOrDefaultAsync(u => u.Username == username && u.IsActive);

        if (user == null || !BCrypt.Net.BCrypt.Verify(password, user.PasswordHash))
            return null;

        if (!user.Company.IsActive)
            return null;

        user.LastLoginAt = DateTime.UtcNow;
        await _sql.SaveChangesAsync();
        return user;
    }

    /// <summary>Registra un usuario en la empresa indicada por su slug.</summary>
    public async Task<(bool ok, string? error)> RegisterAsync(
        string username, string email, string password,
        string role, string dept, string companySlug)
    {
        var company = await _sql.Companies
            .FirstOrDefaultAsync(c => c.Slug == companySlug && c.IsActive);

        if (company == null)
            return (false, "La empresa indicada no existe o está inactiva.");

        if (await _sql.Users.AnyAsync(u => u.Username == username))
            return (false, "El nombre de usuario ya está en uso.");

        if (await _sql.Users.AnyAsync(u => u.Email == email))
            return (false, "El correo electrónico ya está registrado.");

        _sql.Users.Add(new User
        {
            Username = username,
            Email = email,
            PasswordHash = BCrypt.Net.BCrypt.HashPassword(password),
            Role = role,
            Department = dept,
            CompanyId = company.Id,
        });

        await _sql.SaveChangesAsync();
        return (true, null);
    }

    /// <summary>
    /// Registra el primer usuario SuperAdmin al crear una empresa nueva.
    /// Solo para uso interno (no expuesto en UI pública).
    /// </summary>
    public async Task<(bool ok, string? error)> RegisterSuperAdminAsync(
        string username, string email, string password, int companyId)
    {
        if (await _sql.Users.AnyAsync(u => u.Username == username))
            return (false, "El nombre de usuario ya está en uso.");

        if (await _sql.Users.AnyAsync(u => u.Email == email))
            return (false, "El correo electrónico ya está registrado.");

        _sql.Users.Add(new User
        {
            Username = username,
            Email = email,
            PasswordHash = BCrypt.Net.BCrypt.HashPassword(password),
            Role = "SuperAdmin",
            Department = "Administración",
            CompanyId = companyId,
        });

        await _sql.SaveChangesAsync();
        return (true, null);
    }
}
