<?php
// =============================================
// immunedata.php  —  瀏覽免疫資料（來源：gbm_immune_data）
// =============================================
require_once 'config.php';

// 所有可選欄位（白名單）
// 對應 gbm_immune_data 資料表實際欄位
$allFields = [
    'BENPORATH_ES_1'                            => 'Benporath ES 1',
    'BENPORATH_ES_2'                            => 'Benporath ES 2',
    'WONG_EMBRYONIC_STEM_CELL_CORE'             => 'Wong Embryonic Stem Cell Core',
    'IVANOVA_HEMATOPOIESIS_STEM_CELL_LONG_TERM' => 'Ivanova Hematopoiesis Stem Cell Long Term',
    'HALLMARK_HYPOXIA'                          => 'Hallmark Hypoxia',
    'drug resistance'                           => 'Drug Resistance',
    'PRONEURAL'                                 => 'Proneural',
    'CLASSICAL'                                 => 'Classical',
    'MESENCHYMAL'                               => 'Mesenchymal',
    'molecular subtype'                         => 'Molecular Subtype',
    'immune score'                              => 'Immune Score',
    'stroma score'                              => 'Stroma Score',
    'microenvironment score'                    => 'Microenvironment Score',
    // 之後有新欄位在這裡新增即可
];

// $allFields key → 資料表實際欄位名稱
$existingCols = [
    'BENPORATH_ES_1'                            => 'BENPORATH_ES_1',
    'BENPORATH_ES_2'                            => 'BENPORATH_ES_2',
    'WONG_EMBRYONIC_STEM_CELL_CORE'             => 'WONG_EMBRYONIC_STEM_CELL_CORE',
    'IVANOVA_HEMATOPOIESIS_STEM_CELL_LONG_TERM' => 'IVANOVA_HEMATOPOIESIS_STEM_CELL_LONG_TERM',
    'HALLMARK_HYPOXIA'                          => 'HALLMARK_HYPOXIA',
    'drug resistance'                           => 'drug_resistance',
    'PRONEURAL'                                 => 'PRONEURAL',
    'CLASSICAL'                                 => 'CLASSICAL',
    'MESENCHYMAL'                               => 'MESENCHYMAL',
    'molecular subtype'                         => 'MolecularSubtype',
    'immune score'                              => 'immune score',
    'stroma score'                              => 'stroma score',
    'microenvironment score'                    => 'microenvironment score',
];

// 疾病選項（勾選用，比照 clinicaldata.php）
$diseaseOptions = [
    'glioma(gbm)' => 'Glioma(GBM)',
    'others'      => 'Others',
];

// 疾病 key → 免疫資料表名稱
$diseaseImmuneTableMap = [
    'glioma(gbm)' => 'gbm_immune_data',
    // 'others'   => 'others_immune_data',
];

// 固定顯示的主鍵欄位（gbm_immune_data 的主鍵是 Case ID）
$primaryKey   = 'Case ID';
$primaryLabel = 'Patient ID';

// 初始化
$showResults = false;
$results     = [];
$total       = 0;
$totalPages  = 1;
$errorMsg    = '';

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
        $errorMsg    = 'Please select at least one disease category.';
        $showResults = false;
    } else {
        $db = getDB();

        // 組合要 SELECT 的欄位
        $selectCols = [$primaryKey];
        foreach ($selectedFields as $f) {
            if (isset($existingCols[$f])) {
                $selectCols[] = $existingCols[$f];
            }
        }
        $selectCols = array_unique($selectCols);
        $colsSql    = implode(', ', array_map(fn($c) => "`$c`", $selectCols));

        // 組合 UNION：每個選定的疾病各自對應一張免疫資料表，並注入固定的疾病名稱欄位
        $unionParts = [];
        foreach ($diseases as $d) {
            if (!isset($diseaseImmuneTableMap[$d])) continue; // 該疾病尚未有免疫資料表，先略過
            $tbl          = $diseaseImmuneTableMap[$d];
            $diseaseLabel = addslashes($diseaseOptions[$d] ?? $d);
            $unionParts[] = "SELECT $colsSql, '$diseaseLabel' AS `_disease` FROM `$tbl`";
        }

        if (empty($unionParts)) {
            $total      = 0;
            $totalPages = 1;
            $results    = [];
        } else {
            $unionSql = implode(' UNION ALL ', $unionParts);

            $countRes = $db->query("SELECT COUNT(*) AS cnt FROM ($unionSql) AS _combined");
            $countRow = $countRes->fetch(PDO::FETCH_ASSOC);
            $total      = (int)$countRow['cnt'];
            $totalPages = max(1, (int)ceil($total / PER_PAGE));

            $limit = PER_PAGE;
            $res   = $db->query("SELECT * FROM ($unionSql) AS _combined ORDER BY `_disease`, `$primaryKey` LIMIT $offset, $limit");
            if (!$res) {
                $errorMsg    = 'Query failed: ' . $db->errorInfo()[2];
                $showResults = false;
            } else {
                while ($row = $res->fetch(PDO::FETCH_ASSOC)) $results[] = $row;
            }
        }
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
    <title>Metabolite Database - Immune Data</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/browse.css">
</head>
<body>

<?php include BASE_PATH . 'includes/navbar.php'; ?>

<div class="main-container">

    <!-- ===== 選擇區域 ===== -->
    <?php if (!$showResults): ?>
    <div class="selection-container">
        <h1 style="margin-bottom:1.5rem; color:#2c3e50;">Browse Immune Data</h1>

        <form method="GET" action="immunedata.php" id="browseForm">

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
                <div class="section-title">2. Select Data Fields to Display</div>
                <div class="section-subtitle">Patient ID is always displayed. Choose additional information:</div>
                <div style="margin-bottom:0.75rem;">
                    <button type="button" id="allInfoBtn" class="chip" style="font-weight:600;">All Information</button>
                </div>
                <div class="chips-container" id="fieldsContainer">
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
        <a href="immunedata.php" class="btn-back">← Back to Selection</a>

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
                        <th><?= htmlspecialchars($primaryLabel) ?></th>
                        <th>Disease</th>
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
                        <td>
                            <a href="clinicaldata/<?= urlencode($row[$primaryKey]) ?>/?back=<?= urlencode('?' . http_build_query($_GET)) ?>">
                                <?= htmlspecialchars($row[$primaryKey]) ?>
                            </a>
                        </td>
                        <td>
                            <span class="pathway-tag"><?= htmlspecialchars($row['_disease'] ?? '-') ?></span>
                        </td>
                        <?php foreach ($selectedFields as $f): ?>
                        <td>
                            <?php
                            if (!isset($existingCols[$f])) {
                                echo '-';
                            } else {
                                $colName = $existingCols[$f];
                                $val     = $row[$colName] ?? '';
                                echo ($val === '' || $val === null) ? '-' : htmlspecialchars($val);
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

<script>
/* ── Chip 互動（通用） ── */
document.querySelectorAll('.chip').forEach(chip => {
    chip.addEventListener('click', function () {
        const cb = this.querySelector('input[type=checkbox]');
        if (!cb) return;
        cb.checked = !cb.checked;
        this.classList.toggle('selected', cb.checked);
        updateAllInfoBtn();
        validateForm();
    });
});

const allInfoBtn = document.getElementById('allInfoBtn');
if (allInfoBtn) {
    allInfoBtn.addEventListener('click', function () {
        const fieldChips = document.querySelectorAll('#fieldsContainer .chip');
        const allChecked = [...fieldChips].every(c => c.querySelector('input[type=checkbox]').checked);
        fieldChips.forEach(chip => {
            const cb = chip.querySelector('input[type=checkbox]');
            cb.checked = !allChecked;
            chip.classList.toggle('selected', !allChecked);
        });
        this.classList.toggle('selected', !allChecked);
    });
}

function updateAllInfoBtn() {
    if (!allInfoBtn) return;
    const fieldChips = document.querySelectorAll('#fieldsContainer .chip');
    const allChecked = [...fieldChips].every(c => c.querySelector('input[type=checkbox]').checked);
    allInfoBtn.classList.toggle('selected', allChecked);
}

/* ── 疾病選擇：至少選一個才能送出 ── */
function validateForm() {
    const anyDisease = document.querySelectorAll('input[name="diseases[]"]:checked').length > 0;
    document.getElementById('submitBtn').disabled = !anyDisease;
}

document.querySelectorAll('input[name="diseases[]"]').forEach(cb => {
    cb.addEventListener('change', validateForm);
});

updateAllInfoBtn();
validateForm();
</script>
</body>
</html>