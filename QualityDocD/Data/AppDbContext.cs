using Microsoft.EntityFrameworkCore;
using QualityDocD.Models.Domain;

namespace QualityDocD.Data;

public class AppDbContext : DbContext
{
    public AppDbContext(DbContextOptions<AppDbContext> options) : base(options) { }

    public DbSet<User> Users { get; set; }
    public DbSet<Company> Companies { get; set; }
    public DbSet<Document> Documents { get; set; }
    public DbSet<DocumentApproval> DocumentApprovals { get; set; }
    public DbSet<AuditLog> AuditLogs { get; set; }
    public DbSet<Role> Roles { get; set; }
    public DbSet<Department> Departments { get; set; }

    protected override void OnModelCreating(ModelBuilder m)
    {
        base.OnModelCreating(m);

        // ── Company ───────────────────────────────────────────────
        m.Entity<Company>(e =>
        {
            e.HasIndex(c => c.Slug).IsUnique();
            e.HasIndex(c => c.Email).IsUnique();
            e.Property(c => c.IsActive).HasDefaultValue(true);
        });

        // ── Role ──────────────────────────────────────────────────
        m.Entity<Role>(e =>
        {
            e.HasIndex(r => r.Name).IsUnique();
            e.Property(r => r.IsActive).HasDefaultValue(true);
        });

        // ── Department ────────────────────────────────────────────
        m.Entity<Department>(e =>
        {
            e.Property(d => d.IsActive).HasDefaultValue(true);
            e.HasOne(d => d.Company)
             .WithMany()
             .HasForeignKey(d => d.CompanyId)
             .OnDelete(DeleteBehavior.Restrict);
        });

        // ── User ──────────────────────────────────────────────────
        m.Entity<User>(e =>
        {
            e.HasIndex(u => u.Username).IsUnique();
            e.HasIndex(u => u.Email).IsUnique();
            e.Property(u => u.IsActive).HasDefaultValue(true);
            e.HasOne(u => u.Role)
             .WithMany(r => r.Users)
             .HasForeignKey(u => u.RoleId)
             .OnDelete(DeleteBehavior.Restrict);
            e.HasOne(u => u.Department)
             .WithMany(d => d.Users)
             .HasForeignKey(u => u.DepartmentId)
             .OnDelete(DeleteBehavior.Restrict);
            e.ToTable(t => t.UseSqlOutputClause(false));
        });

        // ── Document ──────────────────────────────────────────────
        m.Entity<Document>(e =>
        {
            e.HasIndex(d => d.Code).IsUnique();
            e.Property(d => d.Status).HasConversion<string>();
            e.HasOne(d => d.CreatedByUser)
             .WithMany(u => u.CreatedDocuments)
             .HasForeignKey(d => d.CreatedByUserId)
             .OnDelete(DeleteBehavior.Restrict);
            e.HasOne(d => d.Company)
             .WithMany(c => c.Documents)
             .HasForeignKey(d => d.CompanyId)
             .OnDelete(DeleteBehavior.Restrict);
            e.ToTable(t => t.UseSqlOutputClause(false));
        });

        // ── DocumentApproval ──────────────────────────────────────
        m.Entity<DocumentApproval>(e =>
        {
            e.Property(a => a.Status).HasConversion<string>();
            e.HasOne(a => a.Document)
             .WithMany(d => d.Approvals)
             .HasForeignKey(a => a.DocumentId)
             .OnDelete(DeleteBehavior.Cascade);
            e.HasOne(a => a.Reviewer)
             .WithMany(u => u.Approvals)
             .HasForeignKey(a => a.ReviewerId)
             .OnDelete(DeleteBehavior.Restrict);
            e.ToTable(t => t.UseSqlOutputClause(false));
        });

        // ── AuditLog ──────────────────────────────────────────────
        m.Entity<AuditLog>(e =>
        {
            e.HasOne(l => l.Document)
             .WithMany(d => d.AuditLogs)
             .HasForeignKey(l => l.DocumentId)
             .OnDelete(DeleteBehavior.Cascade);
            e.HasOne(l => l.User)
             .WithMany()
             .HasForeignKey(l => l.UserId)
             .OnDelete(DeleteBehavior.SetNull);
            e.ToTable(t => t.UseSqlOutputClause(false));
        });
    }
}