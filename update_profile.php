<?php
require 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

// Get user role first
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
$role = $user['role'];

// Prepare update data
$name = $input['name'] ?? '';
$email = $input['email'] ?? '';
$phone = $input['phone'] ?? '';
$organizationName = $input['organization_name'] ?? '';

try {
    $pdo->beginTransaction();
    
    if ($role === 'organization') {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET organization_name = ?, email = ?, phone = ?
            WHERE id = ?
        ");
        $stmt->execute([$organizationName, $email, $phone, $userId]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET name = ?, email = ?, phone = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $email, $phone, $userId]);
    }

    $pdo->commit();
    
    // Return updated profile
    $stmt = $pdo->prepare("
        SELECT id, name, email, phone, role, organization_name 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $displayName = ($role === 'organization') 
        ? $updatedUser['organization_name'] 
        : $updatedUser['name'];
    
    echo json_encode([
        'status' => 'success',
        'user' => [
            'name' => $updatedUser['name'],
            'email' => $updatedUser['email'],
            'phone' => $updatedUser['phone'],
            'role' => $updatedUser['role'],
            'organization_name' => $updatedUser['organization_name'],
            'display_name' => $displayName
        ]
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>