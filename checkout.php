<?php
require_once __DIR__ . '/db.php';
$db = getDB();

$data     = json_decode(file_get_contents("php://input"), true);
$cart     = $data['cart']     ?? [];
$email    = trim($data['email']    ?? '');
$password = trim($data['password'] ?? '');

if (!$email || !$password || empty($cart)) {
    echo json_encode(["ok" => false, "error" => "Datos inválidos"]);
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
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("INSERT INTO usuarios (name, email, password) VALUES (?, ?, ?)");
        $stmt->execute(['Cliente', $email, $hash]);
        $usuario_id = $db->lastInsertId();

    // 3. Si esiste => verificar contraseña
    } else {
        if (!password_verify($password, $user['password'])) {
            $db->rollBack();
            echo json_encode(["ok" => false, "error" => "Contraseña incorrecta"]);
            exit;
        }
        $usuario_id = $user['id'];
    }

    // 4. Guardar cada ítem del carrito como pedido
    foreach ($cart as $item) {
        $stmt = $db->prepare("
            INSERT INTO pedidos (usuario_id, product_id, quantity, price, direccion)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $usuario_id,
            (int)$item['id'],
            (int)$item['qty'],
            (float)$item['price'],
            'Sin dirección'
        ]);
    }

    $db->commit();
    echo json_encode(["ok" => true]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(["ok" => false, "error" => $e->getMessage()]);
}