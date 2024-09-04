<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

requireLogin();

$user_id = getCurrentUserId();
$message = '';

function processOperation($conn, $user_id, $barcode, $quantity, $operation) {
    if (!is_numeric($quantity) || $quantity <= 0) {
        return [
            "success" => false,
            "message" => "數量必須為正數",
            "barcode" => $barcode
        ];
    }

    $quantity = (int)$quantity;

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("SELECT id, name, stock FROM products WHERE barcode = ? AND user_id = ? FOR UPDATE");
        $stmt->bind_param("si", $barcode, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $conn->rollback();
            return [
                "success" => false,
                "message" => "找不到該商品",
                "barcode" => $barcode
            ];
        }

        $product = $result->fetch_assoc();
        $new_stock = $operation === 'in' ? $product['stock'] + $quantity : $product['stock'] - $quantity;

        if ($new_stock > 1000000) {
            $conn->rollback();
            return [
                "success" => false,
                "message" => "超過最大庫存限制",
                "name" => $product['name'],
                "currentStock" => $product['stock'],
                "barcode" => $barcode
            ];
        }

        if ($new_stock < 0) {
            $conn->rollback();
            return [
                "success" => false,
                "message" => "庫存不足",
                "name" => $product['name'],
                "currentStock" => $product['stock'],
                "barcode" => $barcode
            ];
        }

        $stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("iii", $new_stock, $product['id'], $user_id);
        $stmt->execute();

        $stmt = $conn->prepare("INSERT INTO inventory_operations (product_id, operation_type, quantity, user_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isii", $product['id'], $operation, $quantity, $user_id);
        $stmt->execute();

        $conn->commit();
        
        error_log("用戶 {$user_id} 對商品 {$product['name']} (ID: {$product['id']}) 進行了 {$operation} 操作，數量: {$quantity}，新庫存: {$new_stock}");

        return [
            "success" => true,
            "name" => $product['name'],
            "quantityChange" => $operation === 'in' ? $quantity : -$quantity,
            "oldStock" => $product['stock'],
            "newStock" => $new_stock,
            "barcode" => $barcode
        ];
    } catch (Exception $e) {
        $conn->rollback();
        error_log("操作失敗: " . $e->getMessage());
        return [
            "success" => false,
            "message" => "操作失敗，請稍後再試。錯誤: " . $e->getMessage(),
            "barcode" => $barcode
        ];
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mode = $_POST['mode'];
    $operation = $_POST['operation'];

    if ($mode == '1' || $mode == '2') {
        $barcode = $_POST['barcode'];
        $quantity = $mode == '1' ? intval($_POST['quantity']) : 1;
        
        if (empty($barcode)) {
            $message = ["success" => false, "message" => "請輸入商品條碼"];
        } else {
            $message = processOperation($conn, $user_id, $barcode, $quantity, $operation);
        }

        if (isset($_POST['ajax']) && $_POST['ajax'] === 'true') {
            echo json_encode($message);
            exit;
        }
    } elseif ($mode == '3') {
        $barcodes = explode("\n", $_POST['barcodes']);
        $results = [];
        foreach ($barcodes as $barcode) {
            $barcode = trim($barcode);
            if (!empty($barcode)) {
                $results[] = processOperation($conn, $user_id, $barcode, 1, $operation);
            }
        }
        $message = $results;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>入庫/出庫 - 庫存管理系統</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .scan-result-item {
            transition: all 0.3s ease;
        }
        .scan-result-item:first-child {
            animation: highlight 2s ease;
        }
        @keyframes highlight {
            0% { background-color: #fecaca; }
            100% { background-color: transparent; }
        }
    </style>
</head>
<body class="bg-gray-100">
<?php include "navbar.php"; ?>


    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-bold mb-4">入庫/出庫操作</h1>
        <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            <form id="operationForm" method="POST">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="mode">
                        選擇操作模式
                    </label>
                    <select id="mode" name="mode" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" onchange="changeMode()">
                        <option value="1">模式一：一件多入</option>
                        <option value="2">模式二：一件一入</option>
                        <option value="3">模式三：批量條碼入庫</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        操作類型
                    </label>
                    <div class="mt-2">
                        <label class="inline-flex items-center">
                            <input type="radio" class="form-radio" name="operation" value="in" checked>
                            <span class="ml-2">入庫</span>
                        </label>
                        <label class="inline-flex items-center ml-6">
                            <input type="radio" class="form-radio" name="operation" value="out">
                            <span class="ml-2">出庫</span>
                        </label>
                    </div>
                </div>
                <div id="mode1" class="mode-content">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="barcode">
                            商品條碼
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="barcode" name="barcode" type="text">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="quantity">
                            數量
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="quantity" name="quantity" type="number" min="1" value="1">
                    </div>
                </div>
                <div id="mode2" class="mode-content" style="display:none;">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="barcode2">
                            商品條碼
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="barcode2" name="barcode" type="text">
                    </div>
                    <div class="mb-4">
                        <label class="inline-flex items-center">
                            <input type="checkbox" class="form-checkbox" id="ttsEnabled" checked>
                            <span class="ml-2">啟用文字轉語音</span>
                        </label>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="ttsRate">
                            語音速度 (0.1 - 10)
                        </label>
                        <input type="number" id="ttsRate" min="0.1" max="10" step="0.1" value="1" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    <div class="mb-4">
                        <button type="button" id="testTTS" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            測試語音
                        </button>
                    </div>
                </div>
                <div id="mode3" class="mode-content" style="display:none;">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="barcodes">
                            批量條碼（每行一個）
                        </label>
                        <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="barcodes" name="barcodes" rows="5"></textarea>
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit">
                        執行操作
                    </button>
                </div>
            </form>
        </div>
        <div id="scanResults" class="bg-white shadow-md rounded px-8 py-6">
            <h2 class="text-2xl font-bold mb-4">掃描結果</h2>
            <div id="scanResultsList"></div>
        </div>
    </div>
    <script>
    function changeMode() {
        var mode = document.getElementById('mode').value;
        var modeContents = document.querySelectorAll('.mode-content');
        var form = document.getElementById('operationForm');

        modeContents.forEach(function(content) {
            content.style.display = 'none';
            var inputs = content.querySelectorAll('input, textarea');
            inputs.forEach(function(input) {
                input.disabled = true;
                input.required = false;
            });
        });

        var activeContent = document.getElementById('mode' + mode);
        activeContent.style.display = 'block';
        var activeInputs = activeContent.querySelectorAll('input, textarea');
        activeInputs.forEach(function(input) {
            input.disabled = false;
            input.required = true;
        });

        if (mode === '1') {
            document.getElementById('quantity').required = true;
        }
    }

    function speak(text, rate = 1) {
        if ("speechSynthesis" in window) {
            speechSynthesis.cancel();  // 取消之前的語音
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = "zh-TW";
            utterance.rate = rate;
            speechSynthesis.speak(utterance);
        }
    }

function addScanResult(result) {
        var resultsList = document.getElementById('scanResultsList');
        var resultItem = document.createElement('div');
        resultItem.className = 'scan-result-item mb-2 p-2 border rounded';

        if (result.success) {
            resultItem.innerHTML = `
                <p><strong>商品:</strong> ${result.name}</p>
                <p><strong>變更庫存:</strong> ${result.quantityChange}</p>
                <p><strong>原本庫存:</strong> ${result.oldStock}</p>
                <p><strong>總庫存:</strong> ${result.newStock}</p>
                <p><strong>條碼:</strong> ${result.barcode}</p>
            `;
        } else {
            resultItem.className += ' bg-red-500 text-white';
            resultItem.innerHTML = `
                <p><strong>未找到商品</strong></p>
                <p><strong>掃描條碼:</strong> ${result.barcode}</p>
                <button onclick="addNewProduct('${result.barcode}')" class="mt-2 bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-2 rounded">
                    新增商品
                </button>
            `;
        }

        resultsList.insertBefore(resultItem, resultsList.firstChild);
    }

    function addNewProduct(barcode) {
        window.open(`add_product.php?barcode=${encodeURIComponent(barcode)}`, '_blank');
    }

    function submitForm(e) {
        e.preventDefault();
        var mode = document.getElementById('mode').value;
        var form = document.getElementById('operationForm');
        var formData = new FormData(form);

        if (mode === '2') {
            formData.append('ajax', 'true');
            fetch('inventory_operation.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                addScanResult(data);
                if (document.getElementById('ttsEnabled').checked) {
                    const rate = parseFloat(document.getElementById('ttsRate').value);
                    if (data.success) {
                        speak(`${data.name}, 新庫存 ${data.newStock}`, rate);
                    } else {
                        speak(data.message, rate);
                    }
                }
                document.getElementById('barcode2').value = '';
                document.getElementById('barcode2').focus();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('操作失敗，請稍後再試');
            });
        } else {
            form.submit();
        }
    }

    // 初始化模式
    changeMode();

    // 為模式二添加回車鍵提交功能
    document.getElementById('barcode2').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            submitForm(e);
        }
    });

    // 監聽模式變更
    document.getElementById('mode').addEventListener('change', changeMode);

    // 表單提交
    document.getElementById('operationForm').addEventListener('submit', submitForm);

    // 測試語音按鈕
    document.getElementById('testTTS').addEventListener('click', function() {
        const rate = parseFloat(document.getElementById('ttsRate').value);
        speak("這是一個測試語音", rate);
    });

    // 初始聚焦到適當的輸入欄位
    window.onload = function() {
        var mode = document.getElementById('mode').value;
        if (mode === '1') {
            document.getElementById('barcode').focus();
        } else if (mode === '2') {
            document.getElementById('barcode2').focus();
        } else if (mode === '3') {
            document.getElementById('barcodes').focus();
        }
    };

    <?php
    if (!empty($message)) {
        if (is_array($message) && isset($message[0])) {
            // 批量處理結果
            echo "var results = " . json_encode($message) . ";";
            echo "results.forEach(function(result) { addScanResult(result); });";
        } else {
            // 單個處理結果
            echo "addScanResult(" . json_encode($message) . ");";
        }
    }
    ?>
    </script>
</body>
</html>
