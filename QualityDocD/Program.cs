using DocumentFormat.OpenXml.InkML;
using Microsoft.AspNetCore.Authentication.Cookies;
using Microsoft.EntityFrameworkCore;
using MongoDB.Driver;
using QualityDocD.Data;
using QualityDocD.Models.Domain;
using QualityDocD.Services;

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
        // Solo aplica migraciones pendientes que aún no se han ejecutado
        // Si la BD ya está al día, no hace nada
        try
        {
            await db.Database.MigrateAsync();
        }
        catch (Exception ex)
        {
            var logger = app.Services.GetRequiredService<ILogger<Program>>();
            logger.LogWarning("MigrateAsync falló (puede ser que la BD ya esté actualizada): {Message}", ex.Message);
        }
    }
    catch (Microsoft.Data.SqlClient.SqlException ex) when (ex.Number == 2714)
    {
        // Las tablas ya existen sin historial de migraciones
    }

    // ── Empresa por defecto (CORREGIDO: Busca por slug, no si la tabla está vacía) ──
    var company = await db.Companies.FirstOrDefaultAsync(c => c.Slug == "demo");
    if (company == null)
    {
        company = new Company
        {
            Name = "Empresa Demo",
            Slug = "demo",
            Email = "admin@demo.qualitydoc.local",
            IsActive = true,
            CreatedAt = DateTime.UtcNow,
        };
        db.Companies.Add(company);
        await db.SaveChangesAsync();
    }

    // ── Seed de Roles ──────────────────────────────────────────────────────────
    if (!await db.Roles.AnyAsync())
    {
        db.Roles.AddRange(
            new Role { Name = "SuperAdmin", Description = "Acceso total al sistema" },
            new Role { Name = "Manager", Description = "Gestión de documentos" },
            new Role { Name = "Reviewer", Description = "Revisar y aprobar" },
            new Role { Name = "Viewer", Description = "Solo lectura" }
        );
        await db.SaveChangesAsync();
    }

    // ── Seed de Departments ────────────────────────────────────────────────────
    if (!await db.Departments.AnyAsync())
    {
        db.Departments.AddRange(
            new Department { Name = "TI", CompanyId = company.Id },
            new Department { Name = "Calidad", CompanyId = company.Id },
            new Department { Name = "Operaciones", CompanyId = company.Id },
            new Department { Name = "Recursos Humanos", CompanyId = company.Id }
        );
        await db.SaveChangesAsync();
    }

    // ── Seed de Usuarios ───────────────────────────────────────────────────────
    if (!await db.Users.AnyAsync())
    {
        var roleSuperAdmin = await db.Roles.FirstAsync(r => r.Name == "SuperAdmin");
        var roleManager = await db.Roles.FirstAsync(r => r.Name == "Manager");
        var roleReviewer = await db.Roles.FirstAsync(r => r.Name == "Reviewer");

        var deptTI = await db.Departments.FirstAsync(d => d.Name == "TI");
        var deptCalidad = await db.Departments.FirstAsync(d => d.Name == "Calidad");

        db.Users.AddRange(
            new User
            {
                Username = "superadmin",
                Email = "superadmin@qualitydoc.local",
                PasswordHash = BCrypt.Net.BCrypt.HashPassword("SuperAdmin123!"),
                RoleId = roleSuperAdmin.Id,
                DepartmentId = deptTI.Id,
                IsActive = true,
                CreatedAt = DateTime.UtcNow,
            },
            new User
            {
                Username = "admin",
                Email = "admin@qualitydoc.local",
                PasswordHash = BCrypt.Net.BCrypt.HashPassword("Admin123!"),
                RoleId = roleSuperAdmin.Id,
                DepartmentId = deptTI.Id,
                IsActive = true,
                CreatedAt = DateTime.UtcNow,
            },
            new User
            {
                Username = "gerente",
                Email = "gerente@qualitydoc.local",
                PasswordHash = BCrypt.Net.BCrypt.HashPassword("Gerente123!"),
                RoleId = roleManager.Id,
                DepartmentId = deptCalidad.Id,
                IsActive = true,
                CreatedAt = DateTime.UtcNow,
            },
            new User
            {
                Username = "revisor1",
                Email = "revisor1@qualitydoc.local",
                PasswordHash = BCrypt.Net.BCrypt.HashPassword("Revisor123!"),
                RoleId = roleReviewer.Id,
                DepartmentId = deptCalidad.Id,
                IsActive = true,
                CreatedAt = DateTime.UtcNow,
            },
            new User
            {
                Username = "revisor2",
                Email = "revisor2@qualitydoc.local",
                PasswordHash = BCrypt.Net.BCrypt.HashPassword("Revisor123!"),
                RoleId = roleReviewer.Id,
                DepartmentId = deptCalidad.Id,
                IsActive = true,
                CreatedAt = DateTime.UtcNow,
            },
            new User
            {
                Username = "editor",
                Email = "editor@qualitydoc.local",
                PasswordHash = BCrypt.Net.BCrypt.HashPassword("Editor123!"),
                RoleId = roleReviewer.Id,
                DepartmentId = deptCalidad.Id,
                IsActive = true,
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
