<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: user_login.php');
    exit;
}

require_once __DIR__ . '/db.php';
$db = getDB();

$uid = (int)$_SESSION['user_id'];

// ─── LOGOUT ───────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: user_login.php');
    exit;
}

// ─── CARGAR PEDIDOS DEL USUARIO ───────────────────────────────────
$orders = $db->prepare("
    SELECT p.id, p.quantity, p.price, p.direccion, p.estado, p.created_at,
           pr.name AS product_name, pr.image AS product_image, pr.category
    FROM pedidos p
    LEFT JOIN products pr ON pr.id = p.product_id
    WHERE p.usuario_id = ?
    ORDER BY p.id DESC
");
$orders->execute([$uid]);
$orders = $orders->fetchAll(PDO::FETCH_ASSOC);

// ─── STATS DEL USUARIO ────────────────────────────────────────────
$totalPedidos  = count($orders);
$totalGastado  = array_sum(array_map(fn($o) => $o['price'] * $o['quantity'], $orders));
$pendientes    = count(array_filter($orders, fn($o) => $o['estado'] === 'pendiente'));
$entregados    = count(array_filter($orders, fn($o) => $o['estado'] === 'entregado'));

// Badge colors por estado
$estadoClass = [
    'pendiente'  => 'badge-pendiente',
    'procesando' => 'badge-procesando',
    'enviado'    => 'badge-enviado',
    'entregado'  => 'badge-entregado',
    'cancelado'  => 'badge-cancelado',
];
$estadoLabel = [
    'pendiente'  => '⏳ Pendiente',
    'procesando' => '⚙️ Procesando',
    'enviado'    => '🚚 Enviado',
    'entregado'  => '✅ Entregado',
    'cancelado'  => '✕ Cancelado',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi cuenta — TechStore</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="./user.css">
</head>
<body class="dash-body">

<!-- TOPBAR -->
<header class="dash-topbar">
  <a href="index.php" class="logo">⚡Tech<span>Store</span></a>
  <div class="dash-topbar-center">
    <span class="topbar-tag">MI CUENTA</span>
  </div>
  <div class="dash-topbar-right">
    <span class="user-greeting">Hola, <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong></span>
    <a href="index.php" class="topbar-link">← Tienda</a>
    <a href="?logout=1" class="logout-btn">Salir</a>
  </div>
</header>

<div class="dash-main">

  <!-- HERO DE USUARIO -->
  <div class="user-hero">
    <div class="user-avatar"><?= strtoupper(substr($_SESSION['user_name'], 0, 2)) ?></div>
    <div class="user-info">
      <h1><?= htmlspecialchars($_SESSION['user_name']) ?></h1>
      <p><?= htmlspecialchars($_SESSION['user_email']) ?></p>
    </div>
  </div>

  <!-- STATS -->
  <div class="user-stats">
    <div class="ustat-card">
      <div class="ustat-label">Pedidos totales</div>
      <div class="ustat-value accent"><?= $totalPedidos ?></div>
    </div>
    <div class="ustat-card">
      <div class="ustat-label">Total gastado</div>
      <div class="ustat-value success">€<?= number_format($totalGastado, 2) ?></div>
    </div>
    <div class="ustat-card">
      <div class="ustat-label">Pendientes</div>
      <div class="ustat-value warning"><?= $pendientes ?></div>
    </div>
    <div class="ustat-card">
      <div class="ustat-label">Entregados</div>
      <div class="ustat-value accent2"><?= $entregados ?></div>
    </div>
  </div>

  <!-- PEDIDOS -->
  <div class="section-header">
    <h2>Mis pedidos</h2>
    <a href="index.php" class="btn-shop">+ Seguir comprando</a>
  </div>

  <?php if (empty($orders)): ?>
    <div class="empty-state">
      <div class="empty-icon">🛒</div>
      <h3>Aún no tienes pedidos</h3>
      <p>Explora la tienda y encuentra tu próximo producto favorito.</p>
      <a href="index.php" class="auth-btn" style="display:inline-block;margin-top:1.5rem;text-decoration:none">Ir a la tienda →</a>
    </div>

  <?php else: ?>
    <div class="orders-list">
      <?php foreach ($orders as $o): ?>
      <div class="order-card">
        <div class="order-img-wrap">
          <img src="<?= htmlspecialchars($o['product_image'] ?? 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=200&q=80') ?>" alt="">
        </div>
        <div class="order-body">
          <div class="order-top">
            <div>
              <div class="order-product"><?= htmlspecialchars($o['product_name'] ?? '—') ?></div>
              <div class="order-cat"><?= htmlspecialchars($o['category'] ?? '') ?></div>
            </div>
            <span class="badge <?= $estadoClass[$o['estado']] ?? 'badge-pendiente' ?>">
              <?= $estadoLabel[$o['estado']] ?? $o['estado'] ?>
            </span>
          </div>
          <div class="order-meta">
            <div class="order-meta-item">
              <span class="meta-label">Cantidad</span>
              <span class="meta-val">×<?= $o['quantity'] ?></span>
            </div>
            <div class="order-meta-item">
              <span class="meta-label">Total</span>
              <span class="meta-val accent">€<?= number_format($o['price'] * $o['quantity'], 2) ?></span>
            </div>
            <div class="order-meta-item">
              <span class="meta-label">Dirección</span>
              <span class="meta-val"><?= htmlspecialchars($o['direccion']) ?></span>
            </div>
            <div class="order-meta-item">
              <span class="meta-label">Fecha</span>
              <span class="meta-val"><?= substr($o['created_at'], 0, 10) ?></span>
            </div>
          </div>
        </div>
        <div class="order-id">#<?= $o['id'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>
</body>
</html>