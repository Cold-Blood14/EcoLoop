<?php
require 'db_connect.php';
try {
    $stmt = $pdo->prepare("
        SELECT 
            m.id AS material_id,
            m.name,
            m.price_per_kg,
            i.quantity AS total_quantity,
            i.min_reserve,
            GREATEST(i.quantity - i.min_reserve, 0) AS available_quantity
        FROM materials m
        JOIN inventory i ON m.id = i.material_id
    ");
    $stmt->execute();
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert numeric fields to proper types
    foreach ($inventory as &$item) {
        $item['price_per_kg'] = (float)$item['price_per_kg'];
        $item['available_quantity'] = (float)$item['available_quantity'];
    }

    echo json_encode([
        'status' => 'success',
        'inventory' => $inventory
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>