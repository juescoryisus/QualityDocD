using Microsoft.AspNetCore.Authentication;
using Microsoft.AspNetCore.Authentication.Cookies;
using Microsoft.AspNetCore.Mvc;
using QualityDocD.Models.ViewModels;
using QualityDocD.Services;
using System.Security.Claims;

namespace QualityDocD.Controllers;

public class AuthController : Controller
{
    private readonly AuthService _auth;

    public AuthController(AuthService auth) => _auth = auth;

    // GET /Auth/Login
    [HttpGet]
    public IActionResult Login(string? returnUrl)
    {
        if (User.Identity?.IsAuthenticated == true)
            return RedirectToAction("Index", "Documents");

        return View(new LoginViewModel { ReturnUrl = returnUrl });
    }

    // POST /Auth/Login
    [HttpPost, ValidateAntiForgeryToken]
    public async Task<IActionResult> Login(LoginViewModel model)
    {
        if (!ModelState.IsValid) return View(model);

        var user = await _auth.ValidateAsync(model.Username, model.Password);
        if (user == null)
        {
            model.Error = "Usuario o contraseña incorrectos, o la empresa está inactiva.";
            return View(model);
        }

        var claims = new List<Claim>
        {
            new(ClaimTypes.NameIdentifier, user.Id.ToString()),
            new(ClaimTypes.Name,           user.Username),
            new(ClaimTypes.Email,          user.Email),
            new(ClaimTypes.Role,           user.Role),
            new("department",              user.Department),
            new("company_id",              user.CompanyId.ToString()),
            new("company_slug",            user.Company.Slug),
            new("company_name",            user.Company.Name),
        };

        var identity = new ClaimsIdentity(claims, CookieAuthenticationDefaults.AuthenticationScheme);
        var principal = new ClaimsPrincipal(identity);

        await HttpContext.SignInAsync(
            CookieAuthenticationDefaults.AuthenticationScheme, principal,
            new AuthenticationProperties
            {
                IsPersistent = true,
                ExpiresUtc = DateTimeOffset.UtcNow.AddHours(8),
            });

        return LocalRedirect(model.ReturnUrl ?? Url.Action("Index", "Documents")!);
    }

    // POST /Auth/Logout
    [HttpPost, ValidateAntiForgeryToken]
    public async Task<IActionResult> Logout()
    {
        await HttpContext.SignOutAsync(CookieAuthenticationDefaults.AuthenticationScheme);
        return RedirectToAction("Login");
    }

    // GET /Auth/Register
    [HttpGet]
    public async Task<IActionResult> Register([FromServices] CompanyService companySvc)
    {
        ViewBag.Companies = await companySvc.GetAllAsync();
        return View(new RegisterViewModel());
    }

    // POST /Auth/Register
    [HttpPost, ValidateAntiForgeryToken]
    public async Task<IActionResult> Register(RegisterViewModel model,
        [FromServices] CompanyService companySvc)
    {
        if (!ModelState.IsValid)
        {
            ViewBag.Companies = await companySvc.GetAllAsync();
            return View(model);
        }

        var (ok, error) = await _auth.RegisterAsync(
            model.Username, model.Email, model.Password,
            model.Role, model.Department, model.CompanySlug);

        if (!ok)
        {
            model.Error = error;
            ViewBag.Companies = await companySvc.GetAllAsync();
            return View(model);
        }

        TempData["Success"] = $"Usuario '{model.Username}' registrado exitosamente.";
        return RedirectToAction("Login");
    }
}