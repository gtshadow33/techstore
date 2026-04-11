<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: user_dashboard.php');
    exit;
}

require_once __DIR__ . '/../db.php';
$db = getDB();

$error = '';
$mode  = $_POST['mode'] ?? 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($mode === 'register') {
        $name      = trim($_POST['name']      ?? '');
        $password2 = trim($_POST['password2'] ?? '');

        if (!$name || !$email || !$password || !$password2) {
            $error = 'Rellena todos los campos.';
        } elseif ($password !== $password2) {
            $error = 'Las contraseñas no coinciden.';
        } elseif (strlen($password) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres.';
        } else {
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Ya existe una cuenta con ese email.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("INSERT INTO usuarios (name, email, password) VALUES (?, ?, ?)");
                $stmt->execute([$name, $email, $hash]);
                $_SESSION['user_id']    = $db->lastInsertId();
                $_SESSION['user_name']  = $name;
                $_SESSION['user_email'] = $email;
                header('Location: user_dashboard.php');
                exit;
            }
        }
    } else {
        if (!$email || !$password) {
            $error = 'Rellena todos los campos.';
        } else {
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                header('Location: user_dashboard.php');
                exit;
            } else {
                $error = 'Email o contraseña incorrectos.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Acceder — TechStore</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="./user.css">
</head>
<body>

<div class="auth-bg">
  <div class="grid-lines"></div>
  <div class="glow glow-1"></div>
  <div class="glow glow-2"></div>
</div>

<header class="auth-header">
  <a href="index.php" class="logo">&#9889;Tech<span>Store</span></a>
</header>

<main class="auth-main">
  <div class="auth-card">

    <div class="auth-toggle">
      <button type="button" class="toggle-btn <?= $mode==='login'?'active':'' ?>" onclick="switchMode(event,'login')">Entrar</button>
      <button type="button" class="toggle-btn <?= $mode==='register'?'active':'' ?>" onclick="switchMode(event,'register')">Crear cuenta</button>
    </div>

    <div class="auth-card-header">
      <div class="auth-icon">&#9654;</div>
      <h1 id="auth-title"><?= $mode==='register' ? 'Crear cuenta' : 'Bienvenido' ?></h1>
      <p id="auth-sub"><?= $mode==='register' ? 'Registrate para empezar a comprar' : 'Accede para ver tus pedidos' ?></p>
    </div>

    <?php if ($error): ?>
      <div class="auth-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="auth-form">
      <input type="hidden" name="mode" id="mode-input" value="<?= htmlspecialchars($mode) ?>">

      <div class="field" id="field-name" style="<?= $mode==='login'?'display:none':'' ?>">
        <label for="name">Nombre</label>
        <input type="text" id="name" name="name" placeholder="Tu nombre completo"
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
      </div>

      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="tu@correo.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
      </div>

      <div class="field">
        <label for="password">Contrasena</label>
        <input type="password" id="password" name="password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required>
      </div>

      <div class="field" id="field-password2" style="<?= $mode==='login'?'display:none':'' ?>">
        <label for="password2">Repetir contrasena</label>
        <input type="password" id="password2" name="password2" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
      </div>

      <button type="submit" class="auth-btn" id="auth-submit">
        <?= $mode==='register' ? 'Crear cuenta &rarr;' : 'Entrar &rarr;' ?>
      </button>
    </form>

    <div class="auth-footer">
      <a href="index.php">&larr; Volver a la tienda</a>
    </div>

  </div>
</main>

<script>
function switchMode(e, mode) {
  document.getElementById('mode-input').value = mode;
  var reg = mode === 'register';
  document.getElementById('field-name').style.display      = reg ? 'flex' : 'none';
  document.getElementById('field-password2').style.display = reg ? 'flex' : 'none';
  document.getElementById('auth-title').textContent  = reg ? 'Crear cuenta'    : 'Bienvenido';
  document.getElementById('auth-sub').textContent    = reg ? 'Registrate para empezar a comprar' : 'Accede para ver tus pedidos';
  document.getElementById('auth-submit').textContent = reg ? 'Crear cuenta \u2192' : 'Entrar \u2192';
  document.querySelectorAll('.toggle-btn').forEach(function(b){ b.classList.remove('active'); });
  e.currentTarget.classList.add('active');
  var err = document.querySelector('.auth-error');
  if (err) err.style.display = 'none';
}
</script>

</body>
</html>