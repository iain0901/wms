<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
error_log("View Purchase Order script started. Order ID: " . ($_GET["id"] ?? "Not provided"));
?>
<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

requireLogin();

$user_id = getCurrentUserId();

if (!isset($_GET['id'])) {
    die("訂單 ID 未提供");
}

$order_id = $_GET['id'];

// 獲取訂單信息
$stmt = $conn->prepare("SELECT po.*, s.name as supplier_name FROM purchase_orders po 
                        JOIN suppliers s ON po.supplier_id = s.id
                        WHERE po.id = ? AND po.user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();

if (!$order) {
    die("訂單不存在或您無權訪問");
}

// 獲取訂單項目
$stmt = $conn->prepare("SELECT poi.*, p.name as product_name FROM purchase_order_items poi
                        JOIN products p ON poi.product_id = p.id
                        WHERE poi.purchase_order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_result = $stmt->get_result();
$items = $items_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>查看採購訂單 - 庫存管理系統</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include "navbar.php"; ?>

    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-bold mb-4">採購訂單詳情</h1>

        <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            <h2 class="text-2xl font-bold mb-4">訂單信息</h2>
            <p><strong>訂單 ID:</strong> <?php echo htmlspecialchars($order['id']); ?></p>
            <p><strong>供應商:</strong> <?php echo htmlspecialchars($order['supplier_name']); ?></p>
            <p><strong>日期:</strong> <?php echo htmlspecialchars($order['order_date']); ?></p>
            <p><strong>狀態:</strong> <?php echo htmlspecialchars($order['status']); ?></p>
            <p><strong>總金額:</strong> <?php echo htmlspecialchars($order['total_amount']); ?></p>
        </div>

        <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            <h2 class="text-2xl font-bold mb-4">訂單項目</h2>
            <table class="min-w-full">
                <thead>
                    <tr>
                        <th class="py-2 px-4 bg-gray-100">產品名稱</th>
                        <th class="py-2 px-4 bg-gray-100">數量</th>
                        <th class="py-2 px-4 bg-gray-100">單價</th>
                        <th class="py-2 px-4 bg-gray-100">總價</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="py-2 px-4"><?php echo htmlspecialchars($item['product_name']); ?></td>
                        <td class="py-2 px-4"><?php echo htmlspecialchars($item['quantity']); ?></td>
                        <td class="py-2 px-4"><?php echo htmlspecialchars($item['unit_price']); ?></td>
                        <td class="py-2 px-4"><?php echo htmlspecialchars($item['quantity'] * $item['unit_price']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            <h2 class="text-2xl font-bold mb-4">備註</h2>
            <p><strong>給供應商的備註:</strong></p>
            <p><?php echo nl2br(htmlspecialchars($order['supplier_remarks'] ?? '')); ?></p>
            <p><strong>給自己的備註:</strong></p>
            <p><?php echo nl2br(htmlspecialchars($order['notes'] ?? '')); ?></p>
        </div>

        <a href="purchase_management.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            返回採購管理
        </a>
    </div>
</body>
</html>
