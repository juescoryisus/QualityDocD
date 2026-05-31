namespace QualityDocD.Models.Domain;

public enum ApprovalStatus { Pending, Approved, Rejected }

public class DocumentApproval
{
    public int Id { get; set; }
    public int DocumentId { get; set; }
    public Document Document { get; set; } = null!;
    public int ReviewerId { get; set; }
    public User Reviewer { get; set; } = null!;
    public int ApprovalOrder { get; set; } = 1;
    public ApprovalStatus Status { get; set; } = ApprovalStatus.Pending;
    public string? Comments { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime? ReviewedAt { get; set; }
}