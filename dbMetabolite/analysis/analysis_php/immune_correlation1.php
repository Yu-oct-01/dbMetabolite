<?php
// =============================================
// analysis/immune_correlation.php  —  分析頁
// =============================================
require_once __DIR__ . '/../config.php';

$db = getDB();$sql = "SELECT 
            i.`Case ID`, 
            i.`HALLMARK_HYPOXIA`, 
            i.`MolecularSubtype`,
            c.`OS_Time`,
            c.`OS_Status`
        FROM `gbm_immune_data` i
        LEFT JOIN `cptac3_pdc000552_clinicaldata` c ON i.`Case ID` = c.`Case Submitter ID`
        WHERE i.`HALLMARK_HYPOXIA` IS NOT NULL";
$stmt =$db->query($sql);$hypoxiaDbData = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Immune Correlation Analysis - Disease Metabolite Database</title>
    <!-- Bootstrap 5 CSS & FontAwesome Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/style.css">
    <!-- ECharts 5 -->
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
    <style>
        body {
            background-color: #f4f6f9;
        }
        .main-card {
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
            border: 1px solid #e9ecef;
            padding: 24px;
        }
        .cross-module-panel {
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
            border: 1px solid #e9ecef;
            padding: 20px;
            position: sticky;
            top: 20px;
        }
        .module-link-card {
            border: 1px solid #eef2f6;
            border-left: 4px solid #adb5bd;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 12px;
            background-color: #fafbfc;
            transition: all 0.2s ease-in-out;
            text-decoration: none;
            display: block;
            color: inherit;
        }
        .module-link-card:hover {
            background-color: #f1f5f9;
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(0,0,0,0.06);
            color: inherit;
        }
        .module-link-card.immunity   { border-left-color: #20c997; }
        .module-link-card.drug       { border-left-color: #fd7e14; }
        .module-link-card.recurrence { border-left-color: #6f42c1; }
        .module-link-card.hypoxia    { border-left-color: #dc3545; }

        #subtype-chart {
            width: 100%;
            height: 430px;
        }
    </style>
</head>
<body>

<?php include BASE_PATH . 'includes/navbar.php'; ?>

<div class="container-fluid py-4 px-4">
    <!-- 頂部列：Previous Page 按鈕 + 模組標題 + 跨維度 Tag -->
    <div class="row align-items-center mb-3">
        <div class="col-auto">
            <a href="javascript:history.back();" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Previous page
            </a>
        </div>
        <div class="col">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <h4 class="fw-bold mb-0 me-2">Immune Correlation Analysis</h4>
                <!-- 跨維度關聯標籤 (Cross-Module Badges) -->
                <span class="badge text-white" style="background-color: #7895b2;"><i class="fa-solid fa-dna me-1"></i> 微環境關聯</span>
                <span class="badge text-white" style="background-color: #7895b2;"><i class="fa-solid fa-shield-halved me-1"></i> 基因突變</span>
                <span class="badge text-white" style="background-color: #7895b2;"><i class="fa-solid fa-capsules me-1"></i> 耐藥性</span>
                <span class="badge text-white" style="background-color: #7895b2;"><i class="fa-solid fa-arrows-rotate me-1"></i> PMT 轉化</span>
            </div>
            <p class="text-muted small mb-0 mt-1">
                Include the classification of classical, mesenchymal, and proneural subtypes, alongside a prognostic analysis for each category.
            </p>
        </div>
    </div>

    <!-- 主要內容區 -->
    <div class="row g-4">
        <!-- 左側主分析圖表區 (70%) -->
        <div class="col-lg-8">
            <div class="main-card">
                <!-- 篩選列 -->
                <div class="row g-3 align-items-center mb-4">
                    <div class="col-md-5">
                        <label class="form-label small fw-bold text-muted mb-1">分析類別</label>
                        <select class="form-select form-select-sm" id="metabolite-select">
                            <option value="heterogeneity" >腫瘤內異質性</option>
                            <option value="mutation" >基因體變異與體細胞突變</option>
                            <option value="microenvironment" selected>腫瘤微環境</option>
                            <option value="protective" >系統性免疫屏障</option>
                            <option value="immunotherapy" >免疫檢查點</option>
                            <option value="clinical" >臨床因素</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted mb-1">分析目標</label>
                        <select class="form-select form-select-sm" id="strat-select">
                            <!-- 由 JavaScript 動態生成 -->
                        </select>
                    </div>
                    <div class="col-md-3 text-end pt-4">
                        <button class="btn btn-primary btn-sm w-100" onclick="updateChart()">
                            <i class="fa-solid fa-filter me-1"></i> 預後分析
                        </button>
                    </div>
                </div>

                <!-- ECharts 主圖表容器 -->
                <div id="subtype-chart"></div>

                <!-- 結果洞察 -->
                <div class="mt-3 p-3 bg-light rounded border">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h6 class="fw-bold small mb-0"><i class="fa-solid fa-circle-info text-primary me-1"></i> 免疫關聯分析</h6>
                        <span class="badge bg-secondary" style="font-size: 0.75rem;">One-way ANOVA: p &lt; 0.0001</span>
                    </div>
                    <p class="text-secondary small mb-0" id="insight-text">
                        目前檢視的代謝物在 <strong>Mesenchymal (MES)</strong> 亞型呈現最高表現量，顯著高於 Proneural 與 Classical。MES 亞型通常伴隨 NF1 缺失、劇烈發炎反應與浸潤性微環境，提示該代謝物可能參與了腫瘤間質化轉化過程。
                    </p>
                </div>
            </div>
        </div>

        <!-- 右側跨維度關聯面板 (Cross-Module Insights Panel) (30%) -->
        <div class="col-lg-4">
            <div class="cross-module-panel">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-diagram-project text-primary me-2"></i>跨維度延伸 (Cross-Module)
                    </h6>
                    <a href="/analysis/overview.php" class="small text-decoration-none">網絡全景 →</a>
                </div>
                <p class="text-muted small mb-3">
                    GBM 分子亞型與免疫浸潤、抗藥性及復發高度關聯。使用者可直接由此探索相應模組：
                </p>

                <div id="module-links"></div>

                <!-- 快速操作按鈕 -->
                <div class="mt-3 pt-3 border-top d-grid gap-2">
                    <button class="btn btn-outline-secondary btn-sm" onclick="alert('正在下載當前亞型分組代謝數據 (CSV)...')">
                        <i class="fa-solid fa-download me-1"></i> 下載亞型表現矩陣 (CSV)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 撈取資料庫低氧數據
const dbHypoxiaData = <?php echo json_encode($hypoxiaDbData ?? []); ?>;
</script>

<script src="immune_correlation.js"></script>

<!-- Bootstrap Bundle with Popper (如果 navbar 含有下拉選單或手機收合功能需要此腳本) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>