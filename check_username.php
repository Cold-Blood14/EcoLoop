<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header('Content-Type: application/json');

require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = $input['username'] ?? '';

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        echo json_encode([
            'available' => !$stmt->fetch()
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'error' => 'Database error',
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}