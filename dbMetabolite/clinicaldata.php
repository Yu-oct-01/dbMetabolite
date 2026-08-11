<?php
// =============================================
// clinicaldata.php  —  瀏覽臨床資料
// =============================================
require_once 'config.php';

// 所有可選欄位（白名單）
$allFields = [
    'sex'                               => 'Sex',
    'race'                              => 'Race',
    'diagnosis_age'                     => 'Age at Diagnosis',

    'disease_type'                      => 'Disease Type',
    'molecular_subtype'                 => 'Molecular Subtype',
    'primary_site'                      => 'Primary Site',
    'site of resection or biopsy'       => 'Site of Resection or Biopsy',

    'vital status'                      => 'Vital Status',
    'cause of death'                    => 'Cause of Death',
    'days to death'                     => 'Days to Death',
    'progression or recurrence'         => 'Progression or Recurrence',
    'last known disease status'         => 'Last Known Disease Status',
    'days to last known disease status' => 'Days to Last Known Disease Status',
    'drug resistance'                   => 'Drug Resistance',

    'os_time'                           => 'OS Time',
    'os_state'                          => 'OS Status',
    'pfs_time'                           => 'PFS Time',
    'pfs_state'                          => 'PFS Status',

    'alcohol intensity'                 => 'Alcohol Intensity',
    // 之後有新欄位在這裡新增即可
];

$diseaseOptions = [
    'glioma(gbm)' => 'Glioma(GBM)',
    'others' => 'Others',
];

// 疾病 key → metabolite_data_source 的 disease_name
$diseaseNameMap = [
    'glioma(gbm)' => 'GBM',
    'others'      => 'Others',
];

// PDC project_number → 臨床資料表名稱
$projectTableMap = [
    'PDC000546' => 'cptac3_pdc000546_clinicaldata',
    'PDC000552' => 'cptac3_pdc000552_clinicaldata',
];

// ── AJAX：依疾病回傳樣本清單（與 metabolites.php 共用相同邏輯）──────
if (isset($_GET['ajax_projects'])) {
    header('Content-Type: application/json');
    $reqDiseases = array_intersect((array)($_GET['diseases'] ?? []), array_keys($diseaseOptions));
    if (empty($reqDiseases)) { echo json_encode([]); exit; }

    $db = getDB();
    $diseaseNames = array_values(array_filter(
        array_map(fn($d) => $diseaseNameMap[$d] ?? null, $reqDiseases)
    ));
    $placeholders = implode(',', array_fill(0, count($diseaseNames), '?'));
    $stmt = $db->prepare(
        "SELECT source, disease_name, project_number
         FROM metabolite_data_source
         WHERE disease_name IN ($placeholders)
         ORDER BY project_number"
    );
    $stmt->execute($diseaseNames);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}
// ─────────────────────────────────────────────────────────────────────

// $allFields key → 資料表實際欄位名稱
$existingCols = [
    'sex'                               => 'Sex',
    'race'                              => 'Race',
    'diagnosis_age'                     => 'Age at Diagnosis',
    'disease_type'                      => 'Disease Type',
    'primary_site'                      => 'Primary Site',
    'site of resection or biopsy'       => 'Site of Resection or Biopsy',
    'vital status'                      => 'Vital Status',
    'cause of death'                    => 'Cause of Death',
    'days to death'                     => 'Days to Death',
    'os_time'                           => 'OS_Time',
    'os_state'                          => 'OS_Status',
    'pfs_time'                           => 'PFS_Time',
    'pfs_state'                          => 'PFS_Status',
    'progression or recurrence'         => 'Progression or Recurrence',
    'last known disease status'         => 'Last Known Disease Status',
    'days to last known disease status' => 'Days to Last Known Disease Status',
    'alcohol intensity'                 => 'Alcohol Intensity',
    
];

// 固定顯示的主鍵欄位
$primaryKey   = 'Case Submitter ID';
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

    // 驗證樣本選項（白名單）
    $selectedProjects = array_intersect((array)($_GET['projects'] ?? []), array_keys($projectTableMap));

    if (empty($diseases)) {
        $errorMsg    = 'Please select at least one disease category.';
        $showResults = false;
    } else {
        $db = getDB();

        // 若未選任何 project，從 DB 動態取得該疾病所有 project 作為預設
        if (empty($selectedProjects)) {
            $diseaseNames = array_values(array_filter(
                array_map(fn($d) => $diseaseNameMap[$d] ?? null, $diseases)
            ));
            if (!empty($diseaseNames)) {
                $ph   = implode(',', array_fill(0, count($diseaseNames), '?'));
                $stmt = $db->prepare("SELECT project_number FROM metabolite_data_source WHERE disease_name IN ($ph) ORDER BY project_number");
                $stmt->execute($diseaseNames);
                $selectedProjects = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'project_number');
                $selectedProjects = array_intersect($selectedProjects, array_keys($projectTableMap));
            }
        }

        // 組合要 SELECT 的欄位
        $selectCols = [$primaryKey];
        foreach ($selectedFields as $f) {
            if (isset($existingCols[$f])) {
                $selectCols[] = $existingCols[$f];
            }
        }
        $selectCols = array_unique($selectCols);
        $colsSql    = implode(', ', array_map(fn($c) => "`$c`", $selectCols));

        // 組合 UNION：每個選定的 project 各產生一段 SELECT，並注入固定的 project_number 欄位
        $unionParts = [];
        foreach ($selectedProjects as $pdc) {
            if (!isset($projectTableMap[$pdc])) continue;
            $tbl         = $projectTableMap[$pdc];
            $pdcEscaped  = addslashes($pdc);
            $unionParts[] = "SELECT $colsSql, '$pdcEscaped' AS `_project` FROM `$tbl`";
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
            $res   = $db->query("SELECT * FROM ($unionSql) AS _combined ORDER BY `_project`, `$primaryKey` LIMIT $offset, $limit");
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

            <!-- 樣本選擇（動態出現） -->
            <div class="selection-section" id="projectSection" style="display:none;">
                <div class="section-title">2. Select Samples</div>
                <div class="section-subtitle">Choose one or more project samples to include</div>
                <div class="chips-container" id="projectContainer"></div>
            </div>

            <!-- 欄位選擇 -->
            <div class="selection-section">
                <div class="section-title">3. Select Additional Data Fields to Display</div>
                <div class="section-subtitle">Patient ID and Sample are always displayed. Choose additional information:</div>
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
        <a href="clinicaldata.php" class="btn-back">← Back to Selection</a>

        <?php if ($errorMsg): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
        <?php else: ?>

        <div class="data-info">
            <strong>Selected Disease:</strong>
            <?= htmlspecialchars(implode(', ', array_map(fn($d) => $diseaseOptions[$d] ?? $d, $diseases))) ?><br>
            <strong>Selected Samples:</strong>
            <?= htmlspecialchars(implode(', ', $selectedProjects)) ?><br>
            <strong>Total Records:</strong> <?= $total ?>
        </div>

        <!-- 分頁（上） -->
        <?php include BASE_PATH . 'includes/pagination.php'; ?>

        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?= htmlspecialchars($primaryLabel) ?></th>
                        <th>Sample</th>
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
                            <span class="pathway-tag"><?= htmlspecialchars($row['_project'] ?? '-') ?></span>
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
        validateForm();
    });
}

function updateAllInfoBtn() {
    if (!allInfoBtn) return;
    const fieldChips = document.querySelectorAll('#fieldsContainer .chip');
    const allChecked = [...fieldChips].every(c => c.querySelector('input[type=checkbox]').checked);
    allInfoBtn.classList.toggle('selected', allChecked);
}

function validateForm() {
    const anyDisease = document.querySelectorAll('input[name="diseases[]"]:checked').length > 0;
    document.getElementById('submitBtn').disabled = !anyDisease;
}

updateAllInfoBtn();
validateForm();

/* ── 疾病選擇 → 動態載入樣本 ── */
let projectFetchController = null;

document.querySelectorAll('input[name="diseases[]"]').forEach(cb => {
    cb.addEventListener('change', () => { validateForm(); loadProjects(); });
});

function loadProjects() {
    const checked = [...document.querySelectorAll('input[name="diseases[]"]:checked')];
    const projectSection  = document.getElementById('projectSection');
    const projectContainer = document.getElementById('projectContainer');

    if (checked.length === 0) {
        projectSection.style.display = 'none';
        projectContainer.innerHTML = '';
        return;
    }

    projectSection.style.display = '';
    projectContainer.innerHTML = '<span style="color:#7f8c8d;font-size:0.9rem;">Loading samples…</span>';

    if (projectFetchController) projectFetchController.abort();
    projectFetchController = new AbortController();

    const params = new URLSearchParams();
    params.append('ajax_projects', '1');
    checked.forEach(cb => params.append('diseases[]', cb.value));

    fetch('clinicaldata.php?' + params.toString(), { signal: projectFetchController.signal })
        .then(r => r.json())
        .then(rows => {
            projectContainer.innerHTML = '';
            if (rows.length === 0) {
                projectContainer.innerHTML = '<span style="color:#7f8c8d;font-size:0.9rem;">No samples found for selected disease(s).</span>';
                return;
            }

            const prevSelected = new Set(
                [...document.querySelectorAll('input[name="projects[]"]:checked')].map(i => i.value)
            );

            rows.forEach(row => {
                const pdc       = row.project_number;
                const isChecked = prevSelected.size > 0 ? prevSelected.has(pdc) : true;
                const label     = document.createElement('label');
                label.className = 'chip' + (isChecked ? ' selected' : '');
                label.innerHTML = `<input type="checkbox" name="projects[]" value="${pdc}"
                    ${isChecked ? 'checked' : ''} style="display:none;">
                    <span style="font-weight:600;">${pdc}</span>
                    <span style="font-size:0.78rem;opacity:0.8;margin-left:4px;">(${row.source})</span>`;
                label.addEventListener('click', function () {
                    const cb = this.querySelector('input[type=checkbox]');
                    if (!cb) return;
                    cb.checked = !cb.checked;
                    this.classList.toggle('selected', cb.checked);
                });
                projectContainer.appendChild(label);
            });
        })
        .catch(err => {
            if (err.name !== 'AbortError') {
                projectContainer.innerHTML = '<span style="color:#e74c3c;font-size:0.9rem;">Failed to load samples. Please try again.</span>';
            }
        });
}

// 若頁面已有預選疾病（防呆）
if (document.querySelectorAll('input[name="diseases[]"]:checked').length > 0) {
    loadProjects();
}
</script>
</body>
</html>
