using Microsoft.AspNetCore.Authentication.Cookies;
using Microsoft.EntityFrameworkCore;
using QualityDocD.Data;
using QualityDocD.Models.Domain;
using QualityDocD.Services;

var builder = WebApplication.CreateBuilder(args);

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

// ── Servicios y HTTP Clients ──────────────────────────────────────────────────
builder.Services.AddScoped<AuthService>();
builder.Services.AddScoped<DocumentService>();
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

// ── Seed automático del admin ──────────────────────────────────────────────────
using (var scope = app.Services.CreateScope())
{
    var db = scope.ServiceProvider.GetRequiredService<AppDbContext>();
    await db.Database.MigrateAsync();

    if (!await db.Users.AnyAsync())
    {
        db.Users.Add(new User
        {
            Username = "admin",
            Email = "admin@qualitydoc.local",
            PasswordHash = BCrypt.Net.BCrypt.HashPassword("Admin123!"),
            Role = "Admin",
            Department = "TI",
            IsActive = true,
            CreatedAt = DateTime.UtcNow
        });
        await db.SaveChangesAsync();
    }
}

// Proxy hacia Node API y Search Service
app.MapReverseProxy();

app.MapControllerRoute(
    name: "default",
    pattern: "{controller=Auth}/{action=Login}/{id?}");

app.Run();