<?php
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

// ── Synonyms 查詢 ────────────────────
$synonyms = [];
if ($linkRow && !empty($linkRow['HMDB_ID'])) {
    $synStmt = $db->prepare("SELECT DISTINCT synonyms FROM hmdb_synonyms WHERE HMDB_ID = ? ORDER BY synonyms");
    $synStmt->execute([$linkRow['HMDB_ID']]);
    $synonyms = $synStmt->fetchAll(PDO::FETCH_COLUMN);
}
// ── Back to Browse URL：優先使用 HTTP_REFERER，fallback 到 /metabolites.php ──
$backUrl = '/metabolites.php';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $ref = $_SERVER['HTTP_REFERER'];
    $refPath = parse_url($ref, PHP_URL_PATH) ?? '';
    // 只允許站內頁面（避免 open redirect）
    $allowedPaths = ['/metabolites.php', '/search/results_name.php', '/search/results_id.php',
                     '/search/results_pathway.php', '/search/results_tme.php'];
    foreach ($allowedPaths as $p) {
        if (str_starts_with($refPath, $p) || $refPath === $p) {
            $backUrl = $ref; // 保留完整 URL（含 query string）
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
    <style>
/* =============================================
   metabolite page styles
   ============================================= */

/* ── 麵包屑 ──────────────────────────────────── */
.breadcrumb {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--color-text-secondary, #888);
    margin-bottom: 1.25rem;
}
.breadcrumb a {
    color: var(--color-link, #185FA5);
    text-decoration: none;
}
.breadcrumb a:hover { text-decoration: underline; }
.bc-sep { opacity: 0.5; }

/* ── Back to Browse 按鈕 ─────────────────────── */
.btn-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 500;
    color: var(--color-text-primary, #111);
    background: var(--color-bg-secondary, #f0f0f0);
    border: 0.5px solid var(--color-border, #d0d0d0);
    border-radius: 6px;
    padding: 6px 14px;
    text-decoration: none;
    cursor: pointer;
    margin-bottom: 1.25rem;
    transition: background 0.12s, border-color 0.12s;
}
.btn-back:hover {
    background: #e4e4e4;
    border-color: #bbb;
    text-decoration: none;
}

/* ── 頁面標題列 ──────────────────────────────── */
.page-header {
    border-bottom: 0.5px solid var(--color-border, #e0e0e0);
    padding-bottom: 1rem;
    margin-bottom: 1.5rem;
}
.page-header h1 {
    font-size: 24px;
    font-weight: 500;
    margin-bottom: 8px;
    color: var(--color-text-primary, #111);
}
.id-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

/* ── 徽章 ────────────────────────────────────── */
.badge {
    display: inline-block;
    font-size: 12px;
    font-weight: 500;
    padding: 3px 10px;
    border-radius: 6px;
    line-height: 1.4;
}
.badge-info    { background: #e6f1fb; color: #0c447c; }
.badge-success { background: #eaf3de; color: #27500a; }
.badge-neutral {
    background: var(--color-bg-secondary, #f5f5f5);
    color: var(--color-text-secondary, #666);
    border: 0.5px solid var(--color-border, #e0e0e0);
}

/* ── Tab 導覽列 ──────────────────────────────── */
.nav-tabs {
    display: flex;
    gap: 0;
    border-bottom: 0.5px solid var(--color-border, #e0e0e0);
    margin-bottom: 1.5rem;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.tab {
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    padding: 8px 16px;
    font-size: 13px;
    color: var(--color-text-secondary, #666);
    cursor: pointer;
    white-space: nowrap;
    transition: color 0.15s;
    outline: none;
}
.tab:hover { color: var(--color-text-primary, #111); }
.tab.active {
    color: var(--color-text-primary, #111);
    border-bottom-color: var(--color-text-primary, #111);
    font-weight: 500;
}

/* ── 雙欄版面 ────────────────────────────────── */
.content-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 260px;
    gap: 1.25rem;
    align-items: start;
}
@media (max-width: 768px) {
    .content-grid { grid-template-columns: 1fr; }
}

/* ── 區塊卡片 ────────────────────────────────── */
.section-block {
    border: 0.5px solid var(--color-border, #e0e0e0);
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 1.25rem;
}
.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 12px;
    font-weight: 500;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--color-text-secondary, #666);
    background: var(--color-bg-secondary, #f7f7f7);
    padding: 8px 14px;
    border-bottom: 0.5px solid var(--color-border, #e0e0e0);
}
.section-content {
    padding: 1rem 1.25rem;
}
.section-content-flush {
    padding: 0.25rem 1.25rem;
}

/* ── 資訊表格 ────────────────────────────────── */
.info-table {
    width: 100%;
    border-collapse: collapse;
}
.info-table tr {
    border-bottom: 0.5px solid var(--color-border, #e8e8e8);
}
.info-table tr:last-child { border-bottom: none; }
.info-table td {
    padding: 9px 0;
    font-size: 14px;
    vertical-align: top;
    line-height: 1.5;
}
.info-table td:first-child {
    color: var(--color-text-secondary, #666);
    width: 42%;
    padding-right: 12px;
}
.info-table td.text-muted {
    color: var(--color-text-secondary, #666);
    font-size: 13px;
    line-height: 1.6;
}
.inchikey {
    font-family: monospace;
    font-size: 12px;
    word-break: break-all;
    background: var(--color-bg-secondary, #f5f5f5);
    padding: 2px 6px;
    border-radius: 4px;
}

/* ── 物化性質格線 ─────────────────────────────── */
.property-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
}
@media (max-width: 480px) {
    .property-grid { grid-template-columns: 1fr; }
}
.prop-card {
    background: var(--color-bg-secondary, #f7f7f7);
    border-radius: 8px;
    padding: 10px 12px;
}
.prop-label {
    font-size: 11px;
    font-weight: 500;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--color-text-secondary, #888);
    margin-bottom: 4px;
}
.prop-value {
    font-size: 14px;
    font-weight: 500;
    color: var(--color-text-primary, #888);
}

/* ── 側欄：結構圖 ─────────────────────────────── */
.structure-box {
    background: var(--color-bg-secondary, #f7f7f7);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 180px;
    padding: 1rem;
}
.structure-img {
    max-width: 100%;
    max-height: 180px;
    object-fit: contain;
}
.structure-caption {
    font-size: 12px;
    color: var(--color-text-secondary, #888);
    text-align: center;
    margin-top: 8px;
}

/* ── 外部連結清單 ────────────────────────────── */
.ext-link-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.ext-link-list li {
    padding: 9px 0;
    border-bottom: 0.5px solid var(--color-border, #f0f0f0);
}
.ext-link-list li:last-child { border-bottom: none; }
.ext-link-list a {
    font-size: 13px;
    color: var(--color-link, #185FA5);
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.ext-link-list a:hover { text-decoration: underline; }
.ext-source {
    font-size: 11px;
    color: var(--color-text-secondary, #999);
    font-weight: 500;
    flex-shrink: 0;
}

/* ── 相關代謝物標籤 ──────────────────────────── */
.metabolite-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.chip {
    font-size: 12px;
    padding: 4px 10px;
    border-radius: 999px;
    background: var(--color-bg-secondary, #f5f5f5);
    border: 0.5px solid var(--color-border, #e0e0e0);
    color: var(--color-text-primary, #111);
    text-decoration: none;
    transition: background 0.12s;
    white-space: nowrap;
}
.chip:hover {
    background: #fff;
    border-color: #aaa;
}

/* ── 計數徽章 ────────────────────────────────── */
.count-badge {
    font-size: 11px;
    font-weight: 500;
    padding: 2px 8px;
    border-radius: 999px;
    background: var(--color-bg-primary, #fff);
    border: 0.5px solid var(--color-border, #e0e0e0);
    color: var(--color-text-secondary, #888);
}

/* ── 無資料提示 ──────────────────────────────── */
.no-data {
    font-size: 13px;
    color: var(--color-text-secondary, #999);
}

/* ── 警告訊息 ────────────────────────────────── */
.alert-danger {
    background: #FCEBEB;
    color: #791F1F;
    border: 0.5px solid #F09595;
    border-radius: 8px;
    padding: 12px 16px;
    font-size: 14px;
    margin: 1.5rem 0;
}

.structure-box.synonym-scroll {
    max-height: 140px;
    overflow-y: auto;
    background: #fafbfc;
    border: 1px solid #d0d7de;
    border-radius: 0.5em;
    padding: 0.6em 0.8em;
    box-sizing: border-box;
    font-size: 0.95em;
    line-height: 1.6;
    word-break: break-all;
}

.expression-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    max-width: 320px;
}

.expression-tag {
    display: inline-block;
    background: #eaf3fb;
    color: #1a5a8a;
    border: 1px solid #b3d4ec;
    border-radius: 12px;
    padding: 2px 9px;
    font-size: 0.78rem;
    white-space: nowrap;
    line-height: 1.5;
}

/* ── 主容器留白 ──────────────────────────────── */
.main-container {
    padding: 1.5rem 1.25rem;
}
    </style>
</head>
<body>

<?php include BASE_PATH . 'includes/navbar.php'; ?>

<div class="main-container">

    <!-- ── Back to Browse 按鈕 ────────────────── -->
    <a href="<?= htmlspecialchars($backUrl) ?>" class="btn-back">← Back to Browse</a>

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
        <button class="tab"        data-target="sec-physical">Metabolite Information</button>
        <button class="tab"        data-target="sec-links">External Links</button>
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
                        <tr><td>Common name</td>        <td></td></tr>
                        <tr><td>Chemical formula</td>   <td></td></tr>
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

            <!-- Metabolite Information -->
            <section id="sec-physical" class="section-block">
                <div class="section-header">Information</div>
                <div class="section-content">
                    <div class="property-grid">
                        <div class="prop-card"><div class="prop-label">代謝物作用</div>             <div class="prop-value">當聯苯進入生物體（如哺乳動物或微生物）時，會經歷一系列的生物轉化（Biotransformation），產生的主要代謝物包括：<br>1. 羥基化反應 (Hydroxylation)： 這是最主要的代謝途徑。聯苯會被細胞色素 P450 酶（CYP450）氧化，生成：4-羥基聯苯 (4-Hydroxybiphenyl)：最主要的產物2-羥基聯苯 (2-Hydroxybiphenyl)2,5-二羥基聯苯 或其他多羥基衍生物<br>2. 結合反應 (Conjugation)： 為了增加水溶性以利排出，這些羥基化產物會進一步與葡萄糖醛酸或硫酸鹽結合</div></div>
                        <div class="prop-card"><div class="prop-label">用途</div>                  <div class="prop-value">1. 酶活性指標：聯苯的羥基化速率常用來評估肝微粒體中 CYP450 酶家族的活性<br>2. 毒理學研究：研究聯苯代謝過程中產生的活性中間體（如環氧化物），了解其對細胞 DNA 或蛋白質的損傷機制<br>3. 微生物降解：在環境代謝組學中，研究細菌如何利用聯苯作為碳源，這對於生物修復（Bioremediation）技術至關重要</div></div>
                        <div class="prop-card"><div class="prop-label">濃度意義</div>               <div class="prop-value">在人體樣本中發現高濃度的聯苯代謝物，通常暗示著環境暴露或食物污染</div></div>
                        <div class="prop-card"><div class="prop-label">對Glioma的影響-詳細</div>     <div class="prop-value">1. 潛在的致癌風險（化學誘導）：聯苯及其衍生物（如多氯聯苯 PCBs）被認為具有神經毒性與致癌潛力
    a. 氧化壓力與炎症： 研究指出，聯苯類的代謝產物可能誘導膠質細胞產生過量的反應性氧族（ROS），導致氧化壓力。
                                         這種長期的慢性炎症環境是促進神經膠質細胞惡化為膠質瘤的誘因之一。
    b. 干擾信號傳導： 某些聯苯類化合物被懷疑會干擾星狀細胞（Astrocytes）的正常功能，進而影響腦部的代謝平衡。
2. 藥物設計中的「聯苯骨架」（關鍵影響）：科學家利用聯苯的疏水性與結構剛性，開發能穿過血腦屏障（BBB）的抗癌藥物。
    a. 抑制腫瘤細胞的遷移與侵襲
        MMP 抑制劑： 許多針對膠質瘤的基質金屬蛋白酶(MMPs)抑制劑都包含聯苯羧酸(Biphenyl carboxylic acid)結構。
    b. 誘導細胞凋亡（Apoptosis）
        聯苯脲類衍生物： 研究發現，某些含聯苯結構的化合物能靶向膠質瘤細胞中的特定激酶（如 PI3K/Akt/mTOR 路徑），
                        啟動細胞凋亡程序，選擇性地殺死腫瘤細胞，而對正常神經細胞傷害較小。
    c. 放射增敏作用
        部分研究嘗試將聯苯衍生物作為放射增敏劑，增強放療對神經膠質瘤細胞的殺傷力。
3. 代謝組學觀點
    在膠質瘤患者的代謝輪廓（Metabolic Profiling）中，偵測到異常的聯苯類化合物，通常具有以下臨床意義：
        1. 環境暴露追蹤： 評估患者是否長期暴露於工業化學品環境。
        2. 藥物代謝監測： 監測其代謝產物（如 4-羥基聯苯）的濃度有助於調整藥物劑量與評估毒性。</div></div>
                        <div class="prop-card"><div class="prop-label">對Glioma的影響-簡單</div>      <div class="prop-value">1. 環境暴露：負面/致癌 -> 誘導氧化壓力、促進細胞癌變<br>2. 藥物開發：正面/治療 -> 作為 MMPs 抑制劑或激酶抑制劑的骨架，抑制侵襲<br>3. 生物學研究：中性 -> 作為研究血腦屏障通透性的模型分子</div></div>
                        <div class="prop-card"><div class="prop-label">來源</div>                   <div class="prop-value">外源性化合物</div></div>
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
                    <?php if (empty($synonyms)): ?>
                        <div class="structure-box structure-placeholder">
                            <span class="no-data">Synonyms not available</span>
                        </div>
                    <?php else: ?>
                        <div class="structure-box synonym-scroll">
                            <?= implode('<br>', array_map('htmlspecialchars', $synonyms)) ?>
                        </div>
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
