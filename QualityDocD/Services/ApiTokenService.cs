using System.IdentityModel.Tokens.Jwt;
using System.Security.Claims;
using System.Text;
using Microsoft.IdentityModel.Tokens;

namespace QualityDocD.Services;

/// <summary>
/// Genera y valida JWT para el API de autenticación externo.
/// Registrar en Program.cs:
///   builder.Services.AddScoped&lt;ApiTokenService&gt;();
/// </summary>
public class ApiTokenService
{
    private readonly IConfiguration _cfg;

    public ApiTokenService(IConfiguration cfg) => _cfg = cfg;

    private string SecretKey =>
        _cfg["ApiAuth:JwtSecret"] ?? "QualityDoc-JWT-Secret-2026-Change-In-Production!";

    private int ExpirationHours =>
        int.TryParse(_cfg["ApiAuth:ExpirationHours"], out var h) ? h : 8;

    // ── Generar token ─────────────────────────────────────────────────────────
    public string GenerateToken(
        int userId, string username, string email,
        string role, string department,
        int companyId, string companySlug, string companyName)
    {
        var key = new SymmetricSecurityKey(Encoding.UTF8.GetBytes(SecretKey));
        var creds = new SigningCredentials(key, SecurityAlgorithms.HmacSha256);

        var claims = new[]
        {
            new Claim(JwtRegisteredClaimNames.Sub,   userId.ToString()),
            new Claim(JwtRegisteredClaimNames.Email, email),
            new Claim(JwtRegisteredClaimNames.Jti,   Guid.NewGuid().ToString()),
            new Claim("username",     username),
            new Claim("role",         role),
            new Claim("department",   department),
            new Claim("company_id",   companyId.ToString()),
            new Claim("company_slug", companySlug),
            new Claim("company_name", companyName),
        };

        var token = new JwtSecurityToken(
            issuer: "QualityDocD",
            audience: "QualityDocD-Clients",
            claims: claims,
            expires: DateTime.UtcNow.AddHours(ExpirationHours),
            signingCredentials: creds
        );

        return new JwtSecurityTokenHandler().WriteToken(token);
    }

    // ── Validar token ─────────────────────────────────────────────────────────
    public ApiTokenPayload? ValidateToken(string? token)
    {
        if (string.IsNullOrWhiteSpace(token)) return null;

        try
        {
            var key = new SymmetricSecurityKey(Encoding.UTF8.GetBytes(SecretKey));
            var handler = new JwtSecurityTokenHandler();

            handler.ValidateToken(token, new TokenValidationParameters
            {
                ValidateIssuerSigningKey = true,
                IssuerSigningKey = key,
                ValidateIssuer = true,
                ValidIssuer = "QualityDocD",
                ValidateAudience = true,
                ValidAudience = "QualityDocD-Clients",
                ValidateLifetime = true,
                ClockSkew = TimeSpan.FromMinutes(5),
            }, out var validatedToken);

            var jwt = (JwtSecurityToken)validatedToken;

            return new ApiTokenPayload
            {
                UserId = int.Parse(jwt.Subject),
                Username = jwt.Claims.First(c => c.Type == "username").Value,
                Email = jwt.Claims.First(c => c.Type == JwtRegisteredClaimNames.Email).Value,
                Role = jwt.Claims.First(c => c.Type == "role").Value,
                Department = jwt.Claims.First(c => c.Type == "department").Value,
                CompanyId = int.TryParse(
                    jwt.Claims.FirstOrDefault(c => c.Type == "company_id")?.Value, out var cid)
                    ? cid : 0,
                CompanySlug = jwt.Claims.FirstOrDefault(c => c.Type == "company_slug")?.Value ?? "",
                CompanyName = jwt.Claims.FirstOrDefault(c => c.Type == "company_name")?.Value ?? "",
            };
        }
        catch
        {
            return null;
        }
    }
}

public class ApiTokenPayload
{
    public int UserId { get; set; }
    public string Username { get; set; } = string.Empty;
    public string Email { get; set; } = string.Empty;
    public string Role { get; set; } = string.Empty;
    public string Department { get; set; } = string.Empty;
    public int CompanyId { get; set; }
    public string CompanySlug { get; set; } = string.Empty;
    public string CompanyName { get; set; } = string.Empty;
}
