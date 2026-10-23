<?php
/**
 * BuscoTuPlan — protecciones compartidas del panel de prospectos
 * Sesión, autenticación, CSRF y conexión a la base de datos.
 */

require_once dirname(__DIR__) . '/config.php';

/* ------------------------------------------------------------------ sesión */
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_samesite', 'Lax');
    @ini_set('session.cookie_httponly', '1');
    session_set_cookie_params(0, '/', '', false, true);
    session_start();
}

header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
// el panel nunca debe servirse desde la caché: datos de prospectos en vivo
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

/* ------------------------------------------------------------------ utilidades */
if (!function_exists('btp_rand')) {
    function btp_rand($n)
    {
        if (function_exists('random_bytes')) { return random_bytes($n); }
        return openssl_random_pseudo_bytes($n);
    }
    function e($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
    function csrf_token()
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(btp_rand(32));
        }
        return $_SESSION['csrf'];
    }
    function csrf_field()
    {
        return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
    }
    function csrf_check()
    {
        $ok = isset($_POST['csrf']) && is_string($_POST['csrf'])
              && hash_equals(csrf_token(), $_POST['csrf']);
        if (!$ok) {
            http_response_code(403);
            exit('La sesión expiró o el formulario no es válido. Vuelve atrás y recarga la página.');
        }
    }
    function db()
    {
        // la conexión (y su zona horaria) vive en config.php -> btp_conectar()
        return btp_conectar();
    }
    /** Limita intentos por IP (archivo en el directorio temporal del sistema). */
    function btp_throttle($clave, $max, $ventana)
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? preg_replace('/[^a-z0-9]/i', '', $_SERVER['REMOTE_ADDR']) : 'x';
        $dir = sys_get_temp_dir() . '/btp_admin';
        if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
        $archivo = $dir . '/' . $clave . '_' . $ip . '.json';
        $ahora = time();
        $marcas = array();
        if (is_file($archivo)) {
            $d = json_decode(@file_get_contents($archivo), true);
            if (is_array($d)) { $marcas = $d; }
        }
        $marcas = array_values(array_filter($marcas, function ($t) use ($ahora, $ventana) {
            return ($ahora - (int) $t) < $ventana;
        }));
        if (count($marcas) >= $max) { return false; }
        return $marcas;
    }
    function btp_throttle_add($clave, $marcas)
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? preg_replace('/[^a-z0-9]/i', '', $_SERVER['REMOTE_ADDR']) : 'x';
        $dir = sys_get_temp_dir() . '/btp_admin';
        $archivo = $dir . '/' . $clave . '_' . $ip . '.json';
        $marcas[] = time();
        @file_put_contents($archivo, json_encode($marcas), LOCK_EX);
    }
}

/* ------------------------------------------------------------------ autenticación */
if (!defined('BTP_SIN_LOGIN') && empty($_SESSION['btp_auth'])) {
    header('Location: index.php');
    exit;
}

/** Lista de estados, en orden de embudo. */
function btp_estados()
{
    return array('nuevo', 'contactado', 'interesado', 'cotizado', 'convertido', 'perdido');
}
