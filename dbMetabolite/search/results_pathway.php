<?php
// =============================================
// results_pathway.php  —  Pathway 搜尋結果頁
// =============================================
// 表格欄位順序：pathway_id | metabolite_type | kegg_id | metabolite_name | pathway_name
// 資料來源：
//   kegg_hsa_metabolism_pathways  (pathway_id, metabolite_type, kegg_id, metabolite_name)
//   kegg_hsa_pathwayname          (pathway_id, pathway_name)
// =============================================
require_once(__DIR__ . '/../config.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ---------- 取得並清理參數 ----------
$step1_field = $_GET['step1_field'] ?? 'pathway_id';   // pathway_id | pathway_name
$step2_type  = $_GET['step2_type']  ?? 'Compound';     // Compound | Gene | Enzyme
$keyword     = trim($_GET['keyword'] ?? '');

// 安全白名單
if (!in_array($step1_field, ['pathway_id', 'pathway_name'])) {
    $step1_field = 'pathway_id';
}
if (!in_array($step2_type, ['Compound', 'Gene', 'Enzyme'])) {
    $step2_type = 'Compound';
}

// ---------- 資料庫連線 ----------
$pdo = getDB();

// ---------- 查詢 ----------
// 單一 JOIN 查詢：直接取得所有需要欄位
// 欄位：m.pathway_id, m.metabolite_type, m.kegg_id, m.metabolite_name, p.pathway_name
$rows         = [];
$total_count  = 0;

if ($keyword !== '') {
    // Step 1：依搜尋欄位決定 WHERE 條件
    $where_field = ($step1_field === 'pathway_id')
        ? 'm.pathway_id'
        : 'p.pathway_name';

    $sql = "
        SELECT
            m.pathway_id,
            m.metabolite_type,
            m.kegg_id,
            m.metabolite_name,
            p.pathway_name
        FROM kegg_hsa_metabolism_pathways m
        LEFT JOIN kegg_hsa_pathwayname p ON p.pathway_id = m.pathway_id
        WHERE {$where_field} LIKE :kw
          AND m.metabolite_type = :type
        ORDER BY m.pathway_id, m.kegg_id
        LIMIT 2000
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':kw'   => '%' . $keyword . '%',
        ':type' => $step2_type,
    ]);
    $rows        = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_count = count($rows);
}

// ---------- 超連結：kegg_id → KEGG entry 頁 ----------
function kegg_url(string $type, string $kegg_id): string {
    return "https://www.kegg.jp/entry/" . urlencode($kegg_id);
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metabolite Database - Pathway Results</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/pathway.css">
</head>
<body>

<?php include BASE_PATH . 'includes/navbar.php'; ?>

<div class="container">
    <h1 style="color:#2c3e50; margin-bottom:6px;">Pathway Search Results</h1>

    <!-- 搜尋摘要列 -->
    <div style="margin-bottom:16px;">
        <a href="search_pathway.php" style="color:#2980b9; text-decoration:none; font-size:14px;">← Back to Pathway Search</a>
    </div>

    <p class="meta-bar">
        Keyword: <strong><?= htmlspecialchars($keyword) ?></strong>
        &nbsp;|&nbsp; Search by: <strong><?= $step1_field === 'pathway_id' ? 'KEGG ID' : 'Pathway Name' ?></strong>
        &nbsp;|&nbsp; Data type: <strong><?= htmlspecialchars($step2_type) ?></strong>
        <?php if ($keyword !== ''): ?>
            <span class="count-badge"><?= $total_count ?> row<?= $total_count !== 1 ? 's' : '' ?></span>
        <?php endif; ?>
    </p>

    <?php if ($keyword === ''): ?>
        <p class="no-result">Please enter a keyword.</p>

    <?php elseif (empty($rows)): ?>
        <p class="no-result">
            No results found for "<strong><?= htmlspecialchars($keyword) ?></strong>"
            (<?= $step2_type ?> in <?= $step1_field === 'pathway_id' ? 'KEGG ID' : 'Pathway Name' ?>).
        </p>

    <?php else: ?>
    <div class="result-wrapper">
        <table class="result-table">
            <thead>
                <tr>
                    <th>Pathway ID</th>
                    <th>Type</th>
                    <th>KEGG ID</th>
                    <th>Metabolite Name</th>
                    <th>Pathway Name</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row):
                $badge_class = 'badge-' . strtolower($row['metabolite_type']);
                $entry_url   = kegg_url($row['metabolite_type'], $row['kegg_id']);
                $pathway_url = 'https://www.kegg.jp/pathway/' . urlencode($row['pathway_id']);
            ?>
                <tr>
                    <!-- Pathway ID → KEGG pathway 頁 -->
                    <td>
                        <a class="kegg-link" href="<?= htmlspecialchars($pathway_url) ?>" target="_blank">
                            <?= htmlspecialchars($row['pathway_id']) ?>
                        </a>
                    </td>

                    <!-- Type badge -->
                    <td>
                        <span class="badge <?= $badge_class ?>">
                            <?= htmlspecialchars($row['metabolite_type']) ?>
                        </span>
                    </td>

                    <!-- KEGG ID → KEGG entry 頁 -->
                    <td>
                        <a class="kegg-link" href="<?= htmlspecialchars($entry_url) ?>" target="_blank">
                            <?= htmlspecialchars($row['kegg_id']) ?>
                        </a>
                    </td>

                    <!-- Metabolite Name -->
                    <td><?= htmlspecialchars($row['metabolite_name'] ?? '—') ?></td>

                    <!-- Pathway Name -->
                    <td><?= htmlspecialchars($row['pathway_name'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>


</div>

<?php include BASE_PATH . 'includes/footer.php'; ?>
</body>
</html>
