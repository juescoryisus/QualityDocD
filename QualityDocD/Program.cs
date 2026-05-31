using Microsoft.AspNetCore.Authentication.Cookies;
using Microsoft.EntityFrameworkCore;
using QualityDocD.Data;
using QualityDocD.Models.Domain;
using QualityDocD.Services;

var builder = WebApplication.CreateBuilder(args);

builder.Services.AddControllersWithViews();

builder.Services.AddDbContext<AppDbContext>(opts =>
    opts.UseSqlServer(
        builder.Configuration.GetConnectionString("SqlServer"),
        sql => sql.EnableRetryOnFailure(5, TimeSpan.FromSeconds(10), null)));

builder.Services.AddDbContext<AuditDbContext>(opts =>
    opts.UseNpgsql(
        builder.Configuration.GetConnectionString("PostgreSQL"),
        npg => npg.EnableRetryOnFailure(5, TimeSpan.FromSeconds(10), null)));

builder.Services.AddScoped<AuthService>();
builder.Services.AddScoped<DocumentService>();
builder.Services.AddHttpContextAccessor();

builder.Services.AddHttpClient("SearchService", client =>
{
    client.BaseAddress = new Uri(
        builder.Configuration["SearchService:BaseUrl"] ?? "http://localhost:3001");
    client.Timeout = TimeSpan.FromSeconds(5);
});

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

using (var scope = app.Services.CreateScope())
{
    var log = scope.ServiceProvider.GetRequiredService<ILogger<Program>>();

    try
    {
        var sqlCtx = scope.ServiceProvider.GetRequiredService<AppDbContext>();
        sqlCtx.Database.EnsureCreated();
        log.LogInformation("SQL Server: tablas verificadas/creadas.");

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
    catch (Exception ex)
    {
        log.LogWarning("SQL Server no disponible: {Message}", ex.Message);
    }

    try
    {
        var pgCtx = scope.ServiceProvider.GetRequiredService<AuditDbContext>();
        pgCtx.Database.EnsureCreated();
        log.LogInformation("PostgreSQL: tablas verificadas/creadas.");
    }
    catch (Exception ex)
    {
        log.LogWarning("PostgreSQL no disponible: {Message}. Los reportes de auditoría no funcionarán.", ex.Message);
    }
}

if (!app.Environment.IsDevelopment())
{
    app.UseExceptionHandler("/Home/Error");
    app.UseHsts();
}

app.UseHttpsRedirection();
app.UseStaticFiles();
app.UseRouting();
app.UseAuthentication();
app.UseAuthorization();

app.MapControllerRoute(
    name: "default",
    pattern: "{controller=Home}/{action=Index}/{id?}");

app.MapGet("/health", () => Results.Ok(new { status = "ok", time = DateTime.UtcNow }));

app.Run();