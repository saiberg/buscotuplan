<?php
/**
 * BuscoTuPlan — Configuración central
 *
 * Este archivo NO debe ser accesible desde el navegador.
 * Ver .htaccess (raíz) y .htaccess (api/) que lo bloquean.
 * En producción el servidor debe denegar la descarga de *.php ejecutándolos
 * (PHP ya emite nada al incluirlo) y de *.sql.
 */

// ---------------------------------------------------------------- Entorno
// 'local' = desarrollo  |  'production' = sitio publicado
define('APP_ENV', 'local');

// ---------------------------------------------------------------- Base de datos
// LOCAL  (MariaDB 10.2 del servicio Windows, puerto 3306)
// En local el sitio corre dentro de Docker, así que el host de la BD viene
// por variable de entorno BTP_DB_HOST (host.docker.internal). Si ejecutas
// PHP fuera de Docker, usa 127.0.0.1 directamente.
if (!function_exists('btp_env')) {
    function btp_env($key, $default)
    {
        $v = getenv($key);
        return ($v === false || $v === '') ? $default : $v;
    }
}

define('DB_HOST', btp_env('BTP_DB_HOST', '127.0.0.1'));
define('DB_PORT', btp_env('BTP_DB_PORT', '3306'));
define('DB_NAME', btp_env('BTP_DB_NAME', 'buscotuplan'));
define('DB_USER', btp_env('BTP_DB_USER', 'buscotuplan'));
define('DB_PASS', btp_env('BTP_DB_PASS', 'btp_local_2026'));

// ---------------------------------------------------------------- Zona horaria
// Sin esto el sitio corre en UTC y las fechas del panel se ven corridas
// (MySQL guarda la hora del servidor). Todo el proyecto usa hora de Chile.
date_default_timezone_set('America/Santiago');

/**
 * Conexión única a la BD con la misma zona horaria que PHP.
 * Así created_at, NOW() y lo que muestra el panel hablan el mismo reloj.
 */
if (!function_exists('btp_conectar')) {
    function btp_conectar()
    {
        static $pdo = null;
        if ($pdo !== null) { return $pdo; }

        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ));

        // La BD debe devolver la hora chilena aunque el servidor esté en UTC
        // (caso típico en hosting). Usamos el offset numérico que calcula PHP
        // porque funciona sin tablas de zonas horarias (algunos MySQL/MariaDB
        // no las traen): 'America/Santiago' da error 1298 en ese caso.
        try {
            $tzScl  = new DateTimeZone('America/Santiago');
            $offset = (new DateTime('now', $tzScl))->format('P');
            $pdo->exec("SET time_zone = '" . $offset . "'");
        } catch (Exception $e) {
            @error_log('btp: no se pudo fijar time_zone (' . $e->getMessage() . ')');
        }

        return $pdo;
    }
}

// PRODUCCIÓN (Hostinger): reemplaza estos 5 valores por los de hPanel
// define('DB_HOST', 'localhost');
// define('DB_PORT', '3306');
// define('DB_NAME', 'u12345_buscotuplan');
// define('DB_USER', 'u12345_buscotuplan');
// define('DB_PASS', 'la_clave_que_te_dio_hostinger');

// ---------------------------------------------------------------- Contacto
define('WHATSAPP_NUMERO', '56950382768');              // sin +, sin espacios
define('WHATSAPP_LINK',   'https://wa.me/56950382768'); // enlace directo
define('TELEFONO_VISIBLE', '+56 9 5038 2768');

// ---------------------------------------------------------------- Sitio
// URL pública. En local se auto-detecta; en producción se fija explícitamente.
if (APP_ENV === 'production') {
    define('SITE_URL', 'https://www.buscotuplan.cl');
} else {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host  = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost:8000';
    define('SITE_URL', $proto . '://' . $host);
}

// ---------------------------------------------------------------- Protección antispam
define('LEAD_RATE_LIMIT', 8);      // envíos máximos...
define('LEAD_RATE_WINDOW', 3600);  // ...por hora e IP
define('LEAD_MIN_MS', 1200);       // mínimo desde que cargó la página (bots)
define('LOG_DIR', __DIR__ . '/api/logs');

// Pixel de Meta (Facebook) existente de tu hermana
define('META_PIXEL_ID', '1707293943768028');

// ---------------------------------------------------------------- Panel de prospectos
// Para cambiar la clave genera un nuevo hash:
//   php -r "echo password_hash('TU_NUEVA_CLAVE', PASSWORD_DEFAULT);"
// y pégalo aquí. Guarda la clave en un lugar seguro: no se puede recuperar.
define('ADMIN_USER', 'admin');
define('ADMIN_PASS_HASH', '$2y$10$dCURlQNpFx1ETLOxu4rdtOl2WjVi2HOuWsrpheIoiqXMCZqtWaWsu');
define('ADMIN_LOGIN_MAX', 6);     // intentos fallidos...
define('ADMIN_LOGIN_WINDOW', 900); // ...por cada 15 minutos

// ------------------------------------------------- Aviso al entrar un lead
// La velocidad de respuesta es lo que más convierte en este rubro: estos
// avisos hacen que tu hermana se entere al instante en vez de revisar el panel.
//
// TELEGRAM (recomendado, gratis, llega en segundos):
//   1. Abre Telegram y habla con @BotFather → /newbot → copia el token.
//   2. Manda un mensaje a tu bot nuevo y abre:
//        https://api.telegram.org/bot<TOKEN>/getUpdates
//      → ahí está "chat" > "id" (empieza con - o con un número).
//   3. Pega ambos valores abajo. Listo: te avisará de cada prospecto.
define('NOTIFY_TELEGRAM_TOKEN', '');   // ej: 7123456789:AAH...
define('NOTIFY_TELEGRAM_CHAT', '');    // ej: 56987654321 o -1001234567890

// CORREO (opcional, respaldo). Vacío = desactivado.
define('NOTIFY_EMAIL', '');            // ej: hola@buscotuplan.cl

// ------------------------------------------------- Google Analytics 4
// No se configura aquí: el ID de medición (G-XXXXXXX) se pega en
// assets/js/ga.js, que es el archivo que carga GA4 en todas las páginas.

// ------------------------------------------------- Tickets de seguimiento
// Cuando el prospecto ya dejó sus datos y luego hace clic en WhatsApp,
// la web le pide registrar eso en su ficha. Para que nadie pueda inventar
// registros ajenos, ese clic viene firmado con este secreto (solo existe
// en el servidor; si lo cambias por texto aleatorio, mejor).
define('TICKET_SECRET', 'btp_wa_3f8c27d1a94e6b50');
