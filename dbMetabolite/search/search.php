<?php
// =============================================
// search.php  —  搜尋頁
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
    <title>Metabolite Database - Search</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>

<?php include BASE_PATH . 'includes/navbar.php'; ?>

<div class="container">
    <h1 style="color:#2c3e50; margin-bottom:30px;">Search Metabolites</h1>

    <!-- ===== 搜尋區 1：名字 ===== -->
    <form method="GET" action="results_name.php" class="search-section">
        <h3>Search by Name & Synonyms</h3>
        <input type="hidden" name="search_field" value="synonym">
        <label style="display:block; margin-bottom:8px; font-weight:600;">Input the Keyword</label>
        <input type="text" name="keyword"
               placeholder="(eg. 5-hydroxytryptamine / 5-HT)"
               style="width:100%; padding:12px 15px; border:2px solid #ddd; border-radius:4px; font-size:16px; margin-bottom:15px;">
        <div style="display:flex; gap:10px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <button type="button" class="btn btn-danger" onclick="location.href='search.php'">Clear</button>
            <button type="button" class="btn btn-success"
                    onclick="this.form.querySelector('[name=keyword]').value='5-hydroxytryptamine'">Example</button>
        </div>
    </form>

    <!-- ===== 搜尋區 2：資料庫 ID ===== -->
    <form method="GET" action="results_id.php" class="search-section">
        <h3>Search by Database ID</h3>
        <label style="display:block; margin-bottom:10px; font-weight:600;">Select a Category</label>
        <div style="display:flex; gap:20px; margin-bottom:15px; flex-wrap:wrap;">
            <label><input type="radio" name="search_field" value="hmdb"> HMDB ID</label>
            <label><input type="radio" name="search_field" value="chebi"> ChEBI ID</label>
            <label><input type="radio" name="search_field" value="pubchem"> PubChem ID</label>
        </div>
        <label style="display:block; margin-bottom:8px; font-weight:600;">Input the ID</label>
        <input type="text" name="keyword"
               placeholder="(eg. HMDB0000259 / CHEBI:28790 / PubChem CID 12591)"
               style="width:100%; padding:12px 15px; border:2px solid #ddd; border-radius:4px; font-size:16px; margin-bottom:15px;">
        <div style="display:flex; gap:10px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <button type="button" class="btn btn-danger" onclick="location.href='search.php'">Clear</button>
            <button type="button" class="btn btn-success"
                    onclick="this.form.querySelector('[name=keyword]').value='HMDB0000259'">Example</button>
        </div>
    </form>

    <!-- ===== 搜尋區 3：資訊搜尋 ===== -->
    <div class="search-section">
        <h3>Search by Information</h3>
        <label style="display:block; margin-bottom:10px; font-weight:600;">Select a Category</label>
        <div style="display:flex; gap:20px; margin-bottom:15px;">
            <label><input type="radio" name="info_field" value="pathway"> Pathway</label>
            <label><input type="radio" name="info_field" value="tme"> Tumor Microenvironment</label>
        </div>
        <div style="display:flex; gap:10px;">
            <button type="button" class="btn btn-primary" onclick="goToInfoSearch()">Search</button>
        </div>
    </div>

</div>

<?php include BASE_PATH . 'includes/footer.php'; ?>
<script src="js/main.js"></script>
<script>
function goToInfoSearch() {
    const selected = document.querySelector('input[name="info_field"]:checked');
    if (!selected) {
        alert('Please select a category.');
        return;
    }
    if (selected.value === 'pathway') {
        location.href = 'search_pathway.php';
    } else if (selected.value === 'tme') {
        location.href = 'search_tme.php';
    }
}
</script>
</body>
</html>
