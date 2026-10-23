<?php
/**
 * BuscoTuPlan — exportación CSV de los prospectos (respeta los filtros)
 */
require __DIR__ . '/_guard.php';

$estados = btp_estados();

$filtro = function ($clave, $max) {
    $v = isset($_GET[$clave]) ? (string) $_GET[$clave] : '';
    return mb_substr(trim($v), 0, $max, 'UTF-8');
};

$q      = $filtro('q', 60);
$estado = $filtro('estado', 20);
$isapre = $filtro('isapre', 60);
$rango  = $filtro('rango', 4);
if (!in_array($estado, $estados, true)) { $estado = ''; }
if (!in_array($rango, array('', 'hoy', '7', '30'), true)) { $rango = ''; }

$where  = array();
$params = array();
if ($q !== '') {
    $like    = '%' . $q . '%';
    $digitos = preg_replace('/\D+/', '', $q);
    // igual que en el listado: '%%' coincidiría con todo, solo se usa con dígitos
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

$sql = 'SELECT id, nombre, telefono, isapre_actual, necesidad, estado, origen,
               utm_source, utm_medium, utm_campaign, utm_content, utm_term, variante,
               referrer, dispositivo, ip, contactado_en, created_at
        FROM leads' . ($where ? (' WHERE ' . implode(' AND ', $where)) : '') .
        ' ORDER BY created_at DESC, id DESC LIMIT 10000';

$st = db()->prepare($sql);
$st->execute($params);
$filas = $st->fetchAll();

$nombreArchivo = 'prospectos_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Pragma: no-cache');
header('Expires: 0');

$salida = fopen('php://output', 'w');
// BOM para que Excel abra bien los acentos
fwrite($salida, "\xEF\xBB\xBF");

$campos = array('id', 'nombre', 'telefono', 'isapre', 'necesidad', 'estado', 'origen',
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
                'variante', 'referrer', 'dispositivo', 'ip', 'primer_contacto', 'registrado');
fputcsv($salida, $campos);

foreach ($filas as $f) {
    fputcsv($salida, array(
        $f['id'], $f['nombre'], $f['telefono'], $f['isapre_actual'], $f['necesidad'],
        $f['estado'], $f['origen'], $f['utm_source'], $f['utm_medium'], $f['utm_campaign'],
        $f['utm_content'], $f['utm_term'], $f['variante'], $f['referrer'], $f['dispositivo'], $f['ip'],
        $f['contactado_en'], $f['created_at'],
    ));
}
fclose($salida);
exit;
