<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

requireLogin();

$user_id = getCurrentUserId();

// 創建 suppliers 表（如果不存在）
$create_table_sql = "
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    contact VARCHAR(255),
    phone VARCHAR(50),
    address TEXT,
    status ENUM('active', 'inactive', 'blacklisted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)";

if ($conn->query($create_table_sql) === FALSE) {
    die("Error creating table: " . $conn->error);
}

// 處理添加新供應商
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_supplier'])) {
    $name = $_POST['name'];
    $contact = $_POST['contact'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];

    $stmt = $conn->prepare("INSERT INTO suppliers (name, contact, phone, address, user_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $name, $contact, $phone, $address, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "供應商添加成功";
    } else {
        $_SESSION['error_message'] = "添加供應商失敗";
    }
    
    header("Location: supplier_management.php");
    exit();
}

// 處理編輯供應商
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_supplier'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $contact = $_POST['contact'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];

    $stmt = $conn->prepare("UPDATE suppliers SET name = ?, contact = ?, phone = ?, address = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ssssii", $name, $contact, $phone, $address, $id, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "供應商信息更新成功";
    } else {
        $_SESSION['error_message'] = "更新供應商信息失敗";
    }
    
    header("Location: supplier_management.php");
    exit();
}

// 處理刪除供應商
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_supplier'])) {
    $id = $_POST['id'];
    $stmt = $conn->prepare("DELETE FROM suppliers WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "供應商刪除成功";
    } else {
        $_SESSION['error_message'] = "刪除供應商失敗";
    }
    
    header("Location: supplier_management.php");
    exit();
}

// 處理更新供應商狀態
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $id = $_POST['id'];
    $status = $_POST['status'];
    $stmt = $conn->prepare("UPDATE suppliers SET status = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sii", $status, $id, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "供應商狀態更新成功";
    } else {
        $_SESSION['error_message'] = "更新供應商狀態失敗";
    }
    
    header("Location: supplier_management.php");
    exit();
}

// 獲取供應商列表
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$start = ($page - 1) * $perPage;

// 獲取排序參數
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) ? $_GET['order'] : 'ASC';

$searchCondition = "";
if (!empty($search)) {
    $searchCondition = " AND (name LIKE ? OR contact LIKE ?)";
}

$stmt = $conn->prepare("SELECT * FROM suppliers WHERE user_id = ?" . $searchCondition . " ORDER BY " . $sort . " " . $order . " LIMIT ? OFFSET ?");
if (!empty($search)) {
    $searchParam = "%$search%";
    $stmt->bind_param("issii", $user_id, $searchParam, $searchParam, $perPage, $start);
} else {
    $stmt->bind_param("iii", $user_id, $perPage, $start);
}
$stmt->execute();
$result = $stmt->get_result();
$suppliers = $result->fetch_all(MYSQLI_ASSOC);

// 獲取總供應商數量
$totalStmt = $conn->prepare("SELECT COUNT(*) as total FROM suppliers WHERE user_id = ?" . $searchCondition);
if (!empty($search)) {
    $totalStmt->bind_param("iss", $user_id, $searchParam, $searchParam);
} else {
    $totalStmt->bind_param("i", $user_id);
}
$totalStmt->execute();
$totalResult = $totalStmt->get_result();
$totalRow = $totalResult->fetch_assoc();
$totalSuppliers = $totalRow['total'];
$totalPages = ceil($totalSuppliers / $perPage);
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>供應商管理 - 庫存管理系統</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include "navbar.php"; ?>

    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-bold mb-4">供應商管理</h1>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $_SESSION['success_message']; ?></span>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $_SESSION['error_message']; ?></span>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- 搜索欄 -->
        <form method="GET" class="mb-4">
            <input type="text" name="search" placeholder="搜索供應商名稱或聯繫人" value="<?php echo htmlspecialchars($search); ?>"
                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            <button type="submit" class="mt-2 bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                搜索
            </button>
        </form>

        <!-- 添加供應商按鈕 -->
        <button onclick="openAddModal()" class="mb-4 bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
            添加供應商
        </button>

        <!-- 供應商列表 -->
        <table class="min-w-full bg-white">
            <thead>
                <tr>
                    <th class="py-2 px-4 border-b"><a href="?sort=name&order=<?php echo $sort == 'name' && $order == 'ASC' ? 'DESC' : 'ASC'; ?>">名稱 <?php echo $sort == 'name' ? ($order == 'ASC' ? '▲' : '▼') : ''; ?></a></th>
                    <th class="py-2 px-4 border-b">聯繫人</th>
                    <th class="py-2 px-4 border-b">電話</th>
                    <th class="py-2 px-4 border-b">地址</th>
                    <th class="py-2 px-4 border-b">操作</th>
                    <th class="py-2 px-4 border-b">狀態</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($suppliers as $supplier): ?>
                <tr>
                    <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($supplier['name']); ?></td>
                    <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($supplier['contact']); ?></td>
                    <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($supplier['phone']); ?></td>
                    <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($supplier['address']); ?></td>
                    <td class="py-2 px-4 border-b">
                        <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($supplier)); ?>)" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-2 rounded mr-1">編輯</button>
                        <button onclick="confirmDelete(<?php echo $supplier['id']; ?>)" class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded">刪除</button>
                    </td>
                    <td class="py-2 px-4 border-b">
                        <select onchange="updateStatus(<?php echo $supplier['id']; ?>, this.value)" class="block appearance-none w-full bg-white border border-gray-400 hover:border-gray-500 px-4 py-2 pr-8 rounded shadow leading-tight focus:outline-none focus:shadow-outline">
                            <option value="active" <?php echo $supplier['status'] == 'active' ? 'selected' : ''; ?>>活躍</option>
                            <option value="inactive" <?php echo $supplier['status'] == 'inactive' ? 'selected' : ''; ?>>非活躍</option>
                            <option value="blacklisted" <?php echo $supplier['status'] == 'blacklisted' ? 'selected' : ''; ?>>黑名單</option>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- 分頁 -->
        <div class="mt-4">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>" 
                   class="inline-block bg-blue-500 text-white px-3 py-1 rounded <?php echo $i === $page ? 'bg-blue-700' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>

    <!-- 添加供應商模態框 -->
    <div id="addModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full" style="display: none;">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <h3 class="text-lg font-bold mb-4">添加供應商</h3>
            <form method="POST">
                <input type="hidden" name="add_supplier" value="1">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="name">
                        名稱
                    </label>
                    <input type="text" id="name" name="name" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="contact">
                        聯繫人
                    </label>
                    <input type="text" id="contact" name="contact" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="phone">
                        電話
                    </label>
                    <input type="text" id="phone" name="phone" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="address">
                        地址
                    </label>
                    <input type="text" id="address" name="address" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="flex justify-end">
                    <button type="button" onclick="closeModal('addModal')" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded mr-2">
                        取消
                    </button>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        添加
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 編輯供應商模態框 -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full" style="display: none;">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <h3 class="text-lg font-bold mb-4">編輯供應商</h3>
            <form method="POST">
                <input type="hidden" name="edit_supplier" value="1">
                <input type="hidden" id="edit_id" name="id">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_name">
                        名稱
                    </label>
                    <input type="text" id="edit_name" name="name" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_contact">
                        聯繫人
                    </label>
                    <input type="text" id="edit_contact" name="contact" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_phone">
                        電話
                    </label>
                    <input type="text" id="edit_phone" name="phone" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_address">
                        地址
                    </label>
                    <input type="text" id="edit_address" name="address" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="flex justify-end">
                    <button type="button" onclick="closeModal('editModal')" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded mr-2">
                        取消
                    </button>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        更新
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        function openEditModal(supplier) {
            document.getElementById('edit_id').value = supplier.id;
            document.getElementById('edit_name').value = supplier.name;
            document.getElementById('edit_contact').value = supplier.contact;
            document.getElementById('edit_phone').value = supplier.phone;
            document.getElementById('edit_address').value = supplier.address;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function confirmDelete(id) {
            if (confirm("確定要刪除這個供應商嗎？")) {
                var form = document.createElement("form");
                form.method = "POST";
                form.action = "supplier_management.php";

                var input = document.createElement("input");
                input.type = "hidden";
                input.name = "delete_supplier";
                input.value = "1";
                form.appendChild(input);

                var idInput = document.createElement("input");
                idInput.type = "hidden";
                idInput.name = "id";
                idInput.value = id;
                form.appendChild(idInput);

                document.body.appendChild(form);
                form.submit();
            }
        }

        function updateStatus(id, status) {
            var form = document.createElement("form");
            form.method = "POST";
            form.action = "supplier_management.php";

            var input = document.createElement("input");
            input.type = "hidden";
            input.name = "update_status";
            input.value = "1";
            form.appendChild(input);

            var idInput = document.createElement("input");
            idInput.type = "hidden";
            idInput.name = "id";
            idInput.value = id;
            form.appendChild(idInput);

            var statusInput = document.createElement("input");
            statusInput.type = "hidden";
            statusInput.name = "status";
            statusInput.value = status;
            form.appendChild(statusInput);

            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>