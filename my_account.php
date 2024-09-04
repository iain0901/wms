<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

requireLogin();

$user_id = getCurrentUserId();
$message = '';

// 獲取當前用戶信息
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// 處理密碼更改請求
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // 驗證當前密碼
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();

    if (password_verify($current_password, $user_data['password'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->bind_param("si", $hashed_password, $user_id);
            if ($update_stmt->execute()) {
                $message = "密碼已成功更新。";
            } else {
                $message = "更新密碼時發生錯誤。";
            }
        } else {
            $message = "新密碼和確認密碼不匹配。";
        }
    } else {
        $message = "當前密碼不正確。";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的帳號 - 庫存管理系統</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include "navbar.php"; ?>


    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-bold mb-4">我的帳號</h1>
        
        <?php if (!empty($message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            <h2 class="text-2xl font-bold mb-4">帳號信息</h2>
            <p class="mb-4">用戶名: <?php echo htmlspecialchars($user['username']); ?></p>
        </div>

        <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            <h2 class="text-2xl font-bold mb-4">更改密碼</h2>
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="current_password">
                        當前密碼
                    </label>
                    <input type="password" id="current_password" name="current_password" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="new_password">
                        新密碼
                    </label>
                    <input type="password" id="new_password" name="new_password" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="confirm_password">
                        確認新密碼
                    </label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <button type="submit" name="change_password" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    更改密碼
                </button>
            </form>
        </div>
    </div>
</body>
</html>
