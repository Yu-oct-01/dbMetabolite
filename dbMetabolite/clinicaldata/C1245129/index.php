
<?php
// =============================================
// clinicaldata/[Patient ID]/index.php  —  病人個別頁面
// =============================================
require_once '../../config.php';

// 從 URL 路徑取得病人 ID
// REQUEST_URI: /clinicaldata/C3L-07213/  →  C3L-07213
$patientId = trim(basename(rtrim($_SERVER['REQUEST_URI'], '/')));

if ($patientId === '' || $patientId === '.') {
    header('Location: ../../clinicaldata.php');
    exit;
}

// 兩張來源資料表
$tables = [
    'cptac3_pdc000546_clinicaldata',
    'cptac3_pdc000552_clinicaldata',
];

// 要顯示的欄位群組（與 clinicaldata.php 一致）
$fieldGroups = [
    'Basic Information' => [
        'Sex',
        'Race',
        'Age at Diagnosis',
    ],
    'Disease' => [
        'Disease Type',
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
    ],
    'Exposure' => [
        'Alcohol Intensity',
    ],
    // Metabolites 由 expression 資料動態產生，不走 fieldGroups
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

// ── 代謝物表達量查詢 ──────────────────────────────────────────
// 兩張 expression 表，欄位名稱即病人 ID；值不為 NULL 才列出
$expressionTables = [
    'cptac3_pdc000546_expressiondata_mean',
    'cptac3_pdc000552_expressiondata_mean',
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
    <style>
        .main-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 20px;
        }

        .page-title {
            background: white;
            padding: 1.5rem 2rem;
            margin-bottom: 0;
            border-bottom: 3px solid #3498db;
        }

        .page-title h1 {
            font-size: 1.8rem;
            color: #2c3e50;
            font-weight: 500;
        }

        .section-block {
            background: white;
            margin-bottom: 0;
            border-bottom: 1px solid #ddd;
        }

        .section-header {
            background: #3498db;
            color: white;
            padding: 0.75rem 2rem;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .section-content {
            padding: 2rem;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table tr {
            border-bottom: 1px solid #eee;
        }

        .info-table tr:last-child {
            border-bottom: none;
        }

        .info-table td {
            padding: 0.75rem 0;
            vertical-align: top;
        }

        .info-table td:first-child {
            width: 280px;
            font-weight: 600;
            color: #2c3e50;
        }

        .info-table td:last-child {
            color: #555;
            line-height: 1.6;
        }

        .btn-back {
            background: #95a5a6;
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 4px;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
            margin: 1.5rem 2rem;
        }

        .btn-back:hover { background: #7f8c8d; }

        .alert-danger {
            background: #fdecea;
            color: #c0392b;
            border: 1px solid #e74c3c;
            border-radius: 4px;
            padding: 1rem 1.5rem;
            margin: 1.5rem 0;
        }

        .metabolite-tag {
            display: inline-block;
            background: #eaf4fb;
            color: #2980b9;
            border: 1px solid #aed6f1;
            border-radius: 4px;
            padding: 0.3rem 0.8rem;
            margin: 0.25rem 0.25rem 0.25rem 0;
            font-size: 0.9rem;
            text-decoration: none;
            transition: background 0.2s;
        }

        .metabolite-tag:hover {
            background: #d6eaf8;
        }

        .no-data {
            color: #999;
            font-style: italic;
        }

        .section-content a {
            color: #2980b9;
            text-decoration: none;
            line-height: 2;
            display: block;
        }

        .section-content a:hover {
            color: #1a5276;
            text-decoration: underline;
        }
    </style>
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
        <a href="javascript:history.back()" class="btn-back">← Back to Clinical Data</a>

    <?php else: ?>

        <!-- 頁面標題 -->
        <div class="page-title">
            <h1>Patient ID: <?= htmlspecialchars($patientId) ?></h1>
        </div>

        <!-- 返回按鈕 -->
        <a href="javascript:history.back()" class="btn-back">← Back to Clinical Data</a>

        <?php foreach ($fieldGroups as $groupName => $fields): ?>
        <div class="section-block">
            <div class="section-header"><?= htmlspecialchars($groupName) ?></div>
            <div class="section-content">
                <table class="info-table">
                    <?php foreach ($fields as $field): ?>
                    <tr>
                        <td><?= htmlspecialchars($field) ?></td>
                        <td><?= displayVal($row[$field] ?? null) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Metabolites Section -->
        <div class="section-block">
            <div class="section-header">Metabolites</div>
            <div class="section-content">
                <?php if (empty($metabolites)): ?>
                    <span class="no-data">No metabolite expression data available for this patient.</span>
                <?php else: ?>
                    <?php foreach ($metabolites as $m): ?>
                        <div>
                            <a href="/metabolite/<?= strtolower(urlencode($m['DMTDB_ID'])) ?>/">
                                <?= htmlspecialchars($m['metabolite_name']) ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>

</div>

<?php include BASE_PATH . 'includes/footer.php'; ?>

</body>
</html>
