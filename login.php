<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    if (login($username, $password)) {
        header("Location: index.php");
        exit();
    } else {
        $error = "Invalid username or password";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登入 - 庫存管理系統</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include "navbar.php"; ?>

    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded shadow-md w-96">
            <h2 class="text-2xl font-bold mb-4">登入</h2>
            <?php if (isset($error)): ?>
                <p class="text-red-500 mb-4"><?php echo $error; ?></p>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-4">
                    <label for="username" class="block text-gray-700 text-sm font-bold mb-2">用戶名</label>
                    <input type="text" id="username" name="username" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-6">
                    <label for="password" class="block text-gray-700 text-sm font-bold mb-2">密碼</label>
                    <input type="password" id="password" name="password" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="flex items-center justify-between">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        登入
                    </button>
                    <a href="register.php" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                        註冊
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
