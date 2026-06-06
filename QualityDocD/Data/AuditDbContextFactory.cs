using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Design;

namespace QualityDocD.Data;

public class AuditDbContextFactory : IDesignTimeDbContextFactory<AuditDbContext>
{
    public AuditDbContext CreateDbContext(string[] args)
    {
        var optionsBuilder = new DbContextOptionsBuilder<AuditDbContext>();

        optionsBuilder.UseNpgsql(
            "Host=localhost;Port=5432;Database=qualitydoc_audit;" +
            "Username=qualitydoc;Password=TuClaveSegura_2026;");

        return new AuditDbContext(optionsBuilder.Options);
    }
} 