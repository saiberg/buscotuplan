/* =====================================================================
   BuscoTuPlan — Google Analytics 4
   ---------------------------------------------------------------------
   CÓMO ACTIVARLO (2 minutos):
     1. Entra a https://analytics.google.com → Administrar → Crear
        propiedad → tipo "Web", con la URL del sitio.
     2. Te da un código de medición que empieza con G-
        (ejemplo: G-AB12CD34EF).
     3. Pégalo abajo, entre comillas simples, en GA4_ID. Guarda. Listo.

   Mientras GA4_ID esté vacío no se le pide NADA a Google, así que no
   rompe nada ni carga recursos si aún no lo tienes.
   Los eventos que dispara main.js (generate_lead, whatsapp_click,
   form_start) aparecen en GA4 > Reportes > Participación > Eventos.
   ===================================================================== */
(function () {
  'use strict';

  var GA4_ID = 'G-V1S0GR8LGX';   // propiedad "Busco Tu Plan" (analytics.google.com)
  if (!GA4_ID) { return; }

  window.dataLayer = window.dataLayer || [];
  function gtag() { window.dataLayer.push(arguments); }
  window.gtag = gtag;

  gtag('js', new Date());
  gtag('config', GA4_ID, {
    send_page_view: true,
    anonymize_ip: true   // privacidad: no guardamos la IP completa
  });

  var s = document.createElement('script');
  s.async = true;
  s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(GA4_ID);
  (document.head || document.getElementsByTagName('head')[0]).appendChild(s);
})();
