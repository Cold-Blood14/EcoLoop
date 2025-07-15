<?php
require 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$txId = $input['tx_id'];
$userId = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();
    
    // Get transaction details
    $stmt = $pdo->prepare("
        SELECT amount 
        FROM transactions 
        WHERE id = ? AND user_id = ? AND status = 'pending' AND type = 'cashout'
    ");
    $stmt->execute([$txId, $userId]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        throw new Exception('Transaction not found or already processed');
    }
    
    $amount = $transaction['amount'];
    
    // Return funds to user balance
    $updateUser = $pdo->prepare("
        UPDATE users 
        SET eco_currency = eco_currency + ? 
        WHERE id = ?
    ");
    $updateUser->execute([$amount, $userId]);
    
    // Update transaction status to cancelled
    $updateTx = $pdo->prepare("
        UPDATE transactions 
        SET status = 'cancelled', type = 'cancelled'
        WHERE id = ? AND user_id = ?
    ");
    $updateTx->execute([$txId, $userId]);
    
    $pdo->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Transaction cancelled successfully',
        'returned_amount' => $amount
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'status' => 'error',
        'message' => 'Cancellation failed: ' . $e->getMessage()
    ]);
}
?>