<?php
// =============================================
// clinicaldata/[Patient ID]/index.php  —  病人個別頁面
// =============================================
require_once '../../config.php';

// 從 URL 路徑取得病人 ID
// REQUEST_URI: /clinicaldata/C3L-07213/  →  C3L-07213
$uriPath   = strtok($_SERVER['REQUEST_URI'], '?');
$patientId = trim(basename(rtrim($uriPath, '/')));

if ($patientId === '' || $patientId === '.') {
    header('Location: ../../clinicaldata.php');
    exit;
}

// 解析安全的返回 URL
$backUrl = '/clinicaldata.php';
if (!empty($_GET['back'])) {
    $parsed = parse_url(urldecode($_GET['back']));
    if (isset($parsed['query']) && ($parsed['path'] ?? '') === '') {
        // back 只帶 query string（例如 ?diseases[]=...）
        $backUrl = '/clinicaldata.php?' . $parsed['query'];
    } elseif (isset($parsed['path']) && preg_match('#^/clinicaldata\.php$#', $parsed['path'])) {
        $safeQuery = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $backUrl = '/clinicaldata.php' . $safeQuery;
    }
}
$backUrlEscaped = htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8');

// 兩張來源資料表
$tables = [
    'cptac3_pdc000546_clinicaldata',
    'cptac3_pdc000552_clinicaldata',
];

// 要顯示的欄位群組
$fieldGroups = [
    'Basic Information' => [
        'Sex',
        'Race',
        'Age at Diagnosis',
    ],
    'Disease' => [
        'Disease Type',
        'Molecular Subtype',
        'Primary Site',
        'Site of Resection or Biopsy',
    ],
    'Survival' => [
        'Vital Status',
        'Cause of Death',
        'Days to Death',
        'Progression or Recurrence',
        'Last Known Disease Status',
        'Days to Last Known Disease Status',
        'Drug Resistance',

        'OS Time',
        'OS Status',
        'PFS Time',
        'PFS Status',
    ],
    'Stemness & Hypoxia Scores' => [
        'BENPORATH_ES_1',
        'BENPORATH_ES_2',
        'WONG_EMBRYONIC_STEM_CELL_CORE',
        'IVANOVA_HEMATOPOIESIS_STEM_CELL_LONG_TERM',
        'Hallmark Hypoxia',
    ],
    'ESTIMATE Scores' => [
        'Immune Score',
        'Stroma Score',
        'Microenvironment Score',
    ],
    'Exposure' => [
        'Alcohol Intensity',
    ],
    // Metabolites 由 expression 資料動態產生，不走 fieldGroups
];
$fieldMapping = [
    'OS Time'          => 'OS_Time',           // 如果資料庫是這個名稱，就保持不變
    'OS Status'        => 'OS_Status',
    'PFS Time'         => 'PFS_Time',
    'PFS Status'       => 'PFS_Status',
];

$db  = getDB();
$row = null;

// 依序查兩張表，找到就停
foreach ($tables as $tbl) {
    $stmt = $db->prepare("SELECT * FROM `$tbl` WHERE `Case Submitter ID` = ? LIMIT 1");
    $stmt->execute([$patientId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $row = $result;
        break;
    }
}

// ── gbm_immune_data 查詢 ────────────────────────────────────
$immuneRow = null;
$immuneStmt = $db->prepare(
    "SELECT * FROM `gbm_immune_data` WHERE `Case ID` = ? LIMIT 1"
);
$immuneStmt->execute([$patientId]);
$immuneResult = $immuneStmt->fetch(PDO::FETCH_ASSOC);

if ($immuneResult) {
    $immuneRow = $immuneResult;
}

// ── 代謝物表達量查詢 ──────────────────────────────────────────
// 兩張 expression 表，欄位名稱即病人 ID；值不為 NULL 才列出
$expressionTables = [
    'cptac3_pdc000546_expressiondata_max',
    'cptac3_pdc000552_expressiondata_max',
];

$metabolites = [];   // [ ['DMTDB_ID'=>..., 'metabolite_name'=>..., 'value'=>...], ... ]

foreach ($expressionTables as $eTbl) {
    // 先確認該表有沒有這個病人 ID 的欄位（避免 SQL 錯誤）
    $colCheck = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = ?
           AND COLUMN_NAME  = ?"
    );
    $colCheck->execute([$eTbl, $patientId]);
    if ((int)$colCheck->fetchColumn() === 0) {
        continue;   // 此表沒有這個病人，跳過
    }

    // 欄名含特殊字元（-），用 backtick 包；PDO 不支援 bind 欄名，用白名單驗證後直接內嵌
    // patientId 已確保來自 URL，再做一次格式驗證
    if (!preg_match('/^[A-Za-z0-9\-]+$/', $patientId)) {
        continue;
    }

    $colEscaped = '`' . $patientId . '`';
    $stmt = $db->prepare(
        "SELECT `DMTDB_ID`, `metabolite_name`, $colEscaped AS `expr_value`
         FROM `$eTbl`
         WHERE $colEscaped IS NOT NULL"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $metabolites[] = [
            'DMTDB_ID'       => $r['DMTDB_ID'],
            'metabolite_name'=> $r['metabolite_name'],
            'value'          => $r['expr_value'],
        ];
    }
}
// ─────────────────────────────────────────────────────────────

// 輔助：顯示欄位值，null/空值顯示 -
function displayVal(?string $val): string {
    return ($val === null || $val === '') ? '-' : htmlspecialchars($val);
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metabolite Database - <?= htmlspecialchars($patientId) ?></title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/clinicaldata.css">
</head>
<body>

<?php include BASE_PATH . 'includes/navbar.php'; ?>

<div class="main-container">

    <?php if (!$row): ?>

        <div class="page-title">
            <h1>Patient not found</h1>
        </div>
        <div class="alert-danger" style="margin: 1.5rem 0;">
            Patient <strong><?= htmlspecialchars($patientId) ?></strong> was not found in the database.
        </div>
        <a href="<?= $backUrlEscaped ?>" class="btn-back">← Back to Clinical Data</a>

    <?php else: ?>

        <!-- 頁面標題 -->
        <div class="page-title">
            <h1>Patient ID: <?= htmlspecialchars($patientId) ?></h1>
        </div>

        <!-- 返回按鈕 -->
        <a href="<?= $backUrlEscaped ?>" class="btn-back">← Back to Clinical Data</a>

        <?php foreach ($fieldGroups as $groupName => $fields): ?>
        <div class="section-block">
            <div class="section-header"><?= htmlspecialchars($groupName) ?></div>
            <div class="section-content">
                <table class="info-table">
                    <?php foreach ($fields as $field): ?>
                    <tr>
                        <td><?= htmlspecialchars($field) ?></td>
                        <td>
                            <?php
                            // 定義哪些欄位要從 gbm_immune_data 表中讀取，並對應到資料庫欄位名稱
                            $immuneFieldsMapping = [
                                'Molecular Subtype'                         => 'MolecularSubtype',
                                'drug_resistance'                           => 'drug_resistance', // 如果你想擺在 Survival
                                'Drug Resistance'                           => 'drug_resistance', // 相容原本表格寫法
                                'BENPORATH_ES_1'                            => 'BENPORATH_ES_1',
                                'BENPORATH_ES_2'                            => 'BENPORATH_ES_2',
                                'WONG_EMBRYONIC_STEM_CELL_CORE'             => 'WONG_EMBRYONIC_STEM_CELL_CORE',
                                'IVANOVA_HEMATOPOIESIS_STEM_CELL_LONG_TERM' => 'IVANOVA_HEMATOPOIESIS_STEM_CELL_LONG_TERM',
                                'Hallmark Hypoxia'                          => 'HALLMARK_HYPOXIA', // 對應表格畫面的名稱
                                'Immune Score'                              => 'immune score',
                                'Stroma Score'                              => 'stroma score',
                                'Microenvironment Score'                    => 'microenvironment score'
                            ];

                            if (array_key_exists($field, $immuneFieldsMapping)) {
                                $dbCol = $immuneFieldsMapping[$field];
                                echo displayVal($immuneRow[$dbCol] ?? null);
                            } else {
                                $dbColumnName = $fieldMapping[$field] ?? $field;
                                echo displayVal($row[$dbColumnName] ?? null);
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Metabolites Section -->
        <div class="section-block">
            <div class="section-header metabolites-header">
                <span>Metabolites</span>
                <?php if (!empty($metabolites)): ?>
                    <span class="metabolites-count-badge"><?= count($metabolites) ?> items</span>
                <?php endif; ?>
            </div>
            <div class="section-content">
                <?php if (empty($metabolites)): ?>
                    <span class="no-data">No metabolite expression data available for this patient.</span>
                <?php else: ?>
                    <div class="metabolites-scroll-box">
                        <?php foreach ($metabolites as $m): ?>
                            <a href="/metabolite/<?= strtolower(urlencode($m['DMTDB_ID'])) ?>/?from=clinical&patient=<?= urlencode($patientId) ?>&back=<?= urlencode($backUrl) ?>">
                                <?= htmlspecialchars($m['metabolite_name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>

</div>

<?php include BASE_PATH . 'includes/footer.php'; ?>

</body>
</html>
