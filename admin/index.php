<?php
define('BTP_SIN_LOGIN', true);
require __DIR__ . '/_guard.php';

// ya autenticado → al panel
if (!empty($_SESSION['btp_auth'])) {
    header('Location: leads.php');
    exit;
}

$error = '';
$intento = btp_throttle('login', ADMIN_LOGIN_MAX, ADMIN_LOGIN_WINDOW);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($intento === false) {
        $error = 'Demasiados intentos. Espera unos minutos y vuelve a intentar.';
    } else {
        csrf_check();
        $user = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
        $pass = isset($_POST['clave']) ? (string) $_POST['clave'] : '';

        $ok = hash_equals(ADMIN_USER, $user) && password_verify($pass, ADMIN_PASS_HASH);
        if ($ok) {
            session_regenerate_id(true);
            $_SESSION['btp_auth'] = true;
            $_SESSION['btp_user'] = $user;
            unset($_SESSION['btp_fail']);
            header('Location: leads.php');
            exit;
        }
        btp_throttle_add('login', $intento);
        $error = 'Usuario o clave incorrectos.';
    }
}
?>
<!doctype html>
<html lang="es-CL">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="description" content="Acceso restringido al panel de prospectos de Busco Tu Plan.">
<title>Iniciar sesión · Busco Tu Plan</title>
<link rel="icon" href="../assets/img/favicon-64.png" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="../assets/css/panel.css?v=20261028a">
</head>
<body class="panel-body">

<main>
<div class="login-card">
  <svg class="login-logo" viewBox="0 0 52 30" aria-hidden="true">
    <circle cx="17" cy="15" r="11.5" fill="none" stroke="#0E7A75" stroke-width="5"/>
    <circle cx="35" cy="15" r="11.5" fill="none" stroke="#1B2B4B" stroke-width="5"/>
  </svg>
  <h1>Panel de prospectos</h1>
  <p class="login-sub">Busco Tu Plan · acceso restringido</p>

  <?php if ($error): ?>
    <div class="alerta alerta-error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <label for="usuario">Usuario</label>
    <input type="text" id="usuario" name="usuario" required autofocus
           value="<?= isset($_POST['usuario']) ? e($_POST['usuario']) : '' ?>">

    <label for="clave">Clave</label>
    <input type="password" id="clave" name="clave" required>

    <button type="submit" class="btn-panel btn-panel-verde">Entrar</button>
  </form>

  <p class="login-pie"><a href="../index.html">← Volver al sitio</a></p>
</div>
</main>

</body>
</html>
