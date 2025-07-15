<?php
ob_start();
header('Content-Type: application/json'); 
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require 'db_connect.php';
session_start();

// Simple admin check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

// Get all orders
$ordersStmt = $pdo->prepare("
    SELECT o.id, u.name AS user_name, o.total_price, o.status, o.created_at
    FROM orders o
    JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
");
$ordersStmt->execute();
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

// Get materials
$materialsStmt = $pdo->query("SELECT * FROM materials");
$materials = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Admin Dashboard</h1>
    
    <h2>Materials</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Price/kg</th>
        </tr>
        <?php foreach ($materials as $material): ?>
        <tr>
            <td><?= $material['id'] ?></td>
            <td><?= $material['name'] ?></td>
            <td><?= number_format($material['price_per_kg'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <h2>Orders</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>User</th>
            <th>Total</th>
            <th>Status</th>
            <th>Date</th>
        </tr>
        <?php foreach ($orders as $order): ?>
        <tr>
            <td><?= $order['id'] ?></td>
            <td><?= $order['user_name'] ?></td>
            <td><?= number_format($order['total_price'], 2) ?></td>
            <td><?= $order['status'] ?></td>
            <td><?= $order['created_at'] ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>