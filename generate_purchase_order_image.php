<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

error_reporting(E_ALL);
ini_set("display_errors", 1);
ini_set("log_errors", 1);
ini_set("error_log", "/tmp/php_errors.log");

requireLogin();

if (!isset($_GET['id'])) {
    error_log("訂單 ID 未提供");
    die("訂單 ID 未提供");
}

$order_id = $_GET['id'];
$user_id = getCurrentUserId();

// 獲取訂單信息
$stmt = $conn->prepare("SELECT po.*, s.name as supplier_name FROM purchase_orders po 
                        JOIN suppliers s ON po.supplier_id = s.id
                        WHERE po.id = ? AND po.user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();

if (!$order) {
    error_log("訂單不存在或無權訪問: Order ID = $order_id, User ID = $user_id");
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

// 創建圖片
$width = 800;
$height = 800 + (count($items) * 30);
$image = imagecreatetruecolor($width, $height);
if (!$image) {
    error_log("無法創建圖片");
    die("無法創建圖片");
}

// 設置顏色
$white = imagecolorallocate($image, 255, 255, 255);
$black = imagecolorallocate($image, 0, 0, 0);
$gray = imagecolorallocate($image, 200, 200, 200);
$red = imagecolorallocate($image, 255, 0, 0);

// 填充背景
imagefill($image, 0, 0, $white);

// 添加標題
$font = '/www/wwwroot/142.171.36.4/fonts/NotoSansCJKtc-Regular.otf';
if (!file_exists($font)) {
    error_log("字體文件不存在: $font");
    die("字體文件不存在");
}

imagettftext($image, 20, 0, 20, 40, $black, $font, "採購單");

// 添加訂單信息
imagettftext($image, 12, 0, 20, 80, $black, $font, "訂單 ID: " . $order['id']);
imagettftext($image, 12, 0, 20, 100, $black, $font, "供應商: " . $order['supplier_name']);
imagettftext($image, 12, 0, 20, 120, $black, $font, "日期: " . $order['order_date']);
imagettftext($image, 12, 0, 20, 140, $black, $font, "狀態: " . $order['status']);

// 添加表格標題
imagettftext($image, 12, 0, 20, 180, $black, $font, "產品");
imagettftext($image, 12, 0, 300, 180, $black, $font, "數量");
imagettftext($image, 12, 0, 400, 180, $black, $font, "單價");
imagettftext($image, 12, 0, 500, 180, $black, $font, "總價");

// 畫線
imageline($image, 20, 190, $width - 20, 190, $gray);

// 添加項目
$y = 220;
foreach ($items as $item) {
    imagettftext($image, 12, 0, 20, $y, $black, $font, $item['product_name']);
    imagettftext($image, 12, 0, 300, $y, $black, $font, $item['quantity']);
    imagettftext($image, 12, 0, 400, $y, $black, $font, $item['unit_price']);
    imagettftext($image, 12, 0, 500, $y, $black, $font, $item['quantity'] * $item['unit_price']);
    $y += 30;
}

// 添加總金額
imagettftext($image, 14, 0, 400, $y + 40, $black, $font, "總金額: " . $order['total_amount']);

// 添加給供應商的備註
$supplier_remarks = wordwrap($order['supplier_remarks'] ?? "", 50, "\n");
$supplier_remarks_lines = explode("\n", $supplier_remarks);
imagettftext($image, 14, 0, 20, $y + 100, $black, $font, "給供應商的備註:");
$y += 130;
foreach ($supplier_remarks_lines as $line) {
    imagettftext($image, 12, 0, 20, $y, $black, $font, $line);
    $y += 20;
}

// 添加給自己的備註
$notes = wordwrap($order['notes'] ?? "", 50, "\n");
$notes_lines = explode("\n", $notes);
imagettftext($image, 14, 0, 20, $y + 40, $red, $font, "給自己的備註 (不會顯示在打印版本):");
$y += 70;
foreach ($notes_lines as $line) {
    imagettftext($image, 12, 0, 20, $y, $red, $font, $line);
    $y += 20;
}

// 保存圖片
$image_dir = '/www/wwwroot/142.171.36.4/purchase_order_images/';
if (!file_exists($image_dir)) {
    if (!mkdir($image_dir, 0755, true)) {
        error_log("無法創建圖片目錄: $image_dir");
        die("無法創建圖片目錄");
    }
    system("chmod 777 $image_dir");
}
$image_filename = 'purchase_order_' . $order_id . '.png';
$image_path = $image_dir . $image_filename;

if (!imagepng($image, $image_path)) {
    error_log("無法保存圖片: $image_path");
    die("無法保存圖片");
}
imagedestroy($image);

// 檢查文件是否成功創建
if (!file_exists($image_path)) {
    error_log("圖片文件未成功創建: $image_path");
    die("生成圖片時出錯");
}

// 獲取文件大小
$filesize = filesize($image_path);
if ($filesize === false || $filesize == 0) {
    error_log("圖片文件大小為零或無法獲取: $image_path");
    die("生成的圖片文件無效");
}

// 提供下載
header('Content-Description: File Transfer');
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="' . $image_filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . $filesize);

if (readfile($image_path) === false) {
    error_log("無法讀取文件: $image_path");
    die("無法讀取生成的圖片文件");
}
exit;
