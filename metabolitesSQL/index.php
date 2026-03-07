<?php
// 1. 設定連線參數
$host = "127.0.0.1";  // 資料庫主機地址
$user = "root";       // 預設帳號
$pass = "88888888";           // 改成自己的密碼
$dbname = "metabolites"; // 剛才建立的資料庫名稱

// 2. 建立連線 (使用 mysqli 擴充功能)
$conn = new mysqli($host, $user, $pass, $dbname);

// 3. 檢查連線是否成功
if ($conn->connect_error) {
    // 如果失敗，停止執行並顯示錯誤
    die("連線失敗: " . $conn->connect_error);
}

echo "<h1>🎉 恭喜！連線成功</h1>";

// 4. 嘗試從資料庫抓取剛才存進去的資料
$sql = "SELECT DMTDB_ID, metabolite_name FROM metabolites_id ORDER BY DMTDB_ID";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<table border='1'><tr><th>代謝物ID</th><th>代謝物名稱</th></tr>";
    // 輸出每一行資料
    while($row = $result->fetch_assoc()) {
        echo "<tr><td>" . $row["DMTDB_ID"]. "</td><td>" . $row["metabolite_name"]. "</td></tr>";
    }
    echo "</table>";
} else {
    echo "資料表裡還沒有資料喔！";
}

// 5. 關閉連線（好習慣）
$conn->close();
?>