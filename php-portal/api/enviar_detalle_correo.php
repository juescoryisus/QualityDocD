<?php
// =============================================================================
// QualityDoc Portal — API: Enviar detalle de auditoría por correo
// POST  /api/enviar_detalle_correo.php
// Body JSON: { "log_id": 123, "email": "auditor@empresa.com" }
// =============================================================================

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Método no permitido. Use POST.']);
    exit;
}

// Ocultar errores en la respuesta (no queremos que HTML de errores rompa el JSON)
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config.php';
requireLogin();

// ── Leer y validar body ───────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);

$logId = filter_var($body['log_id'] ?? null, FILTER_VALIDATE_INT);
$email = filter_var($body['email']  ?? null, FILTER_VALIDATE_EMAIL);

if (!$logId || $logId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'log_id inválido.']);
    exit;
}
if (!$email) {
    echo json_encode(['ok' => false, 'error' => 'Correo electrónico inválido.']);
    exit;
}

// ── Obtener el log de auditoría ───────────────────────────────────────────────
function resolveCol(PDO $pdo, string $table, array $candidates): string {
    $stmt = $pdo->prepare(
        "SELECT column_name FROM information_schema.columns
         WHERE table_name = ? AND column_name = ANY(?)"
    );
    $stmt->execute([$table, '{' . implode(',', $candidates) . '}']);
    return $stmt->fetchColumn() ?: $candidates[0];
}

try {
    $pdo = getPgConnection();

    $cId       = resolveCol($pdo, 'audit_entries', ['id',            'Id']);
    $cAction   = resolveCol($pdo, 'audit_entries', ['action',        'Action']);
    $cDocCode  = resolveCol($pdo, 'audit_entries', ['document_code', 'DocumentCode']);
    $cDocTitle = resolveCol($pdo, 'audit_entries', ['document_title','DocumentTitle']);
    $cUsername = resolveCol($pdo, 'audit_entries', ['username',      'Username']);
    $cOldVal   = resolveCol($pdo, 'audit_entries', ['old_value',     'OldValue']);
    $cNewVal   = resolveCol($pdo, 'audit_entries', ['new_value',     'NewValue']);
    $cIp       = resolveCol($pdo, 'audit_entries', ['ip_address',    'IpAddress']);
    $cCreated  = resolveCol($pdo, 'audit_entries', ['created_at',    'CreatedAt']);

    $stmt = $pdo->prepare(
        "SELECT \"{$cId}\" AS id, \"{$cAction}\" AS action,
                \"{$cDocCode}\" AS document_code, \"{$cDocTitle}\" AS document_title,
                \"{$cUsername}\" AS username, \"{$cOldVal}\" AS old_value,
                \"{$cNewVal}\" AS new_value, \"{$cIp}\" AS ip_address,
                \"{$cCreated}\" AS created_at
         FROM audit_entries WHERE \"{$cId}\" = :id"
    );
    $stmt->execute([':id' => $logId]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$log) {
        echo json_encode(['ok' => false, 'error' => "Registro #{$logId} no encontrado."]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
    exit;
}

// ── Formatear payload JSON ────────────────────────────────────────────────────
function prettyJson(?string $raw): string {
    if (!$raw) return '<em style="color:#9ca3af">Sin datos</em>';
    $dec = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) return htmlspecialchars($raw);
    return '<pre style="background:#1a1d26;color:#a5f3fc;padding:10px;border-radius:6px;font-size:12px;white-space:pre-wrap;word-break:break-all;margin:0;">'
           . htmlspecialchars(json_encode($dec, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
           . '</pre>';
}

$fecha    = $log['created_at'] ? date('d/m/Y H:i:s', strtotime($log['created_at'])) : '—';
$accion   = htmlspecialchars($log['action']         ?? '—');
$usuario  = htmlspecialchars($log['username']       ?? '—');
$docCod   = htmlspecialchars($log['document_code']  ?? '—');
$docTit   = htmlspecialchars($log['document_title'] ?? '—');
$ip       = htmlspecialchars($log['ip_address']     ?? '—');
$newHtml  = prettyJson($log['new_value']);
$oldHtml  = prettyJson($log['old_value']);

// ── Construir cuerpo HTML del correo ─────────────────────────────────────────
$subject = "[QualityDoc] Reporte de Auditoría — {$accion} | #{$logId}";

$html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#0f1117;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0f1117;padding:32px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#1a1d26;border-radius:12px;overflow:hidden;border:1px solid rgba(255,255,255,.08);">

        <!-- Header -->
        <tr><td style="background:#4f8ef7;padding:24px 32px;">
          <h1 style="margin:0;color:#fff;font-size:18px;font-weight:700;">
            🛡️ QualityDoc — Reporte de Auditoría
          </h1>
          <p style="margin:6px 0 0;color:rgba(255,255,255,.8);font-size:13px;">
            Evento #{$logId} generado el {$fecha}
          </p>
        </td></tr>

        <!-- Body -->
        <tr><td style="padding:28px 32px;">

          <!-- Alerta de acción -->
          <div style="background:rgba(79,142,247,.12);border-left:4px solid #4f8ef7;border-radius:6px;padding:14px 18px;margin-bottom:24px;">
            <p style="margin:0;color:#4f8ef7;font-size:15px;font-weight:700;">{$accion}</p>
            <p style="margin:4px 0 0;color:#9ca3af;font-size:12px;">Tipo de evento registrado</p>
          </div>

          <!-- Tabla de datos -->
          <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:24px;">
            <tr style="background:rgba(255,255,255,.03);">
              <td style="padding:10px 14px;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;width:160px;border-bottom:1px solid rgba(255,255,255,.06);">Fecha</td>
              <td style="padding:10px 14px;font-size:13px;color:#d1d5db;border-bottom:1px solid rgba(255,255,255,.06);">{$fecha}</td>
            </tr>
            <tr>
              <td style="padding:10px 14px;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;border-bottom:1px solid rgba(255,255,255,.06);">Usuario</td>
              <td style="padding:10px 14px;font-size:13px;color:#d1d5db;border-bottom:1px solid rgba(255,255,255,.06);">{$usuario}</td>
            </tr>
            <tr style="background:rgba(255,255,255,.03);">
              <td style="padding:10px 14px;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;border-bottom:1px solid rgba(255,255,255,.06);">Código Doc.</td>
              <td style="padding:10px 14px;font-size:13px;color:#d1d5db;font-family:monospace;border-bottom:1px solid rgba(255,255,255,.06);">{$docCod}</td>
            </tr>
            <tr>
              <td style="padding:10px 14px;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;border-bottom:1px solid rgba(255,255,255,.06);">Título Doc.</td>
              <td style="padding:10px 14px;font-size:13px;color:#d1d5db;border-bottom:1px solid rgba(255,255,255,.06);">{$docTit}</td>
            </tr>
            <tr style="background:rgba(255,255,255,.03);">
              <td style="padding:10px 14px;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;">IP Origen</td>
              <td style="padding:10px 14px;font-size:13px;color:#a5f3fc;font-family:monospace;">{$ip}</td>
            </tr>
          </table>

          <!-- Valores nuevos -->
          <p style="color:#10b981;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin:0 0 8px;">
            ↓ Valores Nuevos
          </p>
          {$newHtml}

          <!-- Valores anteriores -->
          <p style="color:#f59e0b;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin:20px 0 8px;">
            ↑ Valores Anteriores
          </p>
          {$oldHtml}

        </td></tr>

        <!-- Footer -->
        <tr><td style="background:rgba(0,0,0,.3);padding:16px 32px;border-top:1px solid rgba(255,255,255,.06);">
          <p style="margin:0;font-size:11px;color:#4b5563;text-align:center;">
            Este reporte fue generado automáticamente por <strong style="color:#6b7280">QualityDoc Portal</strong>.<br>
            No responder a este correo.
          </p>
        </td></tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

// ── Enviar correo ─────────────────────────────────────────────────────────────
// Opción A: mail() nativo de PHP (funciona si el servidor tiene SMTP configurado)
// Opción B: PHPMailer (recomendado para producción — ver instrucciones abajo)

$remitente    = getenv('MAIL_FROM')     ?: 'noreply@qualitydoc.local';
$nombreOrigen = getenv('MAIL_FROM_NAME') ?: 'QualityDoc Portal';

$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: {$nombreOrigen} <{$remitente}>\r\n";
$headers .= "Reply-To: {$remitente}\r\n";
$headers .= "X-Mailer: QualityDoc/1.0\r\n";

// ── ¿Tienes PHPMailer? Descomenta este bloque y comenta el mail() de abajo ───
/*
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
require_once __DIR__ . '/../vendor/autoload.php';

$mailer = new PHPMailer(true);
try {
    $mailer->isSMTP();
    $mailer->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $mailer->SMTPAuth   = true;
    $mailer->Username   = getenv('SMTP_USER');
    $mailer->Password   = getenv('SMTP_PASS');
    $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->Port       = 587;
    $mailer->CharSet    = 'UTF-8';

    $mailer->setFrom(getenv('SMTP_USER'), $nombreOrigen);
    $mailer->addAddress($email);
    $mailer->Subject = $subject;
    $mailer->isHTML(true);
    $mailer->Body = $html;
    $mailer->send();

    echo json_encode(['ok' => true, 'message' => "Reporte enviado a {$email}"]);
} catch (\Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'Error SMTP: ' . $mailer->ErrorInfo]);
}
exit;
*/

// ── mail() nativo ─────────────────────────────────────────────────────────────
$sent = mail($email, $subject, $html, $headers);

if ($sent) {
    echo json_encode([
        'ok'      => true,
        'message' => "Reporte del evento #{$logId} enviado a {$email}",
    ]);
} else {
    echo json_encode([
        'ok'    => false,
        'error' => 'El servidor no pudo enviar el correo. Verifica la configuración SMTP del contenedor PHP.',
    ]);
}
