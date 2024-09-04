<nav class="bg-white shadow-lg">
    <div class="max-w-6xl mx-auto px-4">
        <div class="flex justify-between">
            <div class="flex space-x-7">
                <div>
                    <a href="index.php" class="flex items-center py-4 px-2">
                        <span class="font-semibold text-gray-500 text-lg">庫存管理系統</span>
                    </a>
                </div>
            </div>
            <div class="hidden md:flex items-center space-x-1">
                <?php
                $nav_items = [
                    "index.php" => "首頁",
                    "product_list.php" => "商品列表",
                    "inventory_operation.php" => "入庫/出庫",
                    "product_import_export.php" => "數據導入/導出",
                    "inventory_check.php" => "商品盤點",
                    "my_account.php" => "我的帳號"
                ];
                $current_page = basename($_SERVER["PHP_SELF"]);
                foreach ($nav_items as $url => $title):
                ?>
                    <a href="<?php echo $url; ?>" 
                       class="py-2 px-2 font-medium text-gray-500 rounded hover:bg-green-500 hover:text-white transition duration-300 <?php echo $current_page === $url ? 'bg-green-500 text-white' : ''; ?>">
                        <?php echo $title; ?>
                    </a>
                <?php endforeach; ?>
                <a href="logout.php" class="py-2 px-2 font-medium text-gray-500 rounded hover:bg-red-500 hover:text-white transition duration-300">登出</a>
            </div>
        </div>
    </div>
</nav>
