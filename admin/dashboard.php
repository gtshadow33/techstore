<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';
$db = getDB();

$msg = ''; $msgType = '';

// ─── PRODUCTOS: DELETE ────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM products WHERE id = :id");
    $stmt->execute([':id' => $id]);
    header("Location: dashboard.php?tab=products&msg=deleted");
    exit;
}

// ─── PRODUCTOS: ADD / EDIT ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // --- PRODUCTO ---
    if (in_array($_POST['action'], ['add_product', 'edit_product'])) {
        $name        = trim($_POST['name']        ?? '');
        $price       = (float)($_POST['price']    ?? 0);
        $category    = trim($_POST['category']    ?? '');
        $description = trim($_POST['description'] ?? '');
        $image       = trim($_POST['image']       ?? '');
        $stock       = (int)($_POST['stock']      ?? 0);
        if (!$image) $image = 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=400&q=80';

        if ($_POST['action'] === 'add_product') {
            $stmt = $db->prepare("INSERT INTO products (name, price, category, description, image, stock)
                                  VALUES (:name, :price, :category, :description, :image, :stock)");
            $stmt->execute(compact('name','price','category','description','image','stock'));
            header("Location: dashboard.php?tab=products&msg=added"); exit;
        }
        if ($_POST['action'] === 'edit_product') {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("UPDATE products SET name=:name, price=:price, category=:category,
                                  description=:description, image=:image, stock=:stock WHERE id=:id");
            $stmt->execute(compact('name','price','category','description','image','stock','id'));
            header("Location: dashboard.php?tab=products&msg=edited"); exit;
        }
    }

    // --- USUARIO ---
    if (in_array($_POST['action'], ['add_user', 'edit_user'])) {
        $uname  = trim($_POST['uname']  ?? '');
        $email  = trim($_POST['email']  ?? '');
        $upass  = trim($_POST['upass']  ?? '');

        if ($_POST['action'] === 'add_user') {
            if (!$upass) { header("Location: dashboard.php?tab=users&msg=no_pass"); exit; }
            $hash = password_hash($upass, PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO usuarios (name, email, password) VALUES (:name, :email, :password)");
            $stmt->execute([':name' => $uname, ':email' => $email, ':password' => $hash]);
            header("Location: dashboard.php?tab=users&msg=user_added"); exit;
        }
        if ($_POST['action'] === 'edit_user') {
            $uid = (int)$_POST['uid'];
            if ($upass) {
                $hash = password_hash($upass, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE usuarios SET name=:name, email=:email, password=:password WHERE id=:id");
                $stmt->execute([':name' => $uname, ':email' => $email, ':password' => $hash, ':id' => $uid]);
            } else {
                $stmt = $db->prepare("UPDATE usuarios SET name=:name, email=:email WHERE id=:id");
                $stmt->execute([':name' => $uname, ':email' => $email, ':id' => $uid]);
            }
            header("Location: dashboard.php?tab=users&msg=user_edited"); exit;
        }
    }

    // --- USUARIO: DELETE ---
    if ($_POST['action'] === 'delete_user') {
        $uid = (int)$_POST['uid'];
        $stmt = $db->prepare("DELETE FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $uid]);
        header("Location: dashboard.php?tab=users&msg=user_deleted"); exit;
    }

    // --- PEDIDO: CAMBIAR ESTADO (aplica a todos los del carrito) ---
    if ($_POST['action'] === 'update_order_status') {
        $cart_key = $_POST['cart_key'] ?? '';
        $estado   = $_POST['estado']   ?? 'pendiente';
        $ids      = json_decode($_POST['cart_ids'] ?? '[]', true);
        $allowed  = ['pendiente','procesando','enviado','entregado','cancelado'];
        if (in_array($estado, $allowed) && !empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare("UPDATE pedidos SET estado=? WHERE id IN ($placeholders)");
            $stmt->execute(array_merge([$estado], $ids));
        }
        header("Location: dashboard.php?tab=orders&msg=order_updated"); exit;
    }

    // --- PEDIDO: DELETE ---
    if ($_POST['action'] === 'delete_order') {
        $oid = (int)$_POST['oid'];
        $stmt = $db->prepare("DELETE FROM pedidos WHERE id = :id");
        $stmt->execute([':id' => $oid]);
        header("Location: dashboard.php?tab=orders&msg=order_deleted"); exit;
    }

    // --- CARRITO COMPLETO: DELETE ---
    if ($_POST['action'] === 'delete_cart') {
        $ids = json_decode($_POST['cart_ids'] ?? '[]', true);
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare("DELETE FROM pedidos WHERE id IN ($placeholders)");
            $stmt->execute($ids);
        }
        header("Location: dashboard.php?tab=orders&msg=order_deleted"); exit;
    }
}

// ─── CARGAR DATOS ────────────────────────────────────────────────
$products = $db->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();
$users    = $db->query("SELECT id, name, email, created_at FROM usuarios ORDER BY id DESC")->fetchAll();
$orders   = $db->query("
    SELECT p.id, p.quantity, p.price, p.direccion, p.estado, p.created_at,
           u.id AS usuario_id,
           u.name AS user_name, u.email AS user_email,
           pr.name AS product_name,
           DATE(p.created_at) AS order_date
    FROM pedidos p
    LEFT JOIN usuarios u  ON u.id  = p.usuario_id
    LEFT JOIN products pr ON pr.id = p.product_id
    ORDER BY p.id DESC
")->fetchAll();

// ─── AGRUPAR PEDIDOS POR USUARIO + FECHA (mismo carrito) ─────────
$grouped_orders = [];
foreach ($orders as $o) {
    $key = ($o['usuario_id'] ?? 'guest') . '_' . $o['order_date'];
    if (!isset($grouped_orders[$key])) {
        $grouped_orders[$key] = [
            'key'        => $key,
            'usuario_id' => $o['usuario_id'],
            'user_name'  => $o['user_name'],
            'user_email' => $o['user_email'],
            'direccion'  => $o['direccion'],
            'estado'     => $o['estado'],
            'created_at' => $o['created_at'],
            'order_date' => $o['order_date'],
            'items'      => [],
            'total'      => 0,
        ];
    }
    $grouped_orders[$key]['items'][] = [
        'id'           => $o['id'],
        'product_name' => $o['product_name'],
        'quantity'     => $o['quantity'],
        'price'        => $o['price'],
    ];
    $grouped_orders[$key]['total'] += $o['price'] * $o['quantity'];
}

// ─── EDIT MODES ──────────────────────────────────────────────────
$editProduct = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->execute([':id' => (int)$_GET['edit']]);
    $editProduct = $stmt->fetch() ?: null;
}

$editUser = null;
if (isset($_GET['edit_user'])) {
    $stmt = $db->prepare("SELECT id, name, email FROM usuarios WHERE id = :id");
    $stmt->execute([':id' => (int)$_GET['edit_user']]);
    $editUser = $stmt->fetch() ?: null;
}

// ─── STATS ───────────────────────────────────────────────────────
$totalStock   = array_sum(array_column($products, 'stock'));
$avgPrice     = count($products) ? array_sum(array_column($products, 'price')) / count($products) : 0;
$lowStock     = count(array_filter($products, fn($p) => $p['stock'] <= 10));
$totalRevenue = array_sum(array_map(fn($o) => $o['price'] * $o['quantity'], $orders));

// ─── MESSAGES ────────────────────────────────────────────────────
$messages = [
    'added'         => ['✓ Producto añadido',       'success'],
    'edited'        => ['✓ Producto actualizado',   'success'],
    'deleted'       => ['✗ Producto eliminado',     'danger'],
    'user_added'    => ['✓ Usuario creado',          'success'],
    'user_edited'   => ['✓ Usuario actualizado',    'success'],
    'user_deleted'  => ['✗ Usuario eliminado',      'danger'],
    'no_pass'       => ['✗ La contraseña es obligatoria al crear un usuario', 'danger'],
    'order_updated' => ['✓ Estado actualizado',     'success'],
    'order_deleted' => ['✗ Pedido eliminado',       'danger'],
];
if (isset($_GET['msg'], $messages[$_GET['msg']])) {
    [$msg, $msgType] = $messages[$_GET['msg']];
}

$activeTab = $_GET['tab'] ?? 'products';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — TechStore</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;800&display=swap" rel="stylesheet">
<link href="./dasj.css" rel="stylesheet">

</head>
<body>
<div class="topbar">
  <a href="../index.php" class="logo">⚡Tech<span>Store</span></a>
  <span class="topbar-tag">ADMIN</span>
  <div class="topbar-right">
    <a href="../index.php">← Ver tienda</a>
    <a class="logout-btn" href="logout.php">Salir</a>
  </div>
</div>

<div class="main">
  <div class="page-header">
    <h1>Panel de Control</h1>
    <p>// gestión de productos, usuarios y pedidos</p>
  </div>

  <?php if ($msg): ?>
    <div class="alert <?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- STATS -->
  <div class="stats">
    <div class="stat-card">
      <div class="stat-label">Productos</div>
      <div class="stat-value accent"><?= count($products) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Stock total</div>
      <div class="stat-value accent2"><?= $totalStock ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Precio medio</div>
      <div class="stat-value success">€<?= number_format($avgPrice, 2) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Stock bajo</div>
      <div class="stat-value warning"><?= $lowStock ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Usuarios</div>
      <div class="stat-value accent"><?= count($users) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Carritos</div>
      <div class="stat-value accent2"><?= count($grouped_orders) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Ingresos totales</div>
      <div class="stat-value success">€<?= number_format($totalRevenue, 2) ?></div>
    </div>
  </div>

  <!-- TABS -->
  <div class="tab-nav">
    <button class="tab-btn <?= $activeTab==='products'?'active':'' ?>" onclick="setTab('products')">📦 Productos</button>
    <button class="tab-btn <?= $activeTab==='users'   ?'active':'' ?>" onclick="setTab('users')">👤 Usuarios</button>
    <button class="tab-btn <?= $activeTab==='orders'  ?'active':'' ?>" onclick="setTab('orders')">🛒 Pedidos</button>
  </div>

  <!-- ══════════════════════════════════════════════════════════
       TAB: PRODUCTOS
  ══════════════════════════════════════════════════════════ -->
  <div id="tab-products" class="tab-panel <?= $activeTab==='products'?'active':'' ?>">

    <div class="form-card">
      <h2><?= $editProduct ? '✏️ Editar producto' : '＋ Añadir producto' ?></h2>
      <form method="POST">
        <input type="hidden" name="action" value="<?= $editProduct ? 'edit_product' : 'add_product' ?>">
        <?php if ($editProduct): ?><input type="hidden" name="id" value="<?= $editProduct['id'] ?>"><?php endif; ?>
        <div class="form-grid">
          <div class="form-group">
            <label>Nombre</label>
            <input type="text" name="name" required placeholder="Nombre del producto"
                   value="<?= $editProduct ? htmlspecialchars($editProduct['name']) : '' ?>">
          </div>
          <div class="form-group">
            <label>Precio (€)</label>
            <input type="number" name="price" step="0.01" min="0" required placeholder="0.00"
                   value="<?= $editProduct ? $editProduct['price'] : '' ?>">
          </div>
          <div class="form-group">
            <label>Categoría</label>
            <input type="text" name="category" required placeholder="Ej: Audio, Monitores..."
                   value="<?= $editProduct ? htmlspecialchars($editProduct['category']) : '' ?>">
          </div>
          <div class="form-group">
            <label>Stock</label>
            <input type="number" name="stock" min="0" required placeholder="0"
                   value="<?= $editProduct ? $editProduct['stock'] : '' ?>">
          </div>
          <div class="form-group full">
            <label>Descripción</label>
            <textarea name="description" placeholder="Descripción del producto..."><?= $editProduct ? htmlspecialchars($editProduct['description']) : '' ?></textarea>
          </div>
          <div class="form-group full">
            <label>URL de imagen</label>
            <input type="url" name="image" placeholder="https://..."
                   value="<?= $editProduct ? htmlspecialchars($editProduct['image']) : '' ?>">
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary"><?= $editProduct ? '💾 Guardar cambios' : '＋ Añadir producto' ?></button>
          <?php if ($editProduct): ?>
            <a href="dashboard.php?tab=products" class="btn btn-outline">Cancelar</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div class="section-header"><h2>Productos (<?= count($products) ?>)</h2></div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Img</th><th>Nombre</th><th>Categoría</th><th>Precio</th><th>Stock</th><th>Acciones</th></tr>
        </thead>
        <tbody>
          <?php foreach ($products as $p): ?>
          <tr>
            <td><img class="product-thumb" src="<?= htmlspecialchars($p['image']) ?>" alt=""></td>
            <td>
              <strong><?= htmlspecialchars($p['name']) ?></strong><br>
              <small style="color:var(--muted);font-size:.75rem"><?= htmlspecialchars(substr($p['description']??'',0,60)) ?>...</small>
            </td>
            <td><span style="font-family:'Space Mono',monospace;font-size:.8rem;color:var(--accent)"><?= htmlspecialchars($p['category']) ?></span></td>
            <td class="price-cell">€<?= number_format($p['price'],2) ?></td>
            <td class="stock-cell <?= $p['stock']>10?'stock-ok':'stock-low' ?>"><?= $p['stock'] ?></td>
            <td>
              <div class="actions-cell">
                <a href="?tab=products&edit=<?= $p['id'] ?>" class="btn btn-sm btn-edit">✏️ Editar</a>
                <a href="?tab=products&delete=<?= $p['id'] ?>" class="btn btn-sm btn-danger"
                   onclick="return confirm('¿Eliminar este producto?')">🗑</a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════
       TAB: USUARIOS
  ══════════════════════════════════════════════════════════ -->
  <div id="tab-users" class="tab-panel <?= $activeTab==='users'?'active':'' ?>">

    <div class="form-card">
      <h2><?= $editUser ? '✏️ Editar usuario' : '＋ Añadir usuario' ?></h2>
      <form method="POST">
        <input type="hidden" name="action" value="<?= $editUser ? 'edit_user' : 'add_user' ?>">
        <?php if ($editUser): ?><input type="hidden" name="uid" value="<?= $editUser['id'] ?>"><?php endif; ?>
        <div class="form-grid">
          <div class="form-group">
            <label>Nombre</label>
            <input type="text" name="uname" required placeholder="Nombre completo"
                   value="<?= $editUser ? htmlspecialchars($editUser['name']) : '' ?>">
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required placeholder="correo@ejemplo.com"
                   value="<?= $editUser ? htmlspecialchars($editUser['email']) : '' ?>">
          </div>
          <div class="form-group">
            <label>Contraseña <?= $editUser ? '<small style="color:var(--muted)">(dejar vacío para no cambiar)</small>' : '' ?></label>
            <input type="password" name="upass" placeholder="••••••••"
                   <?= $editUser ? '' : 'required' ?>>
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary"><?= $editUser ? '💾 Guardar cambios' : '＋ Crear usuario' ?></button>
          <?php if ($editUser): ?>
            <a href="dashboard.php?tab=users" class="btn btn-outline">Cancelar</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div class="section-header"><h2>Usuarios (<?= count($users) ?>)</h2></div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>#</th><th>Nombre</th><th>Email</th><th>Registrado</th><th>Acciones</th></tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td style="font-family:'Space Mono',monospace;color:var(--muted)">#<?= $u['id'] ?></td>
            <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
            <td style="color:var(--accent2)"><?= htmlspecialchars($u['email']) ?></td>
            <td style="font-size:.8rem;color:var(--muted)"><?= $u['created_at'] ?></td>
            <td>
              <div class="actions-cell">
                <a href="?tab=users&edit_user=<?= $u['id'] ?>" class="btn btn-sm btn-edit">✏️ Editar</a>
                <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar usuario?')">
                  <input type="hidden" name="action" value="delete_user">
                  <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger">🗑</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════
       TAB: PEDIDOS (agrupados por carrito)
  ══════════════════════════════════════════════════════════ -->
  <div id="tab-orders" class="tab-panel <?= $activeTab==='orders'?'active':'' ?>">

    <div class="section-header">
      <h2>
        Pedidos
        <span class="cart-count"><?= count($grouped_orders) ?> carritos · <?= count($orders) ?> líneas</span>
      </h2>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Cliente</th>
            <th>Productos del carrito</th>
            <th>Dirección</th>
            <th>Estado</th>
            <th>Fecha</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($grouped_orders as $cart):
            $cart_ids = array_column($cart['items'], 'id');
            $cart_ids_json = htmlspecialchars(json_encode($cart_ids));
          ?>
          <tr>
            <!-- Cliente -->
            <td>
              <strong><?= htmlspecialchars($cart['user_name'] ?? '—') ?></strong><br>
              <small style="color:var(--muted);font-size:.75rem"><?= htmlspecialchars($cart['user_email'] ?? '') ?></small>
            </td>

            <!-- Productos del carrito -->
            <td style="min-width:220px">
              <?php foreach ($cart['items'] as $item): ?>
              <div class="cart-item">
                <span class="cart-item-name"><?= htmlspecialchars($item['product_name'] ?? '—') ?></span>
                <span class="cart-item-qty">×<?= $item['quantity'] ?></span>
                <span class="cart-item-price">€<?= number_format($item['price'] * $item['quantity'], 2) ?></span>
                <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar esta línea?')">
                  <input type="hidden" name="action" value="delete_order">
                  <input type="hidden" name="oid" value="<?= $item['id'] ?>">
                  <button type="submit" class="cart-item-del" title="Eliminar línea #<?= $item['id'] ?>">✕</button>
                </form>
              </div>
              <?php endforeach; ?>
              <div class="cart-total">Total: €<?= number_format($cart['total'], 2) ?></div>
            </td>

            <!-- Dirección -->
            <td style="font-size:.8rem;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <?= htmlspecialchars($cart['direccion']) ?>
            </td>

            <!-- Estado (actualiza todos los pedidos del carrito) -->
            <td>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="update_order_status">
                <input type="hidden" name="cart_ids" value="<?= $cart_ids_json ?>">
                <select name="estado" class="estado-select" onchange="this.form.submit()">
                  <?php foreach (['pendiente','procesando','enviado','entregado','cancelado','stock'] as $s): ?>
                    <option value="<?= $s ?>" <?= $cart['estado']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>

            <!-- Fecha -->
            <td style="font-size:.8rem;color:var(--muted)"><?= $cart['order_date'] ?></td>

            <!-- Eliminar carrito completo -->
            <td>
              <form method="POST" onsubmit="return confirm('¿Eliminar todo el carrito (<?= count($cart['items']) ?> productos)?')">
                <input type="hidden" name="action" value="delete_cart">
                <input type="hidden" name="cart_ids" value="<?= $cart_ids_json ?>">
                <button type="submit" class="btn btn-sm btn-danger">🗑 Todo</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /main -->

<script>
function setTab(tab) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  event.currentTarget.classList.add('active');
  const url = new URL(window.location);
  url.searchParams.set('tab', tab);
  history.replaceState({}, '', url);
}
</script>
</body>
</html>