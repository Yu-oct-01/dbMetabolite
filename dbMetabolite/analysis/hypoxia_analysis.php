<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hypoxia & Metabolic Reprogramming Analysis - Disease Metabolite Database</title>
    <!-- Bootstrap 5 CSS 與 FontAwesome 圖標 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        .module-link-card.subtype { border-left-color: #4A90E2; }
        .module-link-card.immunity { border-left-color: #20c997; }
        .module-link-card.drug { border-left-color: #fd7e14; }
        .module-link-card.recurrence { border-left-color: #6f42c1; }
        
        .badge-soft {
            background-color: #e9ecef;
            color: #495057;
            font-size: 0.75rem;
        }
        #chart-container {
            width: 100%;
            height: 420px;
        }
    </style>
</head>
<body>

<div class="container-fluid py-4 px-4">
    <!-- 頂部列：返回按鈕 + 標題 + 跨維度 Tag -->
    <div class="row align-items-center mb-3">
        <div class="col-auto">
            <a href="javascript:history.back();" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Previous page
            </a>
        </div>
        <div class="col">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <h4 class="fw-bold mb-0 me-2">缺氧與代謝重編程 (Hypoxia & Metabolic Reprogramming)</h4>
                <!-- 跨維度關聯標籤 (Cross-Module Badges) -->
                <span class="badge bg-primary"><i class="fa-solid fa-dna me-1"></i> 分子亞型</span>
                <span class="badge bg-success"><i class="fa-solid fa-shield-halved me-1"></i> 免疫反應</span>
                <span class="badge bg-warning text-dark"><i class="fa-solid fa-capsules me-1"></i> 藥物治療</span>
                <span class="badge bg-purple text-white" style="background-color: #6f42c1;"><i class="fa-solid fa-arrows-rotate me-1"></i> 復發轉化</span>
            </div>
            <p class="text-muted small mb-0 mt-1">
                涵蓋轉錄體學 (Transcriptomics) 缺氧特徵基因集、分子病理基因體與臨床生存追蹤資料。
            </p>
        </div>
    </div>

    <!-- 主要內容區 -->
    <div class="row g-4">
        <!-- 左側主分析圖表區 (70%) -->
        <div class="col-lg-8">
            <div class="main-card">
                <!-- 篩選控制項 -->
                <div class="row g-3 align-items-center mb-4">
                    <div class="col-md-5">
                        <label class="form-label small fw-bold text-muted mb-1">目標代謝物 (Metabolite Target)</label>
                        <select class="form-select form-select-sm" id="metabolite-select">
                            <option value="Lactate" selected>L-Lactate (乳酸 - 糖解途徑重要產物)</option>
                            <option value="Kynurenine">L-Kynurenine (犬尿氨酸 - 色胺酸代謝)</option>
                            <option value="2-HG">2-Hydroxyglutarate (2-HG)</option>
                            <option value="Glutamine">L-Glutamine (麩醯胺酸)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted mb-1">分組標準 (Stratification)</label>
                        <select class="form-select form-select-sm">
                            <option>Hypoxia Score (High vs. Low)</option>
                            <option>HIF-1α Expression Quartiles</option>
                        </select>
                    </div>
                    <div class="col-md-3 text-end pt-4">
                        <button class="btn btn-primary btn-sm w-100">
                            <i class="fa-solid fa-chart-line me-1"></i> 重新計算
                        </button>
                    </div>
                </div>

                <!-- 圖表本體 -->
                <div id="chart-container"></div>

                <div class="mt-3 p-3 bg-light rounded">
                    <h6 class="fw-bold small mb-1"><i class="fa-solid fa-circle-info text-primary me-1"></i> 分析觀察發現</h6>
                    <p class="text-secondary small mb-0">
                        在高缺氧評分（Hypoxia High）的膠質母細胞瘤樣本中，<strong>L-Lactate</strong> 濃度呈現顯著上調（p < 0.001），反映出劇烈的 Warburg 效應與微環境酸中毒。酸性微環境常伴隨免疫抑制及間質表型分化。
                    </p>
                </div>
            </div>
        </div>

        <!-- 右側跨維度連結面板 (Cross-Module Insights Panel) (30%) -->
        <div class="col-lg-4">
            <div class="cross-module-panel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-diagram-project text-secondary me-2"></i>關聯分析推薦 (Cross-Module)
                    </h6>
                    <a href="/analysis/overview.php" class="small text-decoration-none">全景網絡 →</a>
                </div>
                <p class="text-muted small mb-3">
                    缺氧與多個生物進展高度交織。無需回上一頁，可直接由此探索該代謝狀態在其他維度的表現：
                </p>

                <!-- 1. 關聯到分子亞型 -->
                <a href="/analysis/Molecular_Subtypes.php?focus=MES" class="module-link-card subtype">
                    <div class="d-flex justify-content-between">
                        <span class="badge bg-primary mb-1">1. 分子亞型</span>
                        <i class="fa-solid fa-arrow-up-right-from-square text-muted small"></i>
                    </div>
                    <div class="fw-bold small text-dark">MES (Mesenchymal) 亞型關聯</div>
                    <div class="text-muted" style="font-size: 0.8rem;">
                        缺氧刺激常伴隨 NF1 缺失及間質型基因上調。檢視在 Classical / MES / Proneural 的濃度差異。
                    </div>
                </a>

                <!-- 2. 關聯到免疫反應 -->
                <a href="/analysis/Immune_Cell_Correlation.php?cell=TAMs" class="module-link-card immunity">
                    <div class="d-flex justify-content-between">
                        <span class="badge bg-success mb-1">2. 免疫微環境</span>
                        <i class="fa-solid fa-arrow-up-right-from-square text-muted small"></i>
                    </div>
                    <div class="fw-bold small text-dark">TAMs 巨噬細胞 M2 極化浸潤</div>
                    <div class="text-muted" style="font-size: 0.8rem;">
                        缺氧微環境會招募並促使巨噬細胞向免疫抑制型態 (M2) 轉換，查看巨噬細胞與乳酸的相關性。
                    </div>
                </a>

                <!-- 3. 關聯到藥物治療反應 -->
                <a href="/analysis/Drug_Response.php?drug=TMZ&factor=hypoxia" class="module-link-card drug">
                    <div class="d-flex justify-content-between">
                        <span class="badge bg-warning text-dark mb-1">3. 藥物與耐藥性</span>
                        <i class="fa-solid fa-arrow-up-right-from-square text-muted small"></i>
                    </div>
                    <div class="fw-bold small text-dark">TMZ 化療敏感性與存活曲線</div>
                    <div class="text-muted" style="font-size: 0.8rem;">
                        缺氧誘導修復酵素上調並減損 TMZ 敏感性。檢視高缺氧組與低缺氧組的 Kaplan-Meier 存活曲線。
                    </div>
                </a>

                <!-- 4. 關聯到腫瘤復發進展 -->
                <a href="/analysis/tumor_recurrence.php?marker=PMT" class="module-link-card recurrence">
                    <div class="d-flex justify-content-between">
                        <span class="badge text-white mb-1" style="background-color: #6f42c1;">4. 原發 vs 復發</span>
                        <i class="fa-solid fa-arrow-up-right-from-square text-muted small"></i>
                    </div>
                    <div class="fw-bold small text-dark">神經轉間質 (PMT) 進展分析</div>
                    <div class="text-muted" style="font-size: 0.8rem;">
                        復發樣本中缺氧訊號顯著增強，常伴隨 Proneural-to-Mesenchymal Transition 惡性表型轉變。
                    </div>
                </a>

                <!-- 下載與整合查詢快捷鍵 -->
                <div class="mt-3 pt-3 border-top d-grid gap-2">
                    <button class="btn btn-outline-secondary btn-sm" onclick="alert('導出目前圖表與對應臨床體學資料表')">
                        <i class="fa-solid fa-download me-1"></i> 下載本頁分析矩陣 (CSV)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 初始化主展示圖表（以 Hypoxia 分組下的乳酸表現量為例）
const chartDom = document.getElementById('chart-container');
const myChart = echarts.init(chartDom);

const option = {
    title: {
        text: 'L-Lactate Relative Abundance in GBM by Hypoxia Status',
        subtext: 'Data source: CPTAC Metabolomics & Transcriptomics (Hypoxia Gene Set)',
        left: 'center',
        textStyle: { fontSize: 14 }
    },
    tooltip: {
        trigger: 'axis',
        axisPointer: { type: 'shadow' }
    },
    grid: {
        left: '8%',
        right: '8%',
        bottom: '12%',
        containLabel: true
    },
    xAxis: {
        type: 'category',
        data: ['Hypoxia Low (n=45)', 'Hypoxia Medium (n=52)', 'Hypoxia High (n=48)'],
        axisLabel: { fontWeight: 'bold' }
    },
    yAxis: {
        type: 'value',
        name: 'Relative Intensity (log2)',
        nameLocation: 'middle',
        nameGap: 40
    },
    series: [
        {
            name: 'Median Relative Abundance',
            type: 'bar',
            barWidth: '40%',
            data: [
                { value: 1.15, itemStyle: { color: '#6c757d' } },
                { value: 2.38, itemStyle: { color: '#4dabf7' } },
                { value: 4.82, itemStyle: { color: '#e03131' } }
            ],
            markPoint: {
                data: [
                    { name: 'Max', value: 'p < 0.001 ***', xAxis: 2, yAxis: 4.9, symbol: 'pin', symbolSize: 30 }
                ]
            }
        }
    ]
};

myChart.setOption(option);
window.addEventListener('resize', () => myChart.resize());
</script>

</body>
</html>