<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

requireLogin();

$user_id = getCurrentUserId();

// 獲取採購訂單列表
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$start = ($page - 1) * $perPage;

$stmt = $conn->prepare("SELECT po.*, s.name as supplier_name FROM purchase_orders po 
                        JOIN suppliers s ON po.supplier_id = s.id
                        WHERE po.user_id = ? 
                        ORDER BY po.order_date DESC 
                        LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $user_id, $perPage, $start);
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);

// 獲取總訂單數量
$totalStmt = $conn->prepare("SELECT COUNT(*) as total FROM purchase_orders WHERE user_id = ?");
$totalStmt->bind_param("i", $user_id);
$totalStmt->execute();
$totalResult = $totalStmt->get_result();
$totalRow = $totalResult->fetch_assoc();
$totalOrders = $totalRow['total'];
$totalPages = ceil($totalOrders / $perPage);

$status_options = [
    "pending" => "待處理",
    "approved" => "已批准",
    "shipped" => "已發貨",
    "received" => "已收貨",
    "cancelled" => "已取消"
];
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>採購管理 - 庫存管理系統</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include "navbar.php"; ?>

    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-bold mb-4">採購管理</h1>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $_SESSION['success_message']; ?></span>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $_SESSION['error_message']; ?></span>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- 創建新採購訂單按鈕 -->
        <a href="create_purchase_order.php" class="mb-4 bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded inline-block">
            創建新採購訂單
        </a>

        <!-- 採購訂單列表 -->
        <table class="min-w-full bg-white">
            <thead>
                <tr>
                    <th class="py-2 px-4 border-b">訂單ID</th>
                    <th class="py-2 px-4 border-b">供應商</th>
                    <th class="py-2 px-4 border-b">訂單日期</th>
                    <th class="py-2 px-4 border-b">總金額</th>
                    <th class="py-2 px-4 border-b">狀態</th>
                    <th class="py-2 px-4 border-b">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($order['id']); ?></td>
                    <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($order['supplier_name']); ?></td>
                    <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($order['order_date']); ?></td>
                    <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($order['total_amount']); ?></td>
                    <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($status_options[$order['status']]); ?></td>
                    <td class="py-2 px-4 border-b">
                        <a href="generate_purchase_order_image.php?id=<?php echo $order['id']; ?>" target="_blank" class="bg-green-500 hover:bg-green-700 text-white font-bold py-1 px-2 rounded mr-1">下載圖片</a>
                        <a href="view_purchase_order.php?id=<?php echo $order['id']; ?>" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-2 rounded mr-1">查看詳情</a>
                        <select onchange="updateOrderStatus(<?php echo $order['id']; ?>, this.value)" class="bg-yellow-500 text-white py-1 px-2 rounded">
                            <?php foreach ($status_options as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo $order['status'] === $value ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- 分頁 -->
        <div class="mt-4">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?php echo $i; ?>" 
                   class="inline-block bg-blue-500 text-white px-3 py-1 rounded <?php echo $i === $page ? 'bg-blue-700' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>

    <script>
    function updateOrderStatus(orderId, newStatus) {
        fetch("update_order_status.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: `order_id=${orderId}&status=${newStatus}`,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
            } else {
                alert("更新失敗：" + data.message);
            }
        })
        .catch((error) => {
            console.error("Error:", error);
            alert("發生錯誤，請稍後再試");
        });
    }
    </script>
</body>
</html>
