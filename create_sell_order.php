<?php
require 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    // Create order
    $orderStmt = $pdo->prepare("
        INSERT INTO orders (user_id, total_price, status)
        VALUES (?, ?, 'completed')
    ");
    $orderStmt->execute([$userId, $input['total']]);
    $orderId = $pdo->lastInsertId();

    // Add order items
    $itemStmt = $pdo->prepare("
        INSERT INTO order_items (order_id, material_id, quantity, price)
        VALUES (?, ?, ?, ?)
    ");

    foreach ($input['items'] as $item) {
        $itemStmt->execute([
            $orderId,
            $item['material_id'],
            $item['quantity'],
            $item['price']
        ]);
    }
    // Update inventory
    foreach ($input['items'] as $item) {
        $updateStmt = $pdo->prepare("
        UPDATE inventory 
        SET quantity = quantity + ? 
        WHERE material_id = ?
    ");
        $updateStmt->execute([$item['quantity'], $item['material_id']]);
    }
    // Update user balance
    $updateUser = $pdo->prepare("
        UPDATE users SET eco_currency = eco_currency + ? 
        WHERE id = ?
    ");
    $updateUser->execute([$input['total'], $userId]);

    // Record transaction
    $transactionStmt = $pdo->prepare("
        INSERT INTO transactions (user_id, type, amount, status)
        VALUES (?, 'sale', ?, 'completed')
    ");
    $transactionStmt->execute([$userId, $input['total']]);

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'order_id' => $orderId,
        'new_balance' => $input['total'] + get_user_balance($pdo, $userId)
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

function get_user_balance($pdo, $userId)
{
    $stmt = $pdo->prepare("SELECT eco_currency FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}
?>