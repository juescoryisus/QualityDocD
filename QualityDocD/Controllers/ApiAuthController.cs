using Microsoft.AspNetCore.Mvc;
using QualityDocD.Services;

namespace QualityDocD.Controllers;

/// <summary>
/// Endpoint de login para servicios externos (PHP portal, integraciones con Mongo, etc.).
/// POST /api/auth/login  → devuelve JWT Bearer token
/// POST /api/auth/validate → valida un token y devuelve su payload
/// </summary>
[ApiController]
[Route("api/auth")]
public class ApiAuthController : ControllerBase
{
    private readonly AuthService _auth;
    private readonly ApiTokenService _tokens;

    public ApiAuthController(AuthService auth, ApiTokenService tokens)
    {
        _auth = auth;
        _tokens = tokens;
    }

    // ── POST /api/auth/login ──────────────────────────────────────────────────
    [HttpPost("login")]
    public async Task<IActionResult> Login([FromBody] ApiLoginRequest req)
    {
        if (string.IsNullOrWhiteSpace(req.Username) || string.IsNullOrWhiteSpace(req.Password))
            return BadRequest(new { error = "username y password son requeridos." });

        var user = await _auth.ValidateAsync(req.Username.Trim(), req.Password);

        if (user == null)
            return Unauthorized(new { error = "Credenciales incorrectas o empresa inactiva." });

        var token = _tokens.GenerateToken(
            user.Id, user.Username, user.Email, user.Role, user.Department,
            user.CompanyId, user.Company.Slug, user.Company.Name);

        return Ok(new
        {
            token,
            expires_in = 28800,
            user = new
            {
                id = user.Id,
                username = user.Username,
                email = user.Email,
                role = user.Role,
                department = user.Department,
                company = new
                {
                    id = user.Company.Id,
                    slug = user.Company.Slug,
                    name = user.Company.Name,
                },
            }
        });
    }

    // ── POST /api/auth/validate ───────────────────────────────────────────────
    [HttpPost("validate")]
    public IActionResult Validate([FromBody] ApiValidateRequest req)
    {
        if (string.IsNullOrWhiteSpace(req.Token))
            return BadRequest(new { error = "token es requerido." });

        var payload = _tokens.ValidateToken(req.Token);

        if (payload == null)
            return Ok(new { valid = false });

        return Ok(new
        {
            valid = true,
            id = payload.UserId,
            username = payload.Username,
            email = payload.Email,
            role = payload.Role,
            department = payload.Department,
            company = new
            {
                id = payload.CompanyId,
                slug = payload.CompanySlug,
                name = payload.CompanyName,
            },
        });
    }

    // ── GET /api/auth/me ──────────────────────────────────────────────────────
    [HttpGet("me")]
    public IActionResult Me()
    {
        var payload = _tokens.ValidateToken(GetBearerToken());
        if (payload == null) return Unauthorized(new { error = "Token inválido o expirado." });

        return Ok(new
        {
            id = payload.UserId,
            username = payload.Username,
            email = payload.Email,
            role = payload.Role,
            department = payload.Department,
            company = new
            {
                id = payload.CompanyId,
                slug = payload.CompanySlug,
                name = payload.CompanyName,
            },
        });
    }

    private string? GetBearerToken()
    {
        var header = Request.Headers.Authorization.ToString();
        return header.StartsWith("Bearer ", StringComparison.OrdinalIgnoreCase)
            ? header["Bearer ".Length..].Trim()
            : null;
    }
}

// ── DTOs ──────────────────────────────────────────────────────────────────────
public record ApiLoginRequest(string Username, string Password);
public record ApiValidateRequest(string Token);
