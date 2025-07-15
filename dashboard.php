<?php
ob_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
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

$userId = $_SESSION['user_id'];

try {
    // Get user info
    $userStmt = $pdo->prepare("SELECT id, name, email, role, eco_points FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();

    // Get orders
    $ordersStmt = $pdo->prepare("
        SELECT o.id, o.total_price, o.created_at, 
               GROUP_CONCAT(m.name, ' (', i.quantity, 'kg)' SEPARATOR ', ') AS items
        FROM orders o
        JOIN order_items i ON o.id = i.order_id
        JOIN materials m ON i.material_id = m.id
        WHERE o.user_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $ordersStmt->execute([$userId]);
    $orders = $ordersStmt->fetchAll();

    echo json_encode([
        'status' => 'success',
        'user' => $user,
        'orders' => $orders
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error',
        'error' => $e->getMessage()
    ]);
}
ob_end_flush();
?>