<?php
// =============================================
// search.php  —  搜尋頁
// =============================================
require_once 'config.php';

// 初始化變數
$results      = [];
$total        = 0;
$searched     = false;
$searchType   = '';   // 搜尋類別
$keyword      = '';   // 搜尋關鍵字
$errorMsg     = '';

// 欄位白名單（防 SQL injection）
$allowedFields = [
    // Basic
    'metabolite-name' => ['column' => 'metabolite_name', 'label' => 'Metabolite Name'],
    'pathway'         => ['column' => 'pathway',         'label' => 'Pathway'],
    // ID
    'hmdb'    => ['column' => 'hmdb_id',    'label' => 'HMDB ID'],
    'chebi'   => ['column' => 'chebi_id',   'label' => 'ChEBI ID'],
    'pubchem' => ['column' => 'pubchem_id', 'label' => 'PubChem ID'],
    // Synonym
    'synonym'     => ['column' => 'synonym_name',     'label' => 'Synonym'],
    'traditional' => ['column' => 'traditional_name', 'label' => 'Traditional Name'],
];

// 分頁
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * PER_PAGE;

// 收到搜尋請求
if (!empty($_GET['keyword']) && !empty($_GET['search_field'])) {
    $searched    = true;
    $keyword     = trim($_GET['keyword']);
    $searchField = $_GET['search_field'];

    if (!isset($allowedFields[$searchField])) {
        $errorMsg = 'Invalid search field.';
    } elseif (strlen($keyword) < 2) {
        $errorMsg = 'Keyword must be at least 2 characters.';
    } else {
        $col = $allowedFields[$searchField]['column'];
        $db  = getDB();

        // ---- 計算總筆數 ----
        $stmtCount = $db->prepare(
            "SELECT COUNT(*) FROM metabolites WHERE `$col` LIKE :kw"
        );
        $stmtCount->execute([':kw' => "%$keyword%"]);
        $total = (int)$stmtCount->fetchColumn();

        // ---- 取得當頁資料 ----
        $stmt = $db->prepare(
            "SELECT mdb_id, metabolite_name, hmdb_id, chebi_id, pubchem_id
             FROM metabolites
             WHERE `$col` LIKE :kw
             ORDER BY mdb_id
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':kw',     "%$keyword%");
        $stmt->bindValue(':limit',  PER_PAGE, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        $results  = $stmt->fetchAll();
        $totalPages = (int)ceil($total / PER_PAGE);

        $searchType = $allowedFields[$searchField]['label'];
    }
}

// 輔助：產生分頁連結 URL
function pageUrl(int $p): string {
    $params = $_GET;
    $params['page'] = $p;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metabolite Database - Search</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php include BASE_PATH . 'includes/navbar.php'; ?>

<div class="container">
    <h1 style="color:#2c3e50; margin-bottom:30px;">Search Metabolites</h1>

    <!-- ===== 搜尋區 1：基本資訊 ===== -->
    <form method="GET" action="search.php" class="search-section">
        <h3>Search by Basic Information</h3>
        <label style="display:block; margin-bottom:10px; font-weight:600;">Select a Category</label>
        <div style="display:flex; gap:20px; margin-bottom:15px;">
            <label><input type="radio" name="search_field" value="metabolite-name"
                <?= ($_GET['search_field'] ?? '') === 'metabolite-name' ? 'checked' : '' ?>> Metabolite Name</label>
            <label><input type="radio" name="search_field" value="pathway"
                <?= ($_GET['search_field'] ?? '') === 'pathway' ? 'checked' : '' ?>> Pathway</label>
        </div>
        <label style="display:block; margin-bottom:8px; font-weight:600;">Input the Keyword</label>
        <input type="text" name="keyword"
               value="<?= htmlspecialchars($_GET['keyword'] ?? '') ?>"
               placeholder="(eg. Serotonin / Tryptophan metabolism)"
               style="width:100%; padding:12px 15px; border:2px solid #ddd; border-radius:4px; font-size:16px; margin-bottom:15px;">
        <div style="display:flex; gap:10px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <button type="button" class="btn btn-danger" onclick="location.href='search.php'">Clear</button>
            <button type="button" class="btn btn-success"
                    onclick="this.form.querySelector('[name=keyword]').value='Serotonin'">Example</button>
        </div>
    </form>

    <!-- ===== 搜尋區 2：資料庫 ID ===== -->
    <form method="GET" action="search.php" class="search-section">
        <h3>Search by Database ID</h3>
        <label style="display:block; margin-bottom:10px; font-weight:600;">Select a Category</label>
        <div style="display:flex; gap:20px; margin-bottom:15px; flex-wrap:wrap;">
            <label><input type="radio" name="search_field" value="hmdb"
                <?= ($_GET['search_field'] ?? 'hmdb') === 'hmdb' ? 'checked' : '' ?>> HMDB ID</label>
            <label><input type="radio" name="search_field" value="chebi"
                <?= ($_GET['search_field'] ?? '') === 'chebi' ? 'checked' : '' ?>> ChEBI ID</label>
            <label><input type="radio" name="search_field" value="pubchem"
                <?= ($_GET['search_field'] ?? '') === 'pubchem' ? 'checked' : '' ?>> PubChem ID</label>
        </div>
        <label style="display:block; margin-bottom:8px; font-weight:600;">Input the ID</label>
        <input type="text" name="keyword"
               placeholder="(eg. HMDB0000259 / CHEBI:28790)"
               style="width:100%; padding:12px 15px; border:2px solid #ddd; border-radius:4px; font-size:16px; margin-bottom:15px;">
        <div style="display:flex; gap:10px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <button type="button" class="btn btn-danger" onclick="location.href='search.php'">Clear</button>
            <button type="button" class="btn btn-success"
                    onclick="this.form.querySelector('[name=keyword]').value='HMDB0000259'">Example</button>
        </div>
    </form>

    <!-- ===== 搜尋區 3：同義詞 ===== -->
    <form method="GET" action="search.php" class="search-section">
        <h3>Search by Synonyms</h3>
        <label style="display:block; margin-bottom:10px; font-weight:600;">Select a Category</label>
        <div style="display:flex; gap:20px; margin-bottom:15px;">
            <label><input type="radio" name="search_field" value="synonym"
                <?= ($_GET['search_field'] ?? 'synonym') === 'synonym' ? 'checked' : '' ?>> Synonym Name</label>
            <label><input type="radio" name="search_field" value="traditional"
                <?= ($_GET['search_field'] ?? '') === 'traditional' ? 'checked' : '' ?>> Traditional Name</label>
        </div>
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

    <!-- ===== 搜尋結果 ===== -->
    <?php if ($errorMsg): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>

    <?php elseif ($searched): ?>
        <div class="table-section">
            <h2>Search Results</h2>
            <p>
                Search: <strong><?= htmlspecialchars($searchType) ?></strong>
                containing "<strong><?= htmlspecialchars($keyword) ?></strong>"
                — <?= $total ?> record(s) found
            </p>

            <?php if ($total > 0): ?>
                <!-- 分頁（上） -->
                <?php include BASE_PATH . 'includes/pagination.php'; ?>

                <div style="overflow-x:auto; margin:1rem 0;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Metabolite ID</th>
                                <th>Metabolite Name</th>
                                <th>HMDB ID</th>
                                <th>ChEBI ID</th>
                                <th>PubChem ID</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['mdb_id']) ?></td>
                                <td>
                                    <a href="metabolite.php?id=<?= urlencode($row['mdb_id']) ?>">
                                        <?= htmlspecialchars($row['metabolite_name']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($row['hmdb_id']): ?>
                                        <a href="https://hmdb.ca/metabolites/<?= urlencode($row['hmdb_id']) ?>" target="_blank">
                                            <?= htmlspecialchars($row['hmdb_id']) ?>
                                        </a>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['chebi_id']): ?>
                                        <a href="https://www.ebi.ac.uk/chebi/searchId.do?chebiId=<?= urlencode($row['chebi_id']) ?>" target="_blank">
                                            <?= htmlspecialchars($row['chebi_id']) ?>
                                        </a>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['pubchem_id']): ?>
                                        <a href="https://pubchem.ncbi.nlm.nih.gov/compound/<?= urlencode(str_replace('CID','',$row['pubchem_id'])) ?>" target="_blank">
                                            <?= htmlspecialchars($row['pubchem_id']) ?>
                                        </a>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- 分頁（下） -->
                <?php include BASE_PATH . 'includes/pagination.php'; ?>

            <?php else: ?>
                <p style="color:#e74c3c;">No results found.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<?php include BASE_PATH . 'includes/footer.php'; ?>
<script src="js/main.js"></script>
</body>
</html>
