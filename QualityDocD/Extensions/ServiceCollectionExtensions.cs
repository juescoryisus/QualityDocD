using Microsoft.EntityFrameworkCore;
using QualityDocD.Data;
using QualityDocD.Services;

namespace QualityDocD.Extensions;

public static class ServiceCollectionExtensions
{
    public static IServiceCollection AddQualityDocServices(
        this IServiceCollection services, IConfiguration config)
    {
        services.AddDbContext<AppDbContext>(opts =>
            opts.UseSqlServer(
                config.GetConnectionString("SqlServer"),
                sql => sql.EnableRetryOnFailure(5, TimeSpan.FromSeconds(10), null)));

        services.AddDbContext<AuditDbContext>(opts =>
            opts.UseNpgsql(
                config.GetConnectionString("PostgreSQL"),
                npg => npg.EnableRetryOnFailure(5, TimeSpan.FromSeconds(10), null)));

        services.AddScoped<AuthService>();
        services.AddScoped<DocumentService>();

        return services;
    }
}