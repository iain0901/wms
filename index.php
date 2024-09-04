<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = getCurrentUserId();

// 獲取總庫存和商品數量
$stmt = $conn->prepare("SELECT SUM(stock) as total_stock, COUNT(*) as total_products, SUM(stock * price) as total_value, SUM(stock * (price - purchase_price)) as total_profit FROM products WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$total_stock = $result['total_stock'] ?? 0;
$total_products = $result['total_products'] ?? 0;
$total_value = $result['total_value'] ?? 0;
$total_profit = $result['total_profit'] ?? 0;

// 獲取熱賣排名
function getTopSellingProducts($conn, $user_id, $limit = 5) {
    $stmt = $conn->prepare("
        SELECT p.name, SUM(CASE WHEN io.operation_type = 'out' THEN io.quantity ELSE 0 END) as total_sold
        FROM products p
        LEFT JOIN inventory_operations io ON p.id = io.product_id
        WHERE p.user_id = ?
        GROUP BY p.id
        ORDER BY total_sold DESC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$top_selling_products = getTopSellingProducts($conn, $user_id);

// 獲取庫存變化、價值和利潤數據（預設為最近30天）
function getInventoryChanges($conn, $days, $user_id) {
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
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$inventory_changes = getInventoryChanges($conn, 30, $user_id);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>庫存管理系統 - 首頁</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
<?php include "navbar.php"; ?>


    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-bold mb-6">庫存管理系統概覽</h1>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">總體統計</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center">
                        <p class="text-gray-600">總庫存數量</p>
                        <p class="text-3xl font-bold text-blue-600"><?php echo number_format($total_stock); ?></p>
                    </div>
                    <div class="text-center">
                        <p class="text-gray-600">商品數量</p>
                        <p class="text-3xl font-bold text-green-600"><?php echo number_format($total_products); ?></p>
                    </div>
                    <div class="text-center">
                        <p class="text-gray-600">商品總價值</p>
                        <p class="text-3xl font-bold text-purple-600">$<?php echo number_format($total_value, 2); ?></p>
                    </div>
                    <div class="text-center">
                        <p class="text-gray-600">總利潤</p>
                        <p class="text-3xl font-bold text-red-600">$<?php echo number_format($total_profit, 2); ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">熱賣商品排名</h2>
                <ol class="list-decimal list-inside">
                    <?php foreach ($top_selling_products as $product): ?>
                        <li class="mb-2"><?php echo htmlspecialchars($product['name']); ?> (銷量: <?php echo $product['total_sold']; ?>)</li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">庫存變化</h2>
                <div class="mb-4">
                    <label for="stockTimeRange" class="block text-sm font-medium text-gray-700">選擇時間範圍：</label>
                    <select id="stockTimeRange" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                        <option value="7">最近 7 天</option>
                        <option value="30" selected>最近 30 天</option>
                        <option value="90">最近 90 天</option>
                        <option value="365">最近一年</option>
                    </select>
                </div>
                <canvas id="stockChart"></canvas>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">價值變化</h2>
                <div class="mb-4">
                    <label for="valueTimeRange" class="block text-sm font-medium text-gray-700">選擇時間範圍：</label>
                    <select id="valueTimeRange" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                        <option value="7">最近 7 天</option>
                        <option value="30" selected>最近 30 天</option>
                        <option value="90">最近 90 天</option>
                        <option value="365">最近一年</option>
                    </select>
                </div>
                <canvas id="valueChart"></canvas>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">利潤變化</h2>
                <div class="mb-4">
                    <label for="profitTimeRange" class="block text-sm font-medium text-gray-700">選擇時間範圍：</label>
                    <select id="profitTimeRange" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                        <option value="7">最近 7 天</option>
                        <option value="30" selected>最近 30 天</option>
                        <option value="90">最近 90 天</option>
                        <option value="365">最近一年</option>
                    </select>
                </div>
                <canvas id="profitChart"></canvas>
            </div>
        </div>
    </div>

    <script>
    const stockCtx = document.getElementById('stockChart').getContext('2d');
    const valueCtx = document.getElementById('valueChart').getContext('2d');
    const profitCtx = document.getElementById('profitChart').getContext('2d');
    let stockChart, valueChart, profitChart;

    function createChart(ctx, label, data, color) {
        return new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map(item => item.date),
                datasets: [{
                    label: label,
                    data: data.map(item => item[label.toLowerCase() + '_change']),
                    borderColor: color,
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    function updateCharts(data) {
        if (stockChart) stockChart.destroy();
        if (valueChart) valueChart.destroy();
        if (profitChart) profitChart.destroy();

        stockChart = createChart(stockCtx, 'Stock', data, 'rgb(75, 192, 192)');
        valueChart = createChart(valueCtx, 'Value', data, 'rgb(153, 102, 255)');
        profitChart = createChart(profitCtx, 'Profit', data, 'rgb(255, 99, 132)');
    }

    // 初始化圖表
    updateCharts(<?php echo json_encode($inventory_changes); ?>);

    // 監聽時間範圍選擇的變化
    document.getElementById('stockTimeRange').addEventListener('change', updateData);
    document.getElementById('valueTimeRange').addEventListener('change', updateData);
    document.getElementById('profitTimeRange').addEventListener('change', updateData);

    function updateData(e) {
        const days = e.target.value;
        fetch(`get_inventory_changes.php?days=${days}`)
            .then(response => response.json())
            .then(data => {
                updateCharts(data);
            })
            .catch(error => console.error('Error:', error));
    }
    </script>
</body>
</html>
