namespace QualityDocD.Extensions;

public static class DateTimeExtensions
{
    // Zona horaria de México (Centro) — UTC-6 / UTC-5 en verano
    private static readonly TimeZoneInfo MexicoTz = TimeZoneInfo.FindSystemTimeZoneById(
        OperatingSystem.IsWindows()
            ? "Central Standard Time"        // ID en Windows
            : "America/Mexico_City");         // ID en Linux/macOS

    /// <summary>Convierte un DateTime UTC a hora de México.</summary>
    public static DateTime ToMexico(this DateTime utc)
    {
        var dt = utc.Kind == DateTimeKind.Unspecified
            ? DateTime.SpecifyKind(utc, DateTimeKind.Utc)
            : utc.ToUniversalTime();
        return TimeZoneInfo.ConvertTimeFromUtc(dt, MexicoTz);
    }

    /// <summary>Convierte y formatea como "dd/MM/yyyy HH:mm".</summary>
    public static string ToMexicoString(this DateTime utc, string format = "dd/MM/yyyy HH:mm")
        => utc.ToMexico().ToString(format);

    /// <summary>Para DateTime? nullable.</summary>
    public static string ToMexicoString(this DateTime? utc, string format = "dd/MM/yyyy HH:mm")
        => utc.HasValue ? utc.Value.ToMexicoString(format) : "—";
}