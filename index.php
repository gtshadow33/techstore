<?php
require_once __DIR__ . '/db.php';

$db = getDB();

$search   = isset($_GET['search'])   ? strtolower(trim($_GET['search'])) : '';
$category = isset($_GET['category']) ? trim($_GET['category'])           : '';

// Categorías únicas desde la tabla categorias
$categories = $db->query("SELECT id, nombre FROM categorias ORDER BY nombre")
                 ->fetchAll(PDO::FETCH_ASSOC);

// Productos filtrados con JOIN a categorias
$sql    = "SELECT p.*, c.nombre AS category 
           FROM products p 
           JOIN categorias c ON p.category_id = c.id 
           WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql     .= " AND (LOWER(p.name) LIKE :s OR LOWER(p.description) LIKE :s2)";
    $params[':s']  = '%' . $search . '%';
    $params[':s2'] = '%' . $search . '%';
}
if ($category !== '') {
    $sql     .= " AND c.nombre = :cat";
    $params[':cat'] = $category;
}
$sql .= " ORDER BY p.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$filtered = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TechStore — Electrónica</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="./style.css">
</head>
<body>

<header>
  <div class="header-inner">
    <a href="index.php" class="logo">Tech<span>Store</span></a>
    <nav>
      <a href="index.php">Tienda</a>
      <a href="admin/login.php">Admin</a>
      <a href="user_acount/login.php">Cuenta</a>
    </nav>
    <button class="cart-btn" onclick="toggleCart()">
      🛒 Carrito
      <span class="cart-count" id="cartCount">0</span>
    </button>
  </div>
</header>

<section class="hero">
  <div class="hero-tag">NUEVA COLECCIÓN 2026</div>
  <h1>Tech para los<br><em>que saben.</em></h1>
  <p>Electrónica de alta gama. Sin rodeos, sin letra pequeña.</p>
</section>

<div class="controls">
  <form class="search-wrap" method="GET">
    <input type="text" name="search" placeholder="Buscar productos..." value="<?= htmlspecialchars($search) ?>">
    <?php if ($category): ?><input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>"><?php endif; ?>
  </form>
  <div class="filter-pills">
    <a href="index.php<?= $search ? '?search='.urlencode($search) : '' ?>" 
       class="pill <?= $category==='' ? 'active' : '' ?>">Todo</a>
    <?php foreach ($categories as $cat): ?>
      <a href="?category=<?= urlencode($cat['nombre']) ?><?= $search ? '&search='.urlencode($search) : '' ?>" 
         class="pill <?= $category===$cat['nombre'] ? 'active' : '' ?>">
        <?= htmlspecialchars($cat['nombre']) ?>
      </a>
    <?php endforeach; ?>
</div>
</div>

<div class="product-grid">
    <?php foreach ($filtered as $p): ?>
    <div class="product-card">
        <a href="/product/product.php?id=<?= $p['id'] ?>">
            <img class="product-img" src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
            <div class="product-body">
                <div class="product-cat"><?= htmlspecialchars($p['category']) ?></div>
                <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
                <div class="product-desc"><?= htmlspecialchars($p['description']) ?></div>
                <div class="product-footer">
                    <div class="product-price"><span>€</span><?= number_format($p['price'], 2) ?></div>
                    <div class="stock-badge <?= $p['stock'] > 10 ? 'stock-ok' : 'stock-low' ?>">
                        <?= $p['stock'] > 0 ? 'Stock: ' . $p['stock'] : 'Agotado' ?>
                    </div>
                </div>
            </div>
        </a>
        <button class="add-btn" onclick='addToCart(<?= json_encode($p) ?>)' <?= $p['stock'] == 0 ? 'disabled' : '' ?>>
            <?= $p['stock'] > 0 ? '+ Añadir al carrito' : 'Sin stock' ?>
        </button>
    </div>
    <?php endforeach; ?>
</div>

<!-- CART -->
<div class="cart-overlay" id="cartOverlay" onclick="toggleCart()"></div>
<div class="cart-panel" id="cartPanel">
  <div class="cart-header">
    <h2>🛒 Tu carrito</h2>
    <button class="close-btn" onclick="toggleCart()">✕</button>
  </div>
  <div class="cart-items" id="cartItems"></div>
  <div class="cart-footer" id="cartFooter" style="display:none">
  <div class="cart-total">Total <span id="cartTotal">€0.00</span></div>
 
  <input  type="email"    id="email" require  placeholder="Tu correo electrónico" style="width:100%;padding:10px;margin-bottom:8px;">
  <input  type="password" id="password" require placeholder="Contraseña (nueva o existente)" style="width:100%;padding:10px;margin-bottom:4px;">
  <input  type="text" id="direccion" require placeholder="Direcion de envio" style="width:100%;padding:10px;margin-bottom:4px;">
  <p id="cart-hint" style="font-size:.75rem;color:var(--muted,#888);margin:0 0 10px">
    Si ya tienes cuenta introduce tu contraseña. Si no, se creará automáticamente.
  </p>
 
  <div id="cart-error" style="display:none;color:#ff6b6b;font-size:.8rem;margin-bottom:8px;padding:6px 10px;border:1px solid #ff6b6b;border-radius:4px"></div>
 
  <button class="checkout-btn" onclick="checkout()">Proceder al pago →</button>
</div>
</div>

<div class="toast" id="toast"></div>

<footer>
  <p>TechStore © 2026 · <a href="admin/login.php">Panel Admin</a></p>
</footer>

<script src="/js/cart.js"></script>

</body>
</html>
