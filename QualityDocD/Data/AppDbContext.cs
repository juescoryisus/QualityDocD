using Microsoft.EntityFrameworkCore;
using QualityDocD.Models.Domain;

namespace QualityDocD.Data;

/// <summary>
/// Contexto principal — SQL Server.
/// Maneja: Users, Documents, DocumentApprovals, AuditLogs.
/// </summary>
public class AppDbContext : DbContext
{
    public AppDbContext(DbContextOptions<AppDbContext> options) : base(options) { }

    public DbSet<User> Users => Set<User>();
    public DbSet<Document> Documents => Set<Document>();
    public DbSet<DocumentApproval> DocumentApprovals => Set<DocumentApproval>();
    public DbSet<AuditLog> AuditLogs => Set<AuditLog>();

    protected override void OnModelCreating(ModelBuilder m)
    {
        base.OnModelCreating(m);

        // ── User ──────────────────────────────────────────────────────────────
        m.Entity<User>(e =>
        {
            e.HasIndex(u => u.Username).IsUnique();
            e.HasIndex(u => u.Email).IsUnique();
            e.Property(u => u.Role).HasDefaultValue("Viewer");
            e.Property(u => u.IsActive).HasDefaultValue(true);
        });

        // ── Document ──────────────────────────────────────────────────────────
        m.Entity<Document>(e =>
        {
            e.HasIndex(d => d.Code).IsUnique();
            e.Property(d => d.Status).HasConversion<string>();
            e.HasOne(d => d.CreatedByUser)
             .WithMany(u => u.CreatedDocuments)
             .HasForeignKey(d => d.CreatedByUserId)
             .OnDelete(DeleteBehavior.Restrict);
        });

        // ── DocumentApproval ─────────────────────────────────────────────────
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
        });

        // ── AuditLog ──────────────────────────────────────────────────────────
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
        });

    }
}
