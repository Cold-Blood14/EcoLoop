<?php
require 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Get orders
    $stmt = $pdo->prepare("
        SELECT o.id, o.total_price, o.status, o.created_at 
        FROM orders o
        WHERE o.user_id = ?
    ");
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert numerical fields to floats
    foreach ($orders as &$order) {
        $order['total_price'] = (float)$order['total_price'];
    }
    
    // Get order items for each order
    foreach ($orders as &$order) {
        $itemStmt = $pdo->prepare("
            SELECT m.name AS material_name, oi.quantity, oi.price
            FROM order_items oi
            JOIN materials m ON oi.material_id = m.id
            WHERE oi.order_id = ?
        ");
        $itemStmt->execute([$order['id']]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert numerical fields to floats
        foreach ($items as &$item) {
            $item['quantity'] = (float)$item['quantity'];
            $item['price'] = (float)$item['price'];
        }
        
        $order['items'] = $items;
    }

    echo json_encode([
        'status' => 'success',
        'orders' => $orders
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>