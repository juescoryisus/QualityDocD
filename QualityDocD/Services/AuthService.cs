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
            .FirstOrDefaultAsync(u => u.Username == username && u.IsActive);

        if (user == null || !BCrypt.Net.BCrypt.Verify(password, user.PasswordHash))
            return null;

        user.LastLoginAt = DateTime.UtcNow;
        await _sql.SaveChangesAsync();
        return user;
    }

    public async Task<(bool ok, string? error)> RegisterAsync(
        string username, string email, string password, string role, string dept)
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
            Role = role,
            Department = dept,
        });

        await _sql.SaveChangesAsync();
        return (true, null);
    }
}