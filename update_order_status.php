<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];
    $user_id = getCurrentUserId();

    $stmt = $conn->prepare("UPDATE purchase_orders SET status = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sii", $new_status, $order_id, $user_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => '狀態更新成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '狀態更新失敗']);
    }
} else {
    echo json_encode(['success' => false, 'message' => '無效的請求方法']);
}
