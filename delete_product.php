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

$stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $id, $user_id);

if ($stmt->execute()) {
    $_SESSION['success_message'] = "商品已成功刪除。";
} else {
    $_SESSION['error_message'] = "刪除商品失敗，請稍後再試。";
}

header("Location: product_list.php");
exit();
?>
