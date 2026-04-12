<?php
require_once __DIR__ . '/../db.php';
$db = getDB();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header('Location: index.php'); exit; }

$stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$p) { header('Location: index.php'); exit; }

$rel = $db->prepare("SELECT * FROM products WHERE category = ? AND id != ? ORDER BY id DESC LIMIT 4");
$rel->execute([$p['category'], $id]);
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

<a href="index.php" class="back-link">← Volver a la tienda</a>

<!-- DETALLE PRODUCTO -->
<div class="product-detail">

  <div class="detail-img-wrap">
    <img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
  </div>

  <div class="detail-info">
    <div class="detail-breadcrumb">
      <a href="index.php">Tienda</a> /
      <a href="index.php?category=<?= urlencode($p['category']) ?>"><?= htmlspecialchars($p['category']) ?></a> /
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
  <p>TechStore © 2026 · <a href="admin/login.php">Panel Admin</a></p>
</footer>

<script>
let cart     = JSON.parse(localStorage.getItem('cart') || '[]');
let detailQty = 1;
const maxStock = <?= (int)$p['stock'] ?>;

function saveCart() { localStorage.setItem('cart', JSON.stringify(cart)); }

function changeDetailQty(delta) {
  detailQty = Math.max(1, Math.min(maxStock, detailQty + delta));
  document.getElementById('detailQty').textContent = detailQty;
}

function addToCartQty(product) {
  const i = cart.findIndex(x => x.id === product.id);
  if (i > -1) cart[i].qty += detailQty;
  else cart.push({ ...product, qty: detailQty });
  saveCart();
  renderCart();
  showToast('✓ ' + detailQty + '× ' + product.name + ' añadido');
  detailQty = 1;
  document.getElementById('detailQty').textContent = 1;
}

function removeFromCart(id) {
  cart = cart.filter(x => x.id !== id);
  saveCart(); renderCart();
}

function changeQty(id, delta) {
  const i = cart.findIndex(x => x.id === id);
  if (i === -1) return;
  cart[i].qty += delta;
  if (cart[i].qty <= 0) cart.splice(i, 1);
  saveCart(); renderCart();
}

function renderCart() {
  const el     = document.getElementById('cartItems');
  const footer = document.getElementById('cartFooter');
  const count  = cart.reduce((s, x) => s + x.qty, 0);
  document.getElementById('cartCount').textContent = count;

  if (cart.length === 0) {
    el.innerHTML = '<div class="empty-cart"><span class="icon">🛒</span><p>El carrito está vacío</p></div>';
    footer.style.display = 'none';
    return;
  }

  footer.style.display = 'block';
  el.innerHTML = cart.map(p => `
    <div class="cart-item">
      <img src="${p.image}" alt="${p.name}">
      <div class="cart-item-info">
        <div class="cart-item-name">${p.name}</div>
        <div class="cart-item-price">€${(p.price * p.qty).toFixed(2)}</div>
        <div class="cart-qty">
          <button class="qty-btn" onclick="changeQty(${p.id}, -1)">−</button>
          <span class="qty-val">${p.qty}</span>
          <button class="qty-btn" onclick="changeQty(${p.id}, 1)">+</button>
        </div>
      </div>
      <button class="remove-btn" onclick="removeFromCart(${p.id})">🗑</button>
    </div>
  `).join('');

  const total = cart.reduce((s, x) => s + x.price * x.qty, 0);
  document.getElementById('cartTotal').textContent = '€' + total.toFixed(2);
}

function toggleCart() {
  document.getElementById('cartOverlay').classList.toggle('open');
  document.getElementById('cartPanel').classList.toggle('open');
}

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2500);
}

function checkout() {
  const email     = document.getElementById('email').value.trim();
  const password  = document.getElementById('password').value.trim();
  const direccion = document.getElementById('direccion').value.trim();
  const errEl     = document.getElementById('cart-error');
  errEl.style.display = 'none';

  if (!email)     { showToast('Introduce tu correo'); return; }
  if (!password)  { showToast('Introduce tu contraseña'); return; }
  if (!direccion) { showToast('Introduce tu dirección'); return; }
  if (cart.length === 0) return;

  fetch('checkout.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ cart, email, password, direccion })
  })
  .then(r => r.json())
  .then(data => {
    if (data.ok) {
      cart = []; saveCart(); renderCart();
      document.getElementById('email').value     = '';
      document.getElementById('password').value  = '';
      document.getElementById('direccion').value = '';
      showToast('✔ Pedido realizado');
    } else {
      errEl.textContent    = data.error || 'Error en el pedido';
      errEl.style.display  = 'block';
    }
  })
  .catch(() => {
    errEl.textContent   = 'Error de conexión';
    errEl.style.display = 'block';
  });
}

renderCart();
</script>
</body>
</html>