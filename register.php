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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    $name = $input['name'] ?? null;
    $email = $input['email'] ?? null;
    $password = $input['password'] ?? null;
    $role = $input['role'] ?? 'user';

    if (!$name || !$email || !$password) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        exit;
    }

    try {

        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->execute([$email]);
        
        if ($checkStmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Email already registered']);
            exit;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $hashedPassword, $role]);

        $userId = $pdo->lastInsertId();

        $userStmt = $pdo->prepare("SELECT id, name, email, role, eco_points FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'User registered successfully',
            'user' => $user
        ]);
        
    } catch (PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Registration failed',
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
ob_end_flush();
?>