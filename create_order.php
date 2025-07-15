<?php
ob_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header('Content-Type: application/json');
session_start();

require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    $items = json_decode(file_get_contents('php://input'), true);
    
    if (empty($items)) {
        echo json_encode(['status' => 'error', 'message' => 'No items provided']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        
        // Calculate total
        $total = 0;
        $points = 0;
        
        foreach ($items as $item) {
            $materialStmt = $pdo->prepare("SELECT price_per_kg FROM materials WHERE id = ?");
            $materialStmt->execute([$item['material_id']]);
            $material = $materialStmt->fetch();
            
            if (!$material) {
                throw new Exception("Invalid material ID: " . $item['material_id']);
            }
            
            $itemTotal = $material['price_per_kg'] * $item['quantity'];
            $total += $itemTotal;
            $points += $item['quantity'] * 10; // 10 points per kg
        }
        
        // Create order
        $orderStmt = $pdo->prepare("INSERT INTO orders (user_id, total_price) VALUES (?, ?)");
        $orderStmt->execute([$userId, $total]);
        $orderId = $pdo->lastInsertId();
        
        // Add order items
        foreach ($items as $item) {
            $materialStmt = $pdo->prepare("SELECT price_per_kg FROM materials WHERE id = ?");
            $materialStmt->execute([$item['material_id']]);
            $material = $materialStmt->fetch();
            
            $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, material_id, quantity, price) VALUES (?, ?, ?, ?)");
            $itemStmt->execute([
                $orderId,
                $item['material_id'],
                $item['quantity'],
                $material['price_per_kg'] * $item['quantity']
            ]);
        }
        
        // Update user points
        $updateStmt = $pdo->prepare("UPDATE users SET eco_points = eco_points + ? WHERE id = ?");
        $updateStmt->execute([$points, $userId]);
        
        $pdo->commit();
        
        echo json_encode([
            'status' => 'success',
            'order_id' => $orderId,
            'points_earned' => $points
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'status' => 'error',
            'message' => 'Order failed',
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
ob_end_flush();
?>