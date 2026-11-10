<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metabolite Profiling - Disease Metabolite Database</title>
    <!-- Bootstrap 5 CSS & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- ECharts 5 -->
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
    <style>
        body { background-color: #f4f6f9; }
        .stat-card {
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            padding: 16px 20px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.03);
        }
        .content-card {
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            min-height: 480px;
        }
        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            font-weight: 600;
            border-bottom: 3px solid #0d6efd;
        }
        .badge-source {
            font-size: 0.75rem;
            background-color: #eef2f6;
            color: #334155;
            border: 1px solid #cbd5e1;
        }
    </style>
</head>
<body>

<div class="container-fluid py-4 px-4">
    <!-- 導航列與標題 -->
    <div class="row align-items-center mb-3">
        <div class="col-auto">
            <a href="javascript:history.back();" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Previous page
            </a>
        </div>
        <div class="col">
            <div class="d-flex align-items-baseline gap-2">
                <h3 class="fw-bold mb-0 text-primary">L-Kynurenine</h3>
                <span class="text-muted">(犬尿氨酸)</span>
                <span class="badge bg-secondary">HMDB0000684</span>
                <span class="badge bg-light text-dark border">KEGG: C00328</span>
            </div>
            <p class="text-muted small mb-0 mt-1">
                色胺酸代謝途徑（Tryptophan Metabolism）產物，藉由活化 AhR 受體促進腫瘤微環境免疫耐受並抑制 T 細胞功能。
            </p>
        </div>
    </div>

    <!-- 頂部概覽：左側 3 個指標卡 + 右側多維度雷達圖 -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="stat-card">
                <div class="text-muted small fw-bold">主要富集分子亞型</div>
                <div class="h4 fw-bold text-dark mb-1">Mesenchymal (MES)</div>
                <div class="small text-danger"><i class="fa-solid fa-arrow-up"></i> log2FC = 2.41 (p < 0.001)</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card">
                <div class="text-muted small fw-bold">微環境免疫相關性</div>
                <div class="h4 fw-bold text-dark mb-1">TAMs (M2 極化)</div>
                <div class="small text-primary"><i class="fa-solid fa-link"></i> Spearman r = 0.68</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card">
                <div class="text-muted small fw-bold">TMZ 化療預後影響</div>
                <div class="h4 fw-bold text-dark mb-1">高表現預後不良</div>
                <div class="small text-danger"><i class="fa-solid fa-triangle-exclamation"></i> HR = 1.84 (Log-rank p=0.002)</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <!-- 綜合 4 維度影響度雷達圖 -->
            <div class="stat-card p-1" style="height: 105px;">
                <div id="mini-radar" style="width: 100%; height: 100%;"></div>
            </div>
        </div>
    </div>

    <!-- 核心查詢引擎呈現：四大維度切換分頁 -->
    <div class="content-card">
        <ul class="nav nav-tabs mb-4" id="dimensionTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="subtype-tab" data-bs-toggle="tab" data-bs-target="#subtype-pane" type="button" role="tab">
                    <i class="fa-solid fa-dna text-primary me-1"></i> 1. 分子亞型 (Subtypes)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="immune-tab" data-bs-toggle="tab" data-bs-target="#immune-pane" type="button" role="tab">
                    <i class="fa-solid fa-shield-halved text-success me-1"></i> 2. 免疫微環境 (Immunity)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="drug-tab" data-bs-toggle="tab" data-bs-target="#drug-pane" type="button" role="tab">
                    <i class="fa-solid fa-capsules text-warning me-1"></i> 3. 藥物治療反應 (Drug Response)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="recurrence-tab" data-bs-toggle="tab" data-bs-target="#recurrence-pane" type="button" role="tab">
                    <i class="fa-solid fa-arrows-rotate text-purple me-1" style="color: #6f42c1;"></i> 4. 原發 vs. 復發 (Progression)
                </button>
            </li>
        </ul>

        <div class="tab-content" id="dimensionTabContent">
            <!-- 維度 1：分子亞型 -->
            <div class="tab-pane fade show active" id="subtype-pane" role="tabpanel">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <div id="subtype-chart" style="width: 100%; height: 380px;"></div>
                    </div>
                    <div class="col-lg-4">
                        <h6 class="fw-bold">亞型分布特徵分析</h6>
                        <p class="small text-muted">
                            L-Kynurenine 在 <strong>Mesenchymal (MES)</strong> 亞型呈現最高表現量，顯著高於 Proneural (PN) 與 Classical (CL)。此現象與 MES 亞型中 IDO1/TDO2 酵素的高度活化以及 NF1 缺失後的炎症微環境密切相關。
                        </p>
                        <div class="mb-3">
                            <span class="badge badge-source">CPTAC Proteomics</span>
                            <span class="badge badge-source">TCGA Subtypes</span>
                            <span class="badge badge-source">NF1 Alteration</span>
                        </div>
                        <a href="/analysis/Molecular_Subtypes.php?focus=MES" class="btn btn-outline-primary btn-sm">
                            前往完整分子亞型分析模組 &rarr;
                        </a>
                    </div>
                </div>
            </div>

            <!-- 維度 2：免疫微環境 -->
            <div class="tab-pane fade" id="immune-pane" role="tabpanel">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <div id="immune-chart" style="width: 100%; height: 380px;"></div>
                    </div>
                    <div class="col-lg-4">
                        <h6 class="fw-bold">免疫抑制相關性</h6>
                        <p class="small text-muted">
                            代謝物濃度與 <strong>腫瘤相關巨噬細胞 (TAMs M2)</strong> 浸潤呈顯著正相關（r = 0.68, p < 0.0001），同時與 CD8+ 殺手 T 細胞活性呈負相關，顯示犬尿氨酸在膠質母細胞瘤微環境中的免疫抑制介導角色。
                        </p>
                        <div class="mb-3">
                            <span class="badge badge-source">CIBERSORT Infiltration</span>
                            <span class="badge badge-source">PD-L1 Expression</span>
                        </div>
                        <a href="/analysis/Immune_Cell_Correlation.php?cell=TAMs" class="btn btn-outline-success btn-sm">
                            查看微環境免疫細胞關聯模組 &rarr;
                        </a>
                    </div>
                </div>
            </div>

            <!-- 維度 3：藥物治療 -->
            <div class="tab-pane fade" id="drug-pane" role="tabpanel">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <div id="drug-chart" style="width: 100%; height: 380px;"></div>
                    </div>
                    <div class="col-lg-4">
                        <h6 class="fw-bold">TMZ 化療敏感性與存活分析</h6>
                        <p class="small text-muted">
                            Kaplan-Meier 存活曲線顯示：在接受標準 Stupp protocol (TMZ 化療) 的病人中，L-Kynurenine 高濃度組之中位存活期（11.2 個月）顯著短於低濃度組（18.6 個月），提示其為潛在的耐藥性預測標記。
                        </p>
                        <div class="mb-3">
                            <span class="badge badge-source">Clinical EHR</span>
                            <span class="badge badge-source">TMZ Treatment</span>
                            <span class="badge badge-source">Survival Curve</span>
                        </div>
                        <a href="/analysis/Drug_Response.php?drug=TMZ" class="btn btn-outline-warning btn-sm">
                            前往藥物反應與存活分析 &rarr;
                        </a>
                    </div>
                </div>
            </div>

            <!-- 維度 4：腫瘤復發 -->
            <div class="tab-pane fade" id="recurrence-pane" role="tabpanel">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <div id="recurrence-chart" style="width: 100%; height: 380px;"></div>
                    </div>
                    <div class="col-lg-4">
                        <h6 class="fw-bold">原發與復發動態轉變 (PMT)</h6>
                        <p class="small text-muted">
                            配對樣本分析（Primary vs. Recurrent）顯示，經放化療復發後的腫瘤組織中，犬尿氨酸濃度多數呈現進一步提升，且與神經轉間質（PMT）過渡評分同步正向演化。
                        </p>
                        <div class="mb-3">
                            <span class="badge badge-source">Paired Cohort (n=32)</span>
                            <span class="badge badge-source">PMT Score</span>
                        </div>
                        <a href="/analysis/tumor_recurrence.php?marker=PMT" class="btn btn-outline-purple btn-sm" style="color: #6f42c1; border-color: #6f42c1;">
                            前往復發與進展分析模組 &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// 1. 迷你雷達圖：四大維度關聯強度
const miniRadarChart = echarts.init(document.getElementById('mini-radar'));
miniRadarChart.setOption({
    radar: {
        indicator: [
            { name: '亞型', max: 5 },
            { name: '免疫', max: 5 },
            { name: '藥敏', max: 5 },
            { name: '復發', max: 5 }
        ],
        radius: 35,
        center: ['50%', '55%'],
        axisName: { fontSize: 10, color: '#6c757d' }
    },
    series: [{
        type: 'radar',
        data: [{
            value: [4.8, 4.5, 4.2, 3.8],
            areaStyle: { color: 'rgba(13, 110, 253, 0.3)' },
            lineStyle: { width: 1.5, color: '#0d6efd' }
        }]
    }]
});

// 2. 維度 1 圖表：分子亞型箱形/柱狀圖
const subtypeChart = echarts.init(document.getElementById('subtype-chart'));
subtypeChart.setOption({
    title: { text: 'L-Kynurenine Abundance across GBM Subtypes', left: 'center', textStyle: { fontSize: 13 } },
    tooltip: { trigger: 'axis' },
    xAxis: { type: 'category', data: ['Classical', 'Proneural', 'Mesenchymal'] },
    yAxis: { type: 'value', name: 'Log2 Intensity' },
    series: [{
        type: 'bar',
        barWidth: '40%',
        data: [
            { value: 1.82, itemStyle: { color: '#6c757d' } },
            { value: 1.25, itemStyle: { color: '#0dcaf0' } },
            { value: 4.23, itemStyle: { color: '#0d6efd' } }
        ],
        markPoint: { data: [{ name: 'Max', value: 'Highest in MES', xAxis: 2, yAxis: 4.3 }] }
    }]
});

// 3. 維度 2 圖表：免疫微環境散佈圖 (TAMs Correlation)
const immuneChart = echarts.init(document.getElementById('immune-chart'));
const mockScatter = Array.from({length: 40}, () => {
    let x = Math.random() * 5 + 1;
    let y = 0.65 * x + (Math.random() * 1.5 - 0.75);
    return [x.toFixed(2), y.toFixed(2)];
});
immuneChart.setOption({
    title: { text: 'Correlation with TAMs (M2 Macrophages Infiltration)', left: 'center', textStyle: { fontSize: 13 } },
    tooltip: { trigger: 'item' },
    xAxis: { name: 'L-Kynurenine (Log2)', type: 'value' },
    yAxis: { name: 'TAMs M2 Score', type: 'value' },
    series: [{
        type: 'scatter',
        symbolSize: 8,
        itemStyle: { color: '#198754' },
        data: mockScatter
    }]
});

// 4. 維度 3 圖表：TMZ 化療 Kaplan-Meier 曲線
const drugChart = echarts.init(document.getElementById('drug-chart'));
drugChart.setOption({
    title: { text: 'Overall Survival with TMZ Treatment (High vs Low Kynurenine)', left: 'center', textStyle: { fontSize: 13 } },
    tooltip: { trigger: 'axis' },
    legend: { data: ['High Expression', 'Low Expression'], bottom: 0 },
    xAxis: { name: 'Months', type: 'category', data: [0, 6, 12, 18, 24, 30, 36] },
    yAxis: { name: 'Survival Probability', min: 0, max: 1 },
    series: [
        { name: 'High Expression', type: 'line', step: 'end', data: [1.0, 0.85, 0.45, 0.22, 0.12, 0.05, 0.02], itemStyle: { color: '#dc3545' } },
        { name: 'Low Expression', type: 'line', step: 'end', data: [1.0, 0.92, 0.72, 0.51, 0.35, 0.22, 0.18], itemStyle: { color: '#0d6efd' } }
    ]
});

// 5. 維度 4 圖表：配對原發 vs 復發變化
const recurrenceChart = echarts.init(document.getElementById('recurrence-chart'));
recurrenceChart.setOption({
    title: { text: 'Paired Comparison: Primary vs. Recurrent Tumors (n=32)', left: 'center', textStyle: { fontSize: 13 } },
    tooltip: { trigger: 'axis' },
    xAxis: { type: 'category', data: ['Primary GBM', 'Recurrent GBM'] },
    yAxis: { name: 'Relative Abundance', type: 'value' },
    series: [{
        type: 'line',
        data: [2.1, 3.8],
        symbolSize: 10,
        itemStyle: { color: '#6f42c1' },
        lineStyle: { width: 3 }
    }]
});

// 解決 Bootstrap Tab 切換時 ECharts 圖表寬度為 0 破版的問題
document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('shown.bs.tab', () => {
        subtypeChart.resize();
        immuneChart.resize();
        drugChart.resize();
        recurrenceChart.resize();
    });
});

window.addEventListener('resize', () => {
    miniRadarChart.resize();
    subtypeChart.resize();
    immuneChart.resize();
    drugChart.resize();
    recurrenceChart.resize();
});
</script>

</body>
</html>