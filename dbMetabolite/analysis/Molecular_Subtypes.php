<?php
// =============================================
// analysis/Molecular_Subtypes.php  —  分析頁
// =============================================
require_once '../config.php';

// 可篩選的分子亞型（對應 MAX_concentration_subtype 欄位的值）
$subtypeOptions = [
    'Classical'   => 'Classical',
    'Mesenchymal' => 'Mesenchymal',
    'Proneural'   => 'Proneural',
];

// Other：額外可加到表格右側的欄位
$otherFieldOptions = [
    'max_expression'   => 'Maximum Expression',
    'pathway'          => 'KEGG Pathway',
    'os_hazard_ratio'  => 'OS Hazard Ratio',
    'pfs_hazard_ratio' => 'PFS Hazard Ratio',
];

// 分子亞型來源資料表（PDC dataset name => table name）
$browseMolecularSubtypeTables = [
    'PDC000546' => 'cptac3_pdc000546_metabolite_molecular',
    'PDC000552' => 'cptac3_pdc000552_metabolite_molecular',
];

// Maximum Expression 來源資料表
$browseExpressionTables = [
    'PDC000546' => 'cptac3_pdc000546_expressiondata_max',
    'PDC000552' => 'cptac3_pdc000552_expressiondata_max',
];

// Hazard Ratio 來源資料表
$browseHazardRatioTables = [
    'PDC000546' => 'cptac3_pdc000546_hazard_ratio_max',
    'PDC000552' => 'cptac3_pdc000552_hazard_ratio_max',
];

// 表達量分級（同 metabolites.php 邏輯）
function browseExpressionTertileLabel(int $tertile): array {
    return match ($tertile) {
        1 => ['label' => 'low expression', 'class' => 'expression-low'],
        2 => ['label' => 'medium expression', 'class' => 'expression-mid'],
        3 => ['label' => 'high expression', 'class' => 'expression-high'],
        default => ['label' => 'N/A', 'class' => ''],
    };
}

// r 值分級（pathway 用，同 metabolites.php 邏輯）
function browsePathwayRGradeClass(?float $r): string {
    if ($r === null) return 'r-na';
    $abs = abs($r);
    $tier = $abs >= 0.7 ? 'strong' : ($abs >= 0.3 ? 'mod' : 'weak');
    $sign = $r >= 0 ? 'pos' : 'neg';
    return "r-{$tier}-{$sign}";
}

// ── 篩選參數 ──────────────────────────────────────────────
// Subtype：都沒勾 = 三種亞型全部顯示；勾幾個就顯示幾個
$selectedSubtypes  = array_intersect((array)($_GET['subtypes'] ?? []), array_keys($subtypeOptions));
$effectiveSubtypes = empty($selectedSubtypes) ? array_keys($subtypeOptions) : $selectedSubtypes;

// Other：勾選後在表格右側加對應欄位
$selectedOtherFields = array_intersect((array)($_GET['other'] ?? []), array_keys($otherFieldOptions));
$needExpression  = in_array('max_expression', $selectedOtherFields, true);
$needPathway     = in_array('pathway', $selectedOtherFields, true);
$needHazardRatio = in_array('os_hazard_ratio', $selectedOtherFields, true) || in_array('pfs_hazard_ratio', $selectedOtherFields, true);

// 分頁
$browsePage = max(1, (int)($_GET['page'] ?? 1));

// 分頁連結（pagination.php 需要此函式）
function pageUrl(int $p): string {
    $params = $_GET;
    $params['page'] = $p;
    return '?' . http_build_query($params);
}

$browseRows       = []; // 每列：DMTDB_ID / metabolite_name / KEGG_ID / dataset(PDC) / subtype
$browseErrorMsg   = '';
$browseTotal      = 0;
$browseTotalPages = 1;

$db = getDB();

try {
    // 依篩選的亞型，分別到兩個資料集撈出符合的代謝物並 JOIN 出名稱 / KEGG_ID
    $phSubtypes = implode(',', array_fill(0, count($effectiveSubtypes), '?'));
    foreach ($browseMolecularSubtypeTables as $pdcLabel => $tblName) {
        $stmt = $db->prepare(
            "SELECT m.DMTDB_ID, i.metabolite_name, i.KEGG_ID, m.MAX_concentration_subtype AS subtype
             FROM `$tblName` m
             JOIN metabolites_id i ON i.DMTDB_ID = m.DMTDB_ID
             WHERE m.MAX_concentration_subtype IN ($phSubtypes)"
        );
        $stmt->execute(array_values($effectiveSubtypes));
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $browseRows[] = [
                'DMTDB_ID'        => $r['DMTDB_ID'],
                'metabolite_name' => $r['metabolite_name'],
                'KEGG_ID'         => $r['KEGG_ID'],
                'dataset'         => $pdcLabel,
                'subtype'         => $r['subtype'],
            ];
        }
    }

    // 排序：先依 Metabolite ID，再依資料集
    usort($browseRows, function ($a, $b) {
        return $a['DMTDB_ID'] === $b['DMTDB_ID']
            ? strcmp($a['dataset'], $b['dataset'])
            : strcmp($a['DMTDB_ID'], $b['DMTDB_ID']);
    });

    $browseTotal      = count($browseRows);
    $browseTotalPages = max(1, (int)ceil($browseTotal / PER_PAGE));
    $browseOffset     = ($browsePage - 1) * PER_PAGE;
    $browsePageRows   = array_slice($browseRows, $browseOffset, PER_PAGE);
} catch (Throwable $e) {
    $browseErrorMsg = '查詢失敗，請稍後再試。';
    $browsePageRows = [];
}

// ── 依 Other 勾選，補查本頁需要的額外資料 ─────────────────────
$browseExpressionMap  = [];
$browseHazardRatioMap = [];
$browsePathwayMap     = [];
$browseCorrelationMap = [];

if (!empty($browsePageRows) && !$browseErrorMsg) {
    $pageDmtdbIds = array_values(array_unique(array_column($browsePageRows, 'DMTDB_ID')));
    $ph = implode(',', array_fill(0, count($pageDmtdbIds), '?'));

    // Maximum Expression
    if ($needExpression) {
        foreach ($browseExpressionTables as $pdcLabel => $tblName) {
            $stmt = $db->prepare(
                "SELECT DMTDB_ID, average_expression, tertile
                 FROM (
                     SELECT DMTDB_ID, average_expression,
                            NTILE(3) OVER (ORDER BY average_expression) AS tertile
                     FROM `$tblName`
                     WHERE average_expression IS NOT NULL
                 ) t
                 WHERE DMTDB_ID IN ($ph)"
            );
            $stmt->execute($pageDmtdbIds);
            while ($eRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $browseExpressionMap[$eRow['DMTDB_ID']][$pdcLabel] = (int)$eRow['tertile'];
            }
        }
    }

    // Hazard Ratio（OS / PFS）
    if ($needHazardRatio) {
        foreach ($browseHazardRatioTables as $pdcLabel => $tblName) {
            $stmt = $db->prepare(
                "SELECT DMTDB_ID, hazard_ratio_OS, hazard_ratio_PFS, OS_p_value, PFS_p_value
                 FROM `$tblName`
                 WHERE DMTDB_ID IN ($ph)"
            );
            $stmt->execute($pageDmtdbIds);
            while ($hRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $dmtId = $hRow['DMTDB_ID'];
                if (isset($hRow['hazard_ratio_OS'])) {
                    $browseHazardRatioMap[$dmtId][$pdcLabel]['os_hazard_ratio'] = $hRow['hazard_ratio_OS'];
                    $browseHazardRatioMap[$dmtId][$pdcLabel]['os_p_value']      = $hRow['OS_p_value'] ?? null;
                }
                if (isset($hRow['hazard_ratio_PFS'])) {
                    $browseHazardRatioMap[$dmtId][$pdcLabel]['pfs_hazard_ratio'] = $hRow['hazard_ratio_PFS'];
                    $browseHazardRatioMap[$dmtId][$pdcLabel]['pfs_p_value']      = $hRow['PFS_p_value'] ?? null;
                }
            }
        }
    }

    // KEGG Pathway
    if ($needPathway) {
        $keggIds = array_values(array_filter(
            array_unique(array_column($browsePageRows, 'KEGG_ID')),
            fn($v) => $v !== null && $v !== ''
        ));
        if (!empty($keggIds)) {
            $phk = implode(',', array_fill(0, count($keggIds), '?'));
            $stmt = $db->prepare(
                "SELECT p.kegg_id, p.pathway_id, n.pathway_name
                 FROM kegg_hsa_metabolism_pathways p
                 LEFT JOIN kegg_hsa_pathwayname n USING (pathway_id)
                 WHERE p.kegg_id IN ($phk)
                 ORDER BY p.kegg_id, p.pathway_id"
            );
            $stmt->execute($keggIds);
            while ($pRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $browsePathwayMap[$pRow['kegg_id']][] = [
                    'pathway_id'   => $pRow['pathway_id'],
                    'pathway_name' => $pRow['pathway_name'] ?? $pRow['pathway_id'],
                    'kegg_id'      => $pRow['kegg_id'],
                ];
            }
        }

        $metaNames = array_values(array_unique(array_column($browsePageRows, 'metabolite_name')));
        if (!empty($metaNames)) {
            $phn = implode(',', array_fill(0, count($metaNames), '?'));
            $stmt = $db->prepare(
                "SELECT metabolite_name, pathway_id, r_meta
                 FROM gbm_metabolite_pathway_correlation_coefficient
                 WHERE metabolite_name IN ($phn)"
            );
            $stmt->execute($metaNames);
            while ($cRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $browseCorrelationMap[$cRow['metabolite_name']][$cRow['pathway_id']] = $cRow['r_meta'];
            }
        }
    }
}

// 若目前帶有 Browse 相關參數，載入頁面時預設開啟 Browse 分頁
// $browseTabActive = isset($_GET['subtypes']) || isset($_GET['other']) || isset($_GET['page']);
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metabolite Database - Analysis</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/browse.css">
    <link rel="stylesheet" href="../../css/expression.css">
    <link rel="stylesheet" href="../../css/pathway.css">
    <link rel="stylesheet" href="../../css/hazard_ratio.css">
    <style>
        #metab-tabs {
            display: flex;
            gap: 32px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }

        #metab-tabs .tab {
            background: none;
            border: none;
            padding: 12px 0;
            font-size: 0.95rem;
            color: #718096;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
            
            /* 1. 給予固定/最小寬度置中，切換時按鈕不位移 */
            min-width: 90px;
            text-align: center;
            transition: color 0.15s ease, border-color 0.15s ease;
        }

        #metab-tabs .tab::after {
            display: block;
            content: attr(data-title);
            font-weight: 700;
            height: 0;
            overflow: hidden;
            visibility: hidden;
        }

        #metab-tabs .tab.active {
            color: #1a202c;
            font-weight: 700;
            border-bottom-color: #1a202c;
        }

        #metab-tabs .tab:hover {
            color: #2d3748;
        }

        .analysis-container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #2c3e50;
        }

        .page-header {
            margin-bottom: 24px;
            border-bottom: 2px solid #edf2f7;
            padding-bottom: 16px;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1a202c;
            margin: 0 0 8px 0;
        }

        .page-subtitle {
            color: #718096;
            font-size: 0.95rem;
            margin: 0;
        }

        .card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 30px;
        }

        .image-wrapper {
            background-color: #fafbfc;
            padding: 24px;
            text-align: center;
            border-bottom: 1px solid #edf2f7;
        }

        .analysis-img {
            max-width: 100%;
            height: auto;
            border-radius: 6px;
            display: inline-block;
        }

        .caption-section {
            padding: 28px 32px;
        }

        .caption-header {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            margin-bottom: 16px;
            gap: 8px;
        }

        .figure-title {
            font-size: 1.15rem;
            font-weight: 600;
            color: #2d3748;
            margin: 0;
        }

        .tag-group {
            display: flex;
            align-items: center;
            gap: 8px; /* 標籤之間的間距 */
            flex-wrap: wrap;
        }

        .tag {
            background-color: #ebf8ff;
            color: #3182ce;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .caption-body {
            font-size: 0.95rem;
            line-height: 1.7;
            color: #4a5568;
            margin-bottom: 20px;
        }

        .key-points {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px dashed #e2e8f0;
        }

        .point-item {
            background: #f7fafc;
            padding: 12px 16px;
            border-radius: 8px;
            border-left: 4px solid #3182ce;
        }

        .point-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #a0aec0;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .point-val {
            font-size: 0.95rem;
            font-weight: 600;
            color: #2d3748;
        }

        .tab-panel {
            display: none;
        }

        .tab-panel.active {
            display: block;
        }

        .empty-panel {
            background: #ffffff;
            border-radius: 12px;
            border: 1px dashed #e2e8f0;
            padding: 60px 20px;
            text-align: center;
            color: #a0aec0;
            font-size: 0.95rem;
        }

        /* ── Browse tab ── */
        .browse-layout {
            display: flex;
            align-items: flex-start;
            gap: 28px;
        }

        .browse-sidebar {
            width: 220px;
            flex-shrink: 0;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            position: sticky;
            top: 20px;
        }

        .sidebar-title {
            font-size: 1rem;
            font-weight: 700;
            color: #1a202c;
            margin: 0 0 16px 0;
        }

        .filter-group {
            margin-bottom: 20px;
        }

        .filter-group:last-child {
            margin-bottom: 0;
        }

        .filter-group-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .filter-checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            font-size: 0.9rem;
            color: #4a5568;
            cursor: pointer;
        }

        .filter-checkbox-label:last-child {
            margin-bottom: 0;
        }

        .filter-checkbox-label input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #3182ce;
            cursor: pointer;
        }

        .browse-contents {
            flex: 1;
            min-width: 0;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }

        /* ── Browse tab：標題色塊 ── */
        .browse-sidebar {
            padding: 0;
            overflow: hidden;
        }

        .sidebar-title {
            margin: 0;
            padding: 16px 20px;
            background: #3498db;
            color: #ffffff;
            font-size: 1rem;
            font-weight: 700;
        }

        #browseFilterForm {
            padding: 20px;
        }

        .browse-title-bar {
            margin: 0;
            padding: 16px 24px;
            background: #3498db;
            color: #ffffff;
            font-size: 1.4rem;
            font-weight: 700;
            border-radius: 12px 12px 0 0;
        }

        .browse-card-body {
            padding: 20px 24px;
        }

        /* ── Browse tab：Total Records 與分頁垂直並排靠左 ── */
        .browse-meta-bar {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 16px;
        }

        .browse-meta-bar .data-info-inline {
            font-size: 0.95rem;
            color: #2c3e50;
            white-space: nowrap;
        }

        .browse-card-body .pagination {
            margin: 0;
            display: flex;
            justify-content: flex-start;
        }

        /* ── Browse tab：表格欄位名稱不要有背景色 ── */
        .browse-contents .data-table thead th {
            background: none;
            color: #1a202c;
            font-weight: 700;
            text-align: left;
            padding: 10px 12px;
            border-bottom: 2px solid #1a202c;
        }

        .browse-contents .data-table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #edf2f7;
        }

        @media (max-width: 720px) {
            .browse-layout {
                flex-direction: column;
            }
            .browse-sidebar {
                width: 100%;
                position: static;
            }
        }
    </style>
</head>
<body>

<?php include BASE_PATH . 'includes/navbar.php'; ?>

<main class="analysis-container">

    <nav class="nav-tabs" id="metab-tabs">
        <button class="tab active" data-target="sec-browse" data-title="Browse">Browse</button>
        <button class="tab" data-target="sec-analysis" data-title="Analysis">Analysis</button>
        <button class="tab" data-target="sec-references" data-title="References">References</button>
    </nav>

    <!-- Browse：依 Subtype / Other 篩選顯示代謝物列表 -->
    <section id="sec-browse" class="tab-panel active">
        <div class="browse-layout">

            <!-- 左側：Display Data 篩選欄 -->
            <aside class="browse-sidebar">
                <h3 class="sidebar-title">Display Data</h3>
                <form method="GET" id="browseFilterForm">
                    <input type="hidden" name="page" value="1">

                    <div class="filter-group">
                        <div class="filter-group-title">Subtype</div>
                        <?php foreach ($subtypeOptions as $val => $label): ?>
                            <label class="filter-checkbox-label">
                                <input type="checkbox" name="subtypes[]" value="<?= htmlspecialchars($val) ?>"
                                       <?= in_array($val, $selectedSubtypes, true) ? 'checked' : '' ?>
                                       onchange="document.getElementById('browseFilterForm').submit();">
                                <?= htmlspecialchars($label) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="filter-group">
                        <div class="filter-group-title">Other</div>
                        <?php foreach ($otherFieldOptions as $val => $label): ?>
                            <label class="filter-checkbox-label">
                                <input type="checkbox" name="other[]" value="<?= htmlspecialchars($val) ?>"
                                       <?= in_array($val, $selectedOtherFields, true) ? 'checked' : '' ?>
                                       onchange="document.getElementById('browseFilterForm').submit();">
                                <?= htmlspecialchars($label) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </form>
            </aside>

            <!-- 右側：Browse 結果表格 -->
            <div class="browse-contents">
                <h1 class="browse-title-bar">Browse Molecular Subtypes</h1>

                <div class="browse-card-body">

                    <?php if ($browseErrorMsg): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($browseErrorMsg) ?></div>
                    <?php else: ?>

                    <?php
                        // includes/pagination.php 需要 $page / $totalPages 這兩個變數名稱
                        $page       = $browsePage;
                        $totalPages = $browseTotalPages;
                    ?>
                    <div class="browse-meta-bar">
                        <div class="data-info-inline"><strong>Total Records:</strong> <?= $browseTotal ?></div>
                        <?php include BASE_PATH . 'includes/pagination.php'; ?>
                    </div>

                        <div class="table-scroll">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Metabolite ID</th>
                                        <th>Name</th>
                                        <th>Subtype</th>
                                        <th>Dataset</th>
                                        <?php foreach ($selectedOtherFields as $f): ?>
                                            <th><?= htmlspecialchars($otherFieldOptions[$f]) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($browsePageRows)): ?>
                                    <tr><td colspan="<?= 4 + count($selectedOtherFields) ?>" style="text-align:center;color:#999;">No records found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($browsePageRows as $row): ?>
                                    <tr>
                                        <td>
                                            <a href="../metabolite/<?= strtolower(htmlspecialchars($row['DMTDB_ID'])) ?>/">
                                                <?= htmlspecialchars($row['DMTDB_ID']) ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($row['metabolite_name']) ?></td>
                                        <td><?= htmlspecialchars($row['subtype']) ?></td>
                                        <td><?= htmlspecialchars($row['dataset']) ?></td>
                                        <?php foreach ($selectedOtherFields as $f): ?>
                                        <td>
                                    <?php
                                    $dmtId = $row['DMTDB_ID'];

                                    if ($f === 'max_expression') {
                                        $expData = $browseExpressionMap[$dmtId] ?? [];
                                        if (empty($expData)) {
                                            echo '-';
                                        } else {
                                            echo '<div class="expression-tags">';
                                            foreach ($expData as $pdcLabel => $tertile) {
                                                $info = browseExpressionTertileLabel((int)$tertile);
                                                echo '<span class="expression-tag ' . $info['class'] . '">'
                                                   . htmlspecialchars($pdcLabel) . ': ' . htmlspecialchars($info['label'])
                                                   . '</span>';
                                            }
                                            echo '</div>';
                                        }

                                    } elseif ($f === 'pathway') {
                                        $keggId   = $row['KEGG_ID'] ?? '';
                                        $pathways = (!empty($keggId) && isset($browsePathwayMap[$keggId]))
                                                    ? $browsePathwayMap[$keggId]
                                                    : [];
                                        if (empty($pathways)) {
                                            echo '-';
                                        } else {
                                            $limit   = 5;
                                            $showAll = count($pathways) <= $limit;
                                            $uid     = 'bpw_' . htmlspecialchars($dmtId) . '_' . htmlspecialchars($row['dataset']);
                                            echo '<div class="pathway-tags">';
                                            foreach ($pathways as $i => $pw) {
                                                $hidden  = (!$showAll && $i >= $limit) ? ' style="display:none;"' : '';
                                                $keggUrl = 'https://www.kegg.jp/kegg-bin/show_pathway?map=' .
                                                            htmlspecialchars($pw['pathway_id']) .
                                                            '&multi_query=' .
                                                            htmlspecialchars($pw['kegg_id'] ?? '');
                                                $r      = $browseCorrelationMap[$row['metabolite_name']][$pw['pathway_id']] ?? null;
                                                $rClass = browsePathwayRGradeClass($r);
                                                echo '<span class="pathway-tag ' . $rClass . '"' . $hidden . ' data-group="' . $uid . '">'
                                                   . '<a href="' . $keggUrl . '" target="_blank" title="' . htmlspecialchars($pw['pathway_id']) . '">'
                                                   . htmlspecialchars($pw['pathway_name'])
                                                   . '</a></span>';
                                            }
                                            if (!$showAll) {
                                                $extra = count($pathways) - $limit;
                                                echo '<span class="pathway-more" onclick="togglePathways(\'' . $uid . '\', this)">'
                                                   . '+' . $extra . ' more</span>';
                                            }
                                            echo '</div>';
                                        }

                                    } elseif ($f === 'os_hazard_ratio' || $f === 'pfs_hazard_ratio') {
                                        $hrData = $browseHazardRatioMap[$dmtId] ?? [];
                                        if (empty($hrData)) {
                                            echo '-';
                                        } else {
                                            $prefix = $f === 'os_hazard_ratio' ? 'os' : 'pfs';
                                            $uid = 'bhr_' . $prefix . '_' . htmlspecialchars($dmtId) . '_' . htmlspecialchars($row['dataset']);
                                            echo '<div class="hazard-ratio-tags">';
                                            $tagCount = 0;
                                            foreach ($hrData as $pdcLabel => $vals) {
                                                $tagCount++;
                                                $val    = $vals[$f] ?? null;
                                                $pValue = $vals[$prefix . '_p_value'] ?? null;

                                                $className = 'non-significant';
                                                if ($pValue !== null && $pValue < 0.05) {
                                                    if ($val > 1)      $className = 'risk-factor';
                                                    elseif ($val < 1) $className = 'protective-factor';
                                                }
                                                $labelText = $className === 'risk-factor' ? 'Risk Factor'
                                                           : ($className === 'protective-factor' ? 'Protective Factor' : 'Non-significant');

                                                $hrTitle = 'HR=' . (($val !== null) ? $val : 'N/A') . ', p=' . (($pValue !== null) ? $pValue : 'N/A');
                                                $hidden = ($tagCount > 3) ? ' style="display:none;"' : '';
                                                echo '<span class="hazard-ratio-tag ' . $className . '"' . $hidden . ' data-group="' . $uid . '" title="' . htmlspecialchars($hrTitle) . '">'
                                                   . htmlspecialchars($pdcLabel) . ': ' . htmlspecialchars($labelText)
                                                   . '</span>';
                                            }
                                            if ($tagCount > 3) {
                                                $extra = $tagCount - 3;
                                                echo '<span class="hazard-ratio-more" onclick="toggleHazardRatios(\'' . $uid . '\', this)">'
                                                   . '+' . $extra . ' more</span>';
                                            }
                                            echo '</div>';
                                        }
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
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Analysis：目前的內容 -->
    <section id="sec-analysis" class="tab-panel">
        <h1 class="page-title">GSVA Score Analysis</h1>
        <div class="card">
            <div class="image-wrapper">
                <?php
                    $imageName = "analysis_result//Figure_6_boxplot_mesenchymal.png";
                    echo '<img src="' . $imageName . '" alt="GSVA Mesenchymal Subtype Boxplot" class="analysis-img">';
                ?>
            </div>

            <section class="caption-section">
                <div class="caption-header">
                    <h2 class="figure-title">Distribution of GSVA Scores for the Three Molecular Subtypes Among Samples Classified as Mesenchymal</h2>
                    <div class="tag-group">
                        <span class="tag">GSVA Score</span>
                    </div>
                </div>

                <p class="caption-body">
                    As illustrated in the boxplots, samples classified as the Mesenchymal subtype exhibited the highest Mesenchymal GSVA enrichment scores. 
                    Nevertheless, samples assigned to the Classical and Proneural subtypes also displayed long-tailed distributions, indicating considerable variation in subtype enrichment. 
                    These extended distributions likely reflect the <strong>intrinsic intratumoral heterogeneity</strong> of GBM and may represent transitional transcriptional states associated with malignant evolution.
                </p>

                <div class="key-points">
                    <div class="point-item">
                        <div class="point-label">Highest Score</div>
                        <div class="point-val">Mesenchymal (~0.44)</div>
                    </div>
                    <div class="point-item">
                        <div class="point-label">Intermediate</div>
                        <div class="point-val">Classical (~0.38)</div>
                    </div>
                    <div class="point-item">
                        <div class="point-label">Lowest Score</div>
                        <div class="point-val">Proneural (~0.33)</div>
                    </div>
                </div>
            </section>
        </div>
    </section>

    <!-- References：先留空 -->
    <section id="sec-references" class="tab-panel">
        <div class="empty-panel">References 內容尚未加入</div>
    </section>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tabs = document.querySelectorAll('#metab-tabs .tab');
        const panels = document.querySelectorAll('.tab-panel');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                const targetId = tab.getAttribute('data-target');

                tabs.forEach(function (t) { t.classList.remove('active'); });
                tab.classList.add('active');

                panels.forEach(function (panel) {
                    panel.classList.toggle('active', panel.id === targetId);
                });
            });
        });
    });

    /* ── Browse tab：Pathway 展開/收折 ── */
    function togglePathways(uid, btn) {
        const tags = document.querySelectorAll('.pathway-tag[data-group="' + uid + '"]');
        const hidden = [...tags].filter(t => t.style.display === 'none');
        if (hidden.length > 0) {
            hidden.forEach(t => t.style.display = '');
            btn.textContent = 'Show less';
        } else {
            let count = 0;
            tags.forEach(t => {
                count++;
                if (count > 5) t.style.display = 'none';
            });
            const extra = tags.length - 5;
            btn.textContent = '+' + extra + ' more';
        }
    }

    /* ── Browse tab：Hazard Ratio 展開/收折 ── */
    function toggleHazardRatios(uid, btn) {
        const tags = document.querySelectorAll('.hazard-ratio-tag[data-group="' + uid + '"]');
        const hidden = [...tags].filter(t => t.style.display === 'none');
        if (hidden.length > 0) {
            hidden.forEach(t => t.style.display = '');
            btn.textContent = 'Show less';
        } else {
            let count = 0;
            tags.forEach(t => {
                count++;
                if (count > 3) t.style.display = 'none';
            });
            const extra = tags.length - 3;
            btn.textContent = '+' + extra + ' more';
        }
    }
</script>

<?php include BASE_PATH . 'includes/footer.php'; ?>

</body>
</html>