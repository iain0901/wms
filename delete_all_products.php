<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

requireLogin();

$user_id = getCurrentUserId();

// 開始事務
$conn->begin_transaction();

try {
    // 首先刪除相關的 inventory_operations 記錄
    $stmt = $conn->prepare("DELETE io FROM inventory_operations io
                            INNER JOIN products p ON io.product_id = p.id
                            WHERE p.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    // 然後刪除產品
    $stmt = $conn->prepare("DELETE FROM products WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    // 如果一切順利，提交事務
    $conn->commit();
    $_SESSION['success_message'] = "所有商品及相關操作記錄已成功刪除。";
} catch (Exception $e) {
    // 如果出現錯誤，回滾事務
    $conn->rollback();
    $_SESSION['error_message'] = "刪除商品時發生錯誤：" . $e->getMessage();
}

header("Location: product_list.php");
exit();
?>
