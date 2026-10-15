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
        { value: "g1", text: "酪胺酸激酶受體 (RTK) 異常" },
        { value: "g2", text: "NF1 缺失與失活" },
        { value: "g3", text: "PTEN 缺失" }
    ],
    "microenvironment": [ // 腫瘤微環境
        { value: "tme1", text: "缺氧(Hypoxia）" },
        { value: "tme2", text: "骨髓源性抑制細胞(MDSCs)、調節性 T 細胞（Tregs）" },
        { value: "tme3", text: "血管周圍 (Perivascular)" },
        { value: "tme4", text: "巨噬細胞/小膠質細胞(TAMs)" },
        { value: "tme5", text: "細胞外基質(ECM)" }
    ]
};

const categorySelect = document.getElementById("metabolite-select");
const targetSelect = document.getElementById("strat-select");

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
    g1: {
        field: null, title: '基因變異：RTK 異常 (EGFR / PDGFRA)',
        modules: [
            { type: 'drug', url: '/analysis/Drug_Response.php?driver=EGFR', title: '...', desc: '。。。' }
        ]
    },
    g2: {
        field: null, title: '基因變異：NF1 缺失與失活',
        modules: [
            { type: 'immunity', url: '/analysis/Immune_Cell_Correlation.php?subtype=MES&cell=TAMs', title: '...', desc: '。。。' },
            { type: 'drug',     url: '/analysis/Drug_Response.php?driver=NF1', title: '...', desc: '。。。' }
        ]
    },
    g3: {
        field: null, title: '基因變異：PTEN 缺失',
        modules: [
            { type: 'drug', url: '/analysis/Drug_Response.php?driver=EGFR_PTEN', title: '...', desc: '。。。' }
        ]
    },
    tme1: {
        field: 'HALLMARK_HYPOXIA',
        title: 'Hypoxia scatter plot across different molecular subtypes',
        yName: 'HALLMARK_HYPOXIA', yMin: 0.6,
        kmLabel: 'Hypoxia',
        insight: `
            <ul class="mb-0 ps-3 lh-base" style="font-size: 0.9rem;">
                <li class="mb-2">
                    <strong class="text-primary">Mesenchymal 亞型整體處於高缺氧狀態：</strong>
                    圖中 Mesenchymal 亞型的 HALLMARK_HYPOXIA 分數幾乎全數集中在 0.88 以上的高區間，整體中位數與下限明顯高於其他兩組，證實 Mesenchymal 亞型與低氧微環境有極高關聯。
                </li>
                <li class="mb-2">
                    <strong class="text-primary">Proneural 亞型低氧分布最廣且下限最低：</strong>
                    Proneural 亞型有較多樣本分布在 0.70～0.85 的較低缺氧區間，符合「典型的 Proneural 腫瘤常處於較低缺氧或對極端低氧承受力較弱」的宏觀趨勢。
                </li>
            </ul>
        `,
        modules: [
            { type: 'immunity', url: '/analysis/immune_correlation.php?subtype=MES&cell=TAMs', title: '低氧與免疫抑制', desc: '低氧抑制免疫細胞活性，並促進免疫抑制微環境的形成。' },
            { type: 'drug',     url: '/analysis/Drug_Response.php?driver=EGFR_PTEN', title: '低氧與藥物耐受性', desc: '低氧區域缺乏氧分子作為放射敏化劑，放療破壞力大幅降低。' },
            { type: 'recurrence', url: '/analysis/tumor_recurrence.php?transition=PMT', title: '低氧與腫瘤再生', desc: '低氧能維持 GSCs 處於低增殖但高耐受的狀態，在放化療結束後重新甦醒並啟動腫瘤再生。' }
        ]
    },
    tme2: {
        field: null, title: '腫瘤微環境：MDSCs / Tregs',
        modules: [
            { type: 'immunity', url: '/analysis/Immune_Cell_Correlation.php?cell=MDSC_Treg', title: '...', desc: '。。。' }
        ]
    },
    tme3: {
        field: null, title: '腫瘤微環境：血管周圍 (Perivascular)',
        modules: [
            { type: 'subtypes', url: '/analysis/hub_subtypes.php?from=perivascular', title: '...', desc: '。。。'},
            { type: 'drug',    url: '/analysis/Drug_Response.php?niche=perivascular', title: '...', desc: '。。。' }
        ]
    },
    tme4: {
        field: null, title: '腫瘤微環境：巨噬細胞 / 小膠質細胞 (TAMs)',
        modules: [
            { type: 'immunity', url: '/analysis/Immune_Cell_Correlation.php?subtype=MES&cell=TAMs', title: '...', desc: '。。。' }
        ]
    },
    tme5: {
        field: 'stroma score',
        title: 'Stroma score scatter plot across different molecular subtypes',
        yName: 'Stroma score', yMin: -0.03,
        kmLabel: 'Stroma',
        insight: `
            <ul class="mb-0 ps-3 lh-base" style="font-size: 0.9rem;">
                <li class="mb-2">
                    <strong class="text-primary">Mesenchymal 亞型整體偏向擁有較高基質分數：</strong>
                    圖中 Mesenchymal 亞型的點的分佈在方較為分散，中高分數（0.04 至 0.12）的樣本佔比明顯高於 Proneural，整體重心中位數在三者中偏高，能推測與較高的發炎反應、血管新生及基質細胞浸潤相關。
                </li>
                <li class="mb-2">
                    <strong class="text-primary">Proneural 亞型整體中位數在三組中偏低：</strong>
                    Proneural 亞型大部分樣本集中在低分數區間（約 0.00 至 0.04），可能代表較為內因性、神經元前驅特徵的腫瘤。
                </li>
            </ul>
        `,
        modules: [
            { type: 'immunity',   url: '/analysis/Immune_Cell_Correlation.php?view=ecm', title: '腫瘤與活化基質細胞', desc: '厚重的基質屏障使 CD8+ T 細胞在穿透基質時受到物理阻礙，大多被困在腫瘤外圍基質帶。' },
            { type: 'drug',       url: '/analysis/Drug_Response.php?driver=EGFR_PTEN', title: '化療遞送屏障與放化療耐受', desc: '高 IFP 阻礙了小分子化療藥物的有效擴散與穿透，而細胞黏附介導的耐藥性會上調抗凋亡蛋白，並賦予細胞抵抗失巢凋亡（Anoikis）的能力' },
            { type: 'recurrence', url: '/analysis/tumor_recurrence.php?transition=PMT', title: '細胞外基質與腫瘤幹細胞', desc: '特殊的 ECM 微結構為腫瘤幹細胞提供了穩定的物理庇護。' }
        ]
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
}

// 初始化 ECharts 圖表
const chartDom = document.getElementById('subtype-chart');
const myChart = echarts.init(chartDom);

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
                    return `<strong>${params.name} 亞型統計</strong><br/>` +
                           `最大值 (Max): ${max.toFixed(4)}<br/>` +
                           `上四分位數 (Q3): ${q3.toFixed(4)}<br/>` +
                           `中位數 (Median): ${median.toFixed(4)}<br/>` +
                           `下四分位數 (Q1): ${q1.toFixed(4)}<br/>` +
                           `最小值 (Min): ${min.toFixed(4)}`;
                } else if (params.seriesType === 'custom') {
                    const [, score, group, id] = params.data;
                    return `<strong>${id}</strong><br/>` +
                           `分子亞型：${group}<br/>` +
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
                data: boxplotData,
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

// 計算 KM 階梯存活曲線與統計指標
function renderKMChart(cfg) {
    const kmLabel  = cfg.kmLabel || cfg.yName;
    const labelHigh = `${kmLabel}-High`;
    const labelLow  = `${kmLabel}-Low`;

    // 1. 篩選有完整存活資料的樣本並排序
    const validData = dbHypoxiaData
        .filter(d => d.OS_Time !== null && d.OS_Status !== null && !isNaN(parseFloat(d.OS_Time))
                     && d[cfg.field] != null && !isNaN(parseFloat(d[cfg.field])))
        .map(d => ({
            id: d['Case ID'],
            score: parseFloat(d[cfg.field]),
            time: parseFloat(d.OS_Time),
            status: parseInt(d.OS_Status, 10)
        }));

    if (validData.length === 0) {
        alert("無可用於 KM 分析的臨床存活期數據！");
        return;
    }

    // 2. 取中位數分為 Hypoxia-High 與 Hypoxia-Low 兩組
    const scores = validData.map(d => d.score).sort((a, b) => a - b);
    const median = scores[Math.floor(scores.length / 2)];

    const highGroup = validData.filter(d => d.score >= median);
    const lowGroup = validData.filter(d => d.score < median);

    // 3. Kaplan-Meier 存活率計算函式
    function computeKM(group) {
        group.sort((a, b) => a.time - b.time);
        let n = group.length;
        let survival = 1.0;
        const curve = [[0, 1.0]];

        const timePoints = {};
        group.forEach(item => {
            if (!timePoints[item.time]) timePoints[item.time] = { events: 0, total: 0 };
            timePoints[item.time].total++;
            if (item.status === 1) timePoints[item.time].events++;
        });

        const times = Object.keys(timePoints).map(Number).sort((a, b) => a - b);
        let atRisk = n;
        times.forEach(t => {
            const { events, total } = timePoints[t];
            if (events > 0 && atRisk > 0) {
                survival *= (1 - events / atRisk);
            }
            curve.push([t, parseFloat(survival.toFixed(4))]);
            atRisk -= total;
        });
        return curve;
    }

    const kmHigh = computeKM(highGroup);
    const kmLow = computeKM(lowGroup);

    // 4. Log-rank 檢定與 HR 近似估算
    let oHigh = 0, eHigh = 0, vHigh = 0;
    const allTimes = Array.from(new Set(validData.map(d => d.time))).sort((a, b) => a - b);

    allTimes.forEach(t => {
        const dHigh = highGroup.filter(d => d.time === t && d.status === 1).length;
        const dLow = lowGroup.filter(d => d.time === t && d.status === 1).length;
        const dTotal = dHigh + dLow;

        const nHigh = highGroup.filter(d => d.time >= t).length;
        const nLow = lowGroup.filter(d => d.time >= t).length;
        const nTotal = nHigh + nLow;

        if (dTotal > 0 && nTotal > 1) {
            const e = (nHigh * dTotal) / nTotal;
            const v = (nHigh * nLow * dTotal * (nTotal - dTotal)) / (nTotal * nTotal * (nTotal - 1));
            oHigh += dHigh;
            eHigh += e;
            vHigh += v;
        }
    });

    const zScore = vHigh > 0 ? (oHigh - eHigh) / Math.sqrt(vHigh) : 0;
    const pValue = (2 * (1 - normalCDF(Math.abs(zScore)))).toFixed(4);
    const hr = (eHigh > 0 && (validData.filter(d => d.status === 1).length - eHigh) > 0)
        ? ((oHigh / eHigh) / ((validData.filter(d => d.status === 1).length - oHigh) / (validData.filter(d => d.status === 1).length - eHigh))).toFixed(2)
        : "1.00";

    // 5. 繪製 ECharts KM 曲線圖
    myChart.clear();
    const option = {
        title: {
            text: `整體存活期 Kaplan-Meier 分析 (${labelHigh} vs. ${labelLow})`,
            subtext: `分組依據：中位數 (${median.toFixed(3)}) | Log-rank p = ${pValue} | Hazard Ratio (HR) = ${hr}`,
            left: 'center',
            textStyle: { fontSize: 15 }
        },
        tooltip: {
            trigger: 'axis',
            formatter: (params) => {
                let html = `<strong>存活天數：${params[0].value[0]} 天</strong><br/>`;
                params.forEach(p => {
                    html += `${p.marker} ${p.seriesName}：${(p.value[1] * 100).toFixed(1)}%<br/>`;
                });
                return html;
            }
        },
        legend: {
            data: [`${labelHigh} (n=${highGroup.length})`, `${labelLow} (n=${lowGroup.length})`],
            bottom: 0
        },
        grid: { left: '10%', right: '10%', bottom: '15%', containLabel: true },
        xAxis: {
            type: 'value',
            name: '整體存活時間 (Overall Survival, Days)',
            nameLocation: 'middle',
            nameGap: 30,
            splitLine: { lineStyle: { type: 'dashed', color: '#e9ecef' } }
        },
        yAxis: {
            type: 'value',
            name: '存活機率 (Survival Probability)',
            nameLocation: 'middle',
            nameGap: 45,
            min: 0,
            max: 1.0,
            axisLabel: { formatter: (val) => `${val * 100}%` },
            splitLine: { lineStyle: { type: 'dashed', color: '#e9ecef' } }
        },
        series: [
            {
                name: `${labelHigh} (n=${highGroup.length})`,
                type: 'line',
                step: 'end',
                data: kmHigh,
                lineStyle: { width: 2.5, color: '#dc3545' },
                itemStyle: { color: '#dc3545' }
            },
            {
                name: `${labelLow} (n=${lowGroup.length})`,                type: 'line',
                step: 'end',
                data: kmLow,
                lineStyle: { width: 2.5, color: '#0d6efd' },
                itemStyle: { color: '#0d6efd' }
            }
        ]
    };
    myChart.setOption(option);

    // 更新下方洞察文字
    document.getElementById("insight-text").innerHTML = 
        `以 ${cfg.yName} 中位數（${median.toFixed(3)}）分組，${labelHigh} 組在整體存活期（OS）之預後表現與 Low 組相比（HR: <strong>${hr}</strong>, Log-rank p-value: <strong>${pValue}</strong>）。`;}

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
    if (cfg && cfg.field) {
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

// 視窗縮放時自動調整圖表大小
window.addEventListener('resize', () => myChart.resize());