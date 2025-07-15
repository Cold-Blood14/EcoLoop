<?php
require 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];
$amount = $input['amount'];

// Validate amount
$stmt = $pdo->prepare("SELECT eco_currency FROM users WHERE id = ?");
$stmt->execute([$userId]);
$balance = $stmt->fetchColumn();

if ($amount < 1000) {
    echo json_encode(['status' => 'error', 'message' => 'Minimum cashout amount is 1,000 TK']);
    exit;
}

if ($amount > $balance) {
    echo json_encode(['status' => 'error', 'message' => 'Insufficient balance']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Deduct from user balance
    $updateStmt = $pdo->prepare("UPDATE users SET eco_currency = eco_currency - ? WHERE id = ?");
    $updateStmt->execute([$amount, $userId]);
    
    // Record transaction with 'cashout' type
    $txStmt = $pdo->prepare("
        INSERT INTO transactions (user_id, type, amount, status)
        VALUES (?, 'cashout', ?, 'pending')
    ");
    $txStmt->execute([$userId, $amount]);
    $transactionId = $pdo->lastInsertId();
    
    $pdo->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Cashout initiated',
        'transaction_id' => $transactionId,
        'new_balance' => $balance - $amount
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>