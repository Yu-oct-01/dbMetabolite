// 定義各類別對應的目標選項 (value 與顯示文字)
const targetOptionsMap = {
    "heterogeneity": [ // 腫瘤內異質性
        { value: "h1", text: "空間異質性與取樣位置" },
        { value: "h2", text: "時間異質性" },
    ],
    "plasticity": [ // 表型可塑性
        { value: "p1", text: "動態細胞狀態" },
    ],
    "mutation": [ // 基因體變異與體細胞突變
        { value: "onco_all", text: "All subtypes" },
        { value: "onco_pn",  text: "Proneural" },
        { value: "onco_cl",  text: "Classical" },
        { value: "onco_mes", text: "Mesenchymal" }
    ],
    "microenvironment": [ // 腫瘤微環境
        { value: "tme1", text: "Hypoxia" },
        { value: "tme2", text: "Stroma" },
        { value: "tme3", text: "Perivascular" },
        { value: "tme4", text: "Macrophage M2" },
        { value: "tme5", text: "T Regulatory Cells（Tregs)" },
        { value: "tme6", text: "MDSCs" }
    ]
};

const categorySelect = document.getElementById("metabolite-select");
const targetSelect = document.getElementById("strat-select");
const boxplotToggle = document.getElementById("boxplot-toggle");
const geneSelect = document.getElementById("gene-select");
let mutKmToken = 0;   // 防止快速切換時舊結果覆蓋新畫面
let currentBoxplotData = [];   // 暫存目前這張圖的 boxplot 統計數據

// 模組卡片樣式
const MODULE_STYLE = {
    subtypes:   { cls: 'subtypes',   badge: 'bg-danger',             style: '', label: '分子亞型關聯' },
    immunity:   { cls: 'immunity',   badge: 'bg-success',           style: '', label: '免疫反應關聯' },
    drug:       { cls: 'drug',       badge: 'bg-warning text-dark', style: '', label: '藥物治療反應' },
    recurrence: { cls: 'recurrence', badge: 'text-white',           style: 'background-color:#6f42c1', label: '復發與進展' }
};

// 每個「分析目標」的圖表設定 + 關聯模組
// field：資料表欄位名稱；目前只有 HALLMARK_HYPOXIA，其他先填 null，之後有資料再補
const targetConfig = {
    h1: {
        field: null, title: '腫瘤內異質性：空間異質性與取樣位置',
        modules: [
            { type: 'recurrence', url: '/analysis/tumor_recurrence.php?view=spatial', title: '...', desc: '。。。' },
            { type: 'immunity',   url: '/analysis/Immune_Cell_Correlation.php?view=spatial', title: '...', desc: '。。。' }
        ]
    },
    h2: {
        field: null, title: '腫瘤內異質性：時間異質性',
        modules: [
            { type: 'recurrence', url: '/analysis/tumor_recurrence.php?transition=PMT', title: '...', desc: '。。。' },
            { type: 'drug',       url: '/analysis/Drug_Response.php?stage=recurrent', title: '...', desc: '。。。' }
        ]
    },
    p1: {
        field: null, title: '表型可塑性：動態細胞狀態',
        modules: [
            { type: 'recurrence', url: '/analysis/tumor_recurrence.php?transition=PMT', title: '...', desc: '。。。' },
            { type: 'subtypes',    url: '/analysis/hub_hypoxia.php?from=subtypes', title: '...', desc: '。。。' }
        ]
    },
    onco_all: { field: null, oncoSubtype: 'ALL',         title: 'Oncoplot：All subtypes',
                insight: 'All cohort top 15 mutated genes.' },
    onco_pn:  { field: null, oncoSubtype: 'Proneural',   title: 'Oncoplot：Proneural',
                insight: 'Proneural Top 15 mutated genes.' },
    onco_cl:  { field: null, oncoSubtype: 'Classical',   title: 'Oncoplot：Classical',
                insight: 'Classical Top 15 mutated genes.' },
    onco_mes: { field: null, oncoSubtype: 'Mesenchymal', title: 'Oncoplot：Mesenchymal',
                insight: 'Mesenchymal Top 15 mutated genes.' },
    tme1: {
        field: 'HALLMARK_HYPOXIA',
        title: 'Hypoxia Box plot + Jittered points across different molecular subtypes',
        yName: 'HALLMARK_HYPOXIA', yMin: 0.6,
        kmLabel: 'Hypoxia',
        // insight: `
        //     <ul class="mb-0 ps-3 lh-base" style="font-size: 0.9rem;">
        //         <li class="mb-2">
        //             <strong class="text-primary">Mesenchymal 亞型整體處於高缺氧狀態：</strong>
        //             圖中 Mesenchymal 亞型的 HALLMARK_HYPOXIA 分數幾乎全數集中在 0.88 以上的高區間，整體中位數與下限明顯高於其他兩組，證實 Mesenchymal 亞型與低氧微環境有極高關聯。
        //         </li>
        //         <li class="mb-2">
        //             <strong class="text-primary">Proneural 亞型低氧分布最廣且下限最低：</strong>
        //             Proneural 亞型有較多樣本分布在 0.70～0.85 的較低缺氧區間，符合「典型的 Proneural 腫瘤常處於較低缺氧或對極端低氧承受力較弱」的宏觀趨勢。
        //         </li>
        //     </ul>
        // `,
        // modules: [
        //     { type: 'immunity', url: '/analysis/immune_correlation.php?subtype=MES&cell=TAMs', title: '低氧與免疫抑制', desc: '低氧抑制免疫細胞活性，並促進免疫抑制微環境的形成。' },
        //     { type: 'drug',     url: '/analysis/Drug_Response.php?driver=EGFR_PTEN', title: '低氧與藥物耐受性', desc: '低氧區域缺乏氧分子作為放射敏化劑，放療破壞力大幅降低。' },
        //     { type: 'recurrence', url: '/analysis/tumor_recurrence.php?transition=PMT', title: '低氧與腫瘤再生', desc: '低氧能維持 GSCs 處於低增殖但高耐受的狀態，在放化療結束後重新甦醒並啟動腫瘤再生。' }
        // ]
    },
    tme2: {
        field: 'stroma score',
        title: 'Stroma score Box plot + Jittered points across different molecular subtypes',
        yName: 'Stroma score', yMin: -0.03,
        kmLabel: 'Stroma',
        // insight: `
        //     <ul class="mb-0 ps-3 lh-base" style="font-size: 0.9rem;">
        //         <li class="mb-2">
        //             <strong class="text-primary">Mesenchymal 亞型整體偏向擁有較高基質分數：</strong>
        //             圖中 Mesenchymal 亞型的點的分佈在方較為分散，中高分數（0.04 至 0.12）的樣本佔比明顯高於 Proneural，整體重心中位數在三者中偏高，能推測與較高的發炎反應、血管新生及基質細胞浸潤相關。
        //         </li>
        //         <li class="mb-2">
        //             <strong class="text-primary">Proneural 亞型整體中位數在三組中偏低：</strong>
        //             Proneural 亞型大部分樣本集中在低分數區間（約 0.00 至 0.04），可能代表較為內因性、神經元前驅特徵的腫瘤。
        //         </li>
        //     </ul>
        // `,
        // modules: [
        //     { type: 'immunity',   url: '/analysis/Immune_Cell_Correlation.php?view=ecm', title: '腫瘤與活化基質細胞', desc: '厚重的基質屏障使 CD8+ T 細胞在穿透基質時受到物理阻礙，大多被困在腫瘤外圍基質帶。' },
        //     { type: 'drug',       url: '/analysis/Drug_Response.php?driver=EGFR_PTEN', title: '化療遞送屏障與放化療耐受', desc: '高 IFP 阻礙了小分子化療藥物的有效擴散與穿透，而細胞黏附介導的耐藥性會上調抗凋亡蛋白，並賦予細胞抵抗失巢凋亡（Anoikis）的能力' },
        //     { type: 'recurrence', url: '/analysis/tumor_recurrence.php?transition=PMT', title: '細胞外基質與腫瘤幹細胞', desc: '特殊的 ECM 微結構為腫瘤幹細胞提供了穩定的物理庇護。' }
        // ]
    },
    tme3: {
        field: 'Perivascular_Score',
        title: 'Perivascular score Box plot + Jittered points across different molecular subtypes',
        yName: 'Perivascular score', yMin: -3,
        kmLabel: 'Perivascular',
    },
    tme4: {
        field: 'Macrophage_M2',
        title: 'Macrophage M2 Box plot + Jittered points across different molecular subtypes',
        yName: 'Macrophage M2', yMin: -0.01,
        kmLabel: 'M2',
    },
    tme5: {
        field: 'Tregs',
        title: 'Tregs Box plot + Jittered points across different molecular subtypes',
        yName: 'Tregs', yMin: -0.005,
        kmLabel: 'Tregs',
    },
    tme6: {
        field: 'MDSCs_Score',
        title: 'MDSCs score Box plot + Jittered points across different molecular subtypes',
        yName: 'MDSCs score', yMin: -1.6,
        kmLabel: 'MDSCs',
    }
};

// 依選項渲染右側模組卡片
function renderModules(cfg) {
    document.getElementById('module-links').innerHTML = (cfg.modules || []).map(m => {
        const s = MODULE_STYLE[m.type];
        return `<a href="${m.url}" class="module-link-card ${s.cls}">
            <div class="d-flex justify-content-between">
                <span class="badge ${s.badge} mb-1" style="${s.style}">${s.label}</span>
                <i class="fa-solid fa-arrow-up-right-from-square text-muted small"></i>
            </div>
            <div class="fw-bold small text-dark">${m.title}</div>
            <div class="text-muted" style="font-size: 0.8rem;">${m.desc}</div>
        </a>`;
    }).join('');
}

// 還沒有資料欄位的目標，顯示提示
function renderPlaceholder(cfg) {
    myChart.clear();
    myChart.setOption({
        title: { text: cfg.title, subtext: '此分析目標尚無對應資料', left: 'center', top: 'middle' }
    });
}

// 更新第二層選單的函式
function updateTargetOptions() {
    const selectedCategory = categorySelect.value;
    const options = targetOptionsMap[selectedCategory] || [];

    targetSelect.innerHTML = options
        .map(opt => `<option value="${opt.value}">${opt.text}</option>`)
        .join("");

    // 只有 Gene Mutation 才顯示基因選單
    document.getElementById('gene-wrap').style.display = (selectedCategory === 'mutation') ? 'flex' : 'none';
}

// 初始化 ECharts 圖表
const chartDom = document.getElementById('subtype-chart');
const myChart = echarts.init(chartDom);

// ===== KM 圖：三個亞型各一個 chart =====
const kmSubtypes = ['PRONEURAL', 'CLASSICAL', 'MESENCHYMAL'];
const kmCharts = {};
function getKMChart(sub) {
    if (!kmCharts[sub]) kmCharts[sub] = echarts.init(document.getElementById('km-' + sub));
    return kmCharts[sub];
}


function showView(mode) {   // 'scatter' | 'placeholder' | 'km' | 'onco'
    const showChart = (mode === 'scatter' || mode === 'placeholder');
    document.getElementById('subtype-chart').style.display = showChart ? 'block' : 'none';
    document.getElementById('boxplot-toggle-wrap').style.display = (mode === 'scatter') ? 'flex' : 'none';   // 只有散布圖才顯示
    document.getElementById('km-charts').style.display    = (mode === 'km') ? 'block' : 'none';
    document.getElementById('oncoplot-wrap').style.display = (mode === 'onco') ? 'block' : 'none';
    if (showChart) myChart.resize();
}

// 新增：載入 Oncoplot
function renderOncoplot(cfg) {
    mutKmToken++;
    showView('onco');
    const img = document.getElementById('oncoplot-img');
    const loading = document.getElementById('oncoplot-loading');
    const err = document.getElementById('oncoplot-error');

    img.style.display = 'none';
    err.style.display = 'none';
    loading.style.display = 'block';

    img.onload = () => { loading.style.display = 'none'; img.style.display = 'inline-block'; };
    img.onerror = () => {
        loading.style.display = 'none';
        err.style.display = 'block';
        err.textContent = 'Oncoplot 產生失敗，請檢查 oncoplot.php 與 Rscript 路徑。';
    };
    img.src = 'oncoplot.php?subtype=' + encodeURIComponent(cfg.oncoSubtype);

    renderModules(cfg);
    document.querySelector('.badge.bg-secondary').innerText = 'Oncoplot (maftools)';
    document.getElementById('insight-text').innerHTML = cfg.insight || '';
}

async function renderMutationKM(cfg) {
    const token = ++mutKmToken;
    const gene = geneSelect.value;
    const img = document.getElementById('oncoplot-img');
    const loading = document.getElementById('oncoplot-loading');
    const err = document.getElementById('oncoplot-error');

    showView('onco');
    img.style.display = 'none';
    err.style.display = 'none';
    loading.style.display = 'block';

    const subLabel = cfg.oncoSubtype === 'ALL' ? 'All subtypes' : cfg.oncoSubtype;
    document.querySelector('.badge.bg-secondary').innerText = 'Log-rank / Cox';

    try {
        const res = await fetch(`mutation_survival.php?gene=${encodeURIComponent(gene)}&subtype=${encodeURIComponent(cfg.oncoSubtype)}`);
        if (token !== mutKmToken) return;
        if (!res.ok) throw new Error(await res.text());

        const blob = await res.blob();
        if (token !== mutKmToken) return;

        img.onload = () => { loading.style.display = 'none'; img.style.display = 'inline-block'; };
        img.src = URL.createObjectURL(blob);
        document.getElementById('insight-text').innerHTML =
            `<strong>${gene}</strong> 突變 vs 野生型的整體存活分析（${subLabel}）。`;
    } catch (e) {
        if (token !== mutKmToken) return;
        loading.style.display = 'none';
        err.style.display = 'block';
        err.textContent = e.message || '分析失敗';
    }
}

function renderSubtypeChart(cfg) {
    myChart.clear();   // 從 KM 圖或提示畫面切回來時需要先清空
    const subtypeCategories = ['PRONEURAL', 'CLASSICAL', 'MESENCHYMAL'];
    
    // 將資料庫數值對應至座標軸索引 [X軸索引, Y軸數值, 亞型名稱, Case ID]
    const rawData = dbHypoxiaData.filter(item => item[cfg.field] != null).map(item => {
        const groupIndex = subtypeCategories.indexOf(item.MolecularSubtype);
        return [
            groupIndex !== -1 ? groupIndex : 0,
            parseFloat(item[cfg.field]) || 0,
            item.MolecularSubtype,
            item['Case ID']
        ];
    });

    // 增加散布抖動效果避免點重疊
    const jitterRange = 35;
    const chartData = rawData.map(item => {
        const jitterPixel = (Math.random() - 0.5) * 2 * jitterRange;
        return [...item, jitterPixel];
    });

    // 計算各組 Box plot 所需的統計數據 [min, Q1, median, Q3, max]
    const boxplotData = subtypeCategories.map((cat, groupIndex) => {
        const values = rawData.filter(item => item[0] === groupIndex).map(item => item[1]).sort((a, b) => a - b);
        if (values.length === 0) return [0, 0, 0, 0, 0];
        
        const min = values[0];
        const max = values[values.length - 1];
        const q1 = values[Math.floor(values.length * 0.25)];
        const median = values[Math.floor(values.length * 0.5)];
        const q3 = values[Math.floor(values.length * 0.75)];
        
        return [min, q1, median, q3, max];
    });
    currentBoxplotData = boxplotData;

    const option = {
        title: {
            text: cfg.title,
            subtext: cfg.subtext,
            left: 'center',
            textStyle: { fontSize: 15 }
        },
        tooltip: {
            trigger: 'item',
            formatter: (params) => {
                if (params.seriesType === 'boxplot') {
                    const [min, q1, median, q3, max] = params.data.slice(1);
                    return `<strong>${params.name}</strong><br/>` +
                           `Max: ${max.toFixed(4)}<br/>` +
                           `Q3: ${q3.toFixed(4)}<br/>` +
                           `Median: ${median.toFixed(4)}<br/>` +
                           `Q1: ${q1.toFixed(4)}<br/>` +
                           `Min: ${min.toFixed(4)}`;
                } else if (params.seriesType === 'custom') {
                    const [, score, group, id] = params.data;
                    return `<strong>${id}</strong><br/>` +
                           `Subtype: ${group}<br/>` +
                           `${cfg.yName}：<strong>${score.toFixed(4)}</strong>`;
                }
            }
        },
        grid: {
            left: '10%',
            right: '10%',
            bottom: '15%',
            containLabel: true
        },
        xAxis: {
            type: 'category',
            data: subtypeCategories,
            axisLabel: {
                fontWeight: 'bold',
                fontSize: 13,
                color: '#333'
            },
            axisTick: { alignWithLabel: true },
            splitLine: {
                show: true,
                lineStyle: { type: 'dashed', color: '#e9ecef' }
            }
        },
        yAxis: {
            type: 'value',
            min: cfg.yMin,
            name: cfg.yName,
            nameLocation: 'middle',
            nameGap: 45,
            axisLine: {
                show: true,
                onZero: false,
                lineStyle: { color: '#333' }
            },
            splitLine: {
                lineStyle: { type: 'dashed', color: '#e9ecef' }
            }
        },
        series: [
            {
                name: cfg.yName,
                type: 'custom',
                renderItem: (params, api) => {
                    const colors = ['#0d6efd', '#ffc107', '#dc3545']; // PN: 藍, CL: 黃, MES: 紅
                    const coord = api.coord([api.value(0), api.value(1)]);
                    const jitterOffset = api.value(4);
                    const groupIndex = api.value(0);

                    return {
                        type: 'circle',
                        cursor: 'pointer',
                        shape: {
                            cx: coord[0] + jitterOffset,
                            cy: coord[1],
                            r: 6.5
                        },
                        style: {
                            fill: colors[groupIndex] || '#0d6efd',
                            stroke: '#ffffff',
                            lineWidth: 1.5,
                            opacity: 0.85
                        }
                    };
                },
                data: chartData
            },
            {
                name: 'boxplot',
                type: 'boxplot',
                data: boxplotToggle.checked ? boxplotData : [],
                itemStyle: {
                    color: 'rgba(248, 249, 250, 50)',
                    borderColor: '#495057',
                    borderWidth: 1.5
                },
                emphasis: {
                    itemStyle: {
                        borderColor: '#0d6efd',
                        borderWidth: 2
                    }
                }
            }
        ]
    };

    myChart.setOption(option);
}

// 純 JavaScript 原生 Kruskal-Wallis 檢定 (包含 Tie 修正與卡方 p-value 近似)
function computeKruskalWallis(data, field, subtypeField) {
    if (!field || !data || data.length === 0) return "無對應資料";

    // 1. 依分子亞型分組收集資料
    const groups = {};
    const allObservations = [];

    data.forEach(item => {
        const sub = item[subtypeField];
        const val = parseFloat(item[field]);
        if (sub && !isNaN(val)) {
            if (!groups[sub]) groups[sub] = [];
            groups[sub].push(val);
            allObservations.push({ group: sub, val: val });
        }
    });

    const groupNames = Object.keys(groups);
    // 至少需要 2 個有效亞型分組，且總樣本數 > 2
    if (groupNames.length < 2 || allObservations.length <= groupNames.length) {
        return "資料不足";
    }

    const N = allObservations.length;

    // 2. 排序並計算秩 (Rank)，處理平局 (Ties)
    allObservations.sort((a, b) => a.val - b.val);

    let tieSum = 0; // 平局修正項
    let i = 0;
    while (i < N) {
        let j = i;
        while (j < N - 1 && allObservations[j + 1].val === allObservations[i].val) {
            j++;
        }
        const count = j - i + 1;
        const averageRank = (i + 1 + j + 1) / 2;
        for (let k = i; k <= j; k++) {
            allObservations[k].rank = averageRank;
        }
        if (count > 1) {
            tieSum += (Math.pow(count, 3) - count);
        }
        i = j + 1;
    }

    // 3. 計算各組 Rank 和 R_i 與樣本數 n_i
    const groupRankSums = {};
    allObservations.forEach(obs => {
        groupRankSums[obs.group] = (groupRankSums[obs.group] || 0) + obs.rank;
    });

    let sumR2_over_n = 0;
    groupNames.forEach(g => {
        const n_i = groups[g].length;
        const R_i = groupRankSums[g];
        sumR2_over_n += (R_i * R_i) / n_i;
    });

    // 4. 計算 H 統計量
    let H = (12 / (N * (N + 1))) * sumR2_over_n - 3 * (N + 1);

    // 平局修正 (Tie correction)
    const C = 1 - (tieSum / (Math.pow(N, 3) - N));
    if (C > 0) {
        H = H / C;
    }

    // 5. 由卡方分佈 (df = k - 1) 計算 p-value
    const df = groupNames.length - 1;
    const pValue = 1 - chisquareCDF(H, df);

    if (isNaN(pValue)) return "統計計算錯誤";

    const pValStr = pValue < 0.0001 ? "< 0.0001" : `= ${pValue.toFixed(4)}`;
    return `Kruskal-Wallis: p ${pValStr}`;
}

// Dunn's test 兩兩比較 (採用 Bonferroni 校正 p-value)
function computeDunnsTest(data, field, subtypeField) {
    if (!field || !data || data.length === 0) return [];

    const groups = {};
    const allObservations = [];

    data.forEach(item => {
        const sub = item[subtypeField];
        const val = parseFloat(item[field]);
        if (sub && !isNaN(val)) {
            if (!groups[sub]) groups[sub] = [];
            groups[sub].push(val);
            allObservations.push({ group: sub, val: val });
        }
    });

    const groupNames = Object.keys(groups);
    if (groupNames.length < 2) return [];

    const N = allObservations.length;
    allObservations.sort((a, b) => a.val - b.val);

    // 計算 Rank (處理 Ties)
    let i = 0;
    while (i < N) {
        let j = i;
        while (j < N - 1 && allObservations[j + 1].val === allObservations[i].val) j++;
        const averageRank = (i + 1 + j + 1) / 2;
        for (let k = i; k <= j; k++) allObservations[k].rank = averageRank;
        i = j + 1;
    }

    const groupRanks = {};
    groupNames.forEach(g => groupRanks[g] = []);
    allObservations.forEach(obs => groupRanks[obs.group].push(obs.rank));

    // 計算平均秩 (Mean Rank)
    const meanRanks = {};
    groupNames.forEach(g => {
        const ranks = groupRanks[g];
        meanRanks[g] = ranks.reduce((a, b) => a + b, 0) / ranks.length;
    });

    // 計算 Tie 修正項
    let tieSum = 0;
    i = 0;
    while (i < N) {
        let j = i;
        while (j < N - 1 && allObservations[j + 1].val === allObservations[i].val) j++;
        const count = j - i + 1;
        if (count > 1) tieSum += (Math.pow(count, 3) - count);
        i = j + 1;
    }
    const varianceFactor = (N * (N + 1) / 12) - (tieSum / (12 * (N - 1)));

    // 兩兩比較組合 (k*(k-1)/2 個對比)
    const comparisons = [];
    const numComparisons = (groupNames.length * (groupNames.length - 1)) / 2;

    for (let a = 0; a < groupNames.length; a++) {
        for (let b = a + 1; b < groupNames.length; b++) {
            const g1 = groupNames[a];
            const g2 = groupNames[b];
            const n1 = groups[g1].length;
            const n2 = groups[g2].length;

            const SE = Math.sqrt(varianceFactor * ((1 / n1) + (1 / n2)));
            const Z = Math.abs(meanRanks[g1] - meanRanks[g2]) / SE;
            
            // 計算雙尾 p-value 並用 Bonferroni 校正
            const pVal = 2 * (1 - normalCDF(Z));
            const adjPVal = Math.min(1.0, pVal * numComparisons);

            comparisons.push({
                group1: g1,
                group2: g2,
                zStat: Z,
                pVal: adjPVal
            });
        }
    }
    return comparisons;
}

// 卡方累積分佈函數 (Chi-Square CDF)
function chisquareCDF(x, df) {
    if (x <= 0) return 0;
    return gammaIncomplete(df / 2, x / 2);
}

// 下不完全 Gamma 函數 (Lower Incomplete Gamma Function)
function gammaIncomplete(a, x) {
    let sum = 1 / a;
    let value = 1 / a;
    for (let i = 1; i < 100; i++) {
        value *= x / (a + i);
        sum += value;
        if (value < 1e-7) break;
    }
    return Math.exp(-x + a * Math.log(x) - logGamma(a)) * sum;
}

// Lanczos 逼近法計算 Log-Gamma
function logGamma(z) {
    const p = [
        676.5203681218851, -1259.139216722289, 771.3234287776531,
        -176.61502916214059, 12.507343278686905, -0.13857109526572012,
        9.984369578019571e-6, 1.5056327351493116e-7
    ];
    if (z < 0.5) return Math.log(Math.PI / Math.sin(Math.PI * z)) - logGamma(1 - z);
    z -= 1;
    let x = 0.99999999999980993;
    for (let i = 0; i < p.length; i++) {
        x += p[i] / (z + i + 1);
    }
    const t = z + p.length - 0.5;
    return 0.5 * Math.log(2 * Math.PI) + (z + 0.5) * Math.log(t) - t + Math.log(x);
}

// 選單切換時的統一入口：圖表 + 右側模組 + 洞察文字
function renderTarget() {
    const cfg = targetConfig[targetSelect.value];
    if (!cfg) return;
    if (cfg.oncoSubtype) { renderOncoplot(cfg); return; }

    showView(cfg.field ? 'scatter' : 'placeholder');
    if (cfg.field) renderSubtypeChart(cfg); else renderPlaceholder(cfg);
    renderModules(cfg);
    document.getElementById('insight-text').innerHTML = cfg.insight || '此目標的統計觀察待補充。';

    // 1. Kruskal-Wallis 檢定結果
    const pValueText = computeKruskalWallis(dbHypoxiaData, cfg.field, 'MolecularSubtype');

    document.querySelector('.badge.bg-secondary').innerText = pValueText;

    // 2. Dunn's test 兩兩比較結果
    const dunnResults = computeDunnsTest(dbHypoxiaData, cfg.field, 'MolecularSubtype');
    let dunnText = "";
    if (dunnResults.length > 0) {
        dunnText = "Dunn's test (adj. p): <br>" + dunnResults.map(d => 
            `${d.group1} vs ${d.group2}: ${d.pVal < 0.0001 ? '< 0.0001' : `= ${d.pVal.toFixed(4)}`}`
        ).join("<br>");
    }

    // 修改這裡：使用 <br> 標籤進行網頁換行
    document.getElementById('insight-text').innerHTML = 
        (cfg.insight || '此目標的統計觀察待補充。') + '<br>' +
        dunnText;

}

// 單一組的 KM 階梯曲線
function computeKM(group) {
    group.sort((a, b) => a.time - b.time);
    let survival = 1.0, atRisk = group.length;
    const curve = [[0, 1.0]];
    const tp = {};
    group.forEach(it => {
        if (!tp[it.time]) tp[it.time] = { events: 0, total: 0 };
        tp[it.time].total++;
        if (it.status === 1) tp[it.time].events++;
    });
    Object.keys(tp).map(Number).sort((a, b) => a - b).forEach(t => {
        const { events, total } = tp[t];
        if (events > 0 && atRisk > 0) survival *= (1 - events / atRisk);
        curve.push([t, parseFloat(survival.toFixed(4))]);
        atRisk -= total;
    });
    return curve;
}

// 畫單一亞型的 KM 圖（用該亞型自己的中位數分高/低組）
function drawKMForSubtype(sub, data, cfg, labelHigh, labelLow) {
    const chart = getKMChart(sub);
    chart.clear();
    chart.resize();

    const scores = data.map(d => d.score).sort((a, b) => a - b);
    const median = scores[Math.floor(scores.length / 2)];
    const highGroup = data.filter(d => d.score >= median);
    const lowGroup  = data.filter(d => d.score <  median);

    if (data.length < 4 || highGroup.length === 0 || lowGroup.length === 0) {
        chart.setOption({ title: { text: sub, subtext: '樣本數不足，無法分組', left: 'center', top: 'middle' } });
        return null;
    }

    // Log-rank 與 HR
    let oHigh = 0, eHigh = 0, vHigh = 0;
    const totalEvents = data.filter(d => d.status === 1).length;
    const allTimes = Array.from(new Set(data.map(d => d.time))).sort((a, b) => a - b);
    allTimes.forEach(t => {
        const dHigh = highGroup.filter(d => d.time === t && d.status === 1).length;
        const dLow  = lowGroup.filter(d => d.time === t && d.status === 1).length;
        const dTotal = dHigh + dLow;
        const nHigh = highGroup.filter(d => d.time >= t).length;
        const nLow  = lowGroup.filter(d => d.time >= t).length;
        const nTotal = nHigh + nLow;
        if (dTotal > 0 && nTotal > 1) {
            eHigh += (nHigh * dTotal) / nTotal;
            vHigh += (nHigh * nLow * dTotal * (nTotal - dTotal)) / (nTotal * nTotal * (nTotal - 1));
            oHigh += dHigh;
        }
    });
    const z = vHigh > 0 ? (oHigh - eHigh) / Math.sqrt(vHigh) : 0;
    const pValue = (2 * (1 - normalCDF(Math.abs(z)))).toFixed(4);
    const hr = (eHigh > 0 && (totalEvents - eHigh) > 0)
        ? ((oHigh / eHigh) / ((totalEvents - oHigh) / (totalEvents - eHigh))).toFixed(2)
        : "1.00";

    const nameH = `${labelHigh} (n=${highGroup.length})`;
    const nameL = `${labelLow} (n=${lowGroup.length})`;

    chart.setOption({
        title: {
            text: `${sub}：${labelHigh} vs. ${labelLow}`,
            subtext: `median: ${median.toFixed(3)} | Log-rank p = ${pValue} | HR = ${hr}`,
            left: 'center', textStyle: { fontSize: 15 }
        },
        tooltip: {
            trigger: 'axis',
            formatter: (params) => {
                let html = `<strong>存活天數：${params[0].value[0]} 天</strong><br/>`;
                params.forEach(p => { html += `${p.marker} ${p.seriesName}：${(p.value[1] * 100).toFixed(1)}%<br/>`; });
                return html;
            }
        },
        legend: { data: [nameH, nameL], bottom: 0 },
        grid: { left: '10%', right: '10%', bottom: '15%', containLabel: true },
        xAxis: { type: 'value', name: 'Overall Survival (Days)', nameLocation: 'middle', nameGap: 30,
                 splitLine: { lineStyle: { type: 'dashed', color: '#e9ecef' } } },
        yAxis: { type: 'value', name: 'Survival Probability', nameLocation: 'middle', nameGap: 45,
                 min: 0, max: 1.0, axisLabel: { formatter: v => `${v * 100}%` },
                 splitLine: { lineStyle: { type: 'dashed', color: '#e9ecef' } } },
        series: [
            { name: nameH, type: 'line', step: 'end', data: computeKM(highGroup),
              lineStyle: { width: 2.5, color: '#dc3545' }, itemStyle: { color: '#dc3545' } },
            { name: nameL, type: 'line', step: 'end', data: computeKM(lowGroup),
              lineStyle: { width: 2.5, color: '#0d6efd' }, itemStyle: { color: '#0d6efd' } }
        ]
    });
    return { sub, n: data.length, median, hr, pValue };
}

// 點「預後分析」：三個亞型各畫一張
function renderKMChart(cfg) {
    const kmLabel   = cfg.kmLabel || cfg.yName;
    const labelHigh = `${kmLabel}-High`;
    const labelLow  = `${kmLabel}-Low`;

    const allValid = dbHypoxiaData
        .filter(d => d.OS_Time !== null && d.OS_Status !== null && !isNaN(parseFloat(d.OS_Time))
                     && d[cfg.field] != null && !isNaN(parseFloat(d[cfg.field])))
        .map(d => ({
            id: d['Case ID'],
            subtype: d.MolecularSubtype,
            score: parseFloat(d[cfg.field]),
            time: parseFloat(d.OS_Time),
            status: parseInt(d.OS_Status, 10)
        }));

    if (allValid.length === 0) {
        alert("無可用於 KM 分析的臨床存活期數據！");
        return;
    }

    showView('km');   // 先顯示容器，ECharts 才量得到尺寸

    const results = kmSubtypes.map(sub =>
        drawKMForSubtype(sub, allValid.filter(d => d.subtype === sub), cfg, labelHigh, labelLow)
    ).filter(Boolean);

    document.getElementById('insight-text').innerHTML = results.map(r =>
        `<strong>${r.sub}</strong>（n=${r.n}，median: ${r.median.toFixed(3)}）：` +
        `HR = <strong>${r.hr}</strong>，Log-rank p = <strong>${r.pValue}</strong>`
    ).join('<br>');
}

// 常態分佈累積函數近似算法 (計算 Log-rank p-value 用)
function normalCDF(x) {
    const t = 1 / (1 + 0.2316419 * Math.abs(x));
    const d = 0.3989423 * Math.exp(-x * x / 2);
    const p = d * t * (0.3193815 + t * (-0.3565638 + t * (1.781478 + t * (-1.821256 + t * 1.330274))));
    return x > 0 ? 1 - p : p;
}

// 點擊「預後分析」按鈕時，切換為 KM 曲線圖
function updateChart() {
    const cfg = targetConfig[targetSelect.value];
    if (cfg && cfg.oncoSubtype) {          // Gene Mutation：單基因突變 KM
        renderMutationKM(cfg);
    } else if (cfg && cfg.field) {         // TME：原本的高低表現 KM
        renderKMChart(cfg);
    }
}

// 第一層切換：重建第二層後，也要更新圖表與模組
categorySelect.addEventListener("change", () => {
    updateTargetOptions();
    renderTarget();
});

// 第二層切換：直接更新散布圖 + 右側模組
targetSelect.addEventListener("change", renderTarget);

document.addEventListener("DOMContentLoaded", () => {
    updateTargetOptions();
    renderTarget();
});

// 監聽散布圖上的點擊事件，點擊後跳轉到病人個別頁面
myChart.on('click', function(params) {
    if (params.componentType === 'series' && params.seriesType === 'custom') {
        const caseId = params.value[3]; // 取出 Case ID (如 C1230738)
        if (caseId) {
            window.location.href = `/clinicaldata/${encodeURIComponent(caseId)}/`;
            // 如果希望在「新分頁」開啟，可改用下面這行：
            // window.open(`/clinicaldata/${encodeURIComponent(caseId)}/`, '_blank');
        }
    }
});

// 切換 Boxplot 顯示/隱藏（只更新 boxplot 那個 series，散布點不會重新抖動）
boxplotToggle.addEventListener('change', () => {
    myChart.setOption({
        series: [
            {},   // 第 0 個：散布點，不變
            { data: boxplotToggle.checked ? currentBoxplotData : [] }   // 第 1 個：boxplot
        ]
    });
});

// 視窗縮放時自動調整圖表大小
// window.addEventListener('resize', () => myChart.resize());
window.addEventListener('resize', () => {
    myChart.resize();
    Object.values(kmCharts).forEach(c => c.resize());
});