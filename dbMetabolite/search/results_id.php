<?php
// =============================================
// results_id.php  —  ID 搜尋結果頁
// =============================================
require_once(__DIR__ . '/../config.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

$results    = [];
$total      = 0;
$searchType = '';
$keyword    = '';
$errorMsg   = '';

$allowedFields = [
    'hmdb' => [
        'table'       => 'metabolites_id',
        'column'      => 'HMDB_ID',
        'link_column' => 'HMDB_link',
        'label'       => 'HMDB ID',
        'group'       => 'id',
    ],
    'chebi' => [
        'table'       => 'metabolites_id',
        'column'      => 'CHEBI_ID',
        'link_column' => 'CHEBI_link',
        'label'       => 'ChEBI ID',
        'group'       => 'id',
    ],
    'pubchem' => [
        'table'       => 'metabolites_id',
        'column'      => 'Pubchem_ID',
        'link_column' => 'Pubchem_link',
        'label'       => 'PubChem ID',
        'group'       => 'id',
    ],
];

$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * PER_PAGE;

if (!empty($_GET['keyword']) && !empty($_GET['search_field'])) {
    $keyword     = trim($_GET['keyword']);
    $searchField = $_GET['search_field'];

    if (!isset($allowedFields[$searchField])) {
        $errorMsg = 'Invalid search field.';
    } elseif (strlen($keyword) < 2) {
        $errorMsg = 'Keyword must be at least 2 characters.';
    } else {
        $fieldConfig = $allowedFields[$searchField];
        $col         = $fieldConfig['column'];
        $tbl         = $fieldConfig['table'];
        $linkCol     = $fieldConfig['link_column'];
        $db          = getDB();

        $stmtCount = $db->prepare("SELECT COUNT(*) FROM `$tbl` WHERE `$col` LIKE :kw");
        $stmtCount->execute([':kw' => "%$keyword%"]);
        $total = (int)$stmtCount->fetchColumn();

        $stmt = $db->prepare(
            "SELECT DMTDB_ID, metabolite_name,
                    `$col`     AS searched_id,
                    `$linkCol` AS searched_link
             FROM `$tbl`
             WHERE `$col` LIKE :kw
             ORDER BY DMTDB_ID
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':kw',     "%$keyword%");
        $stmt->bindValue(':limit',  PER_PAGE, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll();

        $totalPages = (int)ceil($total / PER_PAGE);
        $searchType = $fieldConfig['label'];
    }
} else {
    header('Location: search.php');
    exit;
}

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
    <title>Search Results — Metabolite Database</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>

<?php include BASE_PATH . 'includes/navbar.php'; ?>

<div class="container">

    <!-- 返回按鈕 -->
    <div style="margin-bottom:20px;">
        <a href="search.php" class="btn btn-danger">&larr; Back to Search</a>
    </div>

    <h1 style="color:#2c3e50; margin-bottom:20px;">Search Results</h1>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>

    <?php else: ?>
        <p style="margin-bottom:20px;">
            Search: <strong><?= htmlspecialchars($searchType) ?></strong>
            containing "<strong><?= htmlspecialchars($keyword) ?></strong>"
            — <strong><?= $total ?></strong> record(s) found
        </p>

        <?php if ($total > 0): ?>
            <?php include BASE_PATH . 'includes/pagination.php'; ?>

            <div style="overflow-x:auto; margin:1rem 0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Metabolite ID</th>
                            <th>Metabolite Name</th>
                            <th><?= htmlspecialchars($searchType) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($results as $row): ?>
                        <tr>
                            <td>
                                <a href="metabolite/<?= urlencode(strtolower($row['DMTDB_ID'])) ?>/">
                                    <?= htmlspecialchars($row['DMTDB_ID']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($row['metabolite_name']) ?></td>
                            <td>
                                <?php if (!empty($row['searched_id'])): ?>
                                    <a href="<?= htmlspecialchars($row['searched_link']) ?>" target="_blank">
                                        <?= htmlspecialchars($row['searched_id']) ?>
                                    </a>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php include BASE_PATH . 'includes/pagination.php'; ?>

        <?php else: ?>
            <p style="color:#e74c3c;">No results found.</p>
        <?php endif; ?>
    <?php endif; ?>

</div>

<?php include BASE_PATH . 'includes/footer.php'; ?>
<script src="js/main.js"></script>
</body>
</html>
