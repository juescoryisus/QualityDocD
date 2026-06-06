using Microsoft.EntityFrameworkCore;
using QualityDocD.Data;
using QualityDocD.Services;

namespace QualityDocD.Extensions;

public static class ServiceCollectionExtensions
{
    public static IServiceCollection AddQualityDocServices(
        this IServiceCollection services, IConfiguration config)
    {
        // AppDbContext: migrado de SQL Server a PostgreSQL
        services.AddDbContext<AppDbContext>(opts =>
            opts.UseNpgsql(
                config.GetConnectionString("MainDb"),
                npg => npg.EnableRetryOnFailure(5, TimeSpan.FromSeconds(10), null)));

        // AuditDbContext: ya usaba PostgreSQL, solo cambia el nombre de la clave
        services.AddDbContext<AuditDbContext>(opts =>
            opts.UseNpgsql(
                config.GetConnectionString("AuditDb"),
                npg => npg.EnableRetryOnFailure(5, TimeSpan.FromSeconds(10), null)));

        services.AddScoped<AuthService>();
        services.AddScoped<DocumentService>();

        return services;
    }
}