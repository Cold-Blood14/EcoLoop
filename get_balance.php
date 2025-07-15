<?php
require 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT eco_currency FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $balance = $stmt->fetchColumn();
    
    // Ensure balance is properly formatted as float
    $balance = ($balance !== false) ? (float)$balance : 0.00;

    echo json_encode([
        'status' => 'success',
        'balance' => $balance
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>