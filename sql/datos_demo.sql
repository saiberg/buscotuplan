-- =====================================================================
--  BuscoTuPlan — Datos de demostración para el panel de prospectos
-- =====================================================================
--
--  ESTO NO VA EN PRODUCCIÓN. Es solo para ver el panel poblado mientras
--  se desarrolla o para mostrarlo a alguien.
--
--  Cargarlo:      ver "Cómo cargarlos" al final de este archivo.
--  Borrarlo:      ver "Cómo borrarlos" al final de este archivo.
--
--  Los nombres, teléfonos y textos son inventados. Los isapres y las
--  necesidades son exactamente las opciones del formulario real, para
--  que los filtros del panel calcen con los datos de producción.
--
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO `leads`
  (`id`, `nombre`, `telefono`, `telefono_normalizado`, `email`, `isapre_actual`, `necesidad`,
   `origen`, `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term`,
   `referrer`, `landing_url`, `dispositivo`, `ip`, `user_agent`,
   `estado`, `consentimiento`, `contactado_en`, `created_at`, `updated_at`)
VALUES
-- ------------------------------------------------ nuevos (llegaron hoy)
(1,  'Camila Rojas',      '+56981234567', '981234567', 'camila.rojas@gmail.com',    'Colmena',          'Ahorrar en mi plan actual',
    'landing', 'facebook', 'cpc', 'lanzamiento-oct', 'video-01', '',
    'https://www.facebook.com/', 'https://www.buscotuplan.cl/?utm_source=facebook&utm_medium=cpc&utm_campaign=lanzamiento-oct',
    'movil', '190.45.120.33', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15',
    'nuevo', 1, NULL, NOW() - INTERVAL 2 HOUR,  NOW() - INTERVAL 2 HOUR),

(2,  'Mauricio Silva',    '+56987654321', '987654321', NULL,                        'No tengo / No sé', NULL,
    'landing', NULL, NULL, NULL, NULL, NULL,
    NULL, 'https://www.buscotuplan.cl/',
    'movil', '200.83.44.10', 'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 Chrome/120.0',
    'nuevo', 1, NULL, NOW() - INTERVAL 5 HOUR,  NOW() - INTERVAL 5 HOUR),

(3,  'Javiera Muñoz',     '+56922110099', '922110099', 'javi.munoz@hotmail.com',    'Fundación',        'Ahorrar en mi plan actual',
    'landing', 'facebook', 'cpc', 'lanzamiento-oct', 'estatico-dia', '',
    'https://www.facebook.com/', 'https://www.buscotuplan.cl/?utm_source=facebook&utm_medium=cpc&utm_campaign=lanzamiento-oct',
    'movil', '186.67.56.201', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15',
    'nuevo', 1, NULL, NOW() - INTERVAL 9 HOUR,  NOW() - INTERVAL 9 HOUR),

(4,  'Daniela Sepúlveda', '+56966655544', '966655544', NULL,                        'Masculina',        NULL,
    'landing', 'facebook', 'cpc', 'lanzamiento-oct', 'video-02', '',
    'https://www.facebook.com/', 'https://www.buscotuplan.cl/?utm_source=facebook&utm_medium=cpc&utm_campaign=lanzamiento-oct',
    'movil', '190.45.201.8', 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 Chrome/119.0',
    'nuevo', 1, NULL, NOW() - INTERVAL 1 DAY - INTERVAL 3 HOUR, NOW() - INTERVAL 1 DAY - INTERVAL 3 HOUR),

-- ------------------------------------------------------ contactados
(5,  'Andrés Pino',       '+56976543210', '976543210', 'apino@outlook.cl',          'Banmédica',        'Cambiar de Isapre',
    'landing', 'google', 'cpc', 'isapre-ofertas', 'banmedica-vs-colmena', 'isapre barata',
    'https://www.google.com/', 'https://www.buscotuplan.cl/?utm_source=google&utm_medium=cpc&utm_campaign=isapre-ofertas',
    'desktop', '190.45.10.20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/121.0',
    'contactado', 1, NOW() - INTERVAL 2 DAY - INTERVAL 2 HOUR, NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY),

(6,  'Carlos Vega',       '+56911009988', '911009988', NULL,                        'Banmédica',        'No sé, necesito orientación',
    'landing', 'tiktok', 'organic', NULL, NULL, NULL,
    'https://www.tiktok.com/', 'https://www.buscotuplan.cl/',
    'movil', '181.43.8.90', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15',
    'contactado', 1, NOW() - INTERVAL 8 DAY - INTERVAL 4 HOUR, NOW() - INTERVAL 9 DAY, NOW() - INTERVAL 1 DAY),

(7,  'Pablo Herrera',     '+56955512345', '955512345', 'pherrera@miempresa.cl',     'Particular',       'Cotizar para mi familia',
    'landing-footer', 'google', 'cpc', 'isapre-planes', 'comparativa', 'planes isapre',
    'https://www.google.com/', 'https://www.buscotuplan.cl/?utm_source=google&utm_medium=cpc&utm_campaign=isapre-planes',
    'desktop', '200.12.240.55', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Safari/605.1.15',
    'contactado', 1, NOW() - INTERVAL 1 DAY - INTERVAL 6 HOUR, NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 1 DAY),

-- ------------------------------------------------------ interesados
(8,  'Constanza Muñoz',   '+56988877766', '988877766', 'constanza.munoz@gmail.com', 'Mutualidad / FONASA', 'Cambiar de Isapre',
    'landing', 'google', 'cpc', 'fonasa-vs-isapre', 'texto-a', 'fonasa o isapre',
    'https://www.google.com/', 'https://www.buscotuplan.cl/?utm_source=google&utm_medium=cpc&utm_campaign=fonasa-vs-isapre',
    'movil', '190.45.33.140', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15',
    'interesado', 1, NOW() - INTERVAL 3 DAY - INTERVAL 1 HOUR, NOW() - INTERVAL 4 DAY, NOW() - INTERVAL 2 HOUR),

(9,  'Esteban Vergara',   '+56933344455', '933344455', 'e.vergara@gmail.com',       'Particular',       'Ahorrar en mi plan actual',
    'landing', 'facebook', 'cpc', 'retargeting-7d', 'carrousel', '',
    'https://www.facebook.com/', 'https://www.buscotuplan.cl/?utm_source=facebook&utm_medium=cpc&utm_campaign=retargeting-7d',
    'desktop', '181.43.190.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0',
    'interesado', 1, NOW() - INTERVAL 6 DAY - INTERVAL 5 HOUR, NOW() - INTERVAL 7 DAY, NOW() - INTERVAL 1 DAY),

-- -------------------------------------------------------- cotizados
(10, 'Fernanda López',    '+56965554433', '965554433', 'fer.lopez@uc.cl',           'Consalud',         'Cotizar para mi familia',
    'landing', NULL, NULL, NULL, NULL, NULL,
    NULL, 'https://www.buscotuplan.cl/',
    'movil', '190.45.150.66', 'Mozilla/5.0 (Linux; Android 14; SM-A546B) AppleWebKit/537.36 Chrome/120.0',
    'cotizado', 1, NOW() - INTERVAL 2 DAY - INTERVAL 3 HOUR, NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 8 HOUR),

(11, 'Natalia Fuentes',   '+56977788899', '977788899', 'nat.fuentes@yahoo.cl',      'Colmena',          'Cotizar para mi familia',
    'landing', 'youtube', 'organic', NULL, NULL, NULL,
    'https://www.youtube.com/', 'https://www.buscotuplan.cl/',
    'movil', '200.83.9.177', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_7 like Mac OS X) AppleWebKit/605.1.15',
    'cotizado', 1, NOW() - INTERVAL 14 DAY, NOW() - INTERVAL 15 DAY, NOW() - INTERVAL 3 DAY),

-- ------------------------------------------------------- convertidos
(12, 'Rodrigo Soto',      '+56955443322', '955443322', 'rodrigo.soto@gmail.com',    'Cruz Blanca',      'Agregar cargas (hijos o cónyuge)',
    'landing', 'instagram', 'organic', NULL, NULL, NULL,
    'https://www.instagram.com/', 'https://www.buscotuplan.cl/',
    'movil', '190.45.77.201', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_3 like Mac OS X) AppleWebKit/605.1.15',
    'convertido', 1, NOW() - INTERVAL 5 DAY - INTERVAL 2 HOUR, NOW() - INTERVAL 6 DAY, NOW() - INTERVAL 2 DAY),

(13, 'Marcelo Álvarez',   '+56922233344', '922233344', 'malvarez@empresa.cl',       'Cruz Blanca',      'Agregar cargas (hijos o cónyuge)',
    'landing-footer', 'referido', 'amigo', NULL, NULL, NULL,
    NULL, 'https://www.buscotuplan.cl/',
    'desktop', '200.12.88.4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/121.0',
    'convertido', 1, NOW() - INTERVAL 23 DAY, NOW() - INTERVAL 25 DAY, NOW() - INTERVAL 18 DAY),

-- ---------------------------------------------------------- perdidos
(14, 'Pía Contreras',     '+56944332211', '944332211', NULL,                        'Vida Tres',        'No tengo plan todavía',
    'landing', 'facebook', 'cpc', 'lanzamiento-oct', 'video-01', '',
    'https://www.facebook.com/', 'https://www.buscotuplan.cl/?utm_source=facebook&utm_medium=cpc&utm_campaign=lanzamiento-oct',
    'desktop', '186.67.20.44', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/119.0',
    'perdido', 1, NOW() - INTERVAL 10 DAY, NOW() - INTERVAL 12 DAY, NOW() - INTERVAL 9 DAY);


-- =====================================================================
--  Historial de seguimiento de algunos prospectos
--  (los "nuevos" quedan sin historial a propósito)
-- =====================================================================

INSERT INTO `lead_activities` (`lead_id`, `tipo`, `direccion`, `asunto`, `detalle`, `creado_por`, `created_at`) VALUES
-- Andrés Pino (#5)
(5, 'whatsapp', 'salida', NULL,
    'Hola Andrés, te escribo de Busco Tu Plan por tu cotización de Isapre. Te adjunto la comparación entre Banmédica 170 y Colmena 150.',
    'admin', NOW() - INTERVAL 2 DAY - INTERVAL 1 HOUR),
(5, 'nota', 'interna', 'Pide cotización para su esposa',
    'Sin cargas adicionales por ahora. Quiere comparar el plan 170 contra Colmena antes del viernes.',
    'admin', NOW() - INTERVAL 1 DAY - INTERVAL 8 HOUR),
(5, 'llamada', 'entrada', 'Me prefiere por teléfono',
    'Confirmó que quiere seguir con Banmédica pero con un plan más barato. Pide enviarle la opción con deducible.',
    'admin', NOW() - INTERVAL 20 HOUR),

-- Carlos Vega (#6)
(6, 'whatsapp', 'salida', NULL,
    'Hola Carlos, gracias por escribir. ¿Me cuentas qué necesitas cubrir? Con eso te armo una comparación corta.',
    'admin', NOW() - INTERVAL 8 DAY - INTERVAL 3 HOUR),
(6, 'llamada', 'salida', 'No contestó',
    'Dejé mensaje de voz. Reintento mañana en la mañana.',
    'admin', NOW() - INTERVAL 6 DAY),
(7, 'llamada', 'salida', NULL,
    'Contactado por teléfono. Tiene dudas sobre el bono PAD y quiere simular el copago.',
    'admin', NOW() - INTERVAL 1 DAY - INTERVAL 5 HOUR),

-- Constanza Muñoz (#8)
(8, 'whatsapp', 'salida', NULL,
    'Hola Constanza, te comparé Fonasa contra tres isapres con tu caso (plan familiar). Te dejo el resumen por aquí.',
    'admin', NOW() - INTERVAL 3 DAY - INTERVAL 5 HOUR),
(8, 'cambio_estado', 'interna', 'Estado: contactado',
    'Primer contacto realizado por WhatsApp.',
    'admin', NOW() - INTERVAL 3 DAY - INTERVAL 5 HOUR),
(8, 'llamada', 'entrada', 'Quiere cotizar antes de fin de mes',
    'Le falta solo la documentación de su cónyuge. Le envié la lista por WhatsApp.',
    'admin', NOW() - INTERVAL 2 HOUR),

-- Esteban Vergara (#9)
(9, 'whatsapp', 'salida', NULL,
    'Hola Esteban, según lo que me contaste te conviene quedarse en Particular pero bajando de plan. Te mando la simulación.',
    'admin', NOW() - INTERVAL 6 DAY - INTERVAL 4 HOUR),
(9, 'email', 'salida', 'Comparación de planes enviada',
    'Envié PDF con 3 alternativas y tabla de precios actualizada a octubre.',
    'admin', NOW() - INTERVAL 1 DAY),

-- Fernanda López (#10)
(10, 'whatsapp', 'salida', NULL,
    'Hola Fernanda, aquí tienes la cotización para tu familia (2 adultos + 1 carga) en Consalud y Colmena.',
    'admin', NOW() - INTERVAL 2 DAY - INTERVAL 6 HOUR),
(10, 'cambio_estado', 'interna', 'Estado: cotizado',
    'Cotización enviada, esperando respuesta.',
    'admin', NOW() - INTERVAL 8 HOUR),
(10, 'nota', 'interna', 'Comparar contra el plan de su empleador',
    'Me pidió comparar con el plan colectivo de su trabajo antes de decidir.',
    'admin', NOW() - INTERVAL 7 HOUR),

-- Natalia Fuentes (#11)
(11, 'whatsapp', 'salida', NULL,
    'Hola Natalia, te dejo la comparación de planes Colmena para familia. Si quieres la reviso contigo por videollamada.',
    'admin', NOW() - INTERVAL 14 DAY),
(11, 'llamada', 'salida', NULL,
    'Llamada de 12 minutos. Está esperando que termine su período de gracia de la isapre actual.',
    'admin', NOW() - INTERVAL 3 DAY),

-- Rodrigo Soto (#12)
(12, 'whatsapp', 'salida', NULL,
    'Hola Rodrigo, ya dejé listo el plan con tus dos hijos como cargas. Te paso el detalle.',
    'admin', NOW() - INTERVAL 5 DAY - INTERVAL 1 HOUR),
(12, 'llamada', 'entrada', 'Confirma la contratación',
    'Pidió proceder. Envié el formulario de adhesión y la lista de documentos.',
    'admin', NOW() - INTERVAL 2 DAY),
(12, 'cambio_estado', 'interna', 'Estado: convertido',
    'Plan contratado con cargas agregadas.',
    'admin', NOW() - INTERVAL 2 DAY),

-- Marcelo Álvarez (#13)
(13, 'whatsapp', 'salida', NULL,
    'Hola Marcelo, me pasaron tu contacto. Cuando quieras vemos el plan para agregar a tu cónyuge.',
    'admin', NOW() - INTERVAL 24 DAY),
(13, 'llamada', 'salida', NULL,
    'Primera llamada. Interesado pero sin apuro, quiere comparar con lo que ya tiene.',
    'admin', NOW() - INTERVAL 23 DAY),
(13, 'email', 'salida', 'Propuesta enviada',
    'Envié propuesta con precio cerrado por 12 meses.',
    'admin', NOW() - INTERVAL 20 DAY),
(13, 'cambio_estado', 'interna', 'Estado: convertido',
    'Firmó la adhesión. Cargas activas desde el próximo mes.',
    'admin', NOW() - INTERVAL 18 DAY),

-- Pía Contreras (#14)
(14, 'whatsapp', 'salida', NULL,
    'Hola Pía, gracias por dejar tus datos. Te cuento cómo funciona contratar tu primer plan de Isapre.',
    'admin', NOW() - INTERVAL 11 DAY),
(14, 'llamada', 'entrada', 'No tiene presupuesto aún',
    'Me pidió volver a escribirle en dos meses cuando le mejore el sueldo.',
    'admin', NOW() - INTERVAL 10 DAY),
(14, 'cambio_estado', 'interna', 'Estado: perdido',
    'Sin presupuesto por ahora. Se agendó seguimiento manual.',
    'admin', NOW() - INTERVAL 9 DAY);


-- =====================================================================
--  Cómo cargarlos (en local, con Docker):
--
--    docker exec -w /var/www/html buscotuplan-web php -r "..."
--  o desde phpMyAdmin en producción: Importar → elegir este archivo.
--
--  Cómo borrarlos (borra también el historial por la clave foránea):
--
--    DELETE FROM lead_activities;
--    DELETE FROM leads;
--
--  En producción usa siempre IDs propios: este archivo fija IDs 1..14,
--  así que impórtalo solo si la tabla está vacía.
-- =====================================================================
