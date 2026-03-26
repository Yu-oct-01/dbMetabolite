<?php
// =============================================
// search_pathway.php  —  Pathway 搜尋頁
// =============================================
require_once(__DIR__ . '/../config.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metabolite Database - Search by Pathway</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/pathway.css">
</head>
<body>

<?php include BASE_PATH . 'includes/navbar.php'; ?>

<div class="container">
    <h1 style="color:#2c3e50; margin-bottom:30px;">Search by Pathway</h1>

    <!-- ===== 搜尋區：Pathway ===== -->
    <form method="GET" action="results_pathway.php" class="search-section">

        <!-- Step 1：搜尋欄位 -->
        <label style="display:block; margin-bottom:10px; font-weight:600;">1. Select a Category to Search</label>
        <div style="display:flex; gap:20px; margin-bottom:15px; flex-wrap:wrap;">
            <label><input type="radio" name="step1_field" value="pathway_id"> KEGG ID</label>
            <label><input type="radio" name="step1_field" value="pathway_name"> KEGG Pathway Name</label>
        </div>

        <label style="display:block; margin-bottom:8px; font-weight:600;">Input the ID / Name</label>
        <input type="text" name="keyword"
               placeholder="(eg. hsa01100 / 2-Oxocarboxylic acid metabolism)"
               style="width:100%; padding:12px 15px; border:2px solid #ddd; border-radius:4px; font-size:16px; margin-bottom:15px;">

        <div style="display:flex; gap:10px;">
            <button type="button" class="btn btn-danger"
                    onclick="this.form.keyword.value='';
                             this.form.step1_field[0].checked=true;">Clear</button>
            <button type="button" class="btn btn-success"
                    onclick="this.form.keyword.value='hsa01100';
                             this.form.step1_field[0].checked=true;">Example</button>
        </div>

        <br>

        <!-- Step 2：顯示資料類型 -->
        <label style="display:block; margin-bottom:10px; font-weight:600;">2. Select Additional Data Type to Display</label>
        <div style="display:flex; gap:20px; margin-bottom:15px; flex-wrap:wrap;">
            <label><input type="radio" name="step2_type" value="Compound"> Compound</label>
            <label><input type="radio" name="step2_type" value="Gene"> Gene</label>
            <label><input type="radio" name="step2_type" value="Enzyme"> Enzyme</label>
        </div>

        <div style="display:flex; gap:10px;">
            <button type="submit" class="btn btn-primary">Search</button>
        </div>
    </form>

    <div style="margin-top:15px;">
        <a href="search.php" style="color:#2980b9; text-decoration:none; font-size:14px;">← Back to Search</a>
    </div>
</div>

<?php include BASE_PATH . 'includes/footer.php'; ?>
<script src="js/main.js"></script>
</body>
</html>
