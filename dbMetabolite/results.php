<?php
// =============================================
// results.php  —  搜尋結果頁
// =============================================
require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$results    = [];
$total      = 0;
$searchType = '';
$keyword    = '';
$errorMsg   = '';
$group      = '';

$allowedFields = [
    'metabolite-name' => [
        'table'  => 'metabolites',
        'column' => 'metabolite_name',
        'label'  => 'Metabolite Name',
        'group'  => 'basic',
    ],
    'pathway' => [
        'table'  => 'metabolites',
        'column' => 'pathway',
        'label'  => 'Pathway',
        'group'  => 'basic',
    ],
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
    'synonym' => [
        'table'  => 'metabolites',
        'column' => 'synonym_name',
        'label'  => 'Synonym (KEGG)',
        'group'  => 'synonym',
    ],
    'hmdb-synonym' => [
        'table'  => 'hmdb_synonyms',
        'column' => 'synonyms',
        'label'  => 'Synonym (HMDB)',
        'group'  => 'hmdb-synonym',
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
        $group       = $fieldConfig['group'];
        $db          = getDB();

        if ($group === 'id') {
            $linkCol = $fieldConfig['link_column'];

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

        } elseif ($group === 'hmdb-synonym') {
            // 查 hmdb_synonyms，再 JOIN metabolites_id 取 DMTDB_ID
            $stmtCount = $db->prepare(
                "SELECT COUNT(DISTINCT hs.HMDB_ID)
                 FROM hmdb_synonyms hs
                 WHERE hs.`$col` LIKE :kw"
            );
            $stmtCount->execute([':kw' => "%$keyword%"]);
            $total = (int)$stmtCount->fetchColumn();

            $stmt = $db->prepare(
                "SELECT mi.DMTDB_ID,
                        hs.metabolite_name,
                        hs.HMDB_ID,
                        GROUP_CONCAT(DISTINCT hs.synonyms ORDER BY hs.synonyms SEPARATOR ' | ') AS matched_synonyms
                 FROM hmdb_synonyms hs
                 LEFT JOIN metabolites_id mi ON mi.HMDB_ID = hs.HMDB_ID
                 WHERE hs.`$col` LIKE :kw
                 GROUP BY hs.HMDB_ID, hs.metabolite_name, mi.DMTDB_ID
                 ORDER BY mi.DMTDB_ID
                 LIMIT :limit OFFSET :offset"
            );
            $stmt->bindValue(':kw',     "%$keyword%");
            $stmt->bindValue(':limit',  PER_PAGE, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll();

        } else {
            $stmtCount = $db->prepare("SELECT COUNT(*) FROM `$tbl` WHERE `$col` LIKE :kw");
            $stmtCount->execute([':kw' => "%$keyword%"]);
            $total = (int)$stmtCount->fetchColumn();

            $stmt = $db->prepare(
                "SELECT DMTDB_ID, metabolite_name, HMDB_ID, CHEBI_ID, Pubchem_ID
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
        }

        $totalPages = (int)ceil($total / PER_PAGE);
        $searchType = $fieldConfig['label'];
    }
} else {
    // 沒有帶參數直接進來，跳回搜尋頁
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
    <link rel="stylesheet" href="css/style.css">
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
                            <?php if ($group === 'id'): ?>
                                <th><?= htmlspecialchars($searchType) ?></th>
                            <?php elseif ($group === 'hmdb-synonym'): ?>
                                <th>HMDB ID</th>
                                <th>Matched Synonyms</th>
                            <?php else: ?>
                                <th>HMDB ID</th>
                                <th>ChEBI ID</th>
                                <th>PubChem ID</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($results as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['DMTDB_ID']) ?></td>
                            <td>
                                <a href="metabolite.php?id=<?= urlencode($row['DMTDB_ID']) ?>">
                                    <?= htmlspecialchars($row['metabolite_name']) ?>
                                </a>
                            </td>
                            <?php if ($group === 'id'): ?>
                                <td>
                                    <?php if (!empty($row['searched_id'])): ?>
                                        <a href="<?= htmlspecialchars($row['searched_link']) ?>" target="_blank">
                                            <?= htmlspecialchars($row['searched_id']) ?>
                                        </a>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                            <?php elseif ($group === 'hmdb-synonym'): ?>
                                <td>
                                    <?php if (!empty($row['HMDB_ID'])): ?>
                                        <a href="https://hmdb.ca/metabolites/<?= urlencode($row['HMDB_ID']) ?>" target="_blank">
                                            <?= htmlspecialchars($row['HMDB_ID']) ?>
                                        </a>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['matched_synonyms'] ?? '-') ?></td>
                            <?php else: ?>
                                <td>
                                    <?php if (!empty($row['HMDB_ID'])): ?>
                                        <a href="https://hmdb.ca/metabolites/<?= urlencode($row['HMDB_ID']) ?>" target="_blank">
                                            <?= htmlspecialchars($row['HMDB_ID']) ?>
                                        </a>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['CHEBI_ID'])): ?>
                                        <a href="https://www.ebi.ac.uk/chebi/searchId.do?chebiId=<?= urlencode($row['CHEBI_ID']) ?>" target="_blank">
                                            <?= htmlspecialchars($row['CHEBI_ID']) ?>
                                        </a>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['Pubchem_ID'])): ?>
                                        <a href="https://pubchem.ncbi.nlm.nih.gov/compound/<?= urlencode(str_replace('PubChem CID ', '', $row['Pubchem_ID'])) ?>" target="_blank">
                                            <?= htmlspecialchars($row['Pubchem_ID']) ?>
                                        </a>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                            <?php endif; ?>
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
