<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

requireLogin();

$user_id = getCurrentUserId();
$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: product_list.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $barcode = $_POST['barcode'];
    $stock = $_POST['stock'];
    $price = $_POST['price'];
    $purchase_price = $_POST['purchase_price'];
    
    $image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = array("jpg" => "image/jpg", "jpeg" => "image/jpeg", "gif" => "image/gif", "png" => "image/png");
        $filename = $_FILES["image"]["name"];
        $filetype = $_FILES["image"]["type"];
        $filesize = $_FILES["image"]["size"];
    
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if (!array_key_exists($ext, $allowed)) die("錯誤：請選擇一個有效的文件格式。");
    
        $maxsize = 5 * 1024 * 1024;
        if ($filesize > $maxsize) die("錯誤：文件大小超過限制。");
    
        if (in_array($filetype, $allowed)) {
            $image = "uploads/" . uniqid() . ".$ext";
            move_uploaded_file($_FILES["image"]["tmp_name"], $image);
            
            // 刪除舊圖片
            $stmt = $conn->prepare("SELECT image FROM products WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $old_image = $result->fetch_assoc()['image'];
            if ($old_image && file_exists($old_image)) {
                unlink($old_image);
            }
        } else {
            $error = "錯誤：文件類型不允許。";
        }
    }

    $stmt = $conn->prepare("UPDATE products SET name = ?, barcode = ?, stock = ?, price = ?, purchase_price = ?" . ($image ? ", image = ?" : "") . " WHERE id = ? AND user_id = ?");
    if ($image) {
        $stmt->bind_param("ssiddsii", $name, $barcode, $stock, $price, $purchase_price, $image, $id, $user_id);
    } else {
        $stmt->bind_param("ssiddii", $name, $barcode, $stock, $price, $purchase_price, $id, $user_id);
    }

    if ($stmt->execute()) {
        header("Location: product_list.php");
        exit();
    } else {
        $error = "更新商品失敗，請稍後再試";
    }
} else {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    if (!$product) {
        header("Location: product_list.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>編輯商品 - 庫存管理系統</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include "navbar.php"; ?>


    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-bold mb-4">編輯商品</h1>
        <?php if (isset($error)): ?>
            <p class="text-red-500 mb-4"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="name">
                    商品名稱
                </label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="name" type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="barcode">
                    商品條碼
                </label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="barcode" type="text" name="barcode" value="<?php echo htmlspecialchars($product['barcode']); ?>" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="stock">
                    庫存
                </label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="stock" type="number" name="stock" value="<?php echo htmlspecialchars($product['stock']); ?>" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="price">
                    售價
                </label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="price" type="number" step="0.01" name="price" value="<?php echo htmlspecialchars($product['price']); ?>" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="purchase_price">
                    採購價
                </label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="purchase_price" type="number" step="0.01" name="purchase_price" value="<?php echo htmlspecialchars($product['purchase_price']); ?>" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="image">
                    商品圖片
                </label>
                <?php if ($product['image']): ?>
                    <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="Current product image" class="mb-2 max-w-xs">
                <?php endif; ?>
                <input type="file" name="image" id="image" accept="image/*" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="flex items-center justify-between">
                <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit">
                    更新商品
                </button>
                <a class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800" href="product_list.php">
                    返回商品列表
                </a>
            </div>
        </form>
    </div>
</body>
</html>
