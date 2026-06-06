using Microsoft.AspNetCore.Authentication.Cookies;
using Microsoft.EntityFrameworkCore;
using QualityDocD.Data;
using QualityDocD.Models.Domain;
using QualityDocD.Services;

var builder = WebApplication.CreateBuilder(args);

// ── MVC ───────────────────────────────────────────────────────────────────────
builder.Services.AddControllersWithViews();

// ── Bases de datos (ambas en PostgreSQL) ──────────────────────────────────────
builder.Services.AddDbContext<AppDbContext>(opts =>
    opts.UseNpgsql(
        builder.Configuration.GetConnectionString("MainDb"),
        npg => npg.EnableRetryOnFailure(5, TimeSpan.FromSeconds(10), null)));

builder.Services.AddDbContext<AuditDbContext>(opts =>
    opts.UseNpgsql(
        builder.Configuration.GetConnectionString("AuditDb"),
        npg => npg.EnableRetryOnFailure(5, TimeSpan.FromSeconds(10), null)));

// ── Servicios y HTTP Clients ──────────────────────────────────────────────────
builder.Services.AddScoped<AuthService>();
builder.Services.AddScoped<DocumentService>();
builder.Services.AddScoped<ApiTokenService>();
builder.Services.AddHttpContextAccessor();

builder.Services.AddHttpClient("SearchService", client =>
{
    // Ahora usa la ruta del proxy en lugar del puerto directo
    client.BaseAddress = new Uri("http://localhost:5001/search/");
    client.Timeout = TimeSpan.FromSeconds(5);
});

builder.Services.AddHttpClient<NodeApiAuthService>();

// ── Autenticación y Autorización ──────────────────────────────────────────────
builder.Services.AddAuthentication(CookieAuthenticationDefaults.AuthenticationScheme)
    .AddCookie(opts =>
    {
        opts.LoginPath = "/Auth/Login";
        opts.LogoutPath = "/Auth/Logout";
        opts.AccessDeniedPath = "/Auth/Login";
        opts.ExpireTimeSpan = TimeSpan.FromHours(8);
        opts.SlidingExpiration = true;
    });

builder.Services.AddAuthorization();

// ── YARP — Proxy inverso ──────────────────────────────────────────────────────
builder.Services.AddReverseProxy()
    .LoadFromConfig(builder.Configuration.GetSection("ReverseProxy"));

// ── Build ─────────────────────────────────────────────────────────────────────
var app = builder.Build();

// ── Migraciones automáticas y seed de datos ───────────────────────────────────
using (var scope = app.Services.CreateScope())
{
    var log = scope.ServiceProvider.GetRequiredService<ILogger<Program>>();

    // PostgreSQL principal — migraciones + seed de usuarios
    try
    {
        var mainCtx = scope.ServiceProvider.GetRequiredService<AppDbContext>();
        mainCtx.Database.Migrate();

        if (!mainCtx.Users.Any())
        {
            var now = DateTime.UtcNow;
            mainCtx.Users.AddRange(
                new User { Username = "admin", Email = "admin@qualitydoc.local", PasswordHash = BCrypt.Net.BCrypt.HashPassword("Admin123!"), Role = "Admin", Department = "TI", IsActive = true, CreatedAt = now },
                new User { Username = "gerente", Email = "gerente@qualitydoc.local", PasswordHash = BCrypt.Net.BCrypt.HashPassword("Gerente123!"), Role = "Manager", Department = "Calidad", IsActive = true, CreatedAt = now },
                new User { Username = "revisor", Email = "revisor@qualitydoc.local", PasswordHash = BCrypt.Net.BCrypt.HashPassword("Revisor123!"), Role = "Reviewer", Department = "Operaciones", IsActive = true, CreatedAt = now },
                new User { Username = "operario", Email = "operario@qualitydoc.local", PasswordHash = BCrypt.Net.BCrypt.HashPassword("Operario123!"), Role = "Viewer", Department = "Producción", IsActive = true, CreatedAt = now }
            );
            mainCtx.SaveChanges();
            log.LogInformation("PostgreSQL main: usuarios semilla insertados.");
        }
    }
    catch (Exception ex) { log.LogWarning("PostgreSQL main no disponible: {Message}", ex.Message); }

    // PostgreSQL auditoría — migraciones
    try
    {
        var auditCtx = scope.ServiceProvider.GetRequiredService<AuditDbContext>();
        auditCtx.Database.Migrate();
        log.LogInformation("PostgreSQL audit: migraciones aplicadas correctamente.");
    }
    catch (Exception ex) { log.LogWarning("PostgreSQL audit no disponible: {Message}", ex.Message); }
}

// ── Pipeline HTTP ─────────────────────────────────────────────────────────────
if (!app.Environment.IsDevelopment())
{
    app.UseExceptionHandler("/Home/Error");
    app.UseHsts();
    app.UseHttpsRedirection();
}

app.UseStaticFiles();
app.UseRouting();
app.UseAuthentication();
app.UseAuthorization();

// ── Rutas MVC ─────────────────────────────────────────────────────────────────
app.MapControllerRoute(
    name: "default",
    pattern: "{controller=Home}/{action=Index}/{id?}");

app.MapGet("/health", () => Results.Ok(new { status = "ok", time = DateTime.UtcNow }));

// ── YARP — debe ir al final, después de MapControllerRoute ───────────────────
// Captura /node-api/... → reenvía a localhost:8080
// Captura /search/...   → reenvía a localhost:3001
app.MapReverseProxy();

app.Run();