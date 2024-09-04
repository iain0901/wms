<?php
include "navbar.php"; ?>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';
require_once 'auth.php';

requireLogin();

$user_id = getCurrentUserId();
$message = '';

// 處理導出請求
if (isset($_POST['export'])) {
    set_time_limit(300); // 設置腳本執行時間限制為5分鐘
    ini_set('memory_limit', '256M'); // 增加內存限制

    $export_file = '/www/wwwroot/142.171.36.4/exports/products_export_' . date('Y-m-d_His') . '.csv';
    
    // 確保導出目錄存在
    if (!file_exists('/www/wwwroot/142.171.36.4/exports')) {
        mkdir('/www/wwwroot/142.171.36.4/exports', 0755, true);
    }

    $file = fopen($export_file, 'w');
    if ($file === false) {
        die("無法創建文件。請檢查目錄權限。");
    }

    fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    fputcsv($file, array('商品名稱', '條碼', '庫存', '售價', '採購價'));

    $stmt = $conn->prepare("SELECT name, barcode, stock, price, purchase_price FROM products WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $count = 0;
    while ($row = $result->fetch_assoc()) {
        fputcsv($file, $row);
        $count++;
    }
    fclose($file);

    $message = "已成功生成包含 $count 條記錄的導出文件。<a href='/exports/" . basename($export_file) . "' download>點擊此處下載</a>";
}

// 處理導入請求
if (isset($_POST['import'])) {
    // ... [保持原有的導入邏輯不變] ...
}

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品數據導入/導出 - 庫存管理系統</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-bold mb-4">商品數據導入/導出</h1>
        
        <?php if (!empty($message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            <h2 class="text-2xl font-bold mb-4">導出商品數據</h2>
            <form method="POST">
                <button type="submit" name="export" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    生成CSV導出文件
                </button>
            </form>
        </div>

        <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            <h2 class="text-2xl font-bold mb-4">導入商品數據</h2>
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="csv_file">
                        選擇CSV文件
                    </label>
                    <input type="file" name="csv_file" id="csv_file" accept=".csv" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <button type="submit" name="import" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    導入CSV
                </button>
            </form>
        </div>

        <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            <h2 class="text-2xl font-bold mb-4">CSV格式說明</h2>
            <p class="mb-2">CSV文件應包含以下列：</p>
            <ul class="list-disc list-inside mb-4">
                <li>商品名稱</li>
                <li>條碼</li>
                <li>庫存</li>
                <li>售價</li>
                <li>採購價</li>
            </ul>
            <p>第一行應為列標題。數據從第二行開始。請確保CSV文件使用UTF-8編碼。</p>
        </div>
    </div>
</body>
</html>
