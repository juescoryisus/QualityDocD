using Microsoft.EntityFrameworkCore;

namespace QualityDocD.Data;

/// <summary>
/// Contexto de auditoría — PostgreSQL.
/// Maneja: AuditEntries, ComplianceRecords, AccessLogs.
/// </summary>
public class AuditDbContext : DbContext
{
    public AuditDbContext(DbContextOptions<AuditDbContext> options) : base(options) { }

    public DbSet<AuditEntry> AuditEntries => Set<AuditEntry>();
    public DbSet<ComplianceRecord> ComplianceRecords => Set<ComplianceRecord>();
    public DbSet<AccessLog> AccessLogs => Set<AccessLog>();

    protected override void OnModelCreating(ModelBuilder m)
    {
        base.OnModelCreating(m);

        m.Entity<AuditEntry>(e =>
        {
            e.ToTable("audit_entries");
            e.HasIndex(a => a.CreatedAt);
            e.HasIndex(a => a.DocumentId);
        });

        m.Entity<ComplianceRecord>(e =>
        {
            e.ToTable("compliance_records");
            e.HasIndex(c => new { c.Category, c.Standard }).IsUnique();
        });

        m.Entity<AccessLog>(e =>
        {
            e.ToTable("access_logs");
            e.HasIndex(a => a.AccessedAt);
        });
    }
}

// ── Entidades exclusivas de PostgreSQL ────────────────────────────────────────

public class AuditEntry
{
    public int Id { get; set; }
    public int DocumentId { get; set; }
    public string DocumentCode { get; set; } = string.Empty;
    public int? UserId { get; set; }
    public string? Username { get; set; }
    public string Action { get; set; } = string.Empty;
    public string? OldValue { get; set; }
    public string? NewValue { get; set; }
    public string? IpAddress { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
}

public class ComplianceRecord
{
    public int Id { get; set; }
    public string Category { get; set; } = string.Empty;
    public string Standard { get; set; } = string.Empty;
    public int Approved { get; set; }
    public int Draft { get; set; }
    public int UnderReview { get; set; }
    public int Obsolete { get; set; }
    public int Total { get; set; }
    public DateTime LastUpdated { get; set; } = DateTime.UtcNow;
}

public class AccessLog
{
    public int Id { get; set; }
    public int DocumentId { get; set; }
    public string? Username { get; set; }
    public string? IpAddress { get; set; }
    public string Action { get; set; } = "view";
    public DateTime AccessedAt { get; set; } = DateTime.UtcNow;
}