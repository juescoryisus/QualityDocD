namespace QualityDocD.Models.Domain;

/// <summary>
/// Representa una empresa dentro del sistema.
/// Cada empresa tiene sus propios usuarios y documentos completamente aislados.
/// </summary>
public class Company
{
    public int Id { get; set; }

    /// <summary>Nombre visible de la empresa (ej: "Empresa ABC S.A.")</summary>
    public string Name { get; set; } = string.Empty;

    /// <summary>
    /// Identificador corto para URL/subdominio (ej: "empresa-abc").
    /// Solo letras minúsculas, números y guiones. Debe ser único.
    /// </summary>
    public string Slug { get; set; } = string.Empty;

    /// <summary>Correo de contacto de la empresa.</summary>
    public string Email { get; set; } = string.Empty;

    /// <summary>¿La empresa está habilitada para acceder al sistema?</summary>
    public bool IsActive { get; set; } = true;

    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;

    // ── Navegación ─────────────────────────────────────────────────────────
    public ICollection<User> Users { get; set; } = new List<User>();
    public ICollection<Document> Documents { get; set; } = new List<Document>();
}
