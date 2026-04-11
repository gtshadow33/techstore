<?php
session_start();
if (isset($_SESSION['admin'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = getDB()->prepare("SELECT * FROM admins WHERE username = :u LIMIT 1");
    $stmt->execute([':u' => $username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin']    = true;
        $_SESSION['username'] = $admin['username'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Credenciales incorrectas';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — TechStore</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="./login.css">
</head>
<body>
<div class="box">
  <span class="logo">⚡Tech<span>Store</span></span>
  <span class="sub">// panel de administración</span>
  <form method="POST">
    <label>Usuario</label>
    <input type="text" name="username" placeholder="admin" autocomplete="username">
    <label>Contraseña</label>
    <input type="password" name="password" placeholder="••••••••" autocomplete="current-password">
    <?php if ($error): ?>
      <div class="error">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <button type="submit">Entrar →</button>
  </form>
  <a class="back" href="../index.php">← Volver a la tienda</a>
</div>
</body>
</html>
