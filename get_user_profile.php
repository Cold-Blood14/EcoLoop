<?php
require 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT id, name, email, phone, role, organization_name 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Determine display name based on role
        $displayName = ($user['role'] === 'organization') 
            ? $user['organization_name'] 
            : $user['name'];
        
        echo json_encode([
            'status' => 'success',
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'role' => $user['role'],
                'organization_name' => $user['organization_name'],
                'display_name' => $displayName
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
    }
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>