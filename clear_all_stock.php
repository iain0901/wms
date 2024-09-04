<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

requireLogin();

$user_id = getCurrentUserId();

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("UPDATE products SET stock = 0 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $conn->commit();
    $_SESSION['success_message'] = "所有商品庫存已成功清除。";
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_message'] = "清除庫存時發生錯誤：" . $e->getMessage();
}

header("Location: product_list.php");
exit();
?>
