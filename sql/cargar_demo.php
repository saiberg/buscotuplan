<?php
/**
 * BuscoTuPlan — carga los datos de demostración en el panel.
 *
 * USO (solo desde línea de comandos, nunca desde el navegador):
 *
 *   docker exec -w /var/www/html buscotuplan-web php sql/cargar_demo.php
 *
 * Borra lo que haya en `leads` antes de cargar. Si quieres conservar los
 * prospectos reales, no corras este script.
 */

// Protección: este archivo jamás debe ejecutarse vía HTTP.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta desde la línea de comandos.');
}

require dirname(__DIR__) . '/config.php';

$pdo = btp_conectar();

$archivo = __DIR__ . '/datos_demo.sql';
if (!is_file($archivo)) {
    fwrite(STDERR, "No existe el archivo: $archivo\n");
    exit(1);
}

/**
 * Divide un script SQL en frases, ignorando los ';' que están dentro de
 * comillas simples y borrando comentarios de línea (-- ...).
 */
function partir_sql($sql)
{
    $frases = array();
    $actual = '';
    $en_cadena = false;
    $len = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];

        if ($en_cadena) {
            $actual .= $c;
            if ($c === "'") {
                // '' dentro de una cadena es una comilla escapada
                if ($i + 1 < $len && $sql[$i + 1] === "'") {
                    $actual .= $sql[$i + 1];
                    $i++;
                } else {
                    $en_cadena = false;
                }
            }
            continue;
        }

        if ($c === "'") {                 // comienza una cadena
            $en_cadena = true;
            $actual .= $c;
            continue;
        }
        if ($c === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {   // comentario
            while ($i < $len && $sql[$i] !== "\n") { $i++; }
            $actual .= "\n";
            continue;
        }
        if ($c === ';') {                 // fin de frase
            $frases[] = $actual;
            $actual = '';
            continue;
        }
        $actual .= $c;
    }

    if (trim($actual) !== '') { $frases[] = $actual; }
    return $frases;
}

// Aviso de lo que se va a borrar
$existe = (int) $pdo->query('SELECT COUNT(*) FROM leads')->fetchColumn();
if ($existe > 0) {
    echo "ATENCION: hay $existe prospectos en la base. Se van a BORRAR todos\n";
    echo "para cargar los datos de demostracion.\n";
    echo "Escribe 'si' para confirmar: ";
    $respuesta = trim(fgets(STDIN));
    if (strtolower($respuesta) !== 'si') {
        echo "Cancelado. No se toco nada.\n";
        exit(0);
    }
}

$pdo->exec('DELETE FROM lead_activities');
$pdo->exec('DELETE FROM leads');

// El archivo se parte respetando las comillas: los user_agent traen
// punto y coma dentro ('iPhone; CPU iPhone OS...'), así que un explode
// simple rompería las frases.
$sql = file_get_contents($archivo);
$instrucciones = partir_sql($sql);

$errores = 0;
foreach ($instrucciones as $sentencia) {
    if (trim($sentencia) === '') { continue; }
    try {
        $pdo->exec($sentencia);
    } catch (PDOException $e) {
        $errores++;
        fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
        fwrite(STDERR, "Frase: " . substr(trim($sentencia), 0, 80) . "...\n");
    }
}

$leads = (int) $pdo->query('SELECT COUNT(*) FROM leads')->fetchColumn();
$acts  = (int) $pdo->query('SELECT COUNT(*) FROM lead_activities')->fetchColumn();

echo "\nListo.\n";
echo "  Prospectos cargados: $leads\n";
echo "  Interacciones:       $acts\n";
echo "  Errores:             $errores\n";
echo "\nEntra al panel: http://localhost:8000/admin/\n";
