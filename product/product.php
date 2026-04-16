<?php
require_once __DIR__ . '/../db.php';
$db = getDB();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header('Location: index.php'); exit; }

$stmt = $db->prepare("
  SELECT p.*, c.nombre AS category 
  FROM products p
  JOIN categorias c ON p.category_id = c.id
  WHERE p.id = ?
");
$stmt->execute([$id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$p) { header('Location: index.php'); exit; }

$rel = $db->prepare("SELECT * FROM products WHERE category_id = ? AND id != ? ORDER BY id DESC LIMIT 4");
$rel->execute([$p['category_id'], $id]);
$related = $rel->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($p['name']) ?> — TechStore</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./product.css">
</head>
<body>

<!-- HEADER -->
<header>
  <div class="header-inner">
    <a href="/index.php" class="logo">Tech<span>Store</span></a>
    <nav>
      <a href="/index.php">Tienda</a>
      <a href="/admin/login.php">Admin</a>
      <a href="/user_acount/login.php">Cuenta</a>
    </nav>
    <button class="cart-btn" onclick="toggleCart()">
      🛒 Carrito
      <span class="cart-count" id="cartCount">0</span>
    </button>
  </div>
</header>

<a href="/index.php" class="back-link">← Volver a la tienda</a>

<!-- DETALLE PRODUCTO -->
<div class="product-detail">

  <div class="detail-img-wrap">
    <img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
  </div>

  <div class="detail-info">
    <div class="detail-breadcrumb">
      <a href="/index.php">Tienda</a> /
      <a href="/index.php?category=<?= urlencode($p['category']) ?>"><?= htmlspecialchars($p['category']) ?></a> /
      <span><?= htmlspecialchars($p['name']) ?></span>
    </div>

    <div class="detail-cat"><?= htmlspecialchars($p['category']) ?></div>
    <h1 class="detail-name"><?= htmlspecialchars($p['name']) ?></h1>

    <div class="detail-price">
      <span>€</span><?= number_format($p['price'], 2) ?>
    </div>

    <div class="detail-stock <?= $p['stock'] > 10 ? 'ok' : 'low' ?>">
      <?php if ($p['stock'] > 10): ?>
        ✓ En stock (<?= $p['stock'] ?> unidades)
      <?php elseif ($p['stock'] > 0): ?>
        ⚠ Últimas unidades (<?= $p['stock'] ?> disponibles)
      <?php else: ?>
        ✕ Agotado
      <?php endif; ?>
    </div>

    <hr class="detail-divider">

    <div class="detail-desc-label">Descripción</div>
    <p class="detail-desc"><?= htmlspecialchars($p['description']) ?></p>

    <?php if ($p['stock'] > 0): ?>
    <div class="qty-row">
      <label>Cantidad</label>
      <div class="qty-ctrl">
        <button onclick="changeDetailQty(-1)">−</button>
        <span id="detailQty">1</span>
        <button onclick="changeDetailQty(1)">+</button>
      </div>
    </div>
    <?php endif; ?>

    <button class="detail-add-btn"
      <?= $p['stock'] == 0 ? 'disabled' : '' ?>
      onclick='addToCartQty(<?= json_encode($p) ?>)'>
      <?= $p['stock'] > 0 ? '+ Añadir al carrito' : 'Sin stock' ?>
    </button>
  </div>
</div>

<!-- RELACIONADOS -->
<?php if (!empty($related)): ?>
<div class="related-section">
  <div class="related-title">// También en <?= htmlspecialchars($p['category']) ?></div>
  <div class="related-grid">
    <?php foreach ($related as $r): ?>
    <a href="product.php?id=<?= $r['id'] ?>" class="related-card">
      <img src="<?= htmlspecialchars($r['image']) ?>" alt="<?= htmlspecialchars($r['name']) ?>">
      <div class="related-card-body">
        <div class="related-card-name"><?= htmlspecialchars($r['name']) ?></div>
        <div class="related-card-price">€<?= number_format($r['price'], 2) ?></div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- CART PANEL -->
<div class="cart-overlay" id="cartOverlay" onclick="toggleCart()"></div>
<div class="cart-panel" id="cartPanel">
  <div class="cart-header">
    <h2>🛒 Tu carrito</h2>
    <button class="close-btn" onclick="toggleCart()">✕</button>
  </div>
  <div class="cart-items" id="cartItems"></div>
  <div class="cart-footer" id="cartFooter" style="display:none">
    <div class="cart-total">Total <span id="cartTotal">€0.00</span></div>
    <input type="email"    id="email"     placeholder="Tu correo electrónico">
    <input type="password" id="password"  placeholder="Contraseña">
    <input type="text"     id="direccion" placeholder="Dirección de envío">
    <p>Si ya tienes cuenta introduce tu contraseña. Si no, se creará automáticamente.</p>
    <div id="cart-error"></div>
    <button class="checkout-btn" onclick="checkout()">Proceder al pago →</button>
  </div>
</div>

<div class="toast" id="toast"></div>

<footer>
  <p>TechStore © 2026 · <a href="/admin/login.php">Panel Admin</a></p>
</footer>
<script src="../js/cart.js"></script>

</body>
</html>