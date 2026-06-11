using System.ComponentModel.DataAnnotations;

namespace QualityDocD.Models.ViewModels;

public class LoginViewModel
{
    [Required(ErrorMessage = "El usuario es obligatorio.")]
    [Display(Name = "Usuario")]
    public string Username { get; set; } = string.Empty;

    [Required(ErrorMessage = "La contraseña es obligatoria.")]
    [DataType(DataType.Password)]
    [Display(Name = "Contraseña")]
    public string Password { get; set; } = string.Empty;

    public string? ReturnUrl { get; set; }
    public string? Error { get; set; }
}

public class RegisterViewModel
{
    [Required(ErrorMessage = "El usuario es obligatorio.")]
    [StringLength(100, MinimumLength = 3)]
    [Display(Name = "Nombre de usuario")]
    public string Username { get; set; } = string.Empty;

    [Required(ErrorMessage = "El correo es obligatorio.")]
    [EmailAddress(ErrorMessage = "Correo electrónico inválido.")]
    [Display(Name = "Correo electrónico")]
    public string Email { get; set; } = string.Empty;

    [Required(ErrorMessage = "La contraseña es obligatoria.")]
    [StringLength(100, MinimumLength = 8, ErrorMessage = "Mínimo 8 caracteres.")]
    [DataType(DataType.Password)]
    [Display(Name = "Contraseña")]
    public string Password { get; set; } = string.Empty;

    [Required(ErrorMessage = "Confirme la contraseña.")]
    [DataType(DataType.Password)]
    [Compare("Password", ErrorMessage = "Las contraseñas no coinciden.")]
    [Display(Name = "Confirmar contraseña")]
    public string ConfirmPassword { get; set; } = string.Empty;

    [Display(Name = "Rol")]
    public string Role { get; set; } = "Viewer";

    [Display(Name = "Departamento")]
    public string Department { get; set; } = string.Empty;

    /// <summary>Slug de la empresa a la que se une el nuevo usuario (ej: "empresa-abc").</summary>
    [Required(ErrorMessage = "El identificador de empresa es obligatorio.")]
    [Display(Name = "Identificador de empresa")]
    public string CompanySlug { get; set; } = string.Empty;

    public string? Error { get; set; }
}
