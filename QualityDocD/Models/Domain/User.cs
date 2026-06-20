using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace QualityDocD.Models.Domain;   // ← mismo namespace que Role.cs y Department.cs

[Table("Users")]
public class User
{
    [Key]
    public int Id { get; set; }

    [Required]
    [MaxLength(100)]
    public string Username { get; set; } = string.Empty;

    [Required]
    [MaxLength(200)]
    public string Email { get; set; } = string.Empty;

    [Required]
    public string PasswordHash { get; set; } = string.Empty;

    // ── Rol ────────────────────────────────
    [Required]
    public int RoleId { get; set; }

    [ForeignKey("RoleId")]
    public Role Role { get; set; } = null!;

    // ── Departamento ────────────────────────
    [Required]
    public int DepartmentId { get; set; }

    [ForeignKey("DepartmentId")]
    public Department Department { get; set; } = null!;

    public bool IsActive { get; set; } = true;

    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;

    public DateTime? LastLoginAt { get; set; }

    // ── Navegación inversa ──────────────────
    public ICollection<Document> CreatedDocuments { get; set; } = new List<Document>();
    public ICollection<DocumentApproval> Approvals { get; set; } = new List<DocumentApproval>();
}