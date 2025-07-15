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

    // Validate input
    if (empty($input['items'])) {
        throw new Exception("No items provided");
    }
    
    // Calculate total
    $total = 0;
    foreach ($input['items'] as $item) {
        $materialStmt = $pdo->prepare("SELECT price_per_kg FROM materials WHERE id = ?");
        $materialStmt->execute([$item['material_id']]);
        $material = $materialStmt->fetch();
        
        if (!$material) {
            throw new Exception("Invalid material ID: " . $item['material_id']);
        }
        
        $itemTotal = $material['price_per_kg'] * $item['quantity'];
        $total += $itemTotal;
    }
    
    // Check user balance
    $balanceStmt = $pdo->prepare("SELECT eco_currency FROM users WHERE id = ?");
    $balanceStmt->execute([$userId]);
    $balance = $balanceStmt->fetchColumn();
    
    if ($balance < $total) {
        throw new Exception("Insufficient balance");
    }
    
    // Create order
    $orderStmt = $pdo->prepare("
        INSERT INTO orders (user_id, total_price, status) 
        VALUES (?, ?, 'completed')
    ");
    $orderStmt->execute([$userId, $total]);
    $orderId = $pdo->lastInsertId();
    
    // Add order items
    foreach ($input['items'] as $item) {
        $materialStmt = $pdo->prepare("SELECT price_per_kg FROM materials WHERE id = ?");
        $materialStmt->execute([$item['material_id']]);
        $material = $materialStmt->fetch();
        
        $itemStmt = $pdo->prepare("
            INSERT INTO order_items (order_id, material_id, quantity, price) 
            VALUES (?, ?, ?, ?)
        ");
        $itemStmt->execute([
            $orderId,
            $item['material_id'],
            $item['quantity'],
            $material['price_per_kg'] * $item['quantity']
        ]);
        
        // Update inventory
        $updateInventory = $pdo->prepare("
            UPDATE inventory 
            SET quantity = quantity - ? 
            WHERE material_id = ?
        ");
        $updateInventory->execute([$item['quantity'], $item['material_id']]);
    }
    
    // Deduct from user balance
    $updateBalance = $pdo->prepare("
        UPDATE users 
        SET eco_currency = eco_currency - ? 
        WHERE id = ?
    ");
    $updateBalance->execute([$total, $userId]);
    
    // Record transaction
    $txStmt = $pdo->prepare("
        INSERT INTO transactions (user_id, type, amount, status) 
        VALUES (?, 'purchase', ?, 'completed')
    ");
    $txStmt->execute([$userId, $total]);
    
    $pdo->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Purchase successful',
        'order_id' => $orderId,
        'new_balance' => $balance - $total
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>