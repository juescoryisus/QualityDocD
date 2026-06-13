using System.Text;
using UglyToad.PdfPig;
using DocumentFormat.OpenXml.Packaging;

public class TextExtractionService
{
    public async Task<string?> ExtractTextAsync(Stream fileStream, string fileName, string mimeType)
    {
        var ext = Path.GetExtension(fileName).ToLowerInvariant();

        try
        {
            // PDF
            if (ext == ".pdf" || mimeType == "application/pdf")
            {
                using var ms = new MemoryStream();
                await fileStream.CopyToAsync(ms);
                ms.Position = 0;

                using var pdf = PdfDocument.Open(ms.ToArray());
                var sb = new StringBuilder();
                foreach (var page in pdf.GetPages())
                    sb.AppendLine(page.Text);

                return sb.ToString().Trim();
            }

            // Word DOCX
            if (ext == ".docx" || mimeType == "application/vnd.openxmlformats-officedocument.wordprocessingml.document")
            {
                using var ms = new MemoryStream();
                await fileStream.CopyToAsync(ms);
                ms.Position = 0;

                using var doc = WordprocessingDocument.Open(ms, false);
                var body = doc.MainDocumentPart?.Document?.Body;
                return body?.InnerText?.Trim();
            }

            // Archivos de texto plano (TXT, CSV, MD, JSON, XML, HTML)
            var textExtensions = new[] { ".txt", ".csv", ".md", ".json", ".xml", ".html", ".htm", ".log" };
            if (textExtensions.Contains(ext))
            {
                using var reader = new StreamReader(fileStream, Encoding.UTF8);
                var content = await reader.ReadToEndAsync();
                // Quitar etiquetas HTML si aplica
                if (ext == ".html" || ext == ".htm")
                    content = System.Text.RegularExpressions.Regex.Replace(content, "<[^>]+>", " ");
                return content.Trim();
            }

            return null; // Tipo no soportado
        }
        catch (Exception ex)
        {
            // Log el error pero no falles el upload
            Console.WriteLine($"Text extraction failed for {fileName}: {ex.Message}");
            return null;
        }
    }
}