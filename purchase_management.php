<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

error_reporting(E_ALL);
ini_set("display_errors", 1);

requireLogin();

$user_id = getCurrentUserId();

// 檢查表是否存在
$tables = ["purchase_orders", "purchase_order_items"];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '".$table."'");
    if($result->num_rows == 0) {
        die("錯誤：表 ".$table." 不存在。請確保已正確創建所有必要的數據庫表。");
    }
}

$status_options = [
    "pending" => "待處理",
    "approved" => "已批准",
    "shipped" => "已發貨",
    "received" => "已收貨",
    "cancelled" => "已取消"
];

// 處理創建新採購訂單
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_order'])) {
    $supplier_id = $_POST['supplier_id'];
    $order_date = $_POST['order_date'];
    $total_amount = $_POST['total_amount'];
    $notes = $_POST['notes'];
    $supplier_remarks = $_POST['supplier_remarks'];
    $remarks = $_POST['remarks'];
    $status = array_search($_POST["status"], $status_options);

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("INSERT INTO purchase_orders (user_id, supplier_id, order_date, status, total_amount, notes, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissdss", $user_id, $supplier_id, $order_date, $status, $total_amount, $notes, $remarks);
        
        if ($stmt->execute()) {
            $order_id = $conn->insert_id;
            foreach ($_POST['products'] as $product) {
                $product_id = $product['id'];
                $quantity = $product['quantity'];
                $unit_price = $product['price'];

                if (!empty($product_id) && $product_id != 0) {
                    // 確認產品存在且屬於當前用戶
                    $checkStmt = $conn->prepare("SELECT id FROM products WHERE id = ? AND user_id = ?");
                    $checkStmt->bind_param("ii", $product_id, $user_id);
                    $checkStmt->execute();
                    $checkResult = $checkStmt->get_result();

                    if ($checkResult->num_rows === 0) {
                        throw new Exception("產品ID {$product_id} 不存在或不屬於當前用戶");
                    }

                    $stmt = $conn->prepare("INSERT INTO purchase_order_items (purchase_order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("iiid", $order_id, $product_id, $quantity, $unit_price);
                    $stmt->execute();
                }
            }
            $conn->commit();
            $_SESSION['success_message'] = "採購訂單創建成功";
        } else {
            throw new Exception("創建採購訂單失敗");
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "錯誤：" . $e->getMessage();
    }
    
    header("Location: purchase_management.php");
    exit();
}

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

// 獲取供應商列表（用於創建新訂單）
$supplierStmt = $conn->prepare("SELECT id, name FROM suppliers WHERE user_id = ?");
$supplierStmt->bind_param("i", $user_id);
$supplierStmt->execute();
$supplierResult = $supplierStmt->get_result();
$suppliers = $supplierResult->fetch_all(MYSQLI_ASSOC);

// 獲取產品列表（用於創建新訂單）
$productStmt = $conn->prepare("SELECT id, name, purchase_price FROM products WHERE user_id = ?");
$productStmt->bind_param("i", $user_id);
$productStmt->execute();
$productResult = $productStmt->get_result();
$products = $productResult->fetch_all(MYSQLI_ASSOC);
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
        <button onclick="openCreateOrderModal()" class="mb-4 bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
            創建新採購訂單
        </button>

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
                        <button onclick="viewOrderDetails(<?php echo $order['id']; ?>)" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-2 rounded mr-1">查看詳情</button>
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

    <!-- 創建新採購訂單模態框 -->
    <div id="createOrderModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full" style="display: none;">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-2xl shadow-lg rounded-md bg-white">
            <h3 class="text-lg font-bold mb-4">創建新採購訂單</h3>
            <form method="POST">
                <input type="hidden" name="create_order" value="1">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="supplier_id">
                        供應商
                    </label>
                    <select id="supplier_id" name="supplier_id" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo $supplier['id']; ?>"><?php echo htmlspecialchars($supplier['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="order_date">
                        訂單日期
                    </label>
                    <input type="date" id="order_date" name="order_date" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="status">
                        訂單狀態
                    </label>
                    <select id="status" name="status" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <?php foreach ($status_options as $value => $label): ?>
                            <option value="<?php echo $label; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="productList" class="mb-4">
                    <!-- 產品列表將通過 JavaScript 動態添加 -->
                </div>
                <button type="button" onclick="addProduct()" class="mb-4 bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    添加產品
                </button>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="total_amount">
                        總金額
                    </label>
                    <input type="number" step="0.01" id="total_amount" name="total_amount" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="supplier_remarks">
                        給供應商的備註
                    </label>
                    <textarea id="supplier_remarks" name="supplier_remarks" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="notes">
                        給自己的備註
                    </label>
                    <textarea id="notes" name="notes" placeholder="給自己的備註（不會顯示在採購單上）" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                </div>
                <div class="flex justify-end">
                    <button type="button" onclick="closeModal('createOrderModal')" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded mr-2">
                        取消
                    </button>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        創建訂單
                    </button>
                </div>
            </form>
        </div>
    </div>

            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 設置預設日期為今天
        var today = new Date().toISOString().split('T')[0];
        document.getElementById('order_date').value = today;

        // 監聽產品選擇變化，自動填充採購價
        document.getElementById('productList').addEventListener('change', function(event) {
            if (event.target.tagName === 'SELECT') {
                var selectedOption = event.target.options[event.target.selectedIndex];
                var price = selectedOption.getAttribute('data-price');
                var priceInput = event.target.parentElement.querySelector('input[name$="[price]"]');
                priceInput.value = price;
                updateTotalAmount();
            }
        });

        // 初始添加一個產品行
        addProduct();
    });

    function openCreateOrderModal() {
        document.getElementById('createOrderModal').style.display = 'block';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    function addProduct() {
        const productList = document.getElementById('productList');
        const productCount = productList.children.length;

        const productDiv = document.createElement('div');
        productDiv.className = 'mb-4 flex items-center';
        productDiv.innerHTML = `
            <select name="products[${productCount}][id]" required class="shadow appearance-none border rounded w-1/3 py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline mr-2">
                <option value="">選擇產品</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?php echo $product['id']; ?>" data-price="<?php echo $product['purchase_price']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="number" name="products[${productCount}][quantity]" placeholder="數量" required class="shadow appearance-none border rounded w-1/4 py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline mr-2">
            <input type="number" step="0.01" name="products[${productCount}][price]" placeholder="單價" required class="shadow appearance-none border rounded w-1/4 py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline mr-2" readonly>
            <button type="button" onclick="removeProduct(this)" class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded">
                刪除
            </button>
        `;

        productList.appendChild(productDiv);
        updateTotalAmount();
    }

    function removeProduct(button) {
        button.parentElement.remove();
        updateTotalAmount();
    }

    function updateTotalAmount() {
        let total = 0;
        const products = document.querySelectorAll('#productList > div');
        products.forEach(product => {
            const quantity = product.querySelector('input[name$="[quantity]"]').value;
            const price = product.querySelector('input[name$="[price]"]').value;
            if (quantity && price) {
                total += quantity * price;
            }
        });
        document.getElementById('total_amount').value = total.toFixed(2);
    }

    function viewOrderDetails(orderId) {
        // 這裡可以實現查看訂單詳情的功能
        alert('查看訂單 ' + orderId + ' 的詳情');
    }

    function updateOrderStatus(orderId, newStatus) {
        // 這裡應該發送 AJAX 請求來更新訂單狀態
        // 這是一個簡單的示例，實際應用中應該使用 fetch 或 XMLHttpRequest
        alert('訂單 ' + orderId + ' 的狀態已更新為 ' + newStatus);
        // 在實際應用中，你應該在這裡重新加載頁面或更新 DOM
    }
    </script>
</body>
</html>
