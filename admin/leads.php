<?php
/**
 * BuscoTuPlan — panel de prospectos (listado, detalle, seguimiento)
 */
require __DIR__ . '/_guard.php';
require_once dirname(__DIR__) . '/lib/notificar.php';

$estados = btp_estados();
$perPage = 25;

/* =================================================== acciones POST ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $accion   = isset($_POST['accion']) ? $_POST['accion'] : '';
    $leadId   = isset($_POST['lead_id']) ? (int) $_POST['lead_id'] : 0;
    $filtros  = isset($_POST['filtros']) && is_string($_POST['filtros']) ? $_POST['filtros'] : '';

    if ($leadId > 0) {
        $existe = db()->prepare('SELECT id FROM leads WHERE id = ?');
        $existe->execute(array($leadId));
        if (!$existe->fetchColumn()) { $leadId = 0; }
    }

    if ($leadId > 0 && $accion === 'estado' && isset($_POST['estado']) && in_array($_POST['estado'], $estados, true)) {
        $nuevo = $_POST['estado'];
        $stmt = db()->prepare('UPDATE leads SET estado = ?' .
            ($nuevo === 'contactado' || $nuevo === 'convertido' ? ', contactado_en = IFNULL(contactado_en, NOW())' : '') .
            ' WHERE id = ?');
        $stmt->execute(array($nuevo, $leadId));

        $ins = db()->prepare('INSERT INTO lead_activities (lead_id, tipo, direccion, asunto, detalle, creado_por)
                              VALUES (?, "cambio_estado", "interna", ?, ?, ?)');
        $ins->execute(array($leadId, 'Estado: ' . $nuevo, 'Cambiado desde el panel', $_SESSION['btp_user']));

        header('Location: leads.php?ver=' . $leadId . '&ok=1&' . $filtros);
        exit;
    }

    if ($leadId > 0 && $accion === 'actividad') {
        $tipo  = isset($_POST['tipo']) ? $_POST['tipo'] : 'nota';
        $dir   = isset($_POST['direccion']) ? $_POST['direccion'] : 'interna';
        $texto = isset($_POST['detalle']) ? trim($_POST['detalle']) : '';

        $tiposOk  = array('nota', 'llamada', 'whatsapp', 'email', 'cambio_estado', 'tarea');
        $dirsOk   = array('entrada', 'salida', 'interna');
        if (!in_array($tipo, $tiposOk, true))  { $tipo = 'nota'; }
        if (!in_array($dir, $dirsOk, true))    { $dir  = 'interna'; }

        if ($texto !== '') {
            $ins = db()->prepare('INSERT INTO lead_activities (lead_id, tipo, direccion, asunto, detalle, creado_por)
                                  VALUES (?, ?, ?, NULL, ?, ?)');
            $ins->execute(array($leadId, $tipo, $dir, mb_substr($texto, 0, 2000), $_SESSION['btp_user']));

            if ($tipo === 'whatsapp' || $tipo === 'llamada' || $tipo === 'email') {
                $upd = db()->prepare("UPDATE leads SET estado = IF(estado = 'nuevo', 'contactado', estado),
                                             contactado_en = IFNULL(contactado_en, NOW())
                                       WHERE id = ?");
                $upd->execute(array($leadId));
            }
        }
        header('Location: leads.php?ver=' . $leadId . '&ok=1&' . $filtros);
        exit;
    }

    // Probar que los avisos de lead nuevo llegan bien (Telegram / correo)
    if ($accion === 'avisar_prueba') {
        $resultado = btp_avisar_lead(array(
            'id'          => 'prueba',
            'nombre'      => 'Prueba desde el panel',
            'telefono'    => '+56950382768',
            'isapre'      => 'Colmena',
            'necesidad'   => 'Ahorrar en mi plan actual',
            'dispositivo' => 'movil',
            'utm'         => 'prueba / panel',
        ));
        header('Location: leads.php?aviso=' . rawurlencode($resultado ?: 'sin-canales') . '&' . $filtros);
        exit;
    }

    header('Location: leads.php');
    exit;
}

/* =================================================== filtros GET ====== */
$filtro = function ($clave, $max) {
    $v = isset($_GET[$clave]) ? (string) $_GET[$clave] : '';
    return mb_substr(trim($v), 0, $max, 'UTF-8');
};

$q       = $filtro('q', 60);
$estado  = $filtro('estado', 20);
$isapre  = $filtro('isapre', 60);
$rango   = $filtro('rango', 4);
$page    = max(1, (int) (isset($_GET['page']) ? $_GET['page'] : 1));
$verId   = (int) (isset($_GET['ver']) ? $_GET['ver'] : 0);
$ok      = isset($_GET['ok']);

if (!in_array($estado, $estados, true)) { $estado = ''; }
if (!in_array($rango, array('', 'hoy', '7', '30'), true)) { $rango = ''; }

$qs = array();
if ($q !== '')     { $qs['q'] = $q; }
if ($estado !== ''){ $qs['estado'] = $estado; }
if ($isapre !== ''){ $qs['isapre'] = $isapre; }
if ($rango !== '') { $qs['rango'] = $rango; }
$filtrosQS = http_build_query($qs);

// Resultado del botón "probar aviso"
$aviso = isset($_GET['aviso']) ? (string) $_GET['aviso'] : '';
if (strlen($aviso) > 80) { $aviso = substr($aviso, 0, 80); }

$where  = array();
$params = array();
if ($q !== '') {
    $like    = '%' . $q . '%';
    $digitos = preg_replace('/\D+/', '', $q);
    // el patrón '%%' sobre el teléfono normalizado coincide con todo, así que
    // solo se agrega esa condición cuando la búsqueda contiene dígitos
    if ($digitos !== '') {
        $where[] = '(nombre LIKE :q1 OR telefono LIKE :q2 OR telefono_normalizado LIKE :q3
                     OR isapre_actual LIKE :q4 OR necesidad LIKE :q5)';
        $params[':q3'] = '%' . $digitos . '%';
    } else {
        $where[] = '(nombre LIKE :q1 OR telefono LIKE :q2
                     OR isapre_actual LIKE :q4 OR necesidad LIKE :q5)';
    }
    $params[':q1'] = $like; $params[':q2'] = $like;
    $params[':q4'] = $like; $params[':q5'] = $like;
}
if ($estado !== '') { $where[] = 'estado = :est'; $params[':est'] = $estado; }
if ($isapre !== '') { $where[] = 'isapre_actual = :isa'; $params[':isa'] = $isapre; }
if ($rango === 'hoy') { $where[] = 'DATE(created_at) = CURDATE()'; }
elseif ($rango === '7')  { $where[] = 'created_at >= (NOW() - INTERVAL 7 DAY)'; }
elseif ($rango === '30') { $where[] = 'created_at >= (NOW() - INTERVAL 30 DAY)'; }

$wSQL = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

/* --- KPIs (sin filtros) --- */
$kpi = db()->query("SELECT COUNT(*) total,
                           COALESCE(SUM(estado = 'nuevo'),0) nuevos,
                           COALESCE(SUM(estado IN ('contactado','interesado','cotizado')),0) seguimiento,
                           COALESCE(SUM(estado = 'convertido'),0) convertidos,
                           COALESCE(SUM(DATE(created_at) = CURDATE()),0) hoy
                    FROM leads")->fetch();

/* --- total filtrado + páginas --- */
$st = db()->prepare('SELECT COUNT(*) FROM leads' . $wSQL);
$st->execute($params);
$totalFiltrado = (int) $st->fetchColumn();
$paginas = max(1, (int) ceil($totalFiltrado / $perPage));
if ($page > $paginas) { $page = $paginas; }

/* --- listado --- */
$st = db()->prepare('SELECT id, nombre, telefono, isapre_actual, necesidad, estado, origen,
                            utm_source, utm_campaign, dispositivo, ip, created_at, contactado_en
                     FROM leads' . $wSQL . ' ORDER BY created_at DESC, id DESC
                     LIMIT :lim OFFSET :des');
foreach ($params as $k => $v) { $st->bindValue($k, $v); }
$st->bindValue(':lim', $perPage, PDO::PARAM_INT);
$st->bindValue(':des', ($page - 1) * $perPage, PDO::PARAM_INT);
$st->execute();
$leads = $st->fetchAll();

/* --- isapres presentes (para el filtro) --- */
$isapresBD = db()->query("SELECT DISTINCT isapre_actual FROM leads
                           WHERE isapre_actual IS NOT NULL AND isapre_actual <> ''
                           ORDER BY isapre_actual")->fetchAll(PDO::FETCH_COLUMN);

/* --- detalle --- */
$detalle = null; $actividades = array();
if ($verId > 0) {
    $st = db()->prepare('SELECT * FROM leads WHERE id = ?');
    $st->execute(array($verId));
    $detalle = $st->fetch();
    if ($detalle) {
        $st = db()->prepare('SELECT * FROM lead_activities WHERE lead_id = ? ORDER BY created_at DESC, id DESC');
        $st->execute(array($verId));
        $actividades = $st->fetchAll();
    } else {
        $verId = 0;
    }
}

/* =================================================== helpers ========= */
function fmt_tel($t)
{
    $d = preg_replace('/\D+/', '', (string) $t);
    if (strpos($d, '56') === 0 && strlen($d) >= 11) {
        $d = substr($d, 2);
        return '+56 ' . (strlen($d) === 9 ? substr($d, 0, 1) . ' ' . substr($d, 1, 4) . ' ' . substr($d, 5)
                                           : $d);
    }
    return $t;
}
function wa_de($t) { return 'https://wa.me/' . preg_replace('/\D+/', '', (string) $t); }
function fecha_corta($f)
{
    if (!$f) return '—';
    $ts = strtotime($f);
    $mismoDia = date('Y-m-d') === date('Y-m-d', $ts);
    return $mismoDia
        ? 'Hoy ' . date('H:i', $ts)
        : date('d/m/Y', $ts) . ' ' . date('H:i', $ts);
}
$badge = array(
    'nuevo' => 'b-nuevo', 'contactado' => 'b-contactado', 'interesado' => 'b-interesado',
    'cotizado' => 'b-cotizado', 'convertido' => 'b-convertido', 'perdido' => 'b-perdido',
);
$iconoTipo = array('nota' => '📝', 'llamada' => '📞', 'whatsapp' => '💬',
                   'whatsapp_abierto' => '📲', 'email' => '✉️',
                   'cambio_estado' => '🔄', 'tarea' => '✅');
?>
<!doctype html>
<html lang="es-CL">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="description" content="Panel interno de prospectos de Busco Tu Plan.">
<title>Prospectos · Busco Tu Plan</title>
<link rel="icon" href="../assets/img/favicon-64.png" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="../assets/css/panel.css?v=20261028a">
</head>
<body class="panel-body">

<header class="panel-top">
  <div class="panel-top-in">
    <a class="panel-brand" href="leads.php">
      <svg viewBox="0 0 52 30" aria-hidden="true">
        <circle cx="17" cy="15" r="11.5" fill="none" stroke="#0E7A75" stroke-width="5"/>
        <circle cx="35" cy="15" r="11.5" fill="none" stroke="#1B2B4B" stroke-width="5"/>
      </svg>
      <span>Panel · <strong>Busco Tu Plan</strong></span>
    </a>
    <nav class="panel-acciones">
      <form method="post" class="inline-form"
            onsubmit="return confirm('Enviar un aviso de prueba por Telegram / correo?')">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="avisar_prueba">
        <button type="submit" class="btn-panel btn-panel-ghost">🔔 Probar aviso</button>
      </form>
      <a class="btn-panel btn-panel-ghost"
         href="export.php?<?= e($filtrosQS) ?>">⬇ Exportar CSV</a>
      <a class="btn-panel btn-panel-ghost" href="logout.php">Cerrar sesión</a>
    </nav>
  </div>
</header>

<main class="panel-main">

  <?php if ($ok): ?>
    <div class="alerta alerta-ok">✅ Cambio guardado.</div>
  <?php endif; ?>

  <?php if ($aviso === 'sin-canales'): ?>
    <div class="alerta alerta-error">
      ⚠️ No hay ningún canal de aviso configurado. Abre <strong>config.php</strong> y
      completa <code>NOTIFY_TELEGRAM_TOKEN</code> y <code>NOTIFY_TELEGRAM_CHAT</code>
      (o <code>NOTIFY_EMAIL</code>).
    </div>
  <?php elseif ($aviso !== ''): ?>
    <div class="alerta alerta-ok">📨 Aviso de prueba enviado — <?= e($aviso) ?></div>
  <?php endif; ?>

  <!-- ------------------------------------------------------- KPIs -->
  <section class="kpis">
    <div class="kpi"><b><?= (int) $kpi['total'] ?></b><span>Prospectos</span></div>
    <div class="kpi kpi-nuevo"><b><?= (int) $kpi['nuevos'] ?></b><span>Nuevos sin tocar</span></div>
    <div class="kpi kpi-seg"><b><?= (int) $kpi['seguimiento'] ?></b><span>En seguimiento</span></div>
    <div class="kpi kpi-conv"><b><?= (int) $kpi['convertidos'] ?></b><span>Convertidos</span></div>
    <div class="kpi kpi-hoy"><b><?= (int) $kpi['hoy'] ?></b><span>Llegaron hoy</span></div>
  </section>

  <!-- ------------------------------------------------------- detalle -->
  <?php if ($detalle): ?>
  <section class="detalle">
    <div class="detalle-head">
      <div>
        <p class="detalle-eyebrow">Prospecto #<?= (int) $detalle['id'] ?></p>
        <h2><?= e($detalle['nombre']) ?></h2>
        <p class="detalle-sub">
          📞 <a href="tel:<?= e($detalle['telefono']) ?>"><?= e(fmt_tel($detalle['telefono'])) ?></a>
          · <?= e($detalle['isapre_actual'] ?: 'Isapre no informada') ?>
          <?php if ($detalle['necesidad']): ?>· <?= e($detalle['necesidad']) ?><?php endif; ?>
        </p>
      </div>
      <div class="detalle-btns">
        <a class="btn-panel btn-panel-wa" target="_blank" rel="noopener"
           href="<?= e(wa_de($detalle['telefono'])) ?>?text=<?= rawurlencode('Hola ' . $detalle['nombre'] . ', te escribo de Busco Tu Plan por tu cotización de plan de Isapre.') ?>">
          💬 WhatsApp</a>
        <a class="btn-panel btn-panel-ghost" href="leads.php?<?= e($filtrosQS) ?>">✕ Cerrar</a>
      </div>
    </div>

    <div class="detalle-grid">
      <div class="detalle-datos">
        <dl>
          <div><dt>Estado</dt><dd>
            <form method="post" class="inline-form" onchange="this.submit()">
              <?= csrf_field() ?>
              <input type="hidden" name="accion" value="estado">
              <input type="hidden" name="lead_id" value="<?= (int) $detalle['id'] ?>">
              <input type="hidden" name="filtros" value="<?= e($filtrosQS) ?>">
              <select name="estado" aria-label="Estado del prospecto">
                <?php foreach ($estados as $s): ?>
                  <option value="<?= $s ?>" <?= $detalle['estado'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </dd></div>
          <div><dt>Llegó</dt><dd><?= e(fecha_corta($detalle['created_at'])) ?></dd></div>
          <div><dt>Primer contacto</dt><dd><?= $detalle['contactado_en'] ? e(fecha_corta($detalle['contactado_en'])) : 'Pendiente' ?></dd></div>
          <div><dt>Origen</dt><dd><?= e($detalle['origen']) ?>
            <?php if ($detalle['utm_source']): ?>
              <small class="utm"><?= e($detalle['utm_source']) ?> / <?= e($detalle['utm_medium']) ?>
                <?php if ($detalle['utm_campaign']): ?> / <?= e($detalle['utm_campaign']) ?><?php endif; ?></small>
            <?php endif; ?></dd></div>
          <div><dt>Dispositivo</dt><dd><?= e($detalle['dispositivo'] ?: '—') ?></dd></div>
          <div><dt>Variante</dt><dd><?php
            if ($detalle['variante'] === 'whatsapp') { echo 'CTA WhatsApp'; }
            elseif ($detalle['variante'] === 'formulario') { echo 'CTA formulario'; }
            else { echo '—'; }
          ?></dd></div>
          <div><dt>IP</dt><dd><?= e($detalle['ip'] ?: '—') ?></dd></div>
        </dl>

        <form method="post" class="form-actividad">
          <?= csrf_field() ?>
          <input type="hidden" name="accion" value="actividad">
          <input type="hidden" name="lead_id" value="<?= (int) $detalle['id'] ?>">
          <input type="hidden" name="filtros" value="<?= e($filtrosQS) ?>">
          <h3>Registrar interacción</h3>
          <div class="fila">
            <select name="tipo" aria-label="Tipo">
              <option value="nota">📝 Nota</option>
              <option value="llamada">📞 Llamada</option>
              <option value="whatsapp">💬 WhatsApp</option>
              <option value="email">✉️ Email</option>
              <option value="tarea">✅ Tarea</option>
            </select>
            <select name="direccion" aria-label="Dirección">
              <option value="salida">→ Salida (yo escribí)</option>
              <option value="entrada">← Entrada (me escribió)</option>
              <option value="interna">• Interna</option>
            </select>
          </div>
          <textarea name="detalle" rows="3" required
                    placeholder="Ej: Le envié comparación de planes Banmédica vs Colmena…"></textarea>
          <button type="submit" class="btn-panel btn-panel-verde">Guardar interacción</button>
        </form>
      </div>

      <div class="detalle-actividades">
        <h3>Historial</h3>
        <?php if (!$actividades): ?>
          <p class="vacio">Sin interacciones registradas todavía.</p>
        <?php else: ?>
          <ul class="timeline">
            <?php foreach ($actividades as $a): ?>
              <li class="tl-<?= e($a['tipo']) ?>">
                <span class="tl-ico" aria-hidden="true"><?= isset($iconoTipo[$a['tipo']]) ? $iconoTipo[$a['tipo']] : '📝' ?></span>
                <div>
                  <p class="tl-meta">
                    <strong><?= e(ucfirst(str_replace('_', ' ', $a['tipo']))) ?></strong>
                    · <?= e(ucfirst($a['direccion'])) ?>
                    · <?= e(fecha_corta($a['created_at'])) ?>
                    <?php if ($a['creado_por']): ?> · <?= e($a['creado_por']) ?><?php endif; ?>
                  </p>
                  <?php if ($a['asunto']): ?><p class="tl-asunto"><?= e($a['asunto']) ?></p><?php endif; ?>
                  <p class="tl-detalle"><?= nl2br(e($a['detalle'])) ?></p>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- ------------------------------------------------------- filtros -->
  <form class="filtros" method="get" action="leads.php">
    <?php if ($verId): ?><input type="hidden" name="ver" value="<?= $verId ?>"><?php endif; ?>
    <div class="filtros-campo">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre o teléfono…">
    </div>
    <div class="filtros-campo">
      <select name="estado" aria-label="Filtrar por estado" onchange="this.form.submit()">
        <option value="">Todos los estados</option>
        <?php foreach ($estados as $s): ?>
          <option value="<?= $s ?>" <?= $estado === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filtros-campo">
      <select name="isapre" aria-label="Filtrar por Isapre" onchange="this.form.submit()">
        <option value="">Todas las Isapres</option>
        <?php foreach ($isapresBD as $i): ?>
          <option value="<?= e($i) ?>" <?= $isapre === $i ? 'selected' : '' ?>><?= e($i) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filtros-campo">
      <select name="rango" aria-label="Filtrar por fecha" onchange="this.form.submit()">
        <option value="" <?= $rango === '' ? 'selected' : '' ?>>Todo el tiempo</option>
        <option value="hoy" <?= $rango === 'hoy' ? 'selected' : '' ?>>Hoy</option>
        <option value="7" <?= $rango === '7' ? 'selected' : '' ?>>Últimos 7 días</option>
        <option value="30" <?= $rango === '30' ? 'selected' : '' ?>>Últimos 30 días</option>
      </select>
    </div>
    <button type="submit" class="btn-panel btn-panel-verde">Buscar</button>
    <?php if ($filtrosQS): ?><a class="btn-panel btn-panel-ghost" href="leads.php">Limpiar</a><?php endif; ?>
  </form>

  <!-- ------------------------------------------------------- listado -->
  <section class="tabla-wrap">
    <p class="tabla-resumen">
      <?= $totalFiltrado ?> <?= $totalFiltrado === 1 ? 'prospecto' : 'prospectos' ?>
      <?php if ($filtrosQS): ?>con los filtros actuales<?php endif; ?>
    </p>

    <?php if (!$leads): ?>
      <div class="vacio-grande">
        <p><strong>Aún no hay prospectos con estos filtros.</strong></p>
        <p>Cuando alguien complete el formulario de la landing aparecerá aquí al instante.</p>
      </div>
    <?php else: ?>
      <div class="tabla-scroll">
      <table class="tabla">
        <thead>
          <tr>
            <th scope="col">#</th><th scope="col">Prospecto</th><th scope="col">Isapre</th><th scope="col">Necesidad</th>
            <th scope="col">Estado</th><th scope="col">Origen</th><th scope="col">Llegó</th><th scope="col"><span class="visually-hidden">Acciones</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($leads as $l): ?>
            <tr class="<?= $l['estado'] === 'nuevo' ? 'fila-nueva' : '' ?>">
              <td class="num"><?= (int) $l['id'] ?></td>
              <td>
                <a class="nombre" href="leads.php?ver=<?= (int) $l['id'] ?>&<?= e($filtrosQS) ?>"><?= e($l['nombre']) ?></a>
                <span class="tel"><?= e(fmt_tel($l['telefono'])) ?></span>
              </td>
              <td><?= e($l['isapre_actual'] ?: '—') ?></td>
              <td class="sutil"><?= e($l['necesidad'] ?: '—') ?></td>
              <td>
                <form method="post" class="inline-form" onchange="this.submit()">
                  <?= csrf_field() ?>
                  <input type="hidden" name="accion" value="estado">
                  <input type="hidden" name="lead_id" value="<?= (int) $l['id'] ?>">
                  <input type="hidden" name="filtros" value="<?= e($filtrosQS) ?>">
                  <select name="estado" aria-label="Estado de <?= e($l['nombre']) ?>" class="badge <?= isset($badge[$l['estado']]) ? $badge[$l['estado']] : '' ?>">
                    <?php foreach ($estados as $s): ?>
                      <option value="<?= $s ?>" <?= $l['estado'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </td>
              <td class="sutil">
                <?= e($l['origen']) ?>
                <?php if ($l['utm_source']): ?><small class="utm"><?= e($l['utm_source']) ?><?php if ($l['utm_campaign']): ?> · <?= e($l['utm_campaign']) ?><?php endif; ?></small><?php endif; ?>
              </td>
              <td class="sutil"><?= e(fecha_corta($l['created_at'])) ?></td>
              <td class="acciones">
                <a class="icono" title="Ver y dar seguimiento"
                   href="leads.php?ver=<?= (int) $l['id'] ?>&<?= e($filtrosQS) ?>">👁</a>
                <a class="icono" title="Escribir por WhatsApp" target="_blank" rel="noopener"
                   href="<?= e(wa_de($l['telefono'])) ?>?text=<?= rawurlencode('Hola ' . $l['nombre'] . ', te escribo de Busco Tu Plan por tu cotización de plan de Isapre.') ?>">💬</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>

      <?php if ($paginas > 1): ?>
        <nav class="paginacion" aria-label="Páginas">
          <?php for ($p = 1; $p <= $paginas; $p++):
              $qs['page'] = $p; ?>
            <a class="<?= $p === $page ? 'activo' : '' ?>"
               href="leads.php?<?= e(http_build_query($qs)) ?>"><?= $p ?></a>
          <?php endfor; ?>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </section>

</main>

<footer class="panel-pie">
  Busco Tu Plan · panel interno · los datos son personales, no se comparten.
</footer>

</body>
</html>
