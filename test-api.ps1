# ============================================================
# QualityDocD — Script de prueba del Node API
# Ejecutar desde la raiz del repo:
#   .\test-api.ps1
# ============================================================

$BASE = "http://localhost:5000"

Write-Host "`n=== 1. Creando tablas en PostgreSQL ===" -ForegroundColor Cyan

docker exec -i qualitydoc_postgres psql -U qualitydoc -d qualitydoc_audit -c @"
CREATE TABLE IF NOT EXISTS companies (
  id SERIAL PRIMARY KEY,
  name TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE
);
CREATE TABLE IF NOT EXISTS users (
  id SERIAL PRIMARY KEY,
  company_id INTEGER NOT NULL REFERENCES companies(id),
  name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'viewer',
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE TABLE IF NOT EXISTS documents (
  id SERIAL PRIMARY KEY,
  company_id INTEGER NOT NULL REFERENCES companies(id),
  title TEXT NOT NULL,
  format TEXT NOT NULL DEFAULT 'pdf',
  created_by INTEGER NOT NULL REFERENCES users(id),
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE TABLE IF NOT EXISTS document_versions (
  id SERIAL PRIMARY KEY,
  document_id INTEGER NOT NULL REFERENCES documents(id),
  company_id INTEGER NOT NULL REFERENCES companies(id),
  major_version INTEGER NOT NULL DEFAULT 1,
  minor_version INTEGER NOT NULL DEFAULT 0,
  version_number TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'draft',
  content_url TEXT,
  content_text TEXT,
  created_by INTEGER NOT NULL REFERENCES users(id),
  approved_by INTEGER REFERENCES users(id),
  approved_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE TABLE IF NOT EXISTS search_index (
  id SERIAL PRIMARY KEY,
  document_id INTEGER NOT NULL REFERENCES documents(id),
  version_id INTEGER NOT NULL REFERENCES document_versions(id),
  company_id INTEGER NOT NULL REFERENCES companies(id),
  title_tokens JSONB,
  body_tokens JSONB,
  tokens JSONB
);
"@

Write-Host "`n=== 2. Creando empresa ===" -ForegroundColor Cyan
$company = Invoke-RestMethod -Method Post -Uri "$BASE/api/companies" `
    -ContentType "application/json" `
    -Body '{"name":"Mi Empresa","slug":"mi-empresa"}'
Write-Host "Empresa creada: id=$($company.id) nombre=$($company.name)"

Write-Host "`n=== 3. Creando usuario admin ===" -ForegroundColor Cyan
$user = Invoke-RestMethod -Method Post -Uri "$BASE/api/users" `
    -ContentType "application/json" `
    -Body "{`"companyId`":$($company.id),`"name`":`"Jesus`",`"email`":`"jesus@mi-empresa.com`",`"password`":`"Admin123!`",`"role`":`"admin`"}"
Write-Host "Usuario creado: id=$($user.id) email=$($user.email)"

Write-Host "`n=== 4. Login ===" -ForegroundColor Cyan
$login = Invoke-RestMethod -Method Post -Uri "$BASE/api/auth/login" `
    -ContentType "application/json" `
    -Body '{"email":"jesus@mi-empresa.com","password":"Admin123!","companySlug":"mi-empresa"}'
$token = $login.token
Write-Host "Token obtenido: $($token.Substring(0,30))..."

Write-Host "`n=== 5. Creando documento ===" -ForegroundColor Cyan
$doc = Invoke-RestMethod -Method Post -Uri "$BASE/api/documents" `
    -ContentType "application/json" `
    -Headers @{ Authorization = "Bearer $token" } `
    -Body '{"title":"Manual de Calidad ISO 9001","format":"pdf","contentText":"Politica de calidad de la organizacion"}'
Write-Host "Documento creado: id=$($doc.id) titulo=$($doc.title)"

Write-Host "`n=== 6. Listando documentos ===" -ForegroundColor Cyan
$docs = Invoke-RestMethod -Method Get -Uri "$BASE/api/documents" `
    -Headers @{ Authorization = "Bearer $token" }
Write-Host "Total documentos: $($docs.Count)"
$docs | ForEach-Object { Write-Host "  - [$($_.id)] $($_.title)" }

Write-Host "`n=== Todo OK ===" -ForegroundColor Green
