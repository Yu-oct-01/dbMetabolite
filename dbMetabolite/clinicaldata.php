<?php
// =============================================
// clinicaldata.php  —  瀏覽臨床資料
// =============================================
require_once 'config.php';

// 所有可選欄位（白名單）
$allFields = [
    'gender'          => 'Gender',
    'age'             => 'Age',
    'blood_type'      => 'Blood Type',
    'tumor_stage'     => 'Tumor Stage',
    'survival_status' => 'Survival Status',
    // 之後有新欄位在這裡新增即可
];

$diseaseOptions = [
    'glioma' => 'Glioma',
    'others' => 'Others',
];

// 資料表中實際存在的欄位對應
// key = $allFields 的 key，value = 資料表實際欄位名稱
// 尚未建立的欄位先不列，查詢時自動顯示 -
$existingCols = [
    // 確認欄位名稱後，移除下方對應的 // 即可啟用
    // 'gender'          => 'gender',
    // 'age'             => 'age',
    // 'blood_type'      => 'blood_type',
    // 'tumor_stage'     => 'tumor_stage',
    // 'survival_status' => 'survival_status',
];

// 固定顯示的主鍵欄位（請依實際資料表調整）
$primaryKey   = 'patient_id';  // 資料表主鍵欄位名稱
$primaryLabel = 'Patient ID';  // 顯示標題

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

        // 計算總筆數
        $countRes = $db->query("SELECT COUNT(*) AS cnt FROM clinicaldata");
        $countRow = $countRes->fetch(PDO::FETCH_ASSOC);
        $total    = (int)$countRow['cnt'];
        $totalPages = max(1, (int)ceil($total / PER_PAGE));

        // 固定欄位
        $selectCols = [$primaryKey];

        // 只 SELECT 資料表中實際存在的欄位
        foreach ($selectedFields as $f) {
            if (isset($existingCols[$f])) {
                $selectCols[] = $existingCols[$f];
            }
        }
        $selectCols = array_unique($selectCols);
        $colsSql    = implode(', ', array_map(fn($c) => "`$c`", $selectCols));
        $limit      = PER_PAGE;

        $res = $db->query("SELECT $colsSql FROM clinicaldata ORDER BY `$primaryKey` LIMIT $offset, $limit");
        if (!$res) {
            $errorMsg    = '查詢失敗：' . $db->errorInfo()[2];
            $showResults = false;
        } else {
            while ($row = $res->fetch(PDO::FETCH_ASSOC)) $results[] = $row;
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
    <title>Metabolite Database - Clinical Data</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/browse.css">
</head>
<body>

<?php include BASE_PATH . 'includes/navbar.php'; ?>

<div class="main-container">

    <!-- ===== 選擇區域 ===== -->
    <?php if (!$showResults): ?>
    <div class="selection-container">
        <h1 style="margin-bottom:1.5rem; color:#2c3e50;">Browse Clinical Data</h1>

        <form method="GET" action="clinicaldata.php" id="browseForm">

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
                <div class="section-subtitle">Patient ID is always displayed. Choose additional information:</div>
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
        <a href="clinicaldata.php" class="btn-back">← Back to Selection</a>

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
                        <?php foreach ($selectedFields as $f): ?>
                            <th><?= htmlspecialchars($allFields[$f]) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($results)): ?>
                    <tr><td colspan="<?= 1 + count($selectedFields) ?>" style="text-align:center; color:#999;">No records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($results as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row[$primaryKey]) ?></td>
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
document.querySelectorAll('.chip').forEach(chip => {
    chip.addEventListener('click', function () {
        const cb = this.querySelector('input[type=checkbox]');
        cb.checked = !cb.checked;
        this.classList.toggle('selected', cb.checked);
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
