<?php
// =============================================
// search_tme.php  —  Tumor Microenvironment 搜尋頁
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
    <title>Metabolite Database - Search by Tumor Microenvironment</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>

<?php include BASE_PATH . 'includes/navbar.php'; ?>

<div class="container">
    <h1 style="color:#2c3e50; margin-bottom:30px;">Search by Tumor Microenvironment</h1>

    <!-- ===== 搜尋區 1：Cell Type ===== -->
    <form method="GET" action="results.php" class="search-section">
        <h3>Search by Cell Type</h3>
        <input type="hidden" name="search_field" value="tme_cell_type">

        <label style="display:block; margin-bottom:8px; font-weight:600;">Input Cell Type</label>
        <input type="text" name="keyword"
               placeholder="(eg. T cell / Macrophage / NK cell)"
               style="width:100%; padding:12px 15px; border:2px solid #ddd; border-radius:4px; font-size:16px; margin-bottom:15px;">

        <div style="display:flex; gap:10px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <button type="button" class="btn btn-danger"
                    onclick="this.form.querySelector('[name=keyword]').value=''">Clear</button>
            <button type="button" class="btn btn-success"
                    onclick="this.form.querySelector('[name=keyword]').value='Macrophage'">Example</button>
        </div>
    </form>

    <!-- ===== 搜尋區 2：TME Role ===== -->
    <form method="GET" action="results.php" class="search-section">
        <h3>Search by TME Role</h3>
        <input type="hidden" name="search_field" value="tme_role">

        <label style="display:block; margin-bottom:8px; font-weight:600;">Input TME Role</label>
        <input type="text" name="keyword"
               placeholder="(eg. Immunosuppression / Angiogenesis / Immune activation)"
               style="width:100%; padding:12px 15px; border:2px solid #ddd; border-radius:4px; font-size:16px; margin-bottom:15px;">

        <div style="display:flex; gap:10px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <button type="button" class="btn btn-danger"
                    onclick="this.form.querySelector('[name=keyword]').value=''">Clear</button>
            <button type="button" class="btn btn-success"
                    onclick="this.form.querySelector('[name=keyword]').value='Immunosuppression'">Example</button>
        </div>
    </form>

    <!-- ===== 搜尋區 3：Metabolite Effect ===== -->
    <form method="GET" action="results.php" class="search-section">
        <h3>Search by Metabolite Effect</h3>
        <input type="hidden" name="search_field" value="tme_metabolite_effect">

        <label style="display:block; margin-bottom:8px; font-weight:600;">Input Metabolite Effect</label>
        <input type="text" name="keyword"
               placeholder="(eg. Promotes tumor growth / Inhibits T cell activity)"
               style="width:100%; padding:12px 15px; border:2px solid #ddd; border-radius:4px; font-size:16px; margin-bottom:15px;">

        <div style="display:flex; gap:10px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <button type="button" class="btn btn-danger"
                    onclick="this.form.querySelector('[name=keyword]').value=''">Clear</button>
            <button type="button" class="btn btn-success"
                    onclick="this.form.querySelector('[name=keyword]').value='Inhibits T cell activity'">Example</button>
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
