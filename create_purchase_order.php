<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

// 啟用錯誤報告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 檢查數據庫連接
if (!isset($conn) || !$conn) {
    die("數據庫連接失敗: " . mysqli_connect_error());
}

requireLogin();

$user_id = getCurrentUserId();

// 檢查用戶 ID
if (!$user_id) {
    die("無法獲取用戶 ID");
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
    $status = $_POST['status'];

    if (empty($supplier_id) || empty($order_date) || empty($total_amount) || empty($_POST['products'])) {
        $_SESSION['error_message'] = "請填寫所有必要的欄位。";
    } else {
        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("INSERT INTO purchase_orders (user_id, supplier_id, order_date, status, total_amount, notes, supplier_remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissdss", $user_id, $supplier_id, $order_date, $status, $total_amount, $notes, $supplier_remarks);
            
            if ($stmt->execute()) {
                $order_id = $conn->insert_id;
                foreach ($_POST['products'] as $product) {
                    $product_id = $product['id'];
                    $quantity = $product['quantity'];
                    $unit_price = $product['price'];

                    if (!empty($product_id) && $product_id != 0) {
                        $stmt = $conn->prepare("INSERT INTO purchase_order_items (purchase_order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("iiid", $order_id, $product_id, $quantity, $unit_price);
                        $stmt->execute();
                    }
                }
                $conn->commit();
                $_SESSION['success_message'] = "採購訂單創建成功";
                header("Location: purchase_management.php");
                exit();
            } else {
                throw new Exception("創建採購訂單失敗");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_message'] = "錯誤：" . $e->getMessage();
        }
    }
}

// 獲取供應商列表
$supplierStmt = $conn->prepare("SELECT id, name FROM suppliers WHERE user_id = ?");
$supplierStmt->bind_param("i", $user_id);
$supplierStmt->execute();
$supplierResult = $supplierStmt->get_result();
$suppliers = $supplierResult->fetch_all(MYSQLI_ASSOC);

// 獲取產品列表
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
    <title>創建新採購訂單 - 庫存管理系統</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include "navbar.php"; ?>

    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-bold mb-4">創建新採購訂單</h1>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $_SESSION['error_message']; ?></span>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <form id="orderForm" method="POST" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            <input type="hidden" name="create_order" value="1">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="supplier_id">
                    供應商
                </label>
                <select id="supplier_id" name="supplier_id" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">選擇供應商</option>
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
                        <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="productList" class="mb-4">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b">商品名稱</th>
                            <th class="py-2 px-4 border-b">數量</th>
                            <th class="py-2 px-4 border-b">單價</th>
                            <th class="py-2 px-4 border-b">操作</th>
                        </tr>
                    </thead>
                    <tbody id="productTableBody">
                        <!-- 商品行將在這裡動態添加 -->
                    </tbody>
                </table>
            </div>
            <div class="mb-4">
                <input type="text" id="newProductName" placeholder="商品名稱" class="shadow appearance-none border rounded py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline mr-2">
                <input type="number" id="newProductQuantity" placeholder="數量" class="shadow appearance-none border rounded py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline mr-2">
                <input type="number" step="0.01" id="newProductPrice" placeholder="單價" class="shadow appearance-none border rounded py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline mr-2">
                <button type="button" onclick="addNewProduct()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">添加商品</button>
            </div>
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
            <div class="flex items-center justify-between">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    創建訂單
                </button>
                <a href="purchase_management.php" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                    返回採購管理
                </a>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 設置預設日期為今天
        var today = new Date().toISOString().split('T')[0];
        document.getElementById('order_date').value = today;

        // 初始化商品搜尋
        initProductSearch();
    });

    function initProductSearch() {
        const productInput = document.getElementById('newProductName');
        const productList = document.createElement('ul');
        productList.className = 'absolute z-10 bg-white border border-gray-300 w-full mt-1 rounded-md shadow-lg hidden';
        productInput.parentNode.appendChild(productList);

        const products = <?php echo json_encode($products); ?>;

        productInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const filteredProducts = products.filter(product => 
                product.name.toLowerCase().includes(searchTerm)
            );

            productList.innerHTML = '';
            productList.classList.remove('hidden');

            filteredProducts.forEach(product => {
                const li = document.createElement('li');
                li.className = 'p-2 hover:bg-gray-100 cursor-pointer';
                li.textContent = product.name;
                li.onclick = function() {
                    productInput.value = product.name;
                    document.getElementById('newProductPrice').value = product.purchase_price;
                    productList.classList.add('hidden');
                };
                productList.appendChild(li);
            });

            if (filteredProducts.length === 0) {
                productList.classList.add('hidden');
            }
        });

        document.addEventListener('click', function(e) {
            if (e.target !== productInput) {
                productList.classList.add('hidden');
            }
        });
    }

    function addNewProduct() {
        const name = document.getElementById('newProductName').value.trim();
        const quantity = document.getElementById('newProductQuantity').value.trim();
        const price = document.getElementById('newProductPrice').value.trim();

        if (name && quantity && price) {
            const productTableBody = document.getElementById('productTableBody');
            const rowCount = productTableBody.children.length;

            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td class="py-2 px-4 border-b">
                    <input type="text" name="products[${rowCount}][name]" value="${name}" readonly class="bg-gray-100 shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight">
                </td>
                <td class="py-2 px-4 border-b">
                    <input type="number" name="products[${rowCount}][quantity]" value="${quantity}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </td>
                <td class="py-2 px-4 border-b">
                    <input type="number" step="0.01" name="products[${rowCount}][price]" value="${price}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </td>
                <td class="py-2 px-4 border-b">
                    <button type="button" onclick="removeProduct(this)" class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded">刪除</button>
                </td>
            `;

            productTableBody.appendChild(newRow);
            updateTotalAmount();

            // 清空輸入框
            document.getElementById('newProductName').value = '';
            document.getElementById('newProductQuantity').value = '';
            document.getElementById('newProductPrice').value = '';
        } else {
            alert('請填寫所有商品欄位');
        }
    }

    function removeProduct(button) {
        button.closest('tr').remove();
        updateTotalAmount();
    }

    function updateTotalAmount() {
        let total = 0;
        const products = document.querySelectorAll('#productTableBody tr');
        products.forEach(product => {
            const quantity = parseFloat(product.querySelector('input[name$="[quantity]"]').value) || 0;
            const price = parseFloat(product.querySelector('input[name$="[price]"]').value) || 0;
            total += quantity * price;
        });
        document.getElementById('total_amount').value = total.toFixed(2);
    }

    document.getElementById('orderForm').addEventListener('submit', function(e) {
        const products = document.querySelectorAll('#productTableBody tr');
        if (products.length === 0) {
            e.preventDefault();
            alert('請至少添加一個商品');
        }
    });

    // 監聽商品表格的變化
    document.getElementById('productTableBody').addEventListener('input', function(e) {
        if (e.target.name.includes('[quantity]') || e.target.name.includes('[price]')) {
            updateTotalAmount();
        }
    });
</script>
</body>
</html>
