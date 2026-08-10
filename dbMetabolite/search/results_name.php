<?php
// =============================================
// results_name.php  —  name 搜尋結果頁
// =============================================
require_once(__DIR__ . '/../config.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

$results    = [];
$total      = 0;
$searchType = '';
$keyword    = '';
$errorMsg   = '';
$group      = '';

$allowedFields = [
    'synonym' => [
        'table'  => 'hmdb_synonyms',
        'column' => 'synonyms',
        'label'  => 'Name & Synonyms',
        'group'  => 'hmdb-custom',
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

        if ($group === 'hmdb-custom') {
            /*
             * 搜尋邏輯：
             * 1. 若使用者輸入匹配 metabolite_name（正式名稱）：
             *    → 顯示 DMTDB_ID、Metabolite Name、
             *      以及該代謝物「所有的同義詞」（因為名稱本身就包含在同義詞清單的概念中）
             *
             * 2. 若使用者輸入匹配 synonyms（但不匹配 metabolite_name）：
             *    → 僅顯示 DMTDB_ID、Metabolite Name、使用者輸入所匹配的同義詞
             */

            // --- COUNT ---
            $stmtCount = $db->prepare(
                "SELECT COUNT(DISTINCT mi.DMTDB_ID)
                 FROM metabolites_id mi
                 JOIN hmdb_synonyms hs ON mi.HMDB_ID = hs.HMDB_ID
                 WHERE hs.metabolite_name LIKE :kw OR hs.synonyms LIKE :kw"
            );
            $stmtCount->execute([':kw' => "%$keyword%"]);
            $total = (int)$stmtCount->fetchColumn();

            // --- DATA ---
            // name_match   = 1 → keyword 命中 metabolite_name
            // synonym_match = 1 → keyword 命中 synonyms
            // all_synonyms  → 該代謝物的所有同義詞（供 name_match 時顯示）
            // matched_synonyms → 僅匹配 keyword 的同義詞（供 synonym_match 時顯示）
            $stmt = $db->prepare(
                "SELECT
                    mi.DMTDB_ID,
                    mi.metabolite_name,
                    mi.HMDB_ID,
                    MAX(hs.metabolite_name)                                                    AS hmdb_name,
                    MAX(CASE WHEN hs.metabolite_name LIKE :kw THEN 1 ELSE 0 END)              AS name_match,
                    -- GROUP_CONCAT(DISTINCT hs.synonyms ORDER BY hs.synonyms SEPARATOR ' | ')   AS all_synonyms,
                    GROUP_CONCAT(DISTINCT CASE
                        WHEN hs.synonyms LIKE :kw THEN hs.synonyms
                        ELSE NULL
                    END ORDER BY hs.synonyms SEPARATOR ' | ')                                 AS matched_synonyms
                 FROM metabolites_id mi
                 JOIN hmdb_synonyms hs ON mi.HMDB_ID = hs.HMDB_ID
                 WHERE hs.metabolite_name LIKE :kw OR hs.synonyms LIKE :kw
                 GROUP BY mi.DMTDB_ID, mi.metabolite_name, mi.HMDB_ID
                 ORDER BY mi.DMTDB_ID
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

        }

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
    <link rel="stylesheet" href="/css/search.css">
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
                            <?php if ($group === 'hmdb-synonym'): ?>
                                <th>HMDB ID</th>
                                <th>Matched Synonyms</th>
                            <?php else: ?>
                                <th>Synonyms</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($results as $row): ?>
                        <tr>
                            <td>
                                <a href="/metabolite/<?php echo strtolower($row['DMTDB_ID']); ?>/">
                                    <?php echo htmlspecialchars($row['DMTDB_ID']); ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($row['metabolite_name']) ?></td>

                            <?php if ($group === 'hmdb-synonym'): ?>
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
                                <?php
                                if (!empty($row['name_match']) && $row['name_match'] == 1) {
                                    // 改用 $row['metabolite_name'] 替代 $keyword
                                    echo htmlspecialchars($row['metabolite_name'] . ' is a common name');
                                } elseif (!empty($row['matched_synonyms'])) {
                                    echo htmlspecialchars($row['matched_synonyms']);
                                } else {
                                    echo '-';
                                }
                                ?>
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
