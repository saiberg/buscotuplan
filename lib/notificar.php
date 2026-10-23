<?php
/**
 * BuscoTuPlan — Avisos instantáneos cuando entra un prospecto nuevo.
 *
 * Lo usan:
 *   - api/leads.php  → aviso real cuando alguien completa el formulario
 *   - admin/leads.php → botón "probar aviso" del panel
 *
 * Nunca rompe el envío: si Telegram o el correo fallan, solo lo registra
 * en api/logs/leads.log y sigue.
 *
 * PHP 5.6+ / 7.x / 8.x
 */

if (!function_exists('btp_aviso_texto')) {

    /** Armamos el texto del aviso con todo lo necesario para responder ya. */
    function btp_aviso_texto($lead)
    {
        $nombre   = isset($lead['nombre'])    ? $lead['nombre']    : '';
        $tel      = isset($lead['telefono'])   ? $lead['telefono']  : '';
        $isapre   = isset($lead['isapre'])     ? $lead['isapre']    : '';
        $necesidad= isset($lead['necesidad'])  ? $lead['necesidad'] : '';
        $utm      = isset($lead['utm'])        ? $lead['utm']       : '';
        $disp     = isset($lead['dispositivo'])? $lead['dispositivo'] : '';
        $id       = isset($lead['id'])         ? $lead['id']        : '';

        $lineas   = array();
        $lineas[] = 'NUEVO PROSPECTO' . ($id !== '' ? ' #' . $id : '') . ' · ' . date('d/m H:i');
        $lineas[] = '';
        $lineas[] = 'Nombre:    ' . $nombre;
        if ($tel !== '') { $lineas[] = 'Telefono:  ' . $tel; }
        if ($isapre !== '') { $lineas[] = 'Isapre:    ' . $isapre; }
        if ($necesidad !== '') { $lineas[] = 'Necesita:  ' . $necesidad; }
        if ($utm !== '') { $lineas[] = 'Campana:   ' . $utm; }
        if ($disp !== '') { $lineas[] = 'Desde:     ' . $disp; }

        // Enlace directo para contestarle por WhatsApp al instante
        $digitos = preg_replace('/\D+/', '', (string) $tel);
        if ($digitos !== '') {
            $lineas[] = '';
            $lineas[] = 'Contestar ya: https://wa.me/' . $digitos
                      . '?text=' . rawurlencode('Hola ' . $nombre . ', te escribo de Busco Tu Plan por tu cotización de plan de Isapre.');
        }

        return implode("\n", $lineas);
    }

    /** Envía un mensaje de texto por Telegram. Devuelve true si se envió. */
    function btp_enviar_telegram($texto)
    {
        if (!defined('NOTIFY_TELEGRAM_TOKEN') || NOTIFY_TELEGRAM_TOKEN === ''
            || !defined('NOTIFY_TELEGRAM_CHAT') || NOTIFY_TELEGRAM_CHAT === '') {
            return false;
        }

        $url = 'https://api.telegram.org/bot' . NOTIFY_TELEGRAM_TOKEN . '/sendMessage';
        $datos = http_build_query(array(
            'chat_id' => NOTIFY_TELEGRAM_CHAT,
            'text'    => $texto,
        ));

        $contexto = stream_context_create(array(
            'http' => array(
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n"
                           . "Content-Length: " . strlen($datos) . "\r\n"
                           . "Connection: close\r\n",
                'content' => $datos,
                'timeout' => 4,
                'ignore_errors' => true,
            ),
        ));

        $respuesta = @file_get_contents($url, false, $contexto);
        if ($respuesta === false) { return false; }

        $json = json_decode($respuesta, true);
        return (is_array($json) && isset($json['ok']) && $json['ok'] === true);
    }

    /** Envía el aviso por correo (respaldo). Devuelve true si se envió. */
    function btp_enviar_email($asunto, $cuerpo)
    {
        if (!defined('NOTIFY_EMAIL') || NOTIFY_EMAIL === '' || !function_exists('mail')) {
            return false;
        }

        $cabeceras  = "From: avisos@buscotuplan.cl\r\n";
        $cabeceras .= "Reply-To: no-responder@buscotuplan.cl\r\n";
        $cabeceras .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $cabeceras .= "X-Mailer: PHP/" . phpversion();

        // UTF-8 base64 para que las tildes lleguen bien
        $asunto_cod = '=?UTF-8?B?' . base64_encode($asunto) . '?=';

        return @mail(NOTIFY_EMAIL, $asunto_cod, $cuerpo, $cabeceras);
    }

    /**
     * Aviso principal: intenta Telegram y, si no está configurado, correo.
     * Devuelve un texto corto con lo que pasó (para mostrar en el panel)
     * o cadena vacía si no hay ningún canal configurado.
     */
    function btp_avisar_lead($lead)
    {
        $texto      = btp_aviso_texto($lead);
        $asunto     = 'Nuevo prospecto: ' . (isset($lead['nombre']) ? $lead['nombre'] : '');
        $algo_envio = false;
        $detalles   = array();

        if (defined('NOTIFY_TELEGRAM_TOKEN') && NOTIFY_TELEGRAM_TOKEN !== '') {
            $ok = btp_enviar_telegram($texto);
            if ($ok) {
                $algo_envio = true;
                $detalles[] = 'Telegram ✓';
            } else {
                $detalles[] = 'Telegram ✗';
                btp_log_aviso('Telegram no respondió (revisa el token o el chat_id)');
            }
        }

        if (defined('NOTIFY_EMAIL') && NOTIFY_EMAIL !== '') {
            $ok = btp_enviar_email($asunto, $texto);
            if ($ok) {
                $algo_envio = true;
                $detalles[] = 'Correo ✓';
            } else {
                $detalles[] = 'Correo ✗';
                btp_log_aviso('mail() devolvió false (algunos hosting requieren remitente real)');
            }
        }

        if (!$algo_envio && !$detalles) {
            return '';   // nada configurado: no hacemos ruido
        }
        return implode(' · ', $detalles);
    }

    /** Escríbelo en el mismo log de la API para poder diagnosticar. */
    function btp_log_aviso($mensaje)
    {
        if (!defined('LOG_DIR')) { return; }
        if (!is_dir(LOG_DIR)) { @mkdir(LOG_DIR, 0775, true); }
        $line = '[' . date('Y-m-d H:i:s') . '] aviso — ' . $mensaje . "\n";
        @file_put_contents(LOG_DIR . '/leads.log', $line, FILE_APPEND | LOCK_EX);
    }
}
