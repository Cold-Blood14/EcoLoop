<?php
require 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$txId = $input['tx_id'];
$account = $input['account'];
$password = $input['password'];
$userId = $_SESSION['user_id'];

// Verify password
$stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!password_verify($password, $user['password'])) {
    echo json_encode(['status' => 'error', 'message' => 'Incorrect password']);
    exit;
}

// Validate account number
if (!preg_match('/^01[3-9]\d{8}$/', $account)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid bKash account number']);
    exit;
}

try {
    // Update transaction with account number and complete status
    $updateStmt = $pdo->prepare("
        UPDATE transactions 
        SET account_number = ?, status = 'completed'
        WHERE id = ? AND user_id = ? AND type = 'cashout'
    ");
    $updateStmt->execute([$account, $txId, $userId]);
    
    if ($updateStmt->rowCount() === 0) {
        throw new Exception('Transaction update failed');
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'bKash Transfer Request Successful'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>