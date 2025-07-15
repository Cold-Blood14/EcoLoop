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
$account = $input['account'];
$pin = $input['pin'];

// Validate payment details
if (!preg_match('/^01[3-9]\d{8}$/', $account)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid bKash account number']);
    exit;
}

if (!preg_match('/^\d{4,5}$/', $pin)) {
    echo json_encode(['status' => 'error', 'message' => 'PIN must be 4-5 digits']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Update user balance
    $updateStmt = $pdo->prepare("UPDATE users SET eco_currency = eco_currency + ? WHERE id = ?");
    $updateStmt->execute([$amount, $userId]);
    
    // Record transaction
    $txStmt = $pdo->prepare("
        INSERT INTO transactions (user_id, type, amount, status, account_number)
        VALUES (?, 'deposit', ?, 'completed', ?)
    ");
    $txStmt->execute([$userId, $amount, $account]);
    
    $pdo->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Deposit successful',
        'new_balance' => $amount + get_user_balance($pdo, $userId)
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode([
        'status' => 'error',
        'message' => 'Deposit failed: ' . $e->getMessage()
    ]);
}

function get_user_balance($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT eco_currency FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}
?>