<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

requireLogin();

$user_id = getCurrentUserId();

$search = isset($_GET['search']) ? $_GET['search'] : '';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$start = ($page - 1) * $perPage;

$searchCondition = "";
if (!empty($search)) {
    $searchCondition = " AND (name LIKE ? OR barcode LIKE ?)";
}

$searchParam = "%$search%";

$stmt = $conn->prepare("SELECT * FROM products WHERE user_id = ?" . $searchCondition . " ORDER BY id DESC LIMIT ? OFFSET ?");
if (!empty($search)) {
    $stmt->bind_param("issii", $user_id, $searchParam, $searchParam, $perPage, $start);
} else {
    $stmt->bind_param("iii", $user_id, $perPage, $start);
}
$stmt->execute();
$result = $stmt->get_result();
$products = $result->fetch_all(MYSQLI_ASSOC);

$totalStmt = $conn->prepare("SELECT COUNT(*) as total FROM products WHERE user_id = ?" . $searchCondition);
if (!empty($search)) {
    $totalStmt->bind_param("iss", $user_id, $searchParam, $searchParam);
} else {
    $totalStmt->bind_param("i", $user_id);
}
$totalStmt->execute();
$totalResult = $totalStmt->get_result();
$totalRow = $totalResult->fetch_assoc();
$totalProducts = $totalRow['total'];
$totalPages = ceil($totalProducts / $perPage);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品列表 - 庫存管理系統</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .table-header {
            background-color: #f3f4f6;
            font-weight: bold;
        }
        .table-row:nth-child(even) {
            background-color: #f9fafb;
        }
        .table-row:hover {
            background-color: #e5e7eb;
        }
    </style>
</head>
<body class="bg-gray-100">
<?php include "navbar.php"; ?>


    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-bold mb-4">商品列表</h1>
        
        <form method="GET" class="mb-4">
            <div class="flex items-center">
                <input type="text" name="search" placeholder="搜尋商品名稱或條碼" value="<?php echo htmlspecialchars($search); ?>"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline mr-2">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    搜尋
                </button>
            </div>
        </form>

        <?php
        if (isset($_SESSION['success_message'])) {
            echo "<p class='text-green-500 mb-4'>" . $_SESSION['success_message'] . "</p>";
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo "<p class='text-red-500 mb-4'>" . $_SESSION['error_message'] . "</p>";
            unset($_SESSION['error_message']);
        }
        ?>
        <div class="flex justify-between items-center mb-4">
            <a href="add_product.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">新增商品</a>
            <div>
                <button onclick="confirmDeleteAll()" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline mr-2">刪除所有商品</button>
                <button onclick="confirmClearStock()" class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">清除所有庫存</button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white shadow-md rounded-lg overflow-hidden">
                <thead>
                    <tr class="table-header">
                        <th class="py-3 px-4 text-left">縮圖</th>
                        <th class="py-3 px-4 text-left">名稱</th>
                        <th class="py-3 px-4 text-left">條碼</th>
                        <th class="py-3 px-4 text-left">庫存</th>
                        <th class="py-3 px-4 text-left">售價</th>
                        <th class="py-3 px-4 text-left">採購價</th>
                        <th class="py-3 px-4 text-left">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <tr class="table-row">
                        <td class="py-3 px-4 border-b">
                            <?php if ($product['image']): ?>
                                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-16 h-16 object-cover">
                            <?php else: ?>
                                <div class="w-16 h-16 bg-gray-200 flex items-center justify-center">無圖片</div>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 border-b"><?php echo htmlspecialchars($product['name']); ?></td>
                        <td class="py-3 px-4 border-b"><?php echo htmlspecialchars($product['barcode']); ?></td>
                        <td class="py-3 px-4 border-b"><?php echo htmlspecialchars($product['stock']); ?></td>
                        <td class="py-3 px-4 border-b"><?php echo number_format($product['price'], 2); ?></td>
                        <td class="py-3 px-4 border-b"><?php echo number_format($product['purchase_price'], 2); ?></td>
                        <td class="py-3 px-4 border-b">
                            <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="text-blue-500 hover:text-blue-700">編輯</a>
                            <a href="delete_product.php?id=<?php echo $product['id']; ?>" class="text-red-500 hover:text-red-700 ml-2" onclick="return confirm('確定要刪除這個商品嗎？');">刪除</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            <?php
            $queryParams = $search ? "search=" . urlencode($search) . "&" : "";
            for ($i = 1; $i <= $totalPages; $i++):
            ?>
                <a href="?<?php echo $queryParams; ?>page=<?php echo $i; ?>"
                   class="inline-block bg-blue-500 text-white px-3 py-1 rounded <?php echo $i === $page ? 'bg-blue-700' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
    <script>
    function confirmDeleteAll() {
        Swal.fire({
            title: '確定要刪除所有商品嗎？',
            text: "此操作無法復原！",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: '是的，刪除所有商品！',
            cancelButtonText: '取消'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'delete_all_products.php';
            }
        })
    }

    function confirmClearStock() {
        Swal.fire({
            title: '確定要清除所有庫存嗎？',
            text: "此操作將把所有商品的庫存設為0！",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: '是的，清除所有庫存！',
            cancelButtonText: '取消'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'clear_all_stock.php';
            }
        })
    }
    </script>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1263866510884022"
     crossorigin="anonymous"></script>
<!-- 728成90 -->
<ins class="adsbygoogle"
     style="display:inline-block;width:728px;height:90px"
     data-ad-client="ca-pub-1263866510884022"
     data-ad-slot="4501375100"></ins>
<script>
     (adsbygoogle = window.adsbygoogle || []).push({});
</script>
</body>
</html>
