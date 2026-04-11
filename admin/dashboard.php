<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';
$db = getDB();

$msg = ''; $msgType = '';

// DELETE
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM products WHERE id = :id");
    $stmt->execute([':id' => $id]);
    header("Location: dashboard.php?msg=deleted");
    exit;
}

// ADD / EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $name        = trim($_POST['name']        ?? '');
    $price       = (float)($_POST['price']    ?? 0);
    $category    = trim($_POST['category']    ?? '');
    $description = trim($_POST['description'] ?? '');
    $image       = trim($_POST['image']       ?? '');
    $stock       = (int)($_POST['stock']      ?? 0);

    if (!$image) {
        $image = 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=400&q=80';
    }

    if ($_POST['action'] === 'add') {
        $stmt = $db->prepare("
            INSERT INTO products (name, price, category, description, image, stock)
            VALUES (:name, :price, :category, :description, :image, :stock)
        ");
        $stmt->execute(compact('name','price','category','description','image','stock'));
        header("Location: dashboard.php?msg=added");
        exit;
    }

    if ($_POST['action'] === 'edit') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("
            UPDATE products
               SET name=:name, price=:price, category=:category,
                   description=:description, image=:image, stock=:stock
             WHERE id=:id
        ");
        $stmt->execute(compact('name','price','category','description','image','stock','id'));
        header("Location: dashboard.php?msg=edited");
        exit;
    }
}

// Cargar todos los productos
$products = $db->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();

$editProduct = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->execute([':id' => $editId]);
    $editProduct = $stmt->fetch() ?: null;
}

$messages = [
    'added'   => ['✓ Producto añadido',     'success'],
    'edited'  => ['✓ Producto actualizado', 'success'],
    'deleted' => ['✗ Producto eliminado',   'danger'],
];
if (isset($_GET['msg'], $messages[$_GET['msg']])) {
    [$msg, $msgType] = $messages[$_GET['msg']];
}

// Stats
$totalStock = array_sum(array_column($products, 'stock'));
$avgPrice   = count($products)
    ? array_sum(array_column($products, 'price')) / count($products)
    : 0;
$lowStock   = count(array_filter($products, fn($p) => $p['stock'] <= 10));
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
    <p>// gestión de productos y precios</p>
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
  </div>

  <!-- FORM ADD/EDIT -->
  <div class="form-card">
    <h2><?= $editProduct ? '✏️ Editar producto' : '＋ Añadir producto' ?></h2>
    <form method="POST">
      <input type="hidden" name="action" value="<?= $editProduct ? 'edit' : 'add' ?>">
      <?php if ($editProduct): ?><input type="hidden" name="id" value="<?= $editProduct['id'] ?>"><?php endif; ?>
      <div class="form-grid">
        <div class="form-group">
          <label>Nombre</label>
          <input type="text" name="name" required placeholder="Nombre del producto" value="<?= $editProduct ? htmlspecialchars($editProduct['name']) : '' ?>">
        </div>
        <div class="form-group">
          <label>Precio (€)</label>
          <input type="number" name="price" step="0.01" min="0" required placeholder="0.00" value="<?= $editProduct ? $editProduct['price'] : '' ?>">
        </div>
        <div class="form-group">
          <label>Categoría</label>
          <input type="text" name="category" required placeholder="Ej: Audio, Monitores..." value="<?= $editProduct ? htmlspecialchars($editProduct['category']) : '' ?>">
        </div>
        <div class="form-group">
          <label>Stock</label>
          <input type="number" name="stock" min="0" required placeholder="0" value="<?= $editProduct ? $editProduct['stock'] : '' ?>">
        </div>
        <div class="form-group full">
          <label>Descripción</label>
          <textarea name="description" placeholder="Descripción del producto..."><?= $editProduct ? htmlspecialchars($editProduct['description']) : '' ?></textarea>
        </div>
        <div class="form-group full">
          <label>URL de imagen</label>
          <input type="url" name="image" placeholder="https://..." value="<?= $editProduct ? htmlspecialchars($editProduct['image']) : '' ?>">
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $editProduct ? '💾 Guardar cambios' : '＋ Añadir producto' ?></button>
        <?php if ($editProduct): ?>
          <a href="dashboard.php" class="btn btn-outline">Cancelar</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- PRODUCT TABLE -->
  <div class="section-header">
    <h2>Productos (<?= count($products) ?>)</h2>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Img</th>
          <th>Nombre</th>
          <th>Categoría</th>
          <th>Precio</th>
          <th>Stock</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($products as $p): ?>
        <tr>
          <td><img class="product-thumb" src="<?= htmlspecialchars($p['image']) ?>" alt=""></td>
          <td>
            <strong><?= htmlspecialchars($p['name']) ?></strong><br>
            <small style="color:var(--muted);font-size:0.75rem"><?= htmlspecialchars(substr($p['description'] ?? '',0,60)) ?>...</small>
          </td>
          <td><span style="font-family:'Space Mono',monospace;font-size:0.8rem;color:var(--accent)"><?= htmlspecialchars($p['category']) ?></span></td>
          <td class="price-cell">€<?= number_format($p['price'], 2) ?></td>
          <td class="stock-cell <?= $p['stock'] > 10 ? 'stock-ok' : 'stock-low' ?>"><?= $p['stock'] ?></td>
          <td>
            <div class="actions-cell">
              <a href="?edit=<?= $p['id'] ?>" class="btn btn-sm btn-edit">✏️ Editar</a>
              <a href="?delete=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar este producto?')">🗑</a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
