namespace QualityDocD.Data;


/*using MongoDB.Bson;
using MongoDB.Bson.Serialization.Attributes;
using MongoDB.Driver;

namespace QualityDocD.Data;

/// <summary>
/// Contexto MongoDB.
/// Maneja: document_metadata (metadatos flexibles + búsqueda full-text).
/// </summary>
public class MongoDbContext
{
    private readonly IMongoDatabase _db;

    public MongoDbContext(IConfiguration config)
    {
        var uri = config["MongoDB:Uri"] ?? "mongodb://localhost:27017";
        var dbName = config["MongoDB:Database"] ?? "qualitydoc_meta";
        var client = new MongoClient(uri);
        _db = client.GetDatabase(dbName);
        EnsureIndexes();
    }

    public IMongoCollection<DocumentMeta> DocumentMetas =>
        _db.GetCollection<DocumentMeta>("document_metadata");

    private void EnsureIndexes()
    {
        var col = DocumentMetas;
        var keys = Builders<DocumentMeta>.IndexKeys;

        col.Indexes.CreateMany(new[]
        {
            new CreateIndexModel<DocumentMeta>(
                keys.Ascending(d => d.DocumentId),
                new CreateIndexOptions { Unique = true, Name = "idx_documentId" }),

            new CreateIndexModel<DocumentMeta>(
                keys.Ascending(d => d.Code),
                new CreateIndexOptions { Unique = true, Name = "idx_code" }),

            new CreateIndexModel<DocumentMeta>(
                keys.Ascending(d => d.Category),
                new CreateIndexOptions { Name = "idx_category" }),

            new CreateIndexModel<DocumentMeta>(
                keys.Ascending(d => d.Tags),
                new CreateIndexOptions { Name = "idx_tags" }),

            // Índice full-text con pesos por campo
            new CreateIndexModel<DocumentMeta>(
                keys.Text(d => d.Title)
                    .Text(d => d.Description)
                    .Text("Tags"),
                new CreateIndexOptions
                {
                    Name    = "idx_fulltext",
                    Weights = new BsonDocument
                    {
                        ["Title"]       = 10,
                        ["Tags"]        = 5,
                        ["Standard"]    = 3,
                        ["Description"] = 1,
                    }
                }),
        });
    }
}

// ── Documento MongoDB ─────────────────────────────────────────────────────────

public class DocumentMeta
{
    [BsonId]
    [BsonRepresentation(BsonType.ObjectId)]
    public string? Id { get; set; }

    [BsonElement("documentId")]
    public int DocumentId { get; set; }

    [BsonElement("code")]
    public string Code { get; set; } = string.Empty;

    [BsonElement("title")]
    public string Title { get; set; } = string.Empty;

    [BsonElement("description")]
    public string Description { get; set; } = string.Empty;

    [BsonElement("category")]
    public string Category { get; set; } = string.Empty;

    [BsonElement("standard")]
    public string Standard { get; set; } = string.Empty;

    [BsonElement("tags")]
    public List<string> Tags { get; set; } = new();

    [BsonElement("fileExtension")]
    public string FileExtension { get; set; } = string.Empty;

    [BsonElement("status")]
    public string Status { get; set; } = "Draft";

    [BsonElement("isPublic")]
    public bool IsPublic { get; set; }

    [BsonElement("createdAt")]
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;

    [BsonElement("updatedAt")]
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;
}
*/