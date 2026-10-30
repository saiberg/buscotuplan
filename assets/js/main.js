/* =====================================================================
   BuscoTuPlan — interacciones de la landing
   Sin dependencias. Compatible con navegadores modernos.
   ===================================================================== */
(function () {
  'use strict';

  var WA_BASE = 'https://wa.me/56950382768';

  // La ruta de la API se calcula desde dónde está este archivo, no desde la
  // página. main.js vive en <sitio>/assets/js/, así que la API está dos
  // niveles arriba: funciona igual en la raíz del dominio, en una subcarpeta
  // (como /guias/) o si algún día se sirve en un directorio distinto.
  var API_URL = (function () {
    try {
      var yo = document.currentScript && document.currentScript.src;
      if (yo) { return new URL('../../api/leads.php', yo).href; }
    } catch (e) { /* sin currentScript */ }
    return '/api/leads.php';
  })();

  /* ----------------------------------------------------------
     Medición. Los scripts se cargan aparte (ga.js y el píxel de
     Meta en el HTML); aquí solo avisamos si están disponibles.
     Así el sitio funciona igual aunque no haya nada configurado.
     ---------------------------------------------------------- */
  function track(evento, params) {
    if (typeof window.gtag === 'function') {
      window.gtag('event', evento, params || {});
    }
  }

  function trackMeta(evento) {
    if (typeof fbq === 'function') { fbq('track', evento); }
  }

  // Cada evento lleva la campaña con la que llegó la persona, para poder
  // responder "qué publicidad trae clientes" y no solo "cuántos formularios".
  function paramsCampana(extra) {
    var p = {};
    if (utm && utm.utm_source) {
      p.utm_source   = utm.utm_source || '';
      p.utm_medium   = utm.utm_medium || '';
      p.utm_campaign = utm.utm_campaign || '';
    }
    if (extra) {
      Object.keys(extra).forEach(function (k) { p[k] = extra[k]; });
    }
    return p;
  }

  var $  = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* ---------------------------------------------------------- año */
  var yearEl = $('#year');
  if (yearEl) { yearEl.textContent = new Date().getFullYear(); }

  /* ---------------------------------------------------------- menú móvil */
  var toggle = $('#nav-toggle');
  var nav = $('#nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
    });
    nav.addEventListener('click', function (e) {
      if (e.target.closest('a')) {
        nav.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* ----------------------------------------------------------
     Parámetros UTM + referrer: se guardan en la sesión para que
     sobrevivan a la navegación y se inyectan en cada formulario.
     Así sabes después qué campaña trajo cada prospecto.
     ---------------------------------------------------------- */
  var UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];

  function leerUTM() {
    var params = new URLSearchParams(window.location.search);
    var guardado = {};
    var hay = false;

    try {
      var previo = sessionStorage.getItem('btp_utm');
      if (previo) { guardado = JSON.parse(previo) || {}; }
    } catch (e) { guardado = {}; }

    UTM_KEYS.forEach(function (k) {
      var v = params.get(k);
      if (v) { guardado[k] = v; hay = true; }
    });

    if (params.get('gclid')) { guardado.utm_source = guardado.utm_source || 'google'; guardado.utm_medium = guardado.utm_medium || 'cpc'; hay = true; }

    try {
      if (hay || Object.keys(guardado).length) { sessionStorage.setItem('btp_utm', JSON.stringify(guardado)); }
    } catch (e) { /* modo privado */ }
    return guardado;
  }

  var utm = leerUTM();
  var horaCarga = Date.now();

  /* ---------------------------------------------------------- variante A/B del CTA
     El HTML ya decidió la variante (?cta=wa → data-cta="wa"). Aquí solo la
     medimos y la mandamos junto al prospecto, para poder comparar en el panel
     qué versión trae más gente convencida. */
  var variante = document.documentElement.getAttribute('data-cta') === 'wa'
    ? 'whatsapp' : 'formulario';
  track('cta_variant', { variant: variante });

  // Datos del último prospecto que envió el formulario en esta sesión
  // (id + ticket). Sirven para anotar en su ficha que escribió por
  // WhatsApp. Se guardan en la sesión por si navega a otra página.
  var ultimoLead = null;
  try {
    var guardadoLead = sessionStorage.getItem('btp_lead');
    if (guardadoLead) { ultimoLead = JSON.parse(guardadoLead); }
  } catch (e) { ultimoLead = null; }

  function guardarUltimoLead(res) {
    ultimoLead = (res && res.id) ? { id: res.id, ticket: res.ticket || '' } : null;
    try {
      if (ultimoLead) { sessionStorage.setItem('btp_lead', JSON.stringify(ultimoLead)); }
      else { sessionStorage.removeItem('btp_lead'); }
    } catch (e) { /* modo privado */ }
  }

  function prepararForm(form) {
    UTM_KEYS.forEach(function (k) {
      var input = form.querySelector('input[name="' + k + '"]');
      if (input && utm[k]) { input.value = utm[k]; }
    });
    var ref = form.querySelector('input[name="referrer"]');
    if (ref) { ref.value = document.referrer || ''; }
    var url = form.querySelector('input[name="landing_url"]');
    if (url) { url.value = window.location.href; }
    var ts = form.querySelector('input[name="ts"]');
    if (ts) { ts.value = String(horaCarga); }
  }

  /* ---------------------------------------------------------- formateo del teléfono */
  function soloDigitos(v) { return (v || '').replace(/\D+/g, ''); }

  function formatearTelefono(v) {
    var d = soloDigitos(v);
    // descarta prefijos escritos a mano
    if (d.indexOf('0056') === 0 && d.length > 11) { d = d.slice(4); }
    else if (d.indexOf('056') === 0 && d.length > 11) { d = d.slice(3); }
    else if (d.indexOf('569') === 0 && d.length >= 11) { d = d.slice(2); }
    else if (d.length > 9 && d.slice(0, 2) === '56') { d = d.slice(2); }
    if (d.length > 9) { d = d.slice(0, 9); }

    if (d.length <= 1) { return d; }
    var out = d.slice(0, 1);
    if (d.length > 1) { out += ' ' + d.slice(1, 5); }
    if (d.length > 5) { out += ' ' + d.slice(5, 9); }
    return out;
  }

  $$('.lead-form input[name="telefono"]').forEach(function (input) {
    input.addEventListener('input', function () {
      var pos = input.selectionStart === input.value.length;
      input.value = formatearTelefono(input.value);
      if (pos) {
        try { input.setSelectionRange(input.value.length, input.value.length); } catch (e) {}
      }
    });
  });

  /* ---------------------------------------------------------- validación */
  function setError(form, campo, mensaje) {
    var caja = form.querySelector('[data-error-for="' + campo + '"]');
    if (caja) { caja.textContent = mensaje || ''; }
    var campoEl = form.querySelector('[name="' + campo + '"]');
    if (campoEl) { campoEl.classList.toggle('is-error', !!mensaje); }
    if (campo === 'consent') {
      var lbl = form.querySelector('.check');
      if (lbl) { lbl.classList.toggle('is-error', !!mensaje); }
    }
  }

  function limpiarErrores(form) {
    ['nombre', 'telefono', 'isapre_actual', 'consent', 'email'].forEach(function (c) {
      setError(form, c, '');
    });
    var alerta = form.querySelector('.form-alert');
    if (alerta) { alerta.remove(); }
  }

  function mostrarAlerta(form, texto, urlWa) {
    var alerta = form.querySelector('.form-alert');
    if (!alerta) {
      alerta = document.createElement('div');
      alerta.className = 'form-alert';
      alerta.setAttribute('role', 'alert');
      var btn = form.querySelector('button[type="submit"]');
      if (btn && btn.parentNode) { btn.parentNode.insertBefore(alerta, btn); }
      else { form.appendChild(alerta); }
    }
    alerta.innerHTML = '';
    alerta.appendChild(document.createTextNode(texto + ' '));
    if (urlWa) {
      var a = document.createElement('a');
      a.href = urlWa; a.target = '_blank'; a.rel = 'noopener';
      a.setAttribute('data-wa-lugar', 'alerta-error');
      a.textContent = 'Escríbenos por WhatsApp →';
      alerta.appendChild(a);
    }
  }

  function validar(form) {
    var ok = true;
    var nombre = (form.nombre.value || '').trim();
    var tel = soloDigitos(form.telefono.value);
    var isapre = form.isapre_actual.value;
    var consent = form.querySelector('[name="consent"]');

    setError(form, 'nombre', '');
    setError(form, 'telefono', '');
    setError(form, 'isapre_actual', '');
    setError(form, 'consent', '');

    if (nombre.length < 2 || !/^[\p{L}][\p{L}\s.'’-]*$/u.test(nombre)) {
      setError(form, 'nombre', 'Escribe tu nombre.');
      ok = false;
    }
    if (tel.length < 8 || tel.length > 9) {
      setError(form, 'telefono', 'Revisa el número: 9 dígitos (ej: 9 8765 4321).');
      ok = false;
    }
    if (!isapre) {
      setError(form, 'isapre_actual', 'Selecciona una opción.');
      ok = false;
    }
    if (consent && !consent.checked) {
      setError(form, 'consent', 'Debes autorizar el contacto para poder cotizar.');
      ok = false;
    }
    return ok;
  }

  /* ---------------------------------------------------------- enlaces WhatsApp */
  function waLink(mensaje) {
    return WA_BASE + '?text=' + encodeURIComponent(mensaje || 'Hola, quiero cotizar mi plan de Isapre.');
  }

  function refrescarEnlacesWA() {
    var nombre = '';
    var isapre = '';
    var formPrincipal = $('#form-hero');
    if (formPrincipal) {
      nombre = (formPrincipal.nombre.value || '').trim();
      isapre = formPrincipal.isapre_actual.value;
    }
    var msg = 'Hola, quiero cotizar mi plan de Isapre.';
    if (nombre) { msg += ' Mi nombre es ' + nombre + '.'; }
    if (isapre) { msg += ' Mi Isapre actual es ' + isapre + '.'; }

    $$('[data-wa]').forEach(function (el) {
      var propio = el.getAttribute('data-wa');
      el.href = waLink(propio && propio.indexOf('quiero cotizar') === -1 ? propio : msg);
    });
  }

  $$('[data-wa]').forEach(function (el) {
    var m = el.getAttribute('data-wa');
    el.href = waLink(m);
  });

  if ($('#form-hero')) {
    $('#form-hero').addEventListener('input', function (e) {
      if (e.target.name === 'nombre' || e.target.name === 'isapre_actual') { refrescarEnlacesWA(); }
    });
  }

  /* ---------------------------------------------------------- envío */
  function manejarForm(form) {
    prepararForm(form);

    // Primer acercamiento al formulario: mide cuánta gente empieza a
    // llenarlo frente a cuánta lo termina (si baja, el formulario
    // está pidiendo cosas de más).
    form.addEventListener('focusin', function () {
      if (form.getAttribute('data-inicio') === '1') { return; }
      form.setAttribute('data-inicio', '1');
      track('form_start', paramsCampana({ form_id: form.id }));
    });

    var exito = document.getElementById(
      form.id === 'form-footer' ? 'success-footer' : 'success-hero'
    );

    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      limpiarErrores(form);

      if (!validar(form)) {
        var primeroError = form.querySelector('.is-error');
        if (primeroError) { primeroError.focus({ preventScroll: false }); }
        return;
      }

      var datos = {};
      new FormData(form).forEach(function (v, k) { datos[k] = v; });
      datos.landing_url = datos.landing_url || window.location.href;
      datos.ts = horaCarga;
      datos.variante = variante;

      var btn = form.querySelector('button[type="submit"]');
      btn.classList.add('is-busy');
      btn.disabled = true;

      var control = typeof AbortController !== 'undefined' ? new AbortController() : null;
      var reloj = setTimeout(function () { if (control) { control.abort(); } }, 20000);

      fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(datos),
        signal: control ? control.signal : undefined
      })
        .then(function (r) { return r.json().catch(function () { return {}; }); })
        .then(function (res) {
          clearTimeout(reloj);
          if (res.success) {
            guardarUltimoLead(res);
            // Es la conversión del sitio: en GA4 aparece como evento
            // "generate_lead" (Reparto > Conversiones).
            track('generate_lead', paramsCampana({
              form_id: form.id,
              isapre:  datos.isapre_actual || ''
            }));
            trackMeta('Lead');
            mostrarExito(form, exito, datos);
          } else if (res.errors) {
            Object.keys(res.errors).forEach(function (k) { setError(form, k, res.errors[k]); });
            var f = form.querySelector('.is-error');
            if (f) { f.focus(); }
          } else {
            mostrarAlerta(form, res.error || 'No pudimos guardar tus datos.', res.wa || waLink('Hola, acabo de fallar el formulario de la web.'));
          }
        })
        .catch(function () {
          clearTimeout(reloj);
          mostrarAlerta(form, 'Parece que falló la conexión.', waLink('Hola, el formulario de la web no me cargó bien.'));
        })
        .then(function () {
          btn.classList.remove('is-busy');
          btn.disabled = false;
        });
    });
  }

  function mostrarExito(form, panel, datos) {
    var mensaje = panel ? panel.querySelector('.success-msg') : null;
    if (mensaje && datos && datos.telefono) {
      mensaje.innerHTML = '';
      mensaje.appendChild(document.createTextNode(
        'Te escribiremos por WhatsApp al ' + formatearTelefono(datos.telefono) +
        ' muy pronto con tu comparación de planes. ¿No quieres esperar? Continúa ya mismo:'
      ));
    }
    var waExito = panel ? panel.querySelector('.btn-wa') : null;
    if (waExito) {
      var msg = 'Hola, acabo de dejar mis datos en la web para cotizar mi plan de Isapre.';
      if (datos && datos.nombre) { msg += ' Mi nombre es ' + datos.nombre + '.'; }
      waExito.href = waLink(msg);
    }

    form.style.display = 'none';
    if (panel) { panel.hidden = false; panel.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
  }

  $$('.lead-form').forEach(function (form) {
    manejarForm(form);

    var btnReset = form.parentNode.querySelector('[data-reset]');
    if (btnReset) {
      btnReset.addEventListener('click', function () {
        var panel = btnReset.closest('.form-success');
        if (panel) { panel.hidden = true; }
        form.style.display = '';
        form.reset();
        limpiarErrores(form);
        prepararForm(form);
        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (form.nombre) { form.nombre.focus(); }
      });
    }
  });

  /* ----------------------------------------------------------
     Clic en WhatsApp: es la conversión que más importa del sitio.
     Se mide siempre; y si la persona ya había dejado sus datos,
     queda anotado en su ficha del panel (dirección: entrada).
     ---------------------------------------------------------- */
  document.addEventListener('click', function (ev) {
    var obj = ev.target;
    if (!obj || !obj.closest) { return; }
    var el = obj.closest('[data-wa], .btn-wa, .wa-fab');
    if (!el || !el.href || el.href.indexOf('wa.me') === -1) { return; }

    var lugar = el.getAttribute('data-wa-lugar') || 'otro';
    track('whatsapp_click', paramsCampana({ place: lugar }));
    trackMeta('Contact');

    if (ultimoLead && ultimoLead.id) {
      // Registro silencioso en la base: no bloquea ni molesta si falla.
      fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
          accion: 'wa_click',
          id:     ultimoLead.id,
          ticket: ultimoLead.ticket || '',
          utm:    (utm && utm.utm_source)
                    ? [utm.utm_source, utm.utm_medium, utm.utm_campaign]
                        .filter(Boolean).join(' / ')
                    : ''
        })
      }).catch(function () { /* solo un registro, nada grave */ });
    }
  });

  // Click al teléfono visible (menos frecuente que WhatsApp, pero se mide igual)
  document.addEventListener('click', function (ev) {
    var obj = ev.target;
    if (!obj || !obj.closest) { return; }
    if (obj.closest('a[href^="tel:"]')) {
      track('phone_click', paramsCampana({}));
    }
  });

  /* ---------------------------------------------------------- FAQ: una sola abierta */
  $$('.faq-item').forEach(function (item) {
    item.addEventListener('toggle', function () {
      if (!item.open) { return; }
      $$('.faq-item').forEach(function (otro) {
        if (otro !== item) { otro.open = false; }
      });
    });
  });

})();
