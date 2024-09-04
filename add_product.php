<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

requireLogin();

$user_id = getCurrentUserId();
$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = isset($_POST['name']) ? $_POST['name'] : '';
    $barcode = isset($_POST['barcode']) ? $_POST['barcode'] : '';
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0.0;
    $purchase_price = isset($_POST['purchase_price']) ? floatval($_POST['purchase_price']) : 0.0;
    
    $image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = array("jpg" => "image/jpg", "jpeg" => "image/jpeg", "gif" => "image/gif", "png" => "image/png");
        $filename = $_FILES["image"]["name"];
        $filetype = $_FILES["image"]["type"];
        $filesize = $_FILES["image"]["size"];
    
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if (!array_key_exists($ext, $allowed)) {
            $message = "錯誤：請選擇一個有效的文件格式。";
        } else {
            $maxsize = 30 * 1024 * 1024; // 30MB
            if ($filesize > $maxsize) {
                $message = "錯誤：文件大小超過限制（最大30MB）。";
            } else {
                $image = "uploads/" . uniqid() . ".$ext";
                $upload_path = $_SERVER['DOCUMENT_ROOT'] . '/' . $image;
                
                if (move_uploaded_file($_FILES["image"]["tmp_name"], $upload_path)) {
                    $stmt = $conn->prepare("INSERT INTO products (name, barcode, stock, price, purchase_price, image, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssiddsi", $name, $barcode, $stock, $price, $purchase_price, $image, $user_id);

                    if ($stmt->execute()) {
                        $message = "商品添加成功。";
                    } else {
                        $message = "添加商品失敗，請稍後再試。";
                    }
                } else {
                    $message = "文件上傳失敗，請稍後再試。";
                }
            }
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO products (name, barcode, stock, price, purchase_price, user_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiddi", $name, $barcode, $stock, $price, $purchase_price, $user_id);

        if ($stmt->execute()) {
            $message = "商品添加成功（無圖片）。";
        } else {
            $message = "添加商品失敗，請稍後再試。";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新增商品 - 庫存管理系統</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include "navbar.php"; ?>


    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-bold mb-4">新增商品</h1>
        <?php if ($message != ''): ?>
            <p class="mb-4 p-4 bg-green-100 text-green-700 rounded"><?php echo $message; ?></p>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="name">
                    商品名稱
                </label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="name" type="text" name="name" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="barcode">
                    商品條碼
                </label>
                <div class="flex">
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="barcode" type="text" name="barcode" required>
                    <button type="button" class="ml-2 bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded" onclick="generateEAN13()">
                        生成條碼
                    </button>
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="stock">
                    初始庫存
                </label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="stock" type="number" name="stock" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="price">
                    售價
                </label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="price" type="number" step="0.01" name="price" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="purchase_price">
                    採購價
                </label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="purchase_price" type="number" step="0.01" name="purchase_price" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="image">
                    商品圖片 (最大 30MB)
                </label>
                <input type="file" name="image" id="image" accept="image/*" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="flex items-center justify-between">
                <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit">
                    新增商品
                </button>
                <a class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800" href="product_list.php">
                    返回商品列表
                </a>
            </div>
        </form>
    </div>
    <script>
    function generateEAN13() {
        var ean = '20'; // 假設國家碼為 20
        for(var i = 0; i < 10; i++) {
            ean += Math.floor(Math.random() * 10);
        }
        var sum = 0;
        for(var i = 0; i < 12; i++) {
            sum += parseInt(ean[i]) * (i % 2 === 0 ? 1 : 3);
        }
        var checkDigit = (10 - (sum % 10)) % 10;
        ean += checkDigit;
        document.getElementById('barcode').value = ean;
    }
    </script>
</body>
</html>
