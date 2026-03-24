<?php
// =============================================
// results.php  —  Pathway 搜尋結果頁
// =============================================
require_once(__DIR__ . '/../config.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ---------- 取得並清理參數 ----------
$search_field  = $_GET['search_field'] ?? '';   // pathway_id | pathway_name | compound | gene | enzyme
$keyword       = trim($_GET['keyword'] ?? '');

// search_field 可能被 radio 覆蓋；第一組 radio (step 1) 決定搜尋欄位，第二組 radio (step 2) 決定顯示類型
// 實作方式：把 step1 / step2 分成兩個獨立的 name，避免互蓋
// 若舊版表單只有一個 name="search_field"，這裡做相容處理：
$step1_field   = $_GET['step1_field']  ?? $search_field;   // pathway_id | pathway_name
$step2_type    = $_GET['step2_type']   ?? '';               // compound | gene | enzyme

// 若表單尚未拆分，自動判斷
if (in_array($search_field, ['compound','gene','enzyme'])) {
    $step2_type  = $search_field;
    $step1_field = 'pathway_id';   // 預設用 ID 搜
}
if (in_array($search_field, ['pathway_id','pathway_name'])) {
    $step1_field = $search_field;
}

$step2_type_map = [
    'compound' => 'Compound',
    'gene'     => 'Gene',
    'enzyme'   => 'Enzyme',
];
$type_filter = $step2_type_map[$step2_type] ?? null;   // NULL = 顯示全部類型

// ---------- 資料庫連線 ----------
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

// ---------- Step 1：先找出符合的 pathway_id 清單 ----------
$matched_pathways = [];   // [ ['pathway_id'=>..., 'pathway_name'=>...], ... ]

if ($keyword !== '') {
    if ($step1_field === 'pathway_id') {
        $sql = "SELECT p.pathway_id, p.pathway_name
                FROM kegg_hsa_pathwayname p
                WHERE p.pathway_id LIKE :kw
                ORDER BY p.pathway_id
                LIMIT 200";
    } else {
        // pathway_name
        $sql = "SELECT p.pathway_id, p.pathway_name
                FROM kegg_hsa_pathwayname p
                WHERE p.pathway_name LIKE :kw
                ORDER BY p.pathway_id
                LIMIT 200";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':kw' => '%' . $keyword . '%']);
    $matched_pathways = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------- Step 2：依 pathway_id 清單撈 detail ----------
$results = [];   // [ pathway_id => ['pathway_name'=>..., 'rows'=>[...]] ]

if (!empty($matched_pathways)) {
    $ids       = array_column($matched_pathways, 'pathway_id');
    $nameMap   = array_column($matched_pathways, 'pathway_name', 'pathway_id');

    // 動態佔位符
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $type_sql = $type_filter ? " AND d.type = ? " : "";

    $sql = "SELECT d.pathway_id, d.type, d.entry_id, d.name
            FROM kegg_hsa_pathway_detail d
            WHERE d.pathway_id IN ($placeholders)
            $type_sql
            ORDER BY d.pathway_id, d.type, d.entry_id";

    $stmt = $pdo->prepare($sql);
    $params = $ids;
    if ($type_filter) $params[] = $type_filter;
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 依 pathway_id 分組
    foreach ($matched_pathways as $pw) {
        $results[$pw['pathway_id']] = [
            'pathway_name' => $pw['pathway_name'],
            'rows'         => [],
        ];
    }
    foreach ($rows as $row) {
        $results[$row['pathway_id']]['rows'][] = $row;
    }
}

// ---------- 超連結 prefix ----------
function entry_url(string $type, string $entry_id): string {
    return match($type) {
        'Compound' => "https://www.kegg.jp/entry/" . urlencode($entry_id),
        'Gene'     => "https://www.kegg.jp/entry/" . urlencode($entry_id),
        'Enzyme'   => "https://www.kegg.jp/entry/" . urlencode($entry_id),
        default    => "#",
    };
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metabolite Database - Pathway Results</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .result-block      { margin-bottom: 40px; }
        .pathway-title     { background:#2c3e50; color:#fff; padding:10px 16px; border-radius:4px 4px 0 0; font-size:1rem; }
        .pathway-title span{ color:#aed6f1; font-weight:normal; font-size:.9rem; margin-left:10px; }
        .result-table      { width:100%; border-collapse:collapse; font-size:.9rem; }
        .result-table th   { background:#34495e; color:#fff; padding:8px 12px; text-align:left; }
        .result-table td   { padding:7px 12px; border-bottom:1px solid #eee; }
        .result-table tr:hover td { background:#f0f4f8; }
        .badge             { display:inline-block; padding:2px 8px; border-radius:10px; font-size:.8rem; font-weight:600; }
        .badge-compound    { background:#d5f5e3; color:#1e8449; }
        .badge-gene        { background:#d6eaf8; color:#1a5276; }
        .badge-enzyme      { background:#fdebd0; color:#784212; }
        .no-result         { color:#888; padding:20px 0; }
    </style>
</head>
<body>

<?php include BASE_PATH . 'includes/navbar.php'; ?>

<div class="container">
    <h1 style="color:#2c3e50; margin-bottom:6px;">Search Results</h1>
    <p style="color:#555; margin-bottom:24px;">
        Keyword: <strong><?= htmlspecialchars($keyword) ?></strong>
        &nbsp;|&nbsp; Field: <strong><?= htmlspecialchars($step1_field) ?></strong>
        <?php if ($type_filter): ?>
            &nbsp;|&nbsp; Show: <strong><?= htmlspecialchars($type_filter) ?></strong>
        <?php endif; ?>
    </p>

    <?php if (empty($keyword)): ?>
        <p class="no-result">Please enter a keyword.</p>

    <?php elseif (empty($matched_pathways)): ?>
        <p class="no-result">No pathway found for "<strong><?= htmlspecialchars($keyword) ?></strong>".</p>

    <?php else: ?>
        <?php foreach ($results as $pid => $data): ?>
        <div class="result-block">
            <div class="pathway-title">
                <?= htmlspecialchars($pid) ?>
                <span><?= htmlspecialchars($data['pathway_name']) ?></span>
                <a href="https://www.kegg.jp/pathway/<?= urlencode($pid) ?>"
                   target="_blank"
                   style="margin-left:12px; color:#aed6f1; font-size:.82rem;">[KEGG ↗]</a>
            </div>

            <?php if (empty($data['rows'])): ?>
                <p style="padding:12px 16px; color:#888;">No detail data for this pathway.</p>
            <?php else: ?>
            <table class="result-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>ID</th>
                        <th>Name</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($data['rows'] as $row):
                    $badge_class = 'badge-' . strtolower($row['type']);
                    $url = entry_url($row['type'], $row['entry_id']);
                ?>
                    <tr>
                        <td><span class="badge <?= $badge_class ?>"><?= htmlspecialchars($row['type']) ?></span></td>
                        <td>
                            <a href="<?= htmlspecialchars($url) ?>" target="_blank">
                                <?= htmlspecialchars($row['entry_id']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div style="margin-top:20px;">
        <a href="search_pathway.php" style="color:#2980b9; text-decoration:none; font-size:14px;">← Back to Pathway Search</a>
    </div>
</div>

<?php include BASE_PATH . 'includes/footer.php'; ?>
</body>
</html>
