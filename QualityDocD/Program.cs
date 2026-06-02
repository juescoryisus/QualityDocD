using Microsoft.AspNetCore.Authentication.Cookies;
using Microsoft.EntityFrameworkCore;
using QualityDocD.Data;
using QualityDocD.Models.Domain;
using QualityDocD.Services;
using Microsoft.Extensions.Options;

var builder = WebApplication.CreateBuilder(args);

// ── MVC ──────────────────────────────────────────────────────────────────────
builder.Services.AddControllersWithViews();

// ── Bases de Datos ──────────────────────────────────────────────────────────
builder.Services.AddDbContext<AppDbContext>(opts =>
    opts.UseSqlServer(builder.Configuration.GetConnectionString("SqlServer"),
    sql => sql.EnableRetryOnFailure(5, TimeSpan.FromSeconds(10), null)));

builder.Services.AddDbContext<AuditDbContext>(opts =>
    opts.UseNpgsql(builder.Configuration.GetConnectionString("PostgreSQL"),
    npg => npg.EnableRetryOnFailure(5, TimeSpan.FromSeconds(10), null)));

// ── Servicios y HTTP Clients ─────────────────────────────────────────────────
builder.Services.AddScoped<AuthService>();
builder.Services.AddScoped<DocumentService>();
builder.Services.AddScoped<ApiTokenService>();
builder.Services.AddHttpContextAccessor();


builder.Services.AddHttpClient("SearchService", client =>
{
    client.BaseAddress = new Uri(builder.Configuration["SearchService:BaseUrl"] ?? "http://localhost:3001");
    client.Timeout = TimeSpan.FromSeconds(5);
});

builder.Services.AddHttpClient<NodeApiAuthService>();

// ── Autenticación y Autorización ─────────────────────────────────────────────
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

var app = builder.Build();

// ── Inicialización (Seed de datos) ───────────────────────────────────────────
using (var scope = app.Services.CreateScope())
{
    var log = scope.ServiceProvider.GetRequiredService<ILogger<Program>>();

    // SQL Server Seed
    try
    {
        var sqlCtx = scope.ServiceProvider.GetRequiredService<AppDbContext>();
        sqlCtx.Database.EnsureCreated();

        if (!sqlCtx.Users.Any())
        {
            var now = DateTime.UtcNow;
            sqlCtx.Users.AddRange(
                new User { Username = "admin", Email = "admin@qualitydoc.local", PasswordHash = BCrypt.Net.BCrypt.HashPassword("Admin123!"), Role = "Admin", Department = "TI", IsActive = true, CreatedAt = now },
                new User { Username = "gerente", Email = "gerente@qualitydoc.local", PasswordHash = BCrypt.Net.BCrypt.HashPassword("Gerente123!"), Role = "Manager", Department = "Calidad", IsActive = true, CreatedAt = now },
                new User { Username = "revisor", Email = "revisor@qualitydoc.local", PasswordHash = BCrypt.Net.BCrypt.HashPassword("Revisor123!"), Role = "Reviewer", Department = "Operaciones", IsActive = true, CreatedAt = now },
                new User { Username = "operario", Email = "operario@qualitydoc.local", PasswordHash = BCrypt.Net.BCrypt.HashPassword("Operario123!"), Role = "Viewer", Department = "Producción", IsActive = true, CreatedAt = now }
            );
            sqlCtx.SaveChanges();
            log.LogInformation("SQL Server: usuarios semilla insertados.");
        }
    }
    catch (Exception ex) { log.LogWarning("SQL Server no disponible: {Message}", ex.Message); }

    // Postgres Check
    try
    {
        var pgCtx = scope.ServiceProvider.GetRequiredService<AuditDbContext>();
        pgCtx.Database.EnsureCreated();
        log.LogInformation("PostgreSQL: tablas verificadas/creadas.");
    }
    catch (Exception ex) { log.LogWarning("PostgreSQL no disponible: {Message}", ex.Message); }
}

// ── Pipeline HTTP ────────────────────────────────────────────────────────────
if (!app.Environment.IsDevelopment())
{
    app.UseExceptionHandler("/Home/Error");
    app.UseHsts();
}

// ✅ Solo en producción (o eliminarlo si usas un proxy/Docker que maneja HTTPS)
if (!app.Environment.IsDevelopment())
{
    app.UseExceptionHandler("/Home/Error");
    app.UseHsts();
    app.UseHttpsRedirection(); // ← muévelo aquí dentro
}
app.UseStaticFiles();
app.UseRouting();
app.UseAuthentication();
app.UseAuthorization();

app.MapControllerRoute(name: "default", pattern: "{controller=Home}/{action=Index}/{id?}");
app.MapGet("/health", () => Results.Ok(new { status = "ok", time = DateTime.UtcNow }));

app.Run();