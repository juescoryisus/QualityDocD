using MongoDB.Bson;
using MongoDB.Bson.Serialization.Attributes;
using MongoDB.Driver;
using Microsoft.Extensions.Configuration;

namespace QualityDocD.Data;

public class MongoDbContext
{
    private readonly IMongoDatabase _db;

    public MongoDbContext(IConfiguration config)
    {
        var host = config["MongoDB:Host"] ?? "localhost";
        var port = int.Parse(config["MongoDB:Port"] ?? "27017");
        var dbName = config["MongoDB:Database"] ?? "qualitydoc_meta";
        var username = config["MongoDB:Username"];
        var password = config["MongoDB:Password"];

        MongoClient client;
        if (!string.IsNullOrEmpty(username) && !string.IsNullOrEmpty(password))
        {
            var credential = MongoCredential.CreateCredential("admin", username, password);
            var settings = new MongoClientSettings
            {
                Server = new MongoServerAddress(host, port),
                Credential = credential
            };
            client = new MongoClient(settings);
        }
        else
        {
            client = new MongoClient($"mongodb://{host}:{port}");
        }

        _db = client.GetDatabase(dbName);
        EnsureIndexes();
    }

    public IMongoCollection<DocumentMeta> DocumentMetas =>
        _db.GetCollection<DocumentMeta>("document_metadata");

    private void EnsureIndexes()
    {
        var col = DocumentMetas;
        var keys = Builders<DocumentMeta>.IndexKeys;

        var indexModels = new[]
        {
            new CreateIndexModel<DocumentMeta>(
                keys.Ascending(d => d.DocumentId),
                new CreateIndexOptions { Unique = true, Name = "idx_documentId" }),

            new CreateIndexModel<DocumentMeta>(
                keys.Ascending(d => d.Code),
                new CreateIndexOptions { Unique = true, Name = "idx_code" }),

            new CreateIndexModel<DocumentMeta>(
                keys.Ascending(d => d.CompanyId),
                new CreateIndexOptions { Name = "idx_companyId" }),

            new CreateIndexModel<DocumentMeta>(
                keys.Ascending(d => d.Category),
                new CreateIndexOptions { Name = "idx_category" }),

            new CreateIndexModel<DocumentMeta>(
                keys.Ascending(d => d.Tags),
                new CreateIndexOptions { Name = "idx_tags" }),

            new CreateIndexModel<DocumentMeta>(
                keys.Text(d => d.Title)
                    .Text(d => d.Description)
                    .Text("tags")
                    .Text("fileContent"),          // ← NUEVO: indexa contenido del archivo
                new CreateIndexOptions
                {
                    Name    = "idx_fulltext",
                    Weights = new BsonDocument
                    {
                        ["title"]       = 10,
                        ["tags"]        = 5,
                        ["standard"]    = 3,
                        ["description"] = 1,
                        ["fileContent"] = 2,       // ← NUEVO
                    }
                }),
        };

        try
        {
            col.Indexes.CreateMany(indexModels);
        }
        catch (MongoCommandException)
        {
            // Índice existente con opciones distintas — borrar y recrear
            col.Indexes.DropAll();
            col.Indexes.CreateMany(indexModels);
        }
    }
}

public class DocumentMeta
{
    [BsonId]
    [BsonRepresentation(BsonType.ObjectId)]
    public string? Id { get; set; }

    [BsonElement("documentId")]
    public int DocumentId { get; set; }

    [BsonElement("companyId")]
    public int CompanyId { get; set; }

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

    [BsonElement("fileContent")]
    public string FileContent { get; set; } = string.Empty;    // ← NUEVO

    [BsonElement("createdAt")]
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;

    [BsonElement("updatedAt")]
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;
}