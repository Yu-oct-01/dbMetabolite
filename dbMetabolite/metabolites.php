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
    'max_expression'   => 'Maximum Expression',
    'isomers'          => 'Isomers (RT/RI)',
    'immune_cells'     => 'Immune Cell Related',
    'pathway'          => 'Pathway',
    'drug_response'    => 'Drug Response',
    'immune_status'    => 'Immune Hot/Cold',
    'stemness'         => 'Tumor Stemness',
    'os_hazard_ratio'  => 'OS Hazard Ratio',
    'pfs_hazard_ratio' => 'PFS Hazard Ratio',
    'molecular_subtype'=> 'Molecular Subtype',
];

$diseaseOptions = [
    'glioma(gbm)' => 'Glioma(GBM)',
    'others' => 'Others',
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

// link 欄位對應
$linkCols = [
    'hmdb_id'    => 'HMDB_link',
    'chebi_id'   => 'CHEBI_link',
    'pubchem_id' => 'Pubchem_link',
    'kegg_id'    => 'KEGG_link',
];

// 初始化
$showResults   = false;
$results       = [];
$pathwayMap    = [];   // kegg_id => [ ['pathway_id'=>..., 'pathway_name'=>...], ... ]
$expressionMap = [];   // DMTDB_ID => [ 'PDC000546' => value, 'PDC000552' => value ]
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
                    ];
                }
            }
        }
        // ─────────────────────────────────────────────────────────────

        // ── Maximum Expression 查詢 ───────────────────────────────────
        if ($needExpression && !empty($results)) {
            $dmtdbIds    = array_column($results, 'DMTDB_ID');
            $placeholders = implode(',', array_fill(0, count($dmtdbIds), '?'));

            foreach ($expressionTables as $pdcLabel => $tblName) {
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
    <link rel="stylesheet" href="css/metabolites.css">
    <link rel="stylesheet" href="css/pathway.css">
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

            <!-- 欄位選擇 -->
            <div class="selection-section">
                <div class="section-title">2. Select Additional Data Fields to Display</div>
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
                                        $keggUrl = 'https://www.kegg.jp/pathway/' . htmlspecialchars($pw['pathway_id']);
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

                            // ── Maximum Expression 欄位：從 expressionMap 撈各 PDC dataset 的 average_expression ──
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
/* ── Chip 互動 ── */
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

/* ── Pathway 展開/收折 ── */
function togglePathways(uid, btn) {
    const tags = document.querySelectorAll('.pathway-tag[data-group="' + uid + '"]');
    const hidden = [...tags].filter(t => t.style.display === 'none');
    if (hidden.length > 0) {
        // 展開
        hidden.forEach(t => t.style.display = '');
        btn.textContent = 'Show less';
    } else {
        // 收折：只顯示前 5 條
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