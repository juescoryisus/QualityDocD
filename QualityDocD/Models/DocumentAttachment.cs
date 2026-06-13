using MongoDB.Bson;
using MongoDB.Bson.Serialization.Attributes;

public class DocumentAttachment
{
    [BsonId]
    [BsonRepresentation(BsonType.ObjectId)]
    public string? Id { get; set; }

    [BsonElement("documentId")]
    public int DocumentId { get; set; }

    [BsonElement("fileName")]
    public string FileName { get; set; } = "";

    [BsonElement("originalName")]
    public string OriginalName { get; set; } = "";

    [BsonElement("mimeType")]
    public string MimeType { get; set; } = "";

    [BsonElement("fileSize")]
    public long FileSize { get; set; }

    [BsonElement("textContent")]
    public string? TextContent { get; set; }

    [BsonElement("createdAt")]
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
}