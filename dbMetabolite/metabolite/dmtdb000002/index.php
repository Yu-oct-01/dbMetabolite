<?php
// =============================================
// metabolite/[DMTDB_ID]/index.php  —  代謝物個別頁面 (整合 Analysis 四大維度)
// =============================================
require_once '../../config.php';

$uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$rawId   = trim(basename(rtrim($uriPath, '/')));
$dmtdbId = strtoupper($rawId);

if ($dmtdbId === '' || $dmtdbId === '.') {
    header('Location: ../../metabolites.php');
    exit;
}

$db = getDB();

// 來源臨床病患判斷
$fromClinical = isset($_GET['from']) && $_GET['from'] === 'clinical';
$fromPatient  = '';
if ($fromClinical && isset($_GET['patient'])) {
    $raw = $_GET['patient'];
    if (preg_match('/^[A-Za-z0-9\-]+$/', $raw)) {
        $fromPatient = $raw;
    } else {
        $fromClinical = false;
    }
}

// ── 1. 取得 metabolite_name 與 average_expression ───────────────
$metaboliteName = '';
$expressionData = [];
$expressionTables = [
    'PDC000546' => 'cptac3_pdc000546_expressiondata_max',
    'PDC000552' => 'cptac3_pdc000552_expressiondata_max',
];
foreach ($expressionTables as $pdc => $eTbl) {
    $q = $db->prepare("SELECT metabolite_name, average_expression FROM `$eTbl` WHERE `DMTDB_ID` = ? LIMIT 1");
    $q->execute([$dmtdbId]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        if ($metaboliteName === '' && !empty($row['metabolite_name']))
            $metaboliteName = $row['metabolite_name'];
        if ($row['average_expression'] !== null)
            $expressionData[$pdc] = $row['average_expression'];
    }
}
if ($metaboliteName === '') $metaboliteName = $dmtdbId;

// ── 2. 取得 Molecular Subtypes ───
$molecularSubtypeData = [];
$molecularTables = [
    'PDC000546' => 'cptac3_pdc000546_metabolite_molecular',
    'PDC000552' => 'cptac3_pdc000552_metabolite_molecular',
];
foreach ($molecularTables as $pdc => $mTbl) {
    $q = $db->prepare(
        "SELECT `PRONEURAL`, `CLASSICAL`, `MESENCHYMAL`,
                `MAX_concentration_subtype`, `Subtype_specificity_index`
         FROM `$mTbl`
         WHERE `DMTDB_ID` = ? LIMIT 1"
    );
    $q->execute([$dmtdbId]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $molecularSubtypeData[$pdc] = [
            'subtype'     => $row['MAX_concentration_subtype'],
            'ssi'         => $row['Subtype_specificity_index'],
            'PRONEURAL'   => $row['PRONEURAL'],
            'CLASSICAL'   => $row['CLASSICAL'],
            'MESENCHYMAL' => $row['MESENCHYMAL'],
        ];
    }
}

// ── 3. 取得 Prognosis / Hazard Ratio ──
$hazardRatioData = [];
$hazardRatioTables = [
    'PDC000546' => 'cptac3_pdc000546_hazard_ratio_max',
    'PDC000552' => 'cptac3_pdc000552_hazard_ratio_max',
];
foreach ($hazardRatioTables as $pdc => $hTbl) {
    $q = $db->prepare(
        "SELECT `hazard_ratio_OS`, `hazard_ratio_PFS`, `OS_p_value`, `PFS_p_value`
         FROM `$hTbl`
         WHERE `DMTDB_ID` = ? LIMIT 1"
    );
    $q->execute([$dmtdbId]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        if (isset($row['hazard_ratio_OS'])) {
            $hazardRatioData[$pdc]['os_hazard_ratio'] = $row['hazard_ratio_OS'];
            $hazardRatioData[$pdc]['os_p_value']      = $row['OS_p_value'] ?? null;
        }
        if (isset($row['hazard_ratio_PFS'])) {
            $hazardRatioData[$pdc]['pfs_hazard_ratio'] = $row['hazard_ratio_PFS'];
            $hazardRatioData[$pdc]['pfs_p_value']      = $row['PFS_p_value'] ?? null;
        }
    }
}

// ── 4. 取得 external links ──────────
$externalLinks = [];
$linkStmt = $db->prepare("SELECT * FROM metabolites_id WHERE DMTDB_ID = ? LIMIT 1");
$linkStmt->execute([$dmtdbId]);
$linkRow = $linkStmt->fetch(PDO::FETCH_ASSOC);
if ($linkRow) {
    if (!empty($linkRow['HMDB_ID']) && !empty($linkRow['HMDB_link'])) {
        $externalLinks[] = ['label' => htmlspecialchars($linkRow['HMDB_ID']),     'url' => htmlspecialchars($linkRow['HMDB_link']),    'source' => 'HMDB'];
    }
    if (!empty($linkRow['Pubchem_ID']) && !empty($linkRow['Pubchem_link'])) {
        $externalLinks[] = ['label' => htmlspecialchars($linkRow['Pubchem_ID']),  'url' => htmlspecialchars($linkRow['Pubchem_link']), 'source' => 'PubChem'];
    }
    if (!empty($linkRow['CHEBI_ID']) && !empty($linkRow['CHEBI_link'])) {
        $externalLinks[] = ['label' => htmlspecialchars($linkRow['CHEBI_ID']),    'url' => htmlspecialchars($linkRow['CHEBI_link']),   'source' => 'ChEBI'];
    }
    if (!empty($linkRow['KEGG_ID']) && !empty($linkRow['KEGG_link'])) {
        $externalLinks[] = ['label' => htmlspecialchars($linkRow['KEGG_ID']),     'url' => htmlspecialchars($linkRow['KEGG_link']),    'source' => 'KEGG'];
    }
}

// ── 5. Synonyms 查詢 ──────────
$synonyms = [];
if ($linkRow && !empty($linkRow['HMDB_ID'])) {
    $synStmt = $db->prepare("SELECT DISTINCT synonyms FROM hmdb_synonyms WHERE HMDB_ID = ? ORDER BY synonyms ASC");
    $synStmt->execute([$linkRow['HMDB_ID']]);
    $synonyms = $synStmt->fetchAll(PDO::FETCH_COLUMN);
}

// ── 6. Pathway 查詢 ──────────
$pathways = [];
if ($linkRow && !empty($linkRow['KEGG_ID'])) {
    $keggId = $linkRow['KEGG_ID'];
    $pwStmt = $db->prepare(
        "SELECT p.pathway_id, n.pathway_name
         FROM kegg_hsa_metabolism_pathways p
         LEFT JOIN kegg_hsa_pathwayname n USING (pathway_id)
         WHERE p.kegg_id = ?
         ORDER BY p.pathway_id ASC"
    );
    $pwStmt->execute([$keggId]);
    $pathways = $pwStmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── 7. Back URL 邏輯 ──────────
$backUrl = '/metabolites.php';
if (!empty($_GET['back'])) {
    $decoded = urldecode($_GET['back']);
    $parsed  = parse_url($decoded);
    $path    = $parsed['path'] ?? '';
    if ($path === '' && isset($parsed['query'])) {
        $backUrl = '/clinicaldata.php?' . $parsed['query'];
    } elseif (preg_match('#^/clinicaldata\.php$#', $path)) {
        $safeQuery = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $backUrl   = '/clinicaldata.php' . $safeQuery;
    }
} elseif (!empty($_SERVER['HTTP_REFERER'])) {
    $ref     = $_SERVER['HTTP_REFERER'];
    $refPath = parse_url($ref, PHP_URL_PATH) ?? '';
    $allowedPaths = ['/metabolites.php', '/search/results_name.php', '/search/results_id.php', '/search/results_pathway.php', '/search/results_tme.php'];
    foreach ($allowedPaths as $p) {
        if (str_starts_with($refPath, $p) || $refPath === $p) {
            $backUrl = $ref;
            break;
        }
    }
}

// 整理供 Analysis 連結使用的主要亞型與預後摘要
$topSubtype = '-';
if (!empty($molecularSubtypeData)) {
    $firstPdc = array_key_first($molecularSubtypeData);
    $topSubtype = $molecularSubtypeData[$firstPdc]['subtype'] ?? '-';
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metabolite Database — <?= htmlspecialchars($metaboliteName) ?></title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/metabolite.css">
    <link rel="stylesheet" href="../../css/pathway.css">
    <link rel="stylesheet" href="../../css/hazard_ratio.css">
    <style>
        /* 新增：跨維度分析模組樣式 */
        .cross-analysis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            margin-top: 15px;
        }
        .cross-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            border-top: 4px solid #adb5bd;
        }
        .cross-card.subtype { border-top-color: #0d6efd; }
        .cross-card.immune  { border-top-color: #198754; }
        .cross-card.drug    { border-top-color: #fd7e14; }
        .cross-card.recurrent { border-top-color: #6f42c1; }
        
        .cross-card-header {
            font-size: 13px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .cross-card-value {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 6px;
        }
        .cross-card-desc {
            font-size: 12px;
            color: #64748b;
            line-height: 1.4;
            margin-bottom: 12px;
        }
        .cross-link-btn {
            display: inline-block;
            text-align: center;
            padding: 6px 10px;
            font-size: 12px;
            border-radius: 4px;
            text-decoration: none;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            color: #334155;
            font-weight: 500;
            transition: all 0.2s;
        }
        .cross-link-btn:hover {
            background-color: #0d6efd;
            border-color: #0d6efd;
            color: #ffffff;
        }
    </style>
</head>
<body>

<?php include BASE_PATH . 'includes/navbar.php'; ?>

<div class="main-container">

    <!-- ── Back to Browse 按鈕 ────────────────── -->
    <?php if ($fromClinical && $fromPatient !== ''): ?>
        <a href="/clinicaldata/<?= urlencode($fromPatient) ?>/?back=<?= urlencode($backUrl) ?>" class="btn-back">← Back to <?= htmlspecialchars($fromPatient) ?></a>
    <?php else: ?>
        <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn-back">← Previous page</a>
    <?php endif; ?>

    <!-- ── 頁面標題列 ──────────────────────────── -->
    <div class="page-header">
        <h1><?= htmlspecialchars($metaboliteName) ?></h1>
        <div class="id-row">
            <span class="badge badge-info"><?= htmlspecialchars($dmtdbId) ?></span>
        </div>
    </div>

    <!-- ── Tab 導覽列（加入 Cross-Omics Analysis）─── -->
    <nav class="nav-tabs" id="metab-tabs">
        <button class="tab active" data-target="sec-cross-analysis">Multi-Omics Analysis</button>
        <button class="tab"        data-target="sec-identification">Identification</button>
        <button class="tab"        data-target="sec-molecular">Molecular Subtypes</button>
        <button class="tab"        data-target="sec-prognosis">Prognosis</button>
        <button class="tab"        data-target="sec-metabolite">Metabolite Information</button>
        <button class="tab"        data-target="sec-synonyms">Synonyms</button>
        <button class="tab"        data-target="sec-links">External Links</button>
        <button class="tab"        data-target="sec-pathway">Pathway</button>
    </nav>

    <!-- ── 主體雙欄 ────────────────────────────── -->
    <div class="content-grid">

        <!-- 左欄：主要資訊 -->
        <div class="content-main">

            <!-- ★ 新增：四維度跨體學整合分析入口 (Cross-Module Multi-Omics Analysis) -->
            <section id="sec-cross-analysis" class="section-block">
                <div class="section-header">GBM Cross-Module Multi-Omics Landscape</div>
                <div class="section-content">
                    <p style="font-size: 13px; color: #64748b; margin-top: 0;">
                        此代謝物在膠質母細胞瘤四大核心分析維度（分子亞型、微環境免疫、藥物反應、腫瘤復發）的表現與深入分析捷徑：
                    </p>
                    <div class="cross-analysis-grid">
                        
                        <!-- 1. 分子亞型 -->
                        <div class="cross-card subtype">
                            <div>
                                <div class="cross-card-header">
                                    <span>1. Molecular Subtypes</span>
                                    <span style="color:#0d6efd;">● Subtype</span>
                                </div>
                                <div class="cross-card-value">
                                    <?= htmlspecialchars($topSubtype) ?>
                                </div>
                                <div class="cross-card-desc">
                                    檢視在 Classical, Mesenchymal 與 Proneural 間的顯著分佈與專一性指數 (SSI)。
                                </div>
                            </div>
                            <a href="/analysis/Molecular_Subtypes.php?dmtdb_id=<?= urlencode($dmtdbId) ?>" class="cross-link-btn">
                                前往分子亞型分析 &rarr;
                            </a>
                        </div>

                        <!-- 2. 免疫反應 -->
                        <div class="cross-card immune">
                            <div>
                                <div class="cross-card-header">
                                    <span>2. Immune Microenvironment</span>
                                    <span style="color:#198754;">● TME</span>
                                </div>
                                <div class="cross-card-value">TAMs / T-cells</div>
                                <div class="cross-card-desc">
                                    與腫瘤微環境抑制細胞（TAMs, Tregs）及免疫檢查點配體之相關性分析。
                                </div>
                            </div>
                            <a href="/analysis/Immune_Cell_Correlation.php?dmtdb_id=<?= urlencode($dmtdbId) ?>" class="cross-link-btn">
                                查看微環境免疫細胞關聯 &rarr;
                            </a>
                        </div>

                        <!-- 3. 藥物與耐藥性 -->
                        <div class="cross-card drug">
                            <div>
                                <div class="cross-card-header">
                                    <span>3. Drug Response (TMZ)</span>
                                    <span style="color:#fd7e14;">● Resistance</span>
                                </div>
                                <div class="cross-card-value">
                                    <?php 
                                        $osSummary = 'Data Pending';
                                        if (!empty($hazardRatioData)) {
                                            $firstH = reset($hazardRatioData);
                                            $hr = $firstH['os_hazard_ratio'] ?? null;
                                            $p = $firstH['os_p_value'] ?? null;
                                            if ($p !== null && $p < 0.05) {
                                                $osSummary = ($hr > 1) ? 'Risk Factor (p<0.05)' : 'Protective (p<0.05)';
                                            } else {
                                                $osSummary = 'Non-significant';
                                            }
                                        }
                                        echo htmlspecialchars($osSummary);
                                    ?>
                                </div>
                                <div class="cross-card-desc">
                                    化療藥物 TMZ 臨床存活率 (OS/PFS) 關聯與缺氧/幹性對藥敏之影響。
                                </div>
                            </div>
                            <a href="/analysis/Drug_Response.php?dmtdb_id=<?= urlencode($dmtdbId) ?>" class="cross-link-btn">
                                前往 TMZ 敏感性存活分析 &rarr;
                            </a>
                        </div>

                        <!-- 4. 腫瘤原發 vs. 復發 -->
                        <div class="cross-card recurrent">
                            <div>
                                <div class="cross-card-header">
                                    <span>4. Tumor Recurrence</span>
                                    <span style="color:#6f42c1;">● PMT Transition</span>
                                </div>
                                <div class="cross-card-value">PMT & Progression</div>
                                <div class="cross-card-desc">
                                    配對樣本分析 Primary vs. Recurrent 動態變化及神經轉間質進展。
                                </div>
                            </div>
                            <a href="/analysis/tumor_recurrence.php?dmtdb_id=<?= urlencode($dmtdbId) ?>" class="cross-link-btn">
                                檢視原發/復發演化分析 &rarr;
                            </a>
                        </div>

                    </div>
                </div>
            </section>

            <!-- Identification -->
            <section id="sec-identification" class="section-block">
                <div class="section-header">Identification</div>
                <div class="section-content">
                    <table class="info-table">
                        <tr><td>Common name</td>     <td><?= htmlspecialchars($metaboliteName) ?></td></tr>
                        <tr><td>Chemical formula</td> <td></td></tr>
                        <?php if (!empty($expressionData)): ?>
                        <tr>
                            <td>Maximum Expression</td>
                            <td>
                                <?php foreach ($expressionData as $pdc => $val): ?>
                                    <span class="expression-tag"><?= htmlspecialchars($pdc) ?>: <?= number_format((float)$val, 2) ?></span><br>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <?php endif; ?>

                        <tr><td>IUPAC name</td>       <td></td></tr>
                        <tr><td>SMILES</td>           <td></td></tr>
                        <tr><td>InChI Identifier</td> <td></td></tr>
                        <tr><td>InChIKey</td>         <td></td></tr>
                        <tr><td>Description</td>      <td></td></tr>
                    </table>
                </div>
            </section>

            <!-- Molecular Subtypes (原版保留) -->
            <section id="sec-molecular" class="section-block">
                <div class="section-header">Molecular Subtypes</div>
                <div class="section-content">
                    <table class="info-table">
                        <tr>
                            <td>Proneural Expression</td>
                            <td>
                                <?php if (empty($molecularSubtypeData)): ?>
                                    -
                                <?php else: ?>
                                    <div class="expression-tags">
                                    <?php foreach ($molecularSubtypeData as $pdcLabel => $info):
                                        $val = $info['PRONEURAL'] ?? null;
                                        $formatted = ($val !== null) ? number_format((float)$val, 2) : 'N/A';
                                    ?>
                                        <span class="expression-tag"><?= htmlspecialchars($pdcLabel) ?>: <?= htmlspecialchars($formatted) ?></span>
                                    <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Classical Expression</td>
                            <td>
                                <?php if (empty($molecularSubtypeData)): ?>
                                    -
                                <?php else: ?>
                                    <div class="expression-tags">
                                    <?php foreach ($molecularSubtypeData as $pdcLabel => $info):
                                        $val = $info['CLASSICAL'] ?? null;
                                        $formatted = ($val !== null) ? number_format((float)$val, 2) : 'N/A';
                                    ?>
                                        <span class="expression-tag"><?= htmlspecialchars($pdcLabel) ?>: <?= htmlspecialchars($formatted) ?></span>
                                    <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Mesenchymal Expression</td>
                            <td>
                                <?php if (empty($molecularSubtypeData)): ?>
                                    -
                                <?php else: ?>
                                    <div class="expression-tags">
                                    <?php foreach ($molecularSubtypeData as $pdcLabel => $info):
                                        $val = $info['MESENCHYMAL'] ?? null;
                                        $formatted = ($val !== null) ? number_format((float)$val, 2) : 'N/A';
                                    ?>
                                        <span class="expression-tag"><?= htmlspecialchars($pdcLabel) ?>: <?= htmlspecialchars($formatted) ?></span>
                                    <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Maximum Expression in Molecular Subtypes</td>
                            <td>
                                <?php if (empty($molecularSubtypeData)): ?>
                                    -
                                <?php else: ?>
                                    <div class="expression-tags">
                                    <?php foreach ($molecularSubtypeData as $pdcLabel => $info):
                                        $subtype = $info['subtype'] ?? 'N/A';
                                        $ssi     = $info['ssi'];
                                        $ssiText = ($ssi !== null) ? number_format((float)$ssi, 2) : 'N/A';
                                    ?>
                                        <span class="expression-tag"><?= htmlspecialchars($pdcLabel) ?>: <?= htmlspecialchars($subtype) ?>(<?= htmlspecialchars($ssiText) ?>)</span>
                                    <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </section>

            <!-- Prognosis Information (原版保留) -->
            <section id="sec-prognosis" class="section-block">
                <div class="section-header">Prognosis Information</div>
                <div class="section-content">
                    <table class="info-table">
                        <tr>
                            <td>OS Hazard Ratio</td>
                            <td>
                                <?php if (empty($hazardRatioData)): ?>
                                    -
                                <?php else: ?>
                                    <div class="hazard-ratio-tags">
                                    <?php foreach ($hazardRatioData as $pdcLabel => $vals):
                                        $val    = $vals['os_hazard_ratio'] ?? null;
                                        $pValue = $vals['os_p_value'] ?? null;

                                        $className = 'non-significant';
                                        if ($pValue !== null && $pValue < 0.05) {
                                            if ($val > 1)      $className = 'risk-factor';
                                            elseif ($val < 1)  $className = 'protective-factor';
                                        }
                                        $labelText = $className === 'risk-factor' ? 'Risk Factor'
                                                : ($className === 'protective-factor' ? 'Protective Factor' : 'Non-significant');
                                        $hrTitle = 'HR=' . (($val !== null) ? $val : 'N/A') . ', p=' . (($pValue !== null) ? $pValue : 'N/A');
                                    ?>
                                        <span class="hazard-ratio-tag <?= $className ?>" title="<?= htmlspecialchars($hrTitle) ?>">
                                            <?= htmlspecialchars($pdcLabel) ?>: <?= htmlspecialchars($labelText) ?>
                                        </span>
                                    <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>PFS Hazard Ratio</td>
                            <td>
                                <?php if (empty($hazardRatioData)): ?>
                                    -
                                <?php else: ?>
                                    <div class="hazard-ratio-tags">
                                    <?php foreach ($hazardRatioData as $pdcLabel => $vals):
                                        $val    = $vals['pfs_hazard_ratio'] ?? null;
                                        $pValue = $vals['pfs_p_value'] ?? null;

                                        $className = 'non-significant';
                                        if ($pValue !== null && $pValue < 0.05) {
                                            if ($val > 1)      $className = 'risk-factor';
                                            elseif ($val < 1)  $className = 'protective-factor';
                                        }
                                        $labelText = $className === 'risk-factor' ? 'Risk Factor'
                                                : ($className === 'protective-factor' ? 'Protective Factor' : 'Non-significant');
                                        $hrTitle = 'HR=' . (($val !== null) ? $val : 'N/A') . ', p=' . (($pValue !== null) ? $pValue : 'N/A');
                                    ?>
                                        <span class="hazard-ratio-tag <?= $className ?>" title="<?= htmlspecialchars($hrTitle) ?>">
                                            <?= htmlspecialchars($pdcLabel) ?>: <?= htmlspecialchars($labelText) ?>
                                        </span>
                                    <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </section>

            <!-- Metabolite Information -->
            <section id="sec-metabolite" class="section-block">
                <div class="section-header">Information</div>
                <div class="section-content">
                    <div class="property-grid">
                        <div class="prop-card"><div class="prop-label">代謝物作用</div>             <div class="prop-value"></div></div>
                        <div class="prop-card"><div class="prop-label">用途</div>                  <div class="prop-value"></div></div>
                        <div class="prop-card"><div class="prop-label">對Glioma的影響-詳細</div>    <div class="prop-value"></div></div>
                        <div class="prop-card"><div class="prop-label">對Glioma的影響-簡單</div>    <div class="prop-value"></div></div>
                        <div class="prop-card"><div class="prop-label">來源</div>                  <div class="prop-value"></div></div>
                    </div>
                </div>
            </section>

        </div><!-- /.content-main -->

        <!-- 右欄：側欄 -->
        <aside class="content-sidebar">

            <!-- Synonyms -->
            <div class="section-block" id="sec-synonyms">
                <div class="section-header">Synonyms</div>
                <div class="section-content">
                    <?php if (!empty($synonyms)): ?>
                        <div style="max-height: 200px; overflow-y: auto; border: 1px solid #e0e0e0; padding: 10px; border-radius: 4px; background-color: #fff;">
                            <ul style="list-style: none; padding-left: 0; margin: 0;">
                                <?php foreach ($synonyms as $syn): ?>
                                    <li style="margin-bottom: 6px; font-size: 14px; color: #333; line-height: 1.4; border-bottom: 1px dashed #f0f0f0; padding-bottom: 4px;">
                                        <?= htmlspecialchars($syn) ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <p style="color: #999;">-</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- External links -->
            <section id="sec-links" class="section-block">
                <div class="section-header">External links</div>
                <div class="section-content section-content-flush">
                    <ul class="ext-link-list">
                        <?php if (empty($externalLinks)): ?>
                            <li><span class="no-data">No external links available.</span></li>
                        <?php else: ?>
                            <?php foreach ($externalLinks as$x): ?>
                                <li>
                                    <a href="<?= $x['url'] ?>" target="_blank" rel="noopener">
                                        <?= $x['label'] ?> <span class="ext-source"><?= $x['source'] ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </section>

            <!-- Pathway Information -->
            <section id="sec-pathway" class="section-block">
                <div class="section-header">Pathway</div>
                <div class="section-content">
                    <?php if (!empty($pathways) && !empty($linkRow['KEGG_ID'])): ?>
                        <div class="metab-pathway-tags">
                            <?php foreach ($pathways as $pw):$keggUrl = 'https://www.kegg.jp/kegg-bin/show_pathway?map=' . 
                                        htmlspecialchars($pw['pathway_id']) . 
                                        '&multi_query=' . 
                                        htmlspecialchars($linkRow['KEGG_ID']);
                            ?>
                                <span class="metab-pathway-tag">
                                    <a href="<?= $keggUrl ?>" target="_blank" title="<?= htmlspecialchars($pw['pathway_id']) ?>">
                                        <?= htmlspecialchars($pw['pathway_name'] ?? $pw['pathway_id']) ?>
                                    </a>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="no-data">-</p>
                    <?php endif; ?>
                </div>
            </section>

        </aside><!-- /.content-sidebar -->

    </div><!-- /.content-grid -->
</div><!-- /.main-container -->

<?php include BASE_PATH . 'includes/footer.php'; ?>

<!-- Tab 切換 JS -->
<script>
(function () {
    const tabs = document.querySelectorAll('#metab-tabs .tab');
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('active'); });
            tab.classList.add('active');
            const el = document.getElementById(tab.dataset.target);
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    if ('IntersectionObserver' in window) {
        const sections = document.querySelectorAll('.section-block[id^="sec-"]');
        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    const id = entry.target.id;
                    tabs.forEach(function (t) {
                        t.classList.toggle('active', t.dataset.target === id);
                    });
                }
            });
        }, { threshold: 0.25 });
        sections.forEach(function (s) { observer.observe(s); });
    }
}());
</script>
</body>
</html>