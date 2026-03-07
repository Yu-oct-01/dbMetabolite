<?php
// =============================================
// browse.php  —  瀏覽代謝物
// =============================================
require_once 'config.php';

// 所有可選欄位（白名單）
$allFields = [
    'hmdb_id'          => 'HMDB ID',
    'chebi_id'         => 'ChEBI ID',
    'pubchem_id'       => 'PubChem ID',
    'avg_expression'   => 'Average Expression',
    'isomers'          => 'Isomers (RT/RI)',
    'immune_cells'     => 'Immune Cell Related',
    'pathway'          => 'Pathway',
    'drug_response'    => 'Drug Response',
    'immune_status'    => 'Immune Hot/Cold',
    'stemness'         => 'Tumor Stemness',
    'hazard_ratio'     => 'Hazard Ratio',
    'tumor_grade'      => 'Tumor Grade',
    'molecular_subtype'=> 'Molecular Subtype',
];

$diseaseOptions = [
    'glioma' => 'Glioma',
    'others' => 'Others',
];

// 初始化
$showResults   = false;
$results       = [];
$total         = 0;
$totalPages    = 1;
$errorMsg      = '';

// 分頁
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * PER_PAGE;

// 收到 Submit
if (!empty($_GET['diseases'])) {
    $showResults = true;

    // 驗證疾病選項（白名單）
    $diseases = array_intersect((array)$_GET['diseases'], array_keys($diseaseOptions));

    // 驗證欄位選項（白名單）
    $selectedFields = array_intersect((array)($_GET['fields'] ?? []), array_keys($allFields));

    if (empty($diseases)) {
        $errorMsg = 'Please select at least one disease category.';
        $showResults = false;
    } else {
        $db = getDB();

        // 動態 WHERE 子句：disease IN (...)
        $placeholders = implode(',', array_fill(0, count($diseases), '?'));

        // 計算總筆數
        $stmtCount = $db->prepare(
            "SELECT COUNT(*) FROM metabolites WHERE disease IN ($placeholders)"
        );
        $stmtCount->execute($diseases);
        $total      = (int)$stmtCount->fetchColumn();
        $totalPages = max(1, (int)ceil($total / PER_PAGE));

        // 決定要 SELECT 的欄位（mdb_id 和 metabolite_name 固定）
        $selectCols = ['mdb_id', 'metabolite_name'];
        foreach ($selectedFields as $f) {
            $selectCols[] = $f;   // 已經過白名單驗證
        }
        $colsSql = implode(', ', array_map(fn($c) => "`$c`", $selectCols));

        // 取得當頁資料
        $stmt = $db->prepare(
            "SELECT $colsSql FROM metabolites
             WHERE disease IN ($placeholders)
             ORDER BY mdb_id
             LIMIT ? OFFSET ?"
        );
        $params = array_merge($diseases, [PER_PAGE, $offset]);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
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
    <title>Metabolite Database - Browse</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/browse.css">
</head>
<body>

<?php include BASE_PATH . 'includes/navbar.php'; ?>

<div class="main-container">

    <!-- ===== 選擇區域 ===== -->
    <?php if (!$showResults): ?>
    <div class="selection-container">
        <h1 style="margin-bottom:1.5rem; color:#2c3e50;">Browse Metabolites</h1>

        <form method="GET" action="browse.php" id="browseForm">

            <!-- 疾病選擇 -->
            <div class="selection-section">
                <div class="section-title">1. Select Disease Category</div>
                <div class="section-subtitle">Choose disease types</div>
                <div class="chips-container">
                    <?php foreach ($diseaseOptions as $val => $label): ?>
                        <label class="chip <?= in_array($val, (array)($_GET['diseases'] ?? [])) ? 'selected' : '' ?>">
                            <input type="checkbox" name="diseases[]" value="<?= $val ?>"
                                   <?= in_array($val, (array)($_GET['diseases'] ?? [])) ? 'checked' : '' ?>
                                   style="display:none;">
                            <?= htmlspecialchars($label) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 欄位選擇 -->
            <div class="selection-section">
                <div class="section-title">2. Select Additional Data Fields to Display</div>
                <div class="section-subtitle">Metabolite ID and Name are always displayed. Choose additional information:</div>
                <div class="chips-container">
                    <?php foreach ($allFields as $val => $label): ?>
                        <label class="chip <?= in_array($val, (array)($_GET['fields'] ?? [])) ? 'selected' : '' ?>">
                            <input type="checkbox" name="fields[]" value="<?= $val ?>"
                                   <?= in_array($val, (array)($_GET['fields'] ?? [])) ? 'checked' : '' ?>
                                   style="display:none;">
                            <?= htmlspecialchars($label) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 提交 -->
            <div class="submit-container">
                <button type="submit" class="btn-submit" id="submitBtn" disabled>Submit and Show Results</button>
            </div>

        </form>
    </div>

    <?php else: ?>

    <!-- ===== 結果區域 ===== -->
    <div class="table-container show">
        <a href="browse.php" class="btn-back">← Back to Selection</a>

        <?php if ($errorMsg): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
        <?php else: ?>

        <div class="data-info">
            <strong>Selected Disease:</strong>
            <?= htmlspecialchars(implode(', ', array_map(fn($d) => $diseaseOptions[$d] ?? $d, $diseases))) ?><br>
            <strong>Total Records:</strong> <?= $total ?>
        </div>

        <!-- 分頁（上） -->
        <?php include BASE_PATH . 'includes/pagination.php'; ?>

        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Metabolite ID</th>
                        <th>Metabolite Name</th>
                        <?php foreach ($selectedFields as $f): ?>
                            <th><?= htmlspecialchars($allFields[$f]) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($results)): ?>
                    <tr><td colspan="<?= 2 + count($selectedFields) ?>" style="text-align:center; color:#999;">No records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($results as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['mdb_id']) ?></td>
                        <td>
                            <a href="metabolite.php?id=<?= urlencode($row['mdb_id']) ?>">
                                <?= htmlspecialchars($row['metabolite_name']) ?>
                            </a>
                        </td>
                        <?php foreach ($selectedFields as $f): ?>
                        <td>
                            <?php
                            $val = $row[$f] ?? '';
                            if ($val === '' || $val === null) {
                                echo '-';
                            } elseif ($f === 'hmdb_id') {
                                echo '<a href="https://hmdb.ca/metabolites/' . urlencode($val) . '" target="_blank">' . htmlspecialchars($val) . '</a>';
                            } elseif ($f === 'chebi_id') {
                                echo '<a href="https://www.ebi.ac.uk/chebi/searchId.do?chebiId=' . urlencode($val) . '" target="_blank">' . htmlspecialchars($val) . '</a>';
                            } elseif ($f === 'pubchem_id') {
                                $cid = str_replace('CID', '', $val);
                                echo '<a href="https://pubchem.ncbi.nlm.nih.gov/compound/' . urlencode($cid) . '" target="_blank">' . htmlspecialchars($val) . '</a>';
                            } else {
                                echo htmlspecialchars($val);
                            }
                            ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 分頁（下） -->
        <?php include BASE_PATH . 'includes/pagination.php'; ?>

        <?php endif; ?>
    </div>

    <?php endif; ?>

</div>

<?php include BASE_PATH . 'includes/footer.php'; ?>
<link rel="stylesheet" href="css/browse.css">
<script>
// Chip toggle（純前端，不需要改 JS 檔）
document.querySelectorAll('.chip').forEach(chip => {
    chip.addEventListener('click', function () {
        this.classList.toggle('selected');
        const cb = this.querySelector('input[type=checkbox]');
        cb.checked = !cb.checked;
        validateForm();
    });
});

function validateForm() {
    const anyDisease = document.querySelectorAll('input[name="diseases[]"]:checked').length > 0;
    document.getElementById('submitBtn').disabled = !anyDisease;
}
validateForm();
</script>
</body>
</html>
