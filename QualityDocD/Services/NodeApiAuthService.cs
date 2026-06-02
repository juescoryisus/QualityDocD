using System.Net.Http;
using System.Text;
using System.Text.Json;
using Microsoft.Extensions.Configuration;

namespace QualityDocD.Services;

public class NodeApiOptions
{
    public string Url { get; set; } = "http://localhost:5000";
}

public class NodeApiAuthService
{
    private readonly HttpClient _httpClient;
    private readonly string _nodeApiUrl;

    public NodeApiAuthService(HttpClient httpClient, IConfiguration configuration)
    {
        _httpClient = httpClient;
        _nodeApiUrl = configuration["NodeApi:Url"] ?? "http://localhost:5000";
    }

    public async Task<TokenValidationResult?> ValidateTokenAsync(string token)
    {
        var body = JsonSerializer.Serialize(new { token });
        var content = new StringContent(body, Encoding.UTF8, "application/json");

        var response = await _httpClient.PostAsync(
            $"{_nodeApiUrl}/api/auth/validate", content);

        if (!response.IsSuccessStatusCode) return null;

        var json = await response.Content.ReadAsStringAsync();
        return JsonSerializer.Deserialize<TokenValidationResult>(json,
            new JsonSerializerOptions { PropertyNameCaseInsensitive = true });
    }
}

public class TokenValidationResult
{
    public bool Valid { get; set; }
    public int? UserId { get; set; }
    public int? CompanyId { get; set; }
    public string? CompanySlug { get; set; }
    public string? Role { get; set; }
}