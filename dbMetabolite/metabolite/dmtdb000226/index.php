<?php
// [AUTO-GENERATED-FROM-TEMPLATE] 此頁面由 generate_metabolite_pages.py 自動產生自共用模板；
// 範本更新後重新執行批次模式（--update-existing）會被覆蓋更新。
// 若要個別客製化此頁面，請改用單筆模式（--id/--file），
// 該模式產生的頁面不含此標記，未來批次更新不會覆蓋它。
// =============================================
// metabolite/[DMTDB_ID]/index.php  —  代謝物個別頁面
// =============================================
require_once '../../config.php';

// 從 URL 路徑取得代謝物 ID（先剝離 query string，避免 ?FIELDS[0]=... 干擾）
$uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$rawId   = trim(basename(rtrim($uriPath, '/')));
$dmtdbId = strtoupper($rawId);

if ($dmtdbId === '' || $dmtdbId === '.') {
    header('Location: ../../metabolites.php');
    exit;
}

$db = getDB();


// 從clinical個別頁面進代謝物個別頁面-->讀取來源參數
$fromClinical = isset($_GET['from']) && $_GET['from'] === 'clinical';
$fromPatient  = '';

if ($fromClinical && isset($_GET['patient'])) {
    // 白名單驗證，只允許合法 Patient ID 格式
    $raw = $_GET['patient'];
    if (preg_match('/^[A-Za-z0-9\-]+$/', $raw)) {
        $fromPatient = $raw;
    } else {
        $fromClinical = false;
    }
}



// ── 取得 metabolite_name 與 average_expression ───────────────
$metaboliteName = '';
$expressionData = [];   // ['PDC000546' => 1234.56, 'PDC000552' => 7890.12]
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

// ── 取得 Molecular Subtypes ───
$molecularSubtypeData = []; // ['PDC000546' => ['subtype'=>..,'ssi'=>..,'PRONEURAL'=>..,'CLASSICAL'=>..,'MESENCHYMAL'=>..]]
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

// ── 取得 Prognosis / Hazard Ratio ──
$hazardRatioData = []; // ['PDC000546' => ['os_hazard_ratio'=>.., 'os_p_value'=>.., 'pfs_hazard_ratio'=>.., 'pfs_p_value'=>..]]
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

// ── 取得 external links（從 metabolites_id 取得）──────────
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

// ── index.php Synonyms 查詢 ──────────
$synonyms = [];
if ($linkRow && !empty($linkRow['HMDB_ID'])) {
    // 改為直接撈取多列並明確指定排序，不使用 GROUP_CONCAT 避免特殊符號或長度截斷問題
    $synStmt = $db->prepare(
        "SELECT DISTINCT synonyms 
         FROM hmdb_synonyms 
         WHERE HMDB_ID = ? 
         ORDER BY synonyms ASC"
    );
    $synStmt->execute([$linkRow['HMDB_ID']]);
    
    // 直接將所有 rows 轉為一維陣列
    $synonyms = $synStmt->fetchAll(PDO::FETCH_COLUMN);
}

// ── index.php Pathway 查詢 ──────────
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

// ── Back URL 決定邏輯 ──────────────────────────────────────────
$backUrl = '/metabolites.php';

if (!empty($_GET['back'])) {
    $decoded = urldecode($_GET['back']);
    $parsed  = parse_url($decoded);
    $path    = $parsed['path'] ?? '';
    if ($path === '' && isset($parsed['query'])) {
        // back 只帶 query string（?diseases[]=...）
        $backUrl = '/clinicaldata.php?' . $parsed['query'];
    } elseif (preg_match('#^/clinicaldata\.php$#', $path)) {
        $safeQuery = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $backUrl   = '/clinicaldata.php' . $safeQuery;
    }
} elseif (!empty($_SERVER['HTTP_REFERER'])) {
    $ref     = $_SERVER['HTTP_REFERER'];
    $refPath = parse_url($ref, PHP_URL_PATH) ?? '';
    $allowedPaths = [
        '/metabolites.php',
        '/search/results_name.php',
        '/search/results_id.php',
        '/search/results_pathway.php',
        '/search/results_tme.php',
    ];
    foreach ($allowedPaths as $p) {
        if (str_starts_with($refPath, $p) || $refPath === $p) {
            $backUrl = $ref;
            break;
        }
    }
}
?>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metabolite Database — <?= htmlspecialchars($metaboliteName) ?></title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/metabolite.css">
    <link rel="stylesheet" href="../../css/pathway.css">
    <link rel="stylesheet" href="../../css/hazard_ratio.css">
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

    <!-- ── Tab 導覽列 ─────────────────────────── -->
    <nav class="nav-tabs" id="metab-tabs">
        <button class="tab active" data-target="sec-identification">Identification</button>
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

            <!-- Identification -->
            <section id="sec-identification" class="section-block">
                <div class="section-header">Identification</div>
                <div class="section-content">
                    <table class="info-table">
                        <tr><td>Common name</td>     <td></td></tr>
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

            <!-- Molecular Subtypes -->
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
                                    <div class="pathway-tags">
                                    <?php foreach ($molecularSubtypeData as $pdcLabel => $info):
                                        $val = $info['PRONEURAL'] ?? null;
                                        $formatted = ($val !== null) ? number_format((float)$val, 2) : 'N/A';
                                    ?>
                                        <span class="pathway-tag"><?= htmlspecialchars($pdcLabel) ?>: <?= htmlspecialchars($formatted) ?></span>
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
                                    <div class="pathway-tags">
                                    <?php foreach ($molecularSubtypeData as $pdcLabel => $info):
                                        $val = $info['CLASSICAL'] ?? null;
                                        $formatted = ($val !== null) ? number_format((float)$val, 2) : 'N/A';
                                    ?>
                                        <span class="pathway-tag"><?= htmlspecialchars($pdcLabel) ?>: <?= htmlspecialchars($formatted) ?></span>
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
                                    <div class="pathway-tags">
                                    <?php foreach ($molecularSubtypeData as $pdcLabel => $info):
                                        $val = $info['MESENCHYMAL'] ?? null;
                                        $formatted = ($val !== null) ? number_format((float)$val, 2) : 'N/A';
                                    ?>
                                        <span class="pathway-tag"><?= htmlspecialchars($pdcLabel) ?>: <?= htmlspecialchars($formatted) ?></span>
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
                                    <div class="pathway-tags">
                                    <?php foreach ($molecularSubtypeData as $pdcLabel => $info):
                                        $subtype = $info['subtype'] ?? 'N/A';
                                        $ssi     = $info['ssi'];
                                        $ssiText = ($ssi !== null) ? number_format((float)$ssi, 2) : 'N/A';
                                    ?>
                                        <span class="pathway-tag"><?= htmlspecialchars($pdcLabel) ?>: <?= htmlspecialchars($subtype) ?>(<?= htmlspecialchars($ssiText) ?>)</span>
                                    <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </section>

            <!-- Prognosis Information -->
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
            <div class="section-block">
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
                            <?php foreach ($externalLinks as $x): ?>
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
                            <?php foreach ($pathways as $pw): 
                                $keggUrl = 'https://www.kegg.jp/kegg-bin/show_pathway?map=' . 
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
        }, { threshold: 0.4 });
        sections.forEach(function (s) { observer.observe(s); });
    }
}());
</script>
</body>
</html>