<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

if (!isLoggedIn()) {
    header('HTTP/1.1 403 Forbidden');
    exit('Unauthorized');
}

$user_id = getCurrentUserId();
$days = isset($_GET['days']) ? intval($_GET['days']) : 30;

$stmt = $conn->prepare("
    SELECT 
        DATE(io.created_at) as date,
        SUM(CASE WHEN io.operation_type = 'in' THEN io.quantity ELSE -io.quantity END) as stock_change,
        SUM(CASE WHEN io.operation_type = 'in' THEN io.quantity * p.price ELSE -io.quantity * p.price END) as value_change,
        SUM(CASE WHEN io.operation_type = 'in' THEN io.quantity * (p.price - p.purchase_price) ELSE -io.quantity * (p.price - p.purchase_price) END) as profit_change
    FROM 
        inventory_operations io
    JOIN 
        products p ON io.product_id = p.id
    WHERE 
        io.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) AND io.user_id = ?
    GROUP BY 
        DATE(io.created_at)
    ORDER BY 
        date
");
$stmt->bind_param("ii", $days, $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

header('Content-Type: application/json');
echo json_encode($result);
