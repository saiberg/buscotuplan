<?php
/**
 * BuscoTuPlan — Endpoint de captura de prospectos
 *
 * POST JSON o application/x-www-form-urlencoded
 *   { nombre, telefono, isapre_actual, necesidad, consent, utm_*, ts, website }
 *
 * Respuesta JSON:
 *   { "success": true,  "id": 123, "ticket": "abc...", "wa": "https://wa.me/..." }
 *   { "success": false, "errors": { "telefono": "..." } }
 *
 * Segunda acción (misma URL): registrar que el prospecto abrió WhatsApp.
 *   { "accion": "wa_click", "id": 123, "ticket": "abc...", "utm": "..." }
 *
 * PHP 5.6+ / 7.x / 8.x
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/notificar.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// ---------------------------------------------------------------------------
//  Utilidades
// ---------------------------------------------------------------------------
function out($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function log_error($msg)
{
    if (!is_dir(LOG_DIR)) {
        @mkdir(LOG_DIR, 0775, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $_SERVER['REMOTE_ADDR'] . ' — ' . $msg . "\n";
    @file_put_contents(LOG_DIR . '/leads.log', $line, FILE_APPEND | LOCK_EX);
}

function clean($value, $max = 255)
{
    $value = is_string($value) ? $value : '';
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value);
    // elimina etiquetas y caracteres de control
    $value = strip_tags($value);
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max, 'UTF-8');
    }
    return substr($value, 0, $max);
}

/**
 * Normaliza un teléfono chileno a formato internacional sin '+'.
 * Entradas aceptadas: "9 8765 4321", "+56 9 8765 4321", "56987654321", "0987654321"
 */
function normalizar_telefono($raw)
{
    $digits = preg_replace('/\D+/', '', (string) $raw);

    if (strpos($digits, '0056') === 0) {
        $digits = substr($digits, 4);
    }
    if (strpos($digits, '056') === 0) {
        $digits = substr($digits, 3);
    }
    if (strpos($digits, '56') === 0 && strlen($digits) >= 11) {
        $digits = substr($digits, 2);
    }
    if (strpos($digits, '0') === 0 && strlen($digits) === 9) {
        $digits = substr($digits, 1);
    }
    return $digits;
}

function telefono_valido($nacional)
{
    if (!preg_match('/^\d{8,9}$/', $nacional)) {
        return false;
    }
    // móvil: empieza con 9 · fijo: 2..8 dígitos empezando por 2-7
    $first = $nacional[0];
    if (strlen($nacional) === 9) {
        return $first === '9' || $first === '2';
    }
    return $first >= '2' && $first <= '8';
}

function limitar_por_ip($ip)
{
    $key = preg_replace('/[^a-z0-9]/i', '', $ip);
    if ($key === '') {
        $key = 'unknown';
    }
    $dir = sys_get_temp_dir() . '/btp_rate';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    $archivo = $dir . '/' . $key . '.json';

    $ahora = time();
    $registros = array();
    if (is_file($archivo)) {
        $datos = json_decode(@file_get_contents($archivo), true);
        if (is_array($datos)) {
            $registros = $datos;
        }
    }
    $registros = array_values(array_filter($registros, function ($t) use ($ahora) {
        return ($ahora - (int) $t) < LEAD_RATE_WINDOW;
    }));

    if (count($registros) >= LEAD_RATE_LIMIT) {
        return false;
    }
    $registros[] = $ahora;
    @file_put_contents($archivo, json_encode($registros), LOCK_EX);
    return true;
}

/**
 * Ticket firmado que deja al propio prospecto registrar su clic en
 * WhatsApp sin que alguien pueda inventar actividad en fichas ajenas:
 * sin el secreto (que vive solo en el servidor) no se puede fabricar.
 */
function btp_ticket($leadId)
{
    if (!defined('TICKET_SECRET') || TICKET_SECRET === '') {
        return '';
    }
    return hash_hmac('sha256', 'wa|' . (int) $leadId, TICKET_SECRET);
}

// ---------------------------------------------------------------------------
//  Solo POST
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(array('success' => false, 'error' => 'Método no permitido'), 405);
}

// ---------------------------------------------------------------------------
//  Entrada (JSON o formulario clásico)
// ---------------------------------------------------------------------------
$input = $_POST;
$raw   = file_get_contents('php://input');
if (empty($input) && $raw !== false && strlen($raw) > 0) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

// ---------------------------------------------------------------------------
//  Honeypot + tiempo mínimo (trampas para bots)
//  Se responde "success" igualmente para no delatar la trampa.
// ---------------------------------------------------------------------------
$honeypot = isset($input['website']) ? trim((string) $input['website']) : '';
if ($honeypot !== '') {
    log_error('Honeypot activado — posible bot');
    out(array('success' => true, 'id' => null, 'wa' => WHATSAPP_LINK));
}

if (isset($input['ts']) && $input['ts'] !== '' && $input['ts'] !== null) {
    $elapsed = (int) round(microtime(true) * 1000) - (int) $input['ts'];
    if ($elapsed >= 0 && $elapsed < LEAD_MIN_MS) {
        log_error('Envío demasiado rápido (' . $elapsed . 'ms) — posible bot');
        out(array('success' => true, 'id' => null, 'wa' => WHATSAPP_LINK));
    }
}

$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
if (!limitar_por_ip($ip)) {
    http_response_code(429);
    echo json_encode(array(
        'success' => false,
        'error'   => 'Muchas solicitudes. Intenta de nuevo en unos minutos.',
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------------------
//  Registro de interacción del propio prospecto (clic en WhatsApp)
//  POST { accion: 'wa_click', id: 123, ticket: '...', utm: '...' }
//  No crea prospectos: solo anota en la ficha que escribió por su cuenta.
// ---------------------------------------------------------------------------
if (isset($input['accion']) && $input['accion'] === 'wa_click') {
    $idLead    = isset($input['id']) ? (int) $input['id'] : 0;
    $ticket    = isset($input['ticket']) ? (string) $input['ticket'] : '';
    $esperado  = btp_ticket($idLead);

    if ($idLead <= 0 || $esperado === '' || !hash_equals($esperado, $ticket)) {
        log_error('wa_click con ticket inválido (lead ' . $idLead . ')');
        out(array('success' => false, 'error' => 'Ticket inválido'), 403);
    }

    try {
        $pdo = btp_conectar();

        $existe = $pdo->prepare('SELECT id FROM leads WHERE id = :id');
        $existe->execute(array(':id' => $idLead));
        if (!$existe->fetchColumn()) {
            out(array('success' => false, 'error' => 'Prospecto no encontrado'), 404);
        }

        // Si pulsa varias veces no se duplica: uno solo cada 10 minutos.
        $dup = $pdo->prepare(
            "SELECT id FROM lead_activities
              WHERE lead_id = :id AND tipo = 'whatsapp_abierto'
                AND created_at > (NOW() - INTERVAL 10 MINUTE)
              LIMIT 1"
        );
        $dup->execute(array(':id' => $idLead));
        if ($dup->fetchColumn()) {
            out(array('success' => true, 'repetido' => true));
        }

        $campana = isset($input['utm']) ? clean($input['utm'], 160) : '';
        $detalle = ($campana === '')
            ? 'Abrió WhatsApp desde la web.'
            : 'Abrió WhatsApp desde la web (campaña: ' . $campana . ')';

        $ins = $pdo->prepare(
            "INSERT INTO lead_activities (lead_id, tipo, direccion, asunto, detalle)
             VALUES (:id, 'whatsapp_abierto', 'entrada', NULL, :detalle)"
        );
        $ins->execute(array(':id' => $idLead, ':detalle' => $detalle));

        out(array('success' => true));
    } catch (PDOException $e) {
        log_error('wa_click PDO: ' . $e->getMessage());
        out(array('success' => false, 'error' => 'No se pudo registrar el clic'), 500);
    }
}

// ---------------------------------------------------------------------------
//  Validación
// ---------------------------------------------------------------------------
$errors = array();

$nombre = isset($input['nombre']) ? clean($input['nombre'], 120) : '';
if ($nombre === '' || !preg_match('/^[\p{L}][\p{L}\s\.\-\'’]{1,119}$/u', $nombre)) {
    $errors['nombre'] = 'Escribe tu nombre.';
} elseif (mb_strlen($nombre, 'UTF-8') < 2) {
    $errors['nombre'] = 'Escribe tu nombre completo.';
}

$telefonoRaw = isset($input['telefono']) ? (string) $input['telefono'] : '';
$telefono    = normalizar_telefono($telefonoRaw);
if ($telefonoRaw === '') {
    $errors['telefono'] = 'Tu teléfono es necesario para contactarte.';
} elseif (!telefono_valido($telefono)) {
    $errors['telefono'] = 'Revisa el número: debe tener 9 dígitos (ej: 9 8765 4321).';
}

$isapre = isset($input['isapre_actual']) ? clean($input['isapre_actual'], 60) : '';
if ($isapre === '') {
    $errors['isapre_actual'] = 'Selecciona una opción.';
}

$necesidad = isset($input['necesidad']) ? clean($input['necesidad'], 60) : '';

$email = isset($input['email']) ? clean($input['email'], 160) : '';
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Revisa el correo electrónico.';
}

$consent = !empty($input['consent']) || !empty($input['consentimiento']);
if (!$consent) {
    $errors['consent'] = 'Debes autorizar el contacto para poder cotizar.';
}

if (!empty($errors)) {
    out(array('success' => false, 'errors' => $errors), 422);
}

// ---------------------------------------------------------------------------
//  Procedencia (UTM)
// ---------------------------------------------------------------------------
$utm = array(
    'utm_source'   => clean(isset($input['utm_source'])   ? $input['utm_source']   : '', 120),
    'utm_medium'   => clean(isset($input['utm_medium'])   ? $input['utm_medium']   : '', 120),
    'utm_campaign' => clean(isset($input['utm_campaign']) ? $input['utm_campaign'] : '', 160),
    'utm_content'  => clean(isset($input['utm_content'])  ? $input['utm_content']  : '', 160),
    'utm_term'     => clean(isset($input['utm_term'])     ? $input['utm_term']     : '', 160),
);
$origen = clean(isset($input['origen']) ? $input['origen'] : 'landing', 40);
if ($origen === '') {
    $origen = 'landing';
}

// Variante A/B del CTA que vio esta persona. Solo aceptamos los valores
// que puede mandar nuestra propia web; cualquier otra cosa queda en NULL.
$variante = isset($input['variante']) ? clean($input['variante'], 24) : '';
if (!in_array($variante, array('formulario', 'whatsapp'), true)) {
    $variante = null;
}

// ---------------------------------------------------------------------------
//  Insertar
// ---------------------------------------------------------------------------
try {
    $pdo = btp_conectar();

    $sql = 'INSERT INTO leads
            (nombre, telefono, telefono_normalizado, email, isapre_actual, necesidad,
             origen, utm_source, utm_medium, utm_campaign, utm_content, utm_term, variante,
             referrer, landing_url, dispositivo, ip, user_agent, consentimiento)
            VALUES
            (:nombre, :telefono, :telefono_n, :email, :isapre, :necesidad,
             :origen, :utm_source, :utm_medium, :utm_campaign, :utm_content, :utm_term, :variante,
             :referrer, :landing_url, :dispositivo, :ip, :user_agent, 1)';

    // Evita filas duplicadas si la persona envía dos veces (doble clic,
    // reintento de red o el botón de atrás). Si ya existe el mismo número
    // en los últimos 10 minutos, se devuelve el registro existente.
    $dup = $pdo->prepare(
        'SELECT id FROM leads
          WHERE telefono_normalizado = :tel
            AND created_at > (NOW() - INTERVAL 10 MINUTE)
          ORDER BY id DESC LIMIT 1'
    );
    $dup->execute(array(':tel' => $telefono));
    $existe = $dup->fetchColumn();
    if ($existe) {
        out(array('success' => true, 'id' => (int) $existe, 'wa' => WHATSAPP_LINK,
                  'ticket' => btp_ticket($existe), 'duplicate' => true));
    }

    $stmt = $pdo->prepare($sql);

    $navegador = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $disp = 'otro';
    if (preg_match('/Mobile|Android|iPhone|iPad/i', $navegador)) {
        $disp = 'movil';
    } elseif (preg_match('/Tablet|iPad/i', $navegador)) {
        $disp = 'tablet';
    } elseif (preg_match('/Windows|Macintosh|Linux/i', $navegador)) {
        $disp = 'desktop';
    }

    $params = array(
        ':nombre'        => $nombre,
        // formato internacional E.164: +56 + número nacional (móvil 9 dígitos o fijo 8)
        ':telefono'      => '+56' . $telefono,
        ':telefono_n'    => $telefono,
        ':email'         => ($email === '') ? null : $email,
        ':isapre'        => $isapre,
        ':necesidad'     => ($necesidad === '') ? null : $necesidad,
        ':origen'        => $origen,
        ':utm_source'    => $utm['utm_source'],
        ':utm_medium'    => $utm['utm_medium'],
        ':utm_campaign'  => $utm['utm_campaign'],
        ':utm_content'   => $utm['utm_content'],
        ':utm_term'      => $utm['utm_term'],
        ':variante'      => $variante,
        ':referrer'      => clean(isset($input['referrer']) ? $input['referrer'] : '', 500),
        ':landing_url'   => clean(isset($input['landing_url']) ? $input['landing_url'] : '', 500),
        ':dispositivo'   => $disp,
        ':ip'            => clean($ip, 45),
        ':user_agent'    => clean($navegador, 500),
    );
    $stmt->execute($params);
    $leadId = (int) $pdo->lastInsertId();

    // Aviso inmediato (Telegram/correo) a quien da seguimiento. Va después
    // del INSERT y nunca interrumpe la captura: si falla, queda en el log.
    btp_avisar_lead(array(
        'id'          => $leadId,
        'nombre'      => $params[':nombre'],
        'telefono'    => $params[':telefono'],
        'isapre'      => (string) $params[':isapre'],
        'necesidad'   => (string) $params[':necesidad'],
        'dispositivo' => $disp,
        'utm'         => implode(' / ', array_filter(array(
                            (string) $params[':utm_source'],
                            (string) $params[':utm_medium'],
                            (string) $params[':utm_campaign'],
                        ))),
    ));

    out(array(
        'success' => true,
        'id'      => $leadId,
        'ticket'  => btp_ticket($leadId),
        'wa'      => WHATSAPP_LINK,
    ));
} catch (PDOException $e) {
    log_error('PDO: ' . $e->getMessage());
    out(array(
        'success' => false,
        'error'   => 'No pudimos guardar tus datos. Escríbenos por WhatsApp y te ayudamos al tiro.',
        'wa'      => WHATSAPP_LINK,
    ), 500);
} catch (Exception $e) {
    log_error('General: ' . $e->getMessage());
    out(array(
        'success' => false,
        'error'   => 'Ocurrió un problema inesperado. Intenta nuevamente.',
    ), 500);
}
