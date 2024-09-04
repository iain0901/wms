<?php
include "navbar.php"; ?>
<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

requireLogin();

$user_id = getCurrentUserId();

// 獲取所有商品
function getAllProducts($conn, $user_id) {
    $stmt = $conn->prepare("SELECT id, name, barcode, stock FROM products WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// 更新盤點數據
function updateInventory($conn, $user_id, $inventory_data) {
    $stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ? AND user_id = ?");
    foreach ($inventory_data as $product_id => $new_stock) {
        $stmt->bind_param("iii", $new_stock, $product_id, $user_id);
        $stmt->execute();
    }
}

$products = getAllProducts($conn, $user_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['apply_inventory'])) {
        updateInventory($conn, $user_id, $_SESSION['inventory_check']);
        $_SESSION['message'] = "盤點數據已成功應用到商品庫存。";
        unset($_SESSION['inventory_check']);
        header("Location: inventory_check.php");
        exit;
    } elseif (isset($_POST['reset_inventory'])) {
        unset($_SESSION['inventory_check']);
        $_SESSION['message'] = "所有商品已重置為未盤點狀態。";
        header("Location: inventory_check.php");
        exit;
    } elseif (isset($_POST['barcode'])) {
        $barcode = $_POST['barcode'];
        foreach ($products as $product) {
            if ($product['barcode'] === $barcode) {
                if (!isset($_SESSION['inventory_check'][$product['id']])) {
                    $_SESSION['inventory_check'][$product['id']] = 1;
                } else {
                    $_SESSION['inventory_check'][$product['id']]++;
                }
                $_SESSION['message'] = "商品 '{$product['name']}' 盤點數量 +1";
                break;
            }
        }
    } elseif (isset($_POST['manual_check'])) {
        $product_id = $_POST['product_id'];
        $new_stock = $_POST['new_stock'];
        $_SESSION['inventory_check'][$product_id] = $new_stock;
    }
}

$checked_products = $_SESSION['inventory_check'] ?? [];

// 排序產品
usort($products, function($a, $b) use ($checked_products) {
    $a_checked = isset($checked_products[$a['id']]);
    $b_checked = isset($checked_products[$b['id']]);
    
    if (!$a_checked && !$b_checked) return 0;
    if (!$a_checked) return -1;
    if (!$b_checked) return 1;
    
    $a_diff = $checked_products[$a['id']] - $a['stock'];
    $b_diff = $checked_products[$b['id']] - $b['stock'];
    
    if ($a_diff < 0 && $b_diff >= 0) return -1;
    if ($a_diff >= 0 && $b_diff < 0) return 1;
    if ($a_diff == 0 && $b_diff != 0) return -1;
    if ($a_diff != 0 && $b_diff == 0) return 1;
    if ($a_diff > 0 && $b_diff <= 0) return 1;
    if ($a_diff <= 0 && $b_diff > 0) return -1;
    
    return 0;
});

?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品盤點 - 庫存管理系統</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .bg-yellow-100 { background-color: #fef3c7; }
        .bg-green-100 { background-color: #d1fae5; }
        .bg-blue-100 { background-color: #dbeafe; }
    </style>
</head>
<body class="bg-gray-100">

    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-bold mb-4">商品盤點</h1>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $_SESSION['message']; ?></span>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <div class="mb-4 flex justify-between items-center">
            <form method="POST" class="flex items-center">
                <input type="text" name="barcode" placeholder="掃描商品條碼" 
                       class="shadow appearance-none border rounded py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline mr-2">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    掃描
                </button>
            </form>
            <form method="POST">
                <button type="submit" name="reset_inventory" class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded">
                    全部重新盤點
                </button>
            </form>
        </div>

        <div class="mb-4 bg-gray-200 p-4 rounded">
            <h2 class="font-bold mb-2">顏色說明：</h2>
            <div class="flex items-center mb-2">
                <div class="w-4 h-4 bg-white mr-2"></div>
                <span>未盤點</span>
            </div>
            <div class="flex items-center mb-2">
                <div class="w-4 h-4 bg-yellow-100 mr-2"></div>
                <span>盤點數量少於原庫存</span>
            </div>
            <div class="flex items-center mb-2">
                <div class="w-4 h-4 bg-blue-100 mr-2"></div>
                <span>盤點數量等於原庫存</span>
            </div>
            <div class="flex items-center">
                <div class="w-4 h-4 bg-green-100 mr-2"></div>
                <span>盤點數量多於原庫存</span>
            </div>
        </div>

        <table class="min-w-full bg-white">
            <thead>
                <tr>
                    <th class="py-2 px-4 border-b">商品名稱</th>
                    <th class="py-2 px-4 border-b">條碼</th>
                    <th class="py-2 px-4 border-b">目前庫存</th>
                    <th class="py-2 px-4 border-b">盤點庫存</th>
                    <th class="py-2 px-4 border-b">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <?php 
                    $is_checked = isset($checked_products[$product['id']]);
                    $checked_stock = $is_checked ? $checked_products[$product['id']] : null;
                    $row_class = '';
                    if ($is_checked) {
                        if ($checked_stock < $product['stock']) {
                            $row_class = 'bg-yellow-100';
                        } elseif ($checked_stock > $product['stock']) {
                            $row_class = 'bg-green-100';
                        } else {
                            $row_class = 'bg-blue-100';
                        }
                    }
                    ?>
                    <tr class="<?php echo $row_class; ?>">
                        <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($product['name']); ?></td>
                        <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($product['barcode']); ?></td>
                        <td class="py-2 px-4 border-b"><?php echo $product['stock']; ?></td>
                        <td class="py-2 px-4 border-b">
                            <?php echo $is_checked ? $checked_stock : '-'; ?>
                        </td>
                        <td class="py-2 px-4 border-b">
                            <form method="POST" class="flex items-center">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <input type="number" name="new_stock" value="<?php echo $checked_stock ?? 0; ?>" 
                                       class="shadow appearance-none border rounded py-1 px-2 text-gray-700 leading-tight focus:outline-none focus:shadow-outline mr-2" style="width: 60px;">
                                <button type="submit" name="manual_check" class="bg-green-500 hover:bg-green-700 text-white font-bold py-1 px-2 rounded text-sm">
                                    確認
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="mt-4">
            <form method="POST">
                <button type="submit" name="apply_inventory" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    應用這次的盤點數據到商品庫存
                </button>
            </form>
        </div>
    </div>

    <script>
        // 自動聚焦到掃描輸入框
        window.onload = function() {
            document.querySelector('input[name="barcode"]').focus();
        };
    </script>
</body>
</html>
