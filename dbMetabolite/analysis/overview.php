<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GBM Analysis Multi-Omics Network Overview</title>
    <!-- 引入 Bootstrap 5 與 ECharts 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .overview-container {
            height: calc(100vh - 100px);
            min-height: 650px;
        }
        #network-chart {
            width: 100%;
            height: 100%;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .info-panel {
            height: 100%;
            overflow-y: auto;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 24px;
        }
        .badge-dim {
            font-size: 0.8rem;
            margin-right: 4px;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>

<div class="container-fluid py-3">
    <!-- 頂部導航區：左側返回按鈕 + 標題與說明 -->
    <div class="row mb-3 align-items-center">
        <div class="col-auto">
            <a href="javascript:history.back();" class="btn btn-outline-secondary btn-sm">
                &larr; Previous page
            </a>
        </div>
        <div class="col">
            <h4 class="fw-bold mb-1">GBM Multi-Omics Landscape</h4>
            <p class="text-muted small mb-0">
                點擊或懸停節點以查看多維度關聯分析。黃色邊框標示為跨模組共享的關鍵節點（如 Hypoxia、TAMs、GSC、PTEN loss）。
            </p>
        </div>
    </div>

    <!-- 主顯示區域 -->
    <div class="row overview-container g-3">
        <!-- 左側：ECharts 網絡拓撲圖 (75%) -->
        <div class="col-lg-9 col-md-8 h-100">
            <div id="network-chart"></div>
        </div>

        <!-- 右側：節點互動資訊面板 (25%) -->
        <div class="col-lg-3 col-md-4 h-100">
            <div class="info-panel" id="detail-panel">
                <h5 class="fw-bold text-primary mb-3" id="panel-title">請點選節點</h5>
                <div id="panel-tags" class="mb-3"></div>
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">生物機制與臨床意義</label>
                    <p class="text-secondary small" id="panel-desc">
                        點擊左側網絡中的任一節點，查看該指標在「分子亞型」、「免疫反應」、「藥物治療」及／或「原發/復發進展」間的跨維度連結與分析入口。
                    </p>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">涵蓋體學資料層 (Omics Layers)</label>
                    <div id="panel-omics" class="small text-dark">--</div>
                </div>
                <hr>
                <div id="panel-actions" class="d-grid gap-2">
                    <button class="btn btn-outline-secondary btn-sm" disabled>請選擇終端分析節點</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 1. 定義節點類別樣式
const categories = [
    { name: 'Hub', itemStyle: { color: '#2b3a4a' } },
    { name: 'Molecular Subtypes', itemStyle: { color: '#4A90E2' } },
    { name: 'Immune Response', itemStyle: { color: '#50E3C2' } },
    { name: 'Drug Response', itemStyle: { color: '#F5A623' } },
    { name: 'Recurrence', itemStyle: { color: '#BD10E0' } },
    { name: '跨維度關鍵節點 (Cross-Module Bridges)', itemStyle: { color: '#FF5722' } }
];

// 2. 建立節點資料
const nodes = [
    // --- 核心維度節點 ---
    { id: 'hub_subtype', name: 'Molecular Subtypes', symbolSize: 65, category: 0, desc: '涵蓋 Classical、Mesenchymal 與 Proneural 等分子分型及其分子特徵。', omics: ['Genomics', 'Transcriptomics', 'Proteomics'] },
    { id: 'hub_immune', name: 'Immune Response', symbolSize: 65, category: 0, desc: '微環境免疫抑制細胞浸潤、抗原呈遞機制與免疫檢查點。', omics: ['Transcriptomics', 'CIBERSORT', 'ssGSEA'] },
    { id: 'hub_drug', name: 'Drug Response', symbolSize: 65, category: 0, desc: '化療藥物 TMZ 敏感性、類固醇抗性及血腦障壁通透性影響。', omics: ['Pharmacology', 'Clinical Survival', 'Transcriptomics'] },
    { id: 'hub_recurrence', name: 'Recurrence', symbolSize: 65, category: 0, desc: '原發與復發配對樣本的分子演化、表現型可塑性轉化。', omics: ['Paired RNA-seq', 'DNA Methylation', 'Clinical'] },

    // --- 跨維度橋樑節點 ---
    { 
        id: 'bridge_hypoxia', 
        name: '缺氧與代謝重編程\n(Hypoxia)', 
        symbolSize: 48, 
        category: 5,
        desc: '缺氧環境透過 HIF-1α 促進神經轉間質 (PMT)、降低 TMZ 敏感性，並驅動免疫抑制。',
        omics: ['Transcriptomics (Hypoxia Score)', 'Metabolomics', 'Survival Outcomes'],
        links: ['/analysis/hub_hypoxia.php']
    },
    { 
        id: 'bridge_tams', 
        name: '巨噬細胞/小膠質細胞\n(TAMs)', 
        symbolSize: 45, 
        category: 5,
        desc: '腫瘤相關巨噬細胞的 M2 極化浸潤，與 Mesenchymal 亞型及腫瘤復發惡性化高度相關。',
        omics: ['Transcriptomics', 'Single-cell RNA', 'Clinical Correlation'],
        links: ['/analysis/Immune_Cell_Correlation.php']
    },
    { 
        id: 'bridge_gsc', 
        name: '腫瘤幹細胞\n(GSC / Stemness)', 
        symbolSize: 45, 
        category: 5,
        desc: '具備自體更新與耐藥特性的腫瘤幹細胞，是造成復發與化療阻抗的主因。',
        omics: ['Stemness Score (mRNAsi)', 'Transcriptomics', 'Drug Response'],
        links: ['/analysis/Stemness_Score.php']
    },
    { 
        id: 'bridge_pten', 
        name: 'PTEN loss / 缺失', 
        symbolSize: 42, 
        category: 5,
        desc: 'PTEN 缺失常活化 PI3K/Akt 路徑，影響免疫抗原呈遞 (MHC-I) 並影響預後。',
        omics: ['Genomics (CNV/Mutation)', 'Proteomics', 'Clinical EHR'],
        links: ['/analysis/Molecular_Subtypes.php?gene=PTEN']
    },
    { 
        id: 'bridge_bbb', 
        name: '血腦屏障/血腫瘤屏障\n(BBB / BTB)', 
        symbolSize: 40, 
        category: 5,
        desc: '評估藥物穿透度、血管周圍 (Perivascular) 生態系與系統性免疫隔離。',
        omics: ['Endothelial Markers', 'Drug Penetration', 'Clinical'],
        links: ['/analysis/Drug_Response.php?tab=bbb']
    },

    // --- 子分析節點 ---
    { id: 'egfr_mut', name: 'EGFR 擴增突變\n(Classical)', symbolSize: 34, category: 1, desc: 'Classical 亞型標誌性變異，驅動下游代謝重編程。', omics: ['Genomics', 'Proteomics'] },
    { id: 'pdgfra_mut', name: 'PDGFRA 擴增\n(Proneural)', symbolSize: 34, category: 1, desc: 'Proneural 亞型常見受體酪胺酸激酶 (RTK) 異常。', omics: ['Genomics', 'Transcriptomics'] },
    { id: 'mes_subtype', name: 'MES 間質亞型\n(NF1 缺失)', symbolSize: 34, category: 1, desc: '高惡性度、強發炎與侵襲特徵，通常伴隨較差預後。', omics: ['Transcriptomics', 'Survival'] },
    { id: 'checkpoints', name: '免疫檢查點\n(PD-1 / PD-L1)', symbolSize: 34, category: 2, desc: '微環境 T 細胞耗竭與免疫抑制配體表現量關聯分析。', omics: ['Transcriptomics', 'Immunohistochemistry'] },
    { id: 'mhc_apm', name: 'MHC-I / APM 抗原呈遞', symbolSize: 34, category: 2, desc: '腫瘤細胞逃脫免疫監視的抗原呈遞系統喪失分析。', omics: ['Genomics Mutation', 'RNA Expression'] },
    { id: 's1pr1', name: 'S1PR1 內化', symbolSize: 34, category: 2, desc: 'S1PR1 動態活化 STAT3/Akt 路徑，造成 T 細胞骨髓螯合與化療阻抗。', omics: ['Phosphoproteomics', 'Drug Sensitivity'] },
    { id: 'tmz_chemo', name: 'TMZ 化學治療', symbolSize: 36, category: 3, desc: '第一線烷化劑治療劑量、敏感性與臨床存活時間關聯。', omics: ['Clinical Survival', 'IC50 Data'] },
    { id: 'pmt_trans', name: '神經轉間質 (PMT)', symbolSize: 36, category: 4, desc: 'Proneural-to-Mesenchymal Transition 轉化分析，治療後的適應性轉變。', omics: ['Bulk RNA-seq', 'Paired Samples'] },
    { id: 'mgmt_meth', name: 'MGMT 甲基化狀態', symbolSize: 34, category: 4, desc: '啟動子甲基化評估，為 TMZ 敏感性最重要的預後指標之一。', omics: ['DNA Methylation (450k/EPIC)', 'Clinical'] },
    { id: 'mmr_defect', name: 'MMR 缺陷/突變', symbolSize: 34, category: 4, desc: '錯配修復機制缺陷伴隨高突變負荷 (TMB) 與抗藥復發。', omics: ['Genomics Mutation', 'MMR Gene Set'] }
];

// 3. 建立連線
const links = [
    { source: 'hub_subtype', target: 'egfr_mut' },
    { source: 'hub_subtype', target: 'pdgfra_mut' },
    { source: 'hub_subtype', target: 'mes_subtype' },
    { source: 'hub_immune', target: 'checkpoints' },
    { source: 'hub_immune', target: 'mhc_apm' },
    { source: 'hub_immune', target: 's1pr1' },
    { source: 'hub_drug', target: 'tmz_chemo' },
    { source: 'hub_recurrence', target: 'pmt_trans' },
    { source: 'hub_recurrence', target: 'mgmt_meth' },
    { source: 'hub_recurrence', target: 'mmr_defect' },

    // 跨維度關鍵連線
    { source: 'hub_subtype', target: 'bridge_hypoxia', lineStyle: { width: 3, type: 'dashed' } },
    { source: 'hub_immune', target: 'bridge_hypoxia', lineStyle: { width: 3, type: 'dashed' } },
    { source: 'hub_drug', target: 'bridge_hypoxia', lineStyle: { width: 3, type: 'dashed' } },
    { source: 'hub_recurrence', target: 'bridge_hypoxia', lineStyle: { width: 3, type: 'dashed' } },

    { source: 'hub_subtype', target: 'bridge_tams' },
    { source: 'hub_immune', target: 'bridge_tams', lineStyle: { width: 2.5 } },
    { source: 'bridge_tams', target: 'pmt_trans', lineStyle: { type: 'dotted', color: '#BD10E0' } },

    { source: 'hub_drug', target: 'bridge_gsc' },
    { source: 'hub_recurrence', target: 'bridge_gsc' },

    { source: 'hub_subtype', target: 'bridge_pten' },
    { source: 'hub_immune', target: 'bridge_pten' },

    { source: 'hub_immune', target: 'bridge_bbb' },
    { source: 'hub_drug', target: 'bridge_bbb' },

    { source: 's1pr1', target: 'tmz_chemo', lineStyle: { type: 'dotted', color: '#F5A623' } },
    { source: 'mgmt_meth', target: 'tmz_chemo', lineStyle: { width: 2.5, color: '#F5A623' } }
];

// 4. 初始化 ECharts 圖表
const chartDom = document.getElementById('network-chart');
const myChart = echarts.init(chartDom);

const option = {
    tooltip: {
        trigger: 'item',
        formatter: function (params) {
            if (params.dataType === 'node') {
                return `<b>${params.data.name.replace('\n', ' ')}</b><br/>${params.data.desc || ''}`;
            }
            return `${params.data.source} → ${params.data.target}`;
        }
    },
    legend: [{
        data: categories.map(c => c.name),
        orient: 'horizontal',
        top: 10,
        left: 20,
        textStyle: { fontSize: 12 }
    }],
    series: [{
        type: 'graph',
        layout: 'force',
        data: nodes,
        links: links,
        categories: categories,
        roam: true,
        draggable: true,
        focusNodeAdjacency: true,
        label: {
            show: true,
            position: 'right',
            formatter: '{b}',
            fontSize: 11
        },
        lineStyle: {
            color: 'source',
            curveness: 0.2,
            opacity: 0.7
        },
        emphasis: {
            focus: 'adjacency',
            lineStyle: { width: 4 }
        },
        force: {
            repulsion: 450,
            edgeLength: [60, 160],
            gravity: 0.08
        }
    }]
};

myChart.setOption(option);

// 5. 綁定節點點擊事件
myChart.on('click', function (params) {
    if (params.dataType === 'node') {
        const d = params.data;
        document.getElementById('panel-title').innerText = d.name.replace('\n', ' ');
        document.getElementById('panel-desc').innerText = d.desc || '無詳細機制說明。';
        
        const categoryObj = categories[d.category];
        document.getElementById('panel-tags').innerHTML = `
            <span class="badge" style="background-color: ${categoryObj.itemStyle.color};">${categoryObj.name}</span>
        `;
        
        if (d.omics && d.omics.length > 0) {
            document.getElementById('panel-omics').innerHTML = d.omics.map(o => 
                `<span class="badge bg-light text-dark border badge-dim">${o}</span>`
            ).join('');
        } else {
            document.getElementById('panel-omics').innerText = '未定義資料集';
        }

        const actionsDiv = document.getElementById('panel-actions');
        if (d.links && d.links.length > 0) {
            actionsDiv.innerHTML = `
                <a href="${d.links[0]}" class="btn btn-primary btn-sm">前往 ${d.name.replace('\n', ' ')} 分析頁面 →</a>
                <button class="btn btn-outline-secondary btn-sm mt-1" onclick="alert('查詢此節點在所有代謝物中的相關係數...')">篩選關聯代謝物 (Top Metabolites)</button>
            `;
        } else {
            actionsDiv.innerHTML = `
                <button class="btn btn-outline-secondary btn-sm" disabled>此為主要節點，請點選關聯分支</button>
            `;
        }
    }
});

window.addEventListener('resize', () => myChart.resize());
</script>
</body>
</html>