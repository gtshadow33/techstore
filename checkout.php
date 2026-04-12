<?php
require_once __DIR__ . '/db.php';
$db = getDB();

$data     = json_decode(file_get_contents("php://input"), true);
$cart     = $data['cart']     ?? [];
$email    = trim($data['email']    ?? '');
$password = trim($data['password'] ?? '');
$direcion = trim($data['direccion'] ?? '');

if (!$email || !$password || empty($cart)) {
    echo json_encode(["ok" => false, "error" => "Datos inválidos"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["ok" => false, "error" => "Email invalido"]);
    exit;
}
try {
    $db->beginTransaction();

    // 1. Buscar usuario por enail
    $stmt = $db->prepare("SELECT id, password FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Si no esiste => registrar
    if (!$user) {
         echo json_encode(["ok" => false, "error" => "Registre su cuenta en el apartado cuentas "]);
    } else {
        if (!password_verify($password, $user['password'])) {
            $db->rollBack();
            echo json_encode(["ok" => false, "error" => "Contraseña incorrecta"]);
            exit;
        }
        $usuario_id = $user['id'];
    }

    // 4. Guardar cada ítem del carrito como pedido si falta se pone en stock
    foreach ($cart as $item) {
    $stmtCheck = $db->prepare("SELECT stock FROM products WHERE id = ?");
    $stmtCheck->execute([(int)$item['id']]);
    $product = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    $estado = ($product && $product['stock'] >= (int)$item['qty']) ? 'pendiente' : 'stock';

    $stmt = $db->prepare("
        INSERT INTO pedidos (usuario_id, product_id, quantity, price, direccion, estado)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $usuario_id,
        (int)$item['id'],
        (int)$item['qty'],
        (float)$item['price'],
        $direcion,
        $estado
    ]);
}
    $db->commit();
    echo json_encode(["ok" => true]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(["ok" => false, "error" => $e->getMessage()]);
}