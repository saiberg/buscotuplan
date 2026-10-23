# Busco Tu Plan — Landing de captura de prospectos

Nueva página del negocio de venta y cambio de planes de Isapre.
Objetivo: **maximizar la captura de prospectos** y dejar una base de datos lista
para la app de seguimiento (CRM) que se construirá después.

---

## 1. Qué incluye

| Parte | Archivo | Descripción |
|---|---|---|
| Landing | `index.html` | Página única, mobile-first, con **dos formularios** (hero + CTA final) y sección de guías |
| Guías SEO | `guias/*.html` | 6 páginas de contenido para atraer visitas orgánicas, con formulario propio |
| Panel | `admin/` | Panel privado para **ver y dar seguimiento** a los prospectos |
| Privacidad | `privacidad.html` | Política de datos enlazada desde el consentimiento |
| API | `api/leads.php` | Recibe, valida, protege antispam y guarda los prospectos (+ registro de clics de WhatsApp) |
| Config | `config.php` | Credenciales de BD, WhatsApp, entorno, clave del panel, avisos y ticket |
| BD | `sql/schema.sql` | Tablas `leads` y `lead_activities` (base del futuro CRM) |
| Estilos | `assets/css/styles.css` | Sin frameworks, CSS variables, responsive |
| JS | `assets/js/main.js` | Validación, envío AJAX, UTM, medición, FAQ, menú |
| JS | `assets/js/ga.js` | Carga de Google Analytics 4 (vacío = no carga nada) |
| SEO | `robots.txt`, `sitemap.xml` | Permiten indexar el sitio y declaran las páginas a Google |

### Decisiones de conversión aplicadas
- Formulario de **4 campos** en vez de 9 (sin RUN ni fecha de nacimiento: se piden
  recién si la persona decide concretar).
- El prospecto **se guarda aunque no escriba por WhatsApp** (antes el único destino era WhatsApp).
- El envío es **sin recargar** la página y termina en una pantalla de éxito con
  botón de WhatsApp pre-cargado.
- **Dos formularios**: arriba (above the fold) y al final de la página. Las guías
  traen su propio formulario, así que cada página de contenido también captura.
- Prueba social, cómo funciona, FAQ, aviso legal y botón flotante de WhatsApp.
- Captura de **UTM + referrer + dispositivo** para saber qué campaña trae clientes.
- Medición con el **Pixel de Meta** de tu hermana (evento `Lead` al enviar y
  `Contact` al hacer clic en WhatsApp).
- **Google Analytics 4** con eventos `generate_lead`, `whatsapp_click`,
  `phone_click`, `form_start` y `cta_variant` — todos llevan la campaña junto.
- **Atribución de WhatsApp:** si quien dejó sus datos además hace clic en el botón
  de WhatsApp, la ficha del prospecto queda anotada como *"Whatsapp abierto ·
  Entrada"*, con la campaña. Así se distingue quién solo dejó datos de quien
  realmente escribió.
- **Variante A/B del CTA:** entrar con `?cta=wa` muestra el botón de WhatsApp como
  acción principal del hero (útil en campañas de anuncios). La variante que vio
  cada prospecto queda guardada en la columna `variante` y en el CSV.

---

## 2. Correr el sitio en local

```bash
cd C:\xampp\htdocs\buscotuplan
docker compose up -d --build
```

- Sitio: **http://localhost:8000**
- Base de datos: MariaDB 10.2 propio de la máquina (servicio Windows, puerto 3306)

Detener: `docker compose down`

> El sitio **no** usa el PHP 5.6 de tu XAMPP ni su Apache: usa un contenedor con
> PHP 8.2, igual que el de producción. XAMPP solo sirve para tener MySQL en marcha.

### Si algún día hay que recrear la base de datos
Importa `sql/schema.sql` desde phpMyAdmin o desde la línea de comandos de MySQL.
El usuario de la aplicación (`buscotuplan` / `btp_local_2026`) ya existe y solo
tiene permisos `SELECT, INSERT, UPDATE, DELETE` sobre esa base.

---

## 3. Ver los prospectos capturados

**Opción A — phpMyAdmin** (si lo tienes instalado) → base `buscotuplan` → tabla `leads`.

**Opción B — consulta rápida** (la más útil):

```sql
SELECT id, nombre, telefono, isapre_actual, necesidad,
       origen, utm_source, utm_campaign, estado, created_at
FROM leads
ORDER BY created_at DESC;
```

**Opción C — exportar a Excel/CSV** desde phpMyAdmin → *Exportar* → CSV.

Columnas que te dirán qué funciona:

```sql
-- ¿Qué campaña trae más prospectos?
SELECT utm_campaign, utm_source, COUNT(*) FROM leads GROUP BY 1,2;

-- ¿De dónde llegan? (móvil vs escritorio)
SELECT dispositivo, COUNT(*) FROM leads GROUP BY 1;

-- ¿Cuántos por día?
SELECT DATE(created_at) d, COUNT(*) FROM leads GROUP BY 1 ORDER BY 1 DESC;

-- ¿Qué página capturó más? (landing vs cada guía)
SELECT origen, COUNT(*) FROM leads GROUP BY 1 ORDER BY 2 DESC;

-- ¿La variante con CTA de WhatsApp convierte más?
SELECT variante, COUNT(*) FROM leads GROUP BY 1;

-- ¿Cuántos dejaron datos y además escribieron por WhatsApp?
SELECT COUNT(DISTINCT a.lead_id) FROM lead_activities a
WHERE a.tipo = 'whatsapp_abierto';
```

La tabla `lead_activities` ya está creada para registrar el seguimiento
(llamadas, WhatsApp, cambios de estado, notas) y el panel lo usa.

---

## 4. Panel de prospectos (`/admin/`)

Para que tu hermana no dependa de phpMyAdmin. Entra en **http://localhost:8000/admin/**

**Acceso actual (⚠️ cámbialo antes de publicar):**

| | |
|---|---|
| Usuario | `admin` |
| Clave | `Btp-2026-Panel-7Kq4` |

Para poner una clave nueva:

```bash
docker exec buscotuplan-web php -r "echo password_hash('TU_NUEVA_CLAVE', PASSWORD_DEFAULT);"
```

y pega el resultado en `ADMIN_PASS_HASH` de `config.php`.

**Qué se puede hacer desde ahí:**

- KPIs de un vistazo: total, nuevos sin tocar, en seguimiento, convertidos y llegados hoy.
- Buscar por **nombre, teléfono, isapre o necesidad** (encuentra "banmedica" aunque
  esté escrito "Banmédica") y filtrar por estado, isapre y rango de fechas.
- Cambiar el estado de cada prospecto: `nuevo → contactado → interesado → cotizado →
  convertido / perdido`. El cambio queda registrado solo en el historial.
- Registrar cada interacción (llamada, WhatsApp, email, nota, tarea, entrada o salida)
  y ver el historial completo en línea de tiempo.
- Botón de WhatsApp por prospecto con el mensaje ya escrito.
- **Exportar a CSV** lo que se está viendo (respeta los filtros).

**Protecciones del panel:** sesión privada, token CSRF en cada formulario,
6 intentos fallidos de login por IP cada 15 minutos, y la carpeta se sirve con
`noindex` para que no aparezca en buscadores.

### Datos de prueba en el panel

Para ver el panel poblado sin esperar a que entren prospectos reales:

```bash
docker exec -w /var/www/html buscotuplan-web php sql/cargar_demo.php
```

Carga **14 prospectos inventados** (4 nuevos, 3 contactados, 2 interesados,
2 cotizados, 2 convertidos, 1 perdido) y **26 interacciones** de ejemplo, con
fechas repartidas en los últimos 25 días para que se vean los filtros y los KPIs.
Pide confirmación antes de borrar lo que haya.

- Archivo: `sql/datos_demo.sql` (isapres y necesidades = opciones reales del formulario).
- Para borrarlos:
  ```sql
  DELETE FROM lead_activities;
  DELETE FROM leads;
  ```
- ⚠️ **No corras el cargador en producción**: borra los prospectos reales.
  `sql/` está bloqueado por `.htaccess` y el cargador se niega a ejecutarse por HTTP.

### Avisos de prospectos nuevos (Telegram)

Cada prospecto que entra dispara un aviso para que tu hermana no dependa de entrar
al panel a cada rato:

- **Principal — Telegram:** crea un bot con [@BotFather](https://t.me/BotFather)
  (te da un *token*), envíale un mensaje al bot nuevo y pídele el *chat_id* con
  [@userinfobot](https://t.me/userinfobot). Pega ambos en `config.php`:
  ```php
  define('NOTIFY_TELEGRAM_TOKEN', '7123456789:AAH...');  // el de BotFather
  define('NOTIFY_TELEGRAM_CHAT',  '56987654321');        // el chat_id
  ```
- **Respaldo — correo:** si el token de Telegram está vacío o falla, se intenta
  enviar un correo con `mail()` a `NOTIFY_EMAIL` (configurable en el mismo archivo).
- **Nunca rompen la captura:** si los avisos fallan, el prospecto igual se guarda
  y el error queda en `api/logs/leads.log`.
- **Probarlo:** el panel tiene un botón **"🔔 Probar aviso"** arriba a la derecha.
  Manda el aviso de prueba y te dice si llegó.

### Medición: GA4, atribución de WhatsApp y variante A/B

**Google Analytics 4** — ya configurado con la propiedad **`G-V1S0GR8LGX`**
("Busco Tu Plan", creada el 22/10/2026). El ID vive en `assets/js/ga.js`:

```js
var GA4_ID = 'G-V1S0GR8LGX';
```

Para cambiarlo o apagarlo, se edita esa línea (vacío = no se pide nada a Google).
Está activo en las 8 páginas: `index.html`, `privacidad.html` y las 6 guías.

Verificar que llegue: [analytics.google.com](https://analytics.google.com) →
**Administrar → Flujo de datos → Tiempo real** (aparece al instante), y en
**Reportes → Participación → Eventos** (tarda unos minutos en poblar).
Los eventos que envía son `generate_lead` (llenó el formulario),
`whatsapp_click` (con la zona: hero, footer, éxitos…), `phone_click`,
`form_start` y `cta_variant`.

**Variante con CTA de WhatsApp** — en las campañas de anuncios usa el enlace
`https://www.buscotuplan.cl/?cta=wa`: el botón de WhatsApp pasa a ser la acción
principal. Quien entre así queda marcado como variante `whatsapp` (visible en la
ficha del panel y en el CSV), para comparar qué versión trae más conversión.

**Atribución de WhatsApp** — funciona sola: no hay nada que configurar. El
servidor entrega un *ticket* al guardar el prospecto, y si esa persona luego hace
clic en el botón de WhatsApp, se anota en su ficha con la campaña. El ticket está
firmado con `TICKET_SECRET` (en `config.php`): si quieres, cámbialo por cualquier
texto aleatorio.

---

## 5. Publicar en Hostinger

El dominio `buscotuplan.cl` está en **Hostinger** (atrás de Cloudflare). Pasos:

1. **Crear la base de datos** en hPanel → *Bases de datos* → *MySQL*.
   Anota: nombre de BD, usuario, contraseña y host.
2. **Importar** `sql/schema.sql` con phpMyAdmin de hPanel.
3. **Editar** `config.php`:
   - cambiar `APP_ENV` a `'production'`
   - rellenar `DB_NAME`, `DB_USER`, `DB_PASS` con los de hPanel
   - confirmar `SITE_URL` (`https://www.buscotuplan.cl`)
4. **Subir los archivos** por el Administrador de Archivos o FTP a `public_html`:
   ```
   index.html
   privacidad.html
   robots.txt
   sitemap.xml
   config.php
   .htaccess
   admin/
   api/
   assets/
   guias/
   sql/
   ```
5. **Cambiar la clave del panel** (`ADMIN_USER` y `ADMIN_PASS_HASH` de `config.php`)
   antes de publicar: el panel quedará visible en `buscotuplan.cl/admin/`.
6. **Probar**: enviar un formulario desde el celular y verificar que aparece en `leads`,
   y entrar al panel desde el celular.
7. **Activar HTTPS** desde hPanel (gratis con Let's Encrypt) y descomentar la
   regla HTTP→HTTPS de `.htaccess`.
8. Mantener el dominio apuntando a Hostinger (Cloudflare puede quedarse delante).

> ⚠️ **Acceso al hosting:** por ahora no se sabe quién administra hPanel
> (puede estar a nombre de tu hermana o de quien hizo la página original).
> Es el paso que falta resolver antes de publicar.

---

## 6. Pendientes que DEBE resolver tu hermana antes de publicar

1. **Testimonios reales.** En `index.html` hay 3 tarjetas con texto de ejemplo y
   una franja naranja que dice *"Testimonio de ejemplo"*. Deben reemplazarse por
   testimonios reales o **eliminar toda la sección** (inventarlos puede infringir
   la normativa de publicidad).
2. **Redes sociales.** Los iconos del pie apuntan a `#`. Falta poner las URLs reales
   de YouTube, TikTok, Facebook e Instagram.
3. **Revisar los plazos.** El texto original dice *"te contactamos en menos de
   1 hora"* — en esta versión se evitó prometer plazos concretos, pero si quiere
   prometerlos hay que verificarlos.
4. **Revisión legal del texto de privacidad.** Es una declaración general, no
   asesoría jurídica. Con la nueva ley de datos personales conviene validarlo.
5. **Datos de contacto.** Se mantuvo dirección y teléfono del sitio actual.
   Confirmar que siguen vigentes.

---

## 7. Buenas prácticas del proyecto

- **Versionado de estáticos:** CSS y JS se cargan con `?v=20261020g`.
  Si cambias esos archivos, **sube ese número** en las 8 páginas
  (`index.html`, `privacidad.html` y las 6 de `guias/`); si no, el navegador
  mostrará la versión vieja (nos pasó dos veces al desarrollar).
- **Zona horaria:** `config.php` fija `America/Santiago` en PHP y `btp_conectar()`
  le pide a MySQL la misma hora. Sin eso, el contenedor corre en UTC y las fechas
  quedan 3 horas corridas (y en un hosting con MySQL en UTC, peor). Todas las
  horas del panel y de la base son horas chilenas.
- **Antispam actual:** honeypot + tiempo mínimo de envío + límite de 8 envíos/hora
  por IP + duplicados cada 10 min. Si llegan bots, se puede añadir
  **Cloudflare Turnstile** (gratis) en `api/leads.php`.
- **Backups:** exportar la tabla `leads` con regularidad.
- **Sin dependencias:** no hay Node, npm ni frameworks. Todo es HTML/CSS/JS y PHP.
- **Guías nuevas:** se crean copiando cualquier archivo de `guias/` y cambiando
  título, descripción, `canonical`, contenido y el `origen` del formulario
  (así el panel dice de qué página vino cada prospecto). No olvidar agregarla a
  `sitemap.xml` y a la sección de guías de `index.html`.

---

## 8. Seguridad incluida

- `config.php`, `*.sql` y `*.log` **bloqueados** por `.htaccess` (probado: responde 403).
- Consultas SQL **siempre con prepared statements** (sin inyección).
- Entradas sanitizadas y validadas **también en el servidor**, no solo en el navegador.
- Cabeceras: `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`.
- Usuario de MySQL con **permisos mínimos** sobre una sola base.
- Panel `/admin/`: login con `password_hash`, token **CSRF** en todos los formularios,
  **6 intentos fallidos por IP cada 15 minutos**, cookie de sesión `HttpOnly`,
  `noindex` y sin listado de archivos.
# buscotuplan
