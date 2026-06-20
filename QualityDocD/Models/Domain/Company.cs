namespace QualityDocD.Models.Domain;

public class Company
{
    public int Id { get; set; }
    public string Name { get; set; } = string.Empty;
    public string Slug { get; set; } = string.Empty;
    public string Email { get; set; } = string.Empty;
    public bool IsActive { get; set; } = true;
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;

    // ── Navegación ─────────────────────────────────────────────
    // Users ya NO están aquí — se acceden via Company → Departments → Users
    public ICollection<Document> Documents { get; set; } = new List<Document>();
}
