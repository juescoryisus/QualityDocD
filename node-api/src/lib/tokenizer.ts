const STOP_WORDS = new Set([
  "a", "an", "the", "and", "or", "but", "in", "on", "at", "to", "for",
  "of", "with", "by", "is", "are", "was", "were", "be", "been", "being",
  "have", "has", "had", "do", "does", "did", "will", "would", "should",
  "could", "may", "might", "shall", "can", "de", "la", "el", "los", "las",
  "un", "una", "en", "con", "por", "para", "que", "se", "del",
]);

function normalize(text: string): string {
  return text
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase();
}

export function tokenize(text: string): string[] {
  return normalize(text)
    .replace(/[^a-z0-9\s]/g, " ")
    .split(/\s+/)
    .map((t) => t.trim())
    .filter((t) => t.length > 2 && !STOP_WORDS.has(t));
}

export function extractSnippet(text: string, query: string, windowSize = 80): string {
  const normalText = normalize(text);
  const normalQuery = normalize(query);
  const idx = normalText.indexOf(normalQuery);
  if (idx === -1) {
    return text.slice(0, windowSize) + (text.length > windowSize ? "…" : "");
  }
  const start = Math.max(0, idx - 40);
  const end = Math.min(text.length, idx + query.length + 40);
  const prefix = start > 0 ? "…" : "";
  const suffix = end < text.length ? "…" : "";
  return prefix + text.slice(start, end) + suffix;
}
