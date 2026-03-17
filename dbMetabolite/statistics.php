<?php
// =============================================
// statistic.php  —  統計頁
// =============================================
require_once 'config.php';

// 從資料庫抓取各資料表筆數
$pdo = getDB();
$metaboliteCount  = (int) $pdo->query("SELECT COUNT(*) FROM `metabolites_id`")->fetchColumn();
$clinical552Count = (int) $pdo->query("SELECT COUNT(*) FROM `cptac3_pdc000552_clinicaldata`")->fetchColumn();
$clinical546Count = (int) $pdo->query("SELECT COUNT(*) FROM `cptac3_pdc000546_clinicaldata`")->fetchColumn();
$clinicalTotal    = $clinical552Count + $clinical546Count;
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metabolite Database - Statistics</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php include BASE_PATH . 'includes/navbar.php'; ?>

<div class="container">
    <h1 style="color:#2c3e50; margin-bottom:30px;">Statistic Data</h1>
    <h3 style="color:#2c3e50; margin-bottom:30px;">Glioma:</h3>

    <!-- 數據表格 -->
    <div class="table-section">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Description</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>Number of metabolites</td>
                    <td><?= $metaboliteCount ?></td>
                </tr>
                <tr>
                    <td>2</td>
                    <td>Number of clinical data</td>
                    <td><?= $clinicalTotal ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_PATH . 'includes/footer.php'; ?>

<script src="js/main.js"></script>
</body>
</html>
