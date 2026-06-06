using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Design;


namespace QualityDocD.Data;

public class AppDbContextFactory : IDesignTimeDbContextFactory<AppDbContext>
{
    public AppDbContext CreateDbContext(string[] args)
    {
        var optionsBuilder = new DbContextOptionsBuilder<AppDbContext>();

        optionsBuilder.UseSqlServer(
            "Server=localhost,1433;Database=QualityDocDB;User Id=colaborador;" +
            "Password=TuClaveSegura_2026;TrustServerCertificate=True;");

        return new AppDbContext(optionsBuilder.Options);
    }
}