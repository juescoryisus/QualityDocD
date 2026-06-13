using Microsoft.AspNetCore.Authentication.Cookies;
using Microsoft.EntityFrameworkCore;
using QualityDocD.Data;
using QualityDocD.Models.Domain;
using QualityDocD.Services;
using MongoDB.Driver;

var builder = WebApplication.CreateBuilder(args);

builder.Services.AddScoped<TextExtractionService>();

// ── MVC ───────────────────────────────────────────────────────────────────────
builder.Services.AddControllersWithViews();

// ── SQL Server (documentos y usuarios) ────────────────────────────────────────
builder.Services.AddDbContext<AppDbContext>(opts =>
    opts.UseSqlServer(
        builder.Configuration.GetConnectionString("SqlServer"),
        sql => sql.EnableRetryOnFailure(5, TimeSpan.FromSeconds(10), null)));

// ── PostgreSQL (auditoría y cumplimiento) ─────────────────────────────────────
builder.Services.AddDbContext<AuditDbContext>(opts =>
    opts.UseNpgsql(
        builder.Configuration.GetConnectionString("AuditDb"),
        npg => npg.EnableRetryOnFailure(5, TimeSpan.FromSeconds(10), null)));

// ── MongoDB (metadatos flexibles + búsqueda full-text) ────────────────────────
builder.Services.AddSingleton<MongoDbContext>();
builder.Services.AddSingleton<IMongoDatabase>(sp =>
{
    var cfg = builder.Configuration.GetSection("MongoDB");
    var host = cfg["Host"];
    var port = cfg["Port"];
    var database = cfg["Database"];
    var username = cfg["Username"];
    var password = cfg["Password"];

    var connectionString = $"mongodb://{username}:{password}@{host}:{port}";
    var client = new MongoClient(connectionString);
    return client.GetDatabase(database);
});
// ── Servicios y HTTP Clients ──────────────────────────────────────────────────
builder.Services.AddScoped<AuthService>();
builder.Services.AddScoped<DocumentService>();
builder.Services.AddScoped<CompanyService>();
builder.Services.AddScoped<ApiTokenService>();
builder.Services.AddHttpContextAccessor();

builder.Services.AddHttpClient("SearchService", client =>
{
    client.BaseAddress = new Uri(
        builder.Configuration["SearchService:BaseUrl"] ?? "http://localhost:3001");
    client.Timeout = TimeSpan.FromSeconds(5);
});

builder.Services.AddHttpClient<NodeApiAuthService>();

// ── Autenticación con Cookies ─────────────────────────────────────────────────
builder.Services.AddAuthentication(CookieAuthenticationDefaults.AuthenticationScheme)
    .AddCookie(opts =>
    {
        opts.LoginPath = "/Auth/Login";
        opts.LogoutPath = "/Auth/Logout";
        opts.AccessDeniedPath = "/Auth/AccessDenied";
        opts.ExpireTimeSpan = TimeSpan.FromHours(8);
        opts.SlidingExpiration = true;
    });

builder.Services.AddAuthorization();

// ── YARP Reverse Proxy ────────────────────────────────────────────────────────
builder.Services.AddReverseProxy()
    .LoadFromConfig(builder.Configuration.GetSection("ReverseProxy"));

// ──────────────────────────────────────────────────────────────────────────────
var app = builder.Build();

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

// ── Seed automático ────────────────────────────────────────────────────────────
using (var scope = app.Services.CreateScope())
{
    var db = scope.ServiceProvider.GetRequiredService<AppDbContext>();

    try
    {
        await db.Database.MigrateAsync();
    }
    catch (Microsoft.Data.SqlClient.SqlException ex) when (ex.Number == 2714)
    {
        // Las tablas ya existen sin historial de migraciones
    }

    // ── Empresa por defecto ────────────────────────────────────────────────────
    if (!await db.Companies.AnyAsync())
    {
        db.Companies.Add(new Company
        {
            Name = "Empresa Demo",
            Slug = "demo",
            Email = "admin@demo.qualitydoc.local",
            IsActive = true,
            CreatedAt = DateTime.UtcNow,
        });
        await db.SaveChangesAsync();
    }

    // ── Usuarios de demostración ───────────────────────────────────────────────
    if (!await db.Users.AnyAsync())
    {
        var company = await db.Companies.FirstAsync(c => c.Slug == "demo");

        db.Users.AddRange(
            new User
            {
                Username = "superadmin",
                Email = "superadmin@qualitydoc.local",
                PasswordHash = BCrypt.Net.BCrypt.HashPassword("SuperAdmin123!"),
                Role = "SuperAdmin",
                Department = "TI",
                IsActive = true,
                CompanyId = company.Id,
                CreatedAt = DateTime.UtcNow,
            },
            new User
            {
                Username = "admin",
                Email = "admin@qualitydoc.local",
                PasswordHash = BCrypt.Net.BCrypt.HashPassword("Admin123!"),
                Role = "Admin",
                Department = "TI",
                IsActive = true,
                CompanyId = company.Id,
                CreatedAt = DateTime.UtcNow,
            },
            new User
            {
                Username = "gerente",
                Email = "gerente@qualitydoc.local",
                PasswordHash = BCrypt.Net.BCrypt.HashPassword("Gerente123!"),
                Role = "Manager",
                Department = "Calidad",
                IsActive = true,
                CompanyId = company.Id,
                CreatedAt = DateTime.UtcNow,
            },
            new User
            {
                Username = "revisor1",
                Email = "revisor1@qualitydoc.local",
                PasswordHash = BCrypt.Net.BCrypt.HashPassword("Revisor123!"),
                Role = "Reviewer",
                Department = "Producción",
                IsActive = true,
                CompanyId = company.Id,
                CreatedAt = DateTime.UtcNow,
            },
            new User
            {
                Username = "revisor2",
                Email = "revisor2@qualitydoc.local",
                PasswordHash = BCrypt.Net.BCrypt.HashPassword("Revisor123!"),
                Role = "Reviewer",
                Department = "Calidad",
                IsActive = true,
                CompanyId = company.Id,
                CreatedAt = DateTime.UtcNow,
            },
            new User
            {
                Username = "editor",
                Email = "editor@qualitydoc.local",
                PasswordHash = BCrypt.Net.BCrypt.HashPassword("Editor123!"),
                Role = "Editor",
                Department = "Operaciones",
                IsActive = true,
                CompanyId = company.Id,
                CreatedAt = DateTime.UtcNow,
            }
        );
        await db.SaveChangesAsync();
    }
}

// Proxy hacia Node API y Search Service
app.MapReverseProxy();

app.MapControllerRoute(
    name: "default",
    pattern: "{controller=Auth}/{action=Login}/{id?}");

app.Run();
