<?php
// =============================================
// metabolites.php  —  瀏覽代謝物
// =============================================
require_once 'config.php';

// 所有可選欄位（白名單）
$allFields = [
    'hmdb_id'          => 'HMDB ID',
    'chebi_id'         => 'ChEBI ID',
    'pubchem_id'       => 'PubChem ID',
    'kegg_id'          => 'KEGG ID',
    'max_expression'     => 'Maximum Expression',
    // 'PRONEURAL'          => 'Proneural Expression',
    // 'CLASSICAL'          => 'Classical Expression',
    // 'MESENCHYMAL'        => 'Mesenchymal Expression',
    'molecular_subtypes' => 'Maximum Expression in Molecular Subtypes',
    'pathway'            => 'KEGG Pathway',
    'correlated_pathway' => 'Pathways Correlation Coefficient',
    'prognosis_correlated_pathway' => 'Prognosis-Associated Pathways',
    'os_hazard_ratio'  => 'OS Hazard Ratio',
    'pfs_hazard_ratio' => 'PFS Hazard Ratio'
];

$diseaseOptions = [
    'glioma(gbm)' => 'Glioma(GBM)',
    'others' => 'Others',
];

// 疾病 → disease_name 的對應（用來查 metabolite_data_source）
$diseaseNameMap = [
    'glioma(gbm)' => 'GBM',
];

// 目前 metabolites_id 資料表實際存在的欄位對應
// key = $allFields 的 key，value = 資料表實際欄位名稱
// 尚未建立的欄位不列在這裡，查詢時會自動填空白
$existingCols = [
    'hmdb_id'    => 'HMDB_ID',
    'chebi_id'   => 'CHEBI_ID',
    'pubchem_id' => 'Pubchem_ID',
    'kegg_id'    => 'KEGG_ID',
];

// Maximum Expression 來源資料表（PDC dataset name => table name）
$expressionTables = [
    'PDC000546' => 'cptac3_pdc000546_expressiondata_max',
    'PDC000552' => 'cptac3_pdc000552_expressiondata_max',
];

// Hazard Ratio 來源資料表（PDC dataset name => table name）
$hazardRatioTables = [
    'PDC000546' => 'cptac3_pdc000546_hazard_ratio_max',
    'PDC000552' => 'cptac3_pdc000552_hazard_ratio_max',
];

// Molecular Subtypes 來源資料表（PDC dataset name => table name）
$molecularSubtypeTables = [
    'PDC000546' => 'cptac3_pdc000546_metabolite_molecular',
    'PDC000552' => 'cptac3_pdc000552_metabolite_molecular',
];

// link 欄位對應
$linkCols = [
    'hmdb_id'    => 'HMDB_link',
    'chebi_id'   => 'CHEBI_link',
    'pubchem_id' => 'Pubchem_link',
    'kegg_id'    => 'KEGG_link',
];

// ── AJAX：依疾病回傳樣本清單 ─────────────────────────────────
if (isset($_GET['ajax_projects'])) {
    header('Content-Type: application/json');
    $reqDiseases = (array)($_GET['diseases'] ?? []);
    $reqDiseases = array_intersect($reqDiseases, array_keys($diseaseOptions));
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
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
    exit;
}
// ─────────────────────────────────────────────────────────────


$showResults   = false;
$results       = [];
$pathwayMap    = [];   // kegg_id => [ ['pathway_id'=>..., 'pathway_name'=>...], ... ]
$expressionMap = [];   // DMTDB_ID => [ 'PDC000546' => value, 'PDC000552' => value ]
$hazardRatioMap = [];  // DMTDB_ID => [ 'PDC000546' => value, 'PDC000552' => value ]
$molecularSubtypeMap = []; // DMTDB_ID => [ 'PDC000546' => ['subtype'=>..., 'ssi'=>...], ... ]
$total         = 0;
$totalPages    = 1;
$errorMsg      = '';

// 分頁
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * PER_PAGE;

// 收到 Submit
if (!empty($_GET['diseases'])) {
    $showResults = true;

    // 驗證疾病選項（白名單）— 目前資料表無 disease 欄位，先忽略篩選直接顯示全部
    $diseases = array_intersect((array)$_GET['diseases'], array_keys($diseaseOptions));

    // 驗證欄位選項（白名單）
    $selectedFields = array_intersect((array)($_GET['fields'] ?? []), array_keys($allFields));

    if (empty($diseases)) {
        $errorMsg = 'Please select at least one disease category.';
        $showResults = false;
    } else {
        $db = getDB();   // PDO 連線

        // 計算總筆數（disease 欄位尚未建立，暫時顯示全部資料）
        $countRes   = $db->query("SELECT COUNT(*) AS cnt FROM metabolites_id");
        $countRow = $countRes->fetch(PDO::FETCH_ASSOC);
        $total    = (int)$countRow['cnt'];
        $totalPages = max(1, (int)ceil($total / PER_PAGE));

        // 固定欄位
        $selectCols = ['DMTDB_ID', 'metabolite_name'];

        // 只 SELECT 資料表中實際存在的欄位；其餘欄位在 PHP 端補 -
        foreach ($selectedFields as $f) {
            if (isset($existingCols[$f])) {
                $selectCols[] = $existingCols[$f];
                if (isset($linkCols[$f])) {
                    $selectCols[] = $linkCols[$f];
                }
            }
        }

        // 若有選 pathway 欄位，確保 KEGG_ID 也被 SELECT（用來做 pathway 查詢）
        $needPathway = in_array('pathway', $selectedFields);
        if ($needPathway && !in_array('KEGG_ID', $selectCols)) {
            $selectCols[] = 'KEGG_ID';
        }

        // 若有選 max_expression 欄位
        $needExpression = in_array('max_expression', $selectedFields);

        // 若有選 molecular_subtypes 欄位
        $needMolecularSubtype = in_array('molecular_subtypes', $selectedFields);

        // 若有選 Proneural / Classical / Mesenchymal Expression 欄位
        $needSubtypeExpression = in_array('PRONEURAL', $selectedFields)
                        || in_array('CLASSICAL', $selectedFields)
                        || in_array('MESENCHYMAL', $selectedFields);

        $selectCols = array_unique($selectCols);
        $colsSql    = implode(', ', array_map(fn($c) => "`$c`", $selectCols));
        $limit      = PER_PAGE;

        $res = $db->query("SELECT $colsSql FROM metabolites_id ORDER BY DMTDB_ID LIMIT $offset, $limit");
        if (!$res) {
            $errorMsg    = '查詢失敗：' . $db->error;
            $showResults = false;
        } else {
            while ($row = $res->fetch(PDO::FETCH_ASSOC)) $results[] = $row;
        }

        // ── 若有選 hazard_ratio 欄位
        $needHazardRatio = in_array('os_hazard_ratio', $selectedFields) || in_array('pfs_hazard_ratio', $selectedFields);

        if ($needHazardRatio && !empty($results)) {
            $dmtdbIds    = array_column($results, 'DMTDB_ID');
            $placeholders = implode(',', array_fill(0, count($dmtdbIds), '?'));

            // 只查詢使用者有勾選的 PDC 樣本
            $selectedProjects = array_intersect(
                (array)($_GET['projects'] ?? []),
                array_keys($hazardRatioTables)
            );
            // 若未選任何樣本，預設顯示全部
            $targetTables = empty($selectedProjects)
                ? $hazardRatioTables
                : array_intersect_key($hazardRatioTables, array_flip($selectedProjects));

            foreach ($targetTables as $pdcLabel => $tblName) {
                $stmt = $db->prepare(
                    "SELECT `DMTDB_ID`, `hazard_ratio_OS`, `hazard_ratio_PFS`, `OS_p_value`, `PFS_p_value`
                     FROM `$tblName`
                     WHERE `DMTDB_ID` IN ($placeholders)"
                );
                $stmt->execute(array_values($dmtdbIds));
                while ($hRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $dmtId = $hRow['DMTDB_ID'];
                    if (isset($hRow['hazard_ratio_OS'])) {
                        $hazardRatioMap[$dmtId][$pdcLabel]['os_hazard_ratio'] = $hRow['hazard_ratio_OS'];
                        $hazardRatioMap[$dmtId][$pdcLabel]['os_p_value'] = $hRow['OS_p_value'] ?? null;
                    }
                    if (isset($hRow['hazard_ratio_PFS'])) {
                        $hazardRatioMap[$dmtId][$pdcLabel]['pfs_hazard_ratio'] = $hRow['hazard_ratio_PFS'];
                        $hazardRatioMap[$dmtId][$pdcLabel]['pfs_p_value'] = $hRow['PFS_p_value'] ?? null;
                    }
                }
            }
        }

        // ── Pathway 查詢 ──────────────────────────────────────────────
        // 若使用者有勾選 pathway 欄位，批次撈出本頁所有代謝物的代謝途徑名稱
        if ($needPathway && !empty($results)) {
            // 收集本頁所有非空的 KEGG_ID
            $keggIds = array_filter(
                array_unique(array_column($results, 'KEGG_ID')),
                fn($v) => $v !== null && $v !== ''
            );

            if (!empty($keggIds)) {
                // 用 IN 批次查詢，避免 N+1
                $placeholders = implode(',', array_fill(0, count($keggIds), '?'));
                $stmt = $db->prepare(
                    "SELECT p.kegg_id, p.pathway_id, n.pathway_name
                     FROM kegg_hsa_metabolism_pathways p
                     LEFT JOIN kegg_hsa_pathwayname n USING (pathway_id)
                     WHERE p.kegg_id IN ($placeholders)
                     ORDER BY p.kegg_id, p.pathway_id"
                );
                $stmt->execute(array_values($keggIds));

                while ($pRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $kid = $pRow['kegg_id'];
                    $pathwayMap[$kid][] = [
                        'pathway_id'   => $pRow['pathway_id'],
                        'pathway_name' => $pRow['pathway_name'] ?? $pRow['pathway_id'],
                        'kegg_id'      => $pRow['kegg_id'],
                    ];
                }
            }
        }
        // ─────────────────────────────────────────────────────────────

        // ── Maximum Expression 查詢 ───────────────────────────────────
        if ($needExpression && !empty($results)) {
            $dmtdbIds    = array_column($results, 'DMTDB_ID');
            $placeholders = implode(',', array_fill(0, count($dmtdbIds), '?'));

            // 只查詢使用者有勾選的 PDC 樣本
            $selectedProjects = array_intersect(
                (array)($_GET['projects'] ?? []),
                array_keys($expressionTables)
            );
            // 若未選任何樣本，預設顯示全部
            $targetTables = empty($selectedProjects)
                ? $expressionTables
                : array_intersect_key($expressionTables, array_flip($selectedProjects));

            foreach ($targetTables as $pdcLabel => $tblName) {
                $stmt = $db->prepare(
                    "SELECT `DMTDB_ID`, `average_expression`
                     FROM `$tblName`
                     WHERE `DMTDB_ID` IN ($placeholders)"
                );
                $stmt->execute(array_values($dmtdbIds));
                while ($eRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $expressionMap[$eRow['DMTDB_ID']][$pdcLabel] = $eRow['average_expression'];
                }
            }
        }
        // ─────────────────────────────────────────────────────────────

        // ── Molecular Subtypes / Subtype Expression 查詢 ────────────────
        if (($needMolecularSubtype || $needSubtypeExpression) && !empty($results)) {
            $dmtdbIds    = array_column($results, 'DMTDB_ID');
            $placeholders = implode(',', array_fill(0, count($dmtdbIds), '?'));

            $selectedProjects = array_intersect(
                (array)($_GET['projects'] ?? []),
                array_keys($molecularSubtypeTables)
            );
            $targetTables = empty($selectedProjects)
                ? $molecularSubtypeTables
                : array_intersect_key($molecularSubtypeTables, array_flip($selectedProjects));

            foreach ($targetTables as $pdcLabel => $tblName) {
                $stmt = $db->prepare(
                    "SELECT `DMTDB_ID`, `PRONEURAL`, `CLASSICAL`, `MESENCHYMAL`,
                            `MAX_concentration_subtype`, `Subtype_specificity_index`
                    FROM `$tblName`
                    WHERE `DMTDB_ID` IN ($placeholders)"
                );
                $stmt->execute(array_values($dmtdbIds));
                while ($mRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $molecularSubtypeMap[$mRow['DMTDB_ID']][$pdcLabel] = [
                        'subtype'     => $mRow['MAX_concentration_subtype'],
                        'ssi'         => $mRow['Subtype_specificity_index'],
                        'PRONEURAL'   => $mRow['PRONEURAL'],
                        'CLASSICAL'   => $mRow['CLASSICAL'],
                        'MESENCHYMAL' => $mRow['MESENCHYMAL'],
                    ];
                }
            }
        }
        // ─────────────────────────────────────────────────────────────
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
    <link rel="stylesheet" href="css/pathway.css">
    <link rel="stylesheet" href="css/hazard_ratio.css">
</head>
<body>

<?php include BASE_PATH . 'includes/navbar.php'; ?>

<div class="main-container">

    <!-- ===== 選擇區域 ===== -->
    <?php if (!$showResults): ?>
    <div class="selection-container">
        <h1 style="margin-bottom:1.5rem; color:#2c3e50;">Browse Metabolites</h1>

        <form method="GET" action="metabolites.php" id="browseForm">

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
                <div class="chips-container" id="projectContainer">
                    <!-- 由 JavaScript 動態填入 -->
                </div>
            </div>

            <!-- 欄位選擇 -->
            <div class="selection-section">
                <div class="section-title" id="fieldsStepTitle">3. Select Additional Data Fields to Display</div>
                <div class="section-subtitle">Metabolite ID and Name are always displayed. Choose additional information:</div>
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
        <a href="metabolites.php" class="btn-back">← Back to Selection</a>

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
                        <td>
                            <a href="metabolite/<?= strtolower(htmlspecialchars($row['DMTDB_ID'])) ?>/">
                            <?= htmlspecialchars($row['DMTDB_ID']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($row['metabolite_name']) ?></td>
                        <?php foreach ($selectedFields as $f): ?>
                        <td>
                            <?php
                            // ── Pathway 欄位：從 pathwayMap 撈對應途徑 ──
                            if ($f === 'pathway') {
                                $keggId   = $row['KEGG_ID'] ?? '';
                                $pathways = (!empty($keggId) && isset($pathwayMap[$keggId]))
                                            ? $pathwayMap[$keggId]
                                            : [];

                                if (empty($pathways)) {
                                    echo '-';
                                } else {
                                    // 最多先顯示 5 條，其餘收折（純 CSS toggle）
                                    $limit   = 5;
                                    $showAll = count($pathways) <= $limit;
                                    $uid     = 'pw_' . htmlspecialchars($row['DMTDB_ID']);
                                    echo '<div class="pathway-tags">';
                                    foreach ($pathways as $i => $pw) {
                                        $hidden = (!$showAll && $i >= $limit) ? ' style="display:none;"' : '';
                                        $keggUrl = 'https://www.kegg.jp/kegg-bin/show_pathway?map=' . 
                                                    htmlspecialchars($pw['pathway_id']) . 
                                                    '&multi_query=' . 
                                                    htmlspecialchars($pw['kegg_id'] ?? '');
                                        echo '<span class="pathway-tag"' . $hidden . ' data-group="' . $uid . '">'
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

                            // ── Maximum Expression 欄位
                            } elseif ($f === 'max_expression') {
                                $dmtId  = $row['DMTDB_ID'];
                                $expData = $expressionMap[$dmtId] ?? [];
                                if (empty($expData)) {
                                    echo '-';
                                } else {
                                    echo '<div class="pathway-tags">';
                                    foreach ($expData as $pdcLabel => $expVal) {
                                        $formatted = ($expVal !== null) ? number_format((float)$expVal, 2) : 'N/A';
                                        echo '<span class="pathway-tag">'
                                           . htmlspecialchars($pdcLabel) . ': ' . htmlspecialchars($formatted)
                                           . '</span>';
                                    }
                                    echo '</div>';
                                }

                            // ── Proneural / Classical / Mesenchymal Expression 欄位
                            } elseif ($f === 'PRONEURAL' || $f === 'CLASSICAL' || $f === 'MESENCHYMAL') {
                                $dmtId   = $row['DMTDB_ID'];
                                $subData = $molecularSubtypeMap[$dmtId] ?? [];
                                if (empty($subData)) {
                                    echo '-';
                                } else {
                                    echo '<div class="pathway-tags">';
                                    foreach ($subData as $pdcLabel => $info) {
                                        $val = $info[$f] ?? null;
                                        $formatted = ($val !== null) ? number_format((float)$val, 2) : 'N/A';
                                        echo '<span class="pathway-tag">'
                                           . htmlspecialchars($pdcLabel) . ': ' . htmlspecialchars($formatted)
                                           . '</span>';
                                    }
                                    echo '</div>';
                                }

                            // ── Molecular Subtypes 欄位
                            } elseif ($f === 'molecular_subtypes') {
                                $dmtId  = $row['DMTDB_ID'];
                                $subData = $molecularSubtypeMap[$dmtId] ?? [];
                                if (empty($subData)) {
                                    echo '-';
                                } else {
                                    echo '<div class="pathway-tags">';
                                    foreach ($subData as $pdcLabel => $info) {
                                        $subtype = $info['subtype'] ?? 'N/A';
                                        $ssi     = $info['ssi'];
                                        $ssiText = ($ssi !== null) ? number_format((float)$ssi, 2) : 'N/A';
                                        echo '<span class="pathway-tag">'
                                            . htmlspecialchars($pdcLabel) . ': ' . htmlspecialchars($subtype)
                                            . '(' . htmlspecialchars($ssiText) . ')'
                                            . '</span>';
                                    }
                                    echo '</div>';
                                }
                            
                            // os_hazard_ratio 欄位
                            } elseif ($f === 'os_hazard_ratio') {
                                $dmtId = $row['DMTDB_ID'];
                                $hrData = $hazardRatioMap[$dmtId] ?? [];
                                if (empty($hrData)) {
                                    echo '-';
                                } else {
                                    $uid = 'hr_os_' . htmlspecialchars($row['DMTDB_ID']);
                                    echo '<div class="hazard-ratio-tags">';
                                    $tagCount = 0;
                                    foreach ($hrData as $pdcLabel => $vals) {
                                        $tagCount++;
                                        $val = $vals['os_hazard_ratio'] ?? null;
                                        $pValue = $vals['os_p_value'] ?? null;
                                        # $pFormatted = ($pValue !== null) ? $pValue : 'N/A';
                                        
                                        // 判斷分類
                                        $className = 'non-significant';
                                        if ($pValue !== null && $pValue < 0.05) {
                                            if ($val > 1) {
                                                $className = 'risk-factor';
                                            } elseif ($val < 1) {
                                                $className = 'protective-factor';
                                            }
                                        }

                                        // 顯示文字：不直接顯示數值，改顯示分類文字
                                        if ($className === 'risk-factor') {
                                            $labelText = 'Risk Factor';
                                        } elseif ($className === 'protective-factor') {
                                            $labelText = 'Protective Factor';
                                        } else {
                                            $labelText = 'Non-significant';
                                        }

                                        // title：滑鼠移到上面時顯示實際數值（HR / p-value）
                                        $hrTitle = 'HR=' . (($val !== null) ? $val : 'N/A')
                                                 . ', p=' . (($pValue !== null) ? $pValue : 'N/A');
                                        
                                        // 最多先顯示 3 條
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

                            // pfs_hazard_ratio 欄位
                            } elseif ($f === 'pfs_hazard_ratio') {
                                $dmtId = $row['DMTDB_ID'];
                                $hrData = $hazardRatioMap[$dmtId] ?? [];
                                if (empty($hrData)) {
                                    echo '-';
                                } else {
                                    $uid = 'hr_pfs_' . htmlspecialchars($row['DMTDB_ID']);
                                    echo '<div class="hazard-ratio-tags">';
                                    $tagCount = 0;
                                    foreach ($hrData as $pdcLabel => $vals) {
                                        $tagCount++;
                                        $val = $vals['pfs_hazard_ratio'] ?? null;
                                        $pValue = $vals['pfs_p_value'] ?? null;
                                        # $pFormatted = ($pValue !== null) ? $pValue : 'N/A';
                                        
                                        // 判斷分類
                                        $className = 'non-significant';
                                        if ($pValue !== null && $pValue < 0.05) {
                                            if ($val > 1) {
                                                $className = 'risk-factor';
                                            } elseif ($val < 1) {
                                                $className = 'protective-factor';
                                            }
                                        }

                                        // 顯示文字：不直接顯示數值，改顯示分類文字
                                        if ($className === 'risk-factor') {
                                            $labelText = 'Risk Factor';
                                        } elseif ($className === 'protective-factor') {
                                            $labelText = 'Protective Factor';
                                        } else {
                                            $labelText = 'Non-significant';
                                        }

                                        // title：滑鼠移到上面時顯示實際數值（HR / p-value）
                                        $hrTitle = 'HR=' . (($val !== null) ? $val : 'N/A')
                                                 . ', p=' . (($pValue !== null) ? $pValue : 'N/A');
                                        
                                        // 最多先顯示 3 條
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
                            // ── 其他欄位（原邏輯不變）──
                            } elseif (!isset($existingCols[$f])) {
                                echo '-';
                            } else {
                                $colName = $existingCols[$f];
                                $val     = $row[$colName] ?? '';
                                if ($val === '' || $val === null) {
                                    echo '-';
                                } elseif ($f === 'hmdb_id') {
                                    $link = $row['HMDB_link'] ?? '';
                                    echo $link
                                        ? '<a href="' . htmlspecialchars($link) . '" target="_blank">' . htmlspecialchars($val) . '</a>'
                                        : htmlspecialchars($val);
                                } elseif ($f === 'chebi_id') {
                                    $link = $row['CHEBI_link'] ?? '';
                                    echo $link
                                        ? '<a href="' . htmlspecialchars($link) . '" target="_blank">' . htmlspecialchars($val) . '</a>'
                                        : htmlspecialchars($val);
                                } elseif ($f === 'pubchem_id') {
                                    $link = $row['Pubchem_link'] ?? '';
                                    echo $link
                                        ? '<a href="' . htmlspecialchars($link) . '" target="_blank">' . htmlspecialchars($val) . '</a>'
                                        : htmlspecialchars($val);
                                } elseif ($f === 'kegg_id') {
                                    $link = $row['KEGG_link'] ?? '';
                                    echo $link
                                        ? '<a href="' . htmlspecialchars($link) . '" target="_blank">' . htmlspecialchars($val) . '</a>'
                                        : htmlspecialchars($val);
                                } else {
                                    echo htmlspecialchars($val);
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

        <!-- 分頁（下） -->
        <?php include BASE_PATH . 'includes/pagination.php'; ?>

        <?php endif; ?>
    </div>

    <?php endif; ?>

</div>

<?php include BASE_PATH . 'includes/footer.php'; ?>
<link rel="stylesheet" href="css/metabolites.css">
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
/* ── Hazard Ratio 展開/收折 ── */
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

// All Information 按鈕：全選 / 取消全選 fields
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

function bindDiseaseChips() {
    document.querySelectorAll('input[name="diseases[]"]').forEach(cb => {
        cb.addEventListener('change', onDiseaseChange);
    });
}

function onDiseaseChange() {
    validateForm();
    loadProjects();
}

function loadProjects() {
    const checked = [...document.querySelectorAll('input[name="diseases[]"]:checked')];
    const projectSection = document.getElementById('projectSection');
    const projectContainer = document.getElementById('projectContainer');

    if (checked.length === 0) {
        projectSection.style.display = 'none';
        projectContainer.innerHTML = '';
        return;
    }

    // 顯示 loading
    projectSection.style.display = '';
    projectContainer.innerHTML = '<span style="color:#7f8c8d;font-size:0.9rem;">Loading samples…</span>';

    // 取消上一個請求
    if (projectFetchController) projectFetchController.abort();
    projectFetchController = new AbortController();

    const params = new URLSearchParams();
    params.append('ajax_projects', '1');
    checked.forEach(cb => params.append('diseases[]', cb.value));

    fetch('metabolites.php?' + params.toString(), { signal: projectFetchController.signal })
        .then(r => r.json())
        .then(rows => {
            projectContainer.innerHTML = '';
            if (rows.length === 0) {
                projectContainer.innerHTML = '<span style="color:#7f8c8d;font-size:0.9rem;">No samples found for selected disease(s).</span>';
                return;
            }

            // 記住之前已勾選的 projects（換疾病時保留使用者的選擇）
            const prevSelected = new Set(
                [...document.querySelectorAll('input[name="projects[]"]:checked')].map(i => i.value)
            );

            rows.forEach(row => {
                const pdc = row.project_number;
                const isChecked = prevSelected.size > 0 ? prevSelected.has(pdc) : true; // 預設全選
                const label = document.createElement('label');
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

bindDiseaseChips();

// 若頁面重新整理時疾病已有預選（不太可能，但防呆）
if (document.querySelectorAll('input[name="diseases[]"]:checked').length > 0) {
    loadProjects();
}

/* ── Pathway 展開/收折 ── */
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
</script>
</body>
</html>