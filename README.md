# WMS庫存管理系統

## 項目概述

WMS庫存管理系統是一個基於PHP的Web應用程序，旨在幫助中小型企業管理其庫存、供應商和產品信息。該系統提供了直觀的用戶界面和強大的功能，使庫存管理變得簡單高效。

## 主要功能

1. **用戶認證**
   - 用戶註冊和登錄
   - 密碼加密存儲

2. **產品管理**
   - 添加、編輯和刪除產品
   - 產品庫存追蹤
   - 產品搜索和過濾

3. **庫存操作**
   - 入庫和出庫操作
   - 庫存變動歷史記錄
   - 庫存盤點功能

4. **供應商管理**
   - 添加、編輯和刪除供應商信息
   - 供應商狀態管理（活躍、非活躍、黑名單）
   - 供應商搜索和排序

5. **數據導入/導出**
   - 支持CSV格式的產品數據導入/導出

6. **數據可視化**
   - 庫存變化趨勢圖
   - 產品銷售統計

7. **系統設置**
   - 用戶帳戶管理
   - 系統參數配置

## 技術棧

- 後端：PHP 7.4+
- 數據庫：MySQL 5.7+
- 前端：HTML5, CSS3, JavaScript, Tailwind CSS
- 其他：Chart.js (用於數據可視化)

## 安裝指南

1. 克隆儲存庫：
   ```
   git clone https://github.com/iain0901/wms.git
   ```

2. 配置Web服務器（如Apache或Nginx）將根目錄指向項目的public文件夾。

3. 創建MySQL數據庫，並導入提供的SQL文件：
   ```
   mysql -u your_username -p your_database_name < database.sql
   ```

4. 複製 `config.example.php` 文件為 `config.php`，並更新數據庫連接信息：
   ```
   cp config.example.php config.php
   ```

5. 編輯 `config.php`，填入您的數據庫詳情：
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'your_database_name');
   ```

6. 確保 `uploads` 和 `exports` 目錄可寫：
   ```
   chmod 755 uploads exports
   ```

7. 訪問您的網站，並使用默認管理員帳戶登錄：
   - 用戶名：admin
   - 密碼：admin123

   首次登錄後，請立即更改密碼。

## 使用說明

1. **產品管理**：
   - 點擊 "產品列表" 查看所有產品。
   - 使用 "添加產品" 按鈕新增產品。
   - 點擊產品行的 "編輯" 或 "刪除" 進行相應操作。

2. **庫存操作**：
   - 在 "入庫/出庫" 頁面進行庫存操作。
   - 使用條碼掃描或手動輸入產品信息。

3. **供應商管理**：
   - 在 "供應商管理" 頁面查看和管理供應商信息。
   - 使用頂部的搜索欄快速查找供應商。

4. **數據導入/導出**：
   - 訪問 "數據導入/導出" 頁面。
   - 按照提供的模板格式準備CSV文件。
   - 使用相應的按鈕進行導入或導出操作。

5. **報表和統計**：
   - 在首頁查看庫存概況和銷售統計圖表。
   - 使用日期選擇器自定義報表時間範圍。

## 貢獻指南

我們歡迎所有形式的貢獻，包括但不限於：
- 報告問題
- 提交功能請求
- 提交代碼修復或新功能
- 改進文檔

請遵循以下步驟：

1. Fork 本項目
2. 創建您的特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交您的更改 (`git commit -m 'Add some AmazingFeature'`)
4. 將您的更改推送到分支 (`git push origin feature/AmazingFeature`)
5. 打開一個Pull Request

## 授權協議

本項目採用 GPL-3.0 授權協議。查看 [LICENSE](LICENSE) 文件了解更多信息。

## 聯繫方式

如果您有任何問題或建議，請通過以下方式聯繫我們：

- 項目負責人：iain
- Email：iain@100thy.com
- 項目 GitHub 地址：https://github.com/iain0901/wms

## 致謝

感謝所有為本項目做出貢獻的開發者和用戶。您的支持是我們不斷改進的動力。

這個版本是純 Markdown 格式的，適合直接用作 GitHub 儲存庫的 README.md 文件。它保留了所有重要信息，並且格式化得當，可以在 GitHub 上良好顯示。您可以直接將這個內容保存為 `README.md` 文件，並添加到您的 GitHub 儲存庫中。
