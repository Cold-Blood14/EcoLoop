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
    $input = json_decode(file_get_contents('php://input'), true);
    
    $name = $input['name'] ?? null;
    $email = $input['email'] ?? null;
    $userId = $_SESSION['user_id'];

    try {
        // Check email availability
        if ($email) {
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
            $checkStmt->execute([$email, $userId]);
            
            if ($checkStmt->fetch()) {
                echo json_encode(['status' => 'error', 'message' => 'Email already taken']);
                exit;
            }
        }

        // Build update query
        $updates = [];
        $params = [];
        
        if ($name) {
            $updates[] = "name = ?";
            $params[] = $name;
        }
        
        if ($email) {
            $updates[] = "email = ?";
            $params[] = $email;
        }
        
        if (count($updates) === 0) {
            echo json_encode(['status' => 'error', 'message' => 'No updates provided']);
            exit;
        }
        
        $params[] = $userId;
        
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Get updated user
        $userStmt = $pdo->prepare("SELECT id, username, name, email, role, eco_points FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        
        echo json_encode([
            'status' => 'success',
            'user' => $user
        ]);
        
    } catch (PDOException $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error',
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
ob_end_flush();
?>
