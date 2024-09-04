<?php
session_start();

// 商品資料
$products = [
    '460877573639' => '乾淨白',
    '460608991029' => '寶石藍',
    '460611177651' => '暗夜黑',
    '461512960302' => '5米捲尺',
    '460610981316' => '天空藍',
    '460611768895' => '豆沙綠',
    '460610281652' => '水泥灰',
    '460740754506' => '地毯紅',
    '460610149187' => '真透明',
    '460769056071' => '橘防潮珠',
    '461512568257' => '藍防潮珠',
    '460611269849' => '香蕉黃',
    '460610392351' => '橄欖黃綠',
    '460610640777' => '桃子紅',
    '460611098366' => '金屬紅',
    '460879685276' => '草原綠',
    '460610534566' => '夜空紫',
    '460611840843' => '金屬銅',
    '460741116832' => '櫻花粉',
    '460879709805' => '樹木綠',
    '460611623476' => '金屬銀',
    '460877856443' => '皮膚色'
];

// 初始化掃描記錄
if (!isset($_SESSION['scan_history'])) {
    $_SESSION['scan_history'] = [];
}

// 處理表單提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $barcode = $_POST['barcode'];
    if (isset($products[$barcode])) {
        $result = $products[$barcode];
        // 添加到掃描記錄
        array_unshift($_SESSION['scan_history'], ['barcode' => $barcode, 'product' => $result, 'time' => date('Y-m-d H:i:s')]);
        // 只保留最近的 10 條記錄
        $_SESSION['scan_history'] = array_slice($_SESSION['scan_history'], 0, 10);
    } else {
        $result = "未找到對應的商品";
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品條碼掃描器</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
<?php include "navbar.php"; ?>

    <h1>商品條碼掃描器</h1>
    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">
        <label for="barcode">請輸入條碼：</label>
        <input type="text" id="barcode" name="barcode" autofocus>
        <input type="submit" value="確認">
    </form>

    <?php
    if (isset($result)) {
        echo "<p id='result' style='display:none;'>$result</p>";
        echo "<p>掃描的商品是：$result</p>";
    }
    ?>

    <h2>掃描記錄</h2>
    <table>
        <tr>
            <th>條碼</th>
            <th>商品名稱</th>
            <th>掃描時間</th>
        </tr>
        <?php foreach ($_SESSION['scan_history'] as $scan): ?>
        <tr>
            <td><?php echo htmlspecialchars($scan['barcode']); ?></td>
            <td><?php echo htmlspecialchars($scan['product']); ?></td>
            <td><?php echo htmlspecialchars($scan['time']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <script>
    // 自動提交表單當輸入欄位失去焦點時
    document.getElementById('barcode').addEventListener('blur', function() {
        this.form.submit();
    });

    // 語音合成功能
    function speak(text) {
        if ('speechSynthesis' in window) {
            var utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'zh-TW'; // 設置語言為繁體中文
            utterance.rate = 1.5; // 設置語速，1.0 是正常速度，1.5 是快 50%
            speechSynthesis.speak(utterance);
        }
    }

    // 當頁面加載完成後，如果有結果就讀出來
    window.onload = function() {
        var resultElement = document.getElementById('result');
        if (resultElement) {
            speak(resultElement.innerText);
        }
    }
    </script>
</body>
</html>