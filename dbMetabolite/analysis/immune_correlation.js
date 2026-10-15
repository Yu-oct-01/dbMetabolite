// 定義各類別對應的目標選項 (value 與顯示文字)
const targetOptionsMap = {
    "heterogeneity": [ // 腫瘤內異質性
        { value: "h1", text: "空間異質性與取樣位置" },
        { value: "h2", text: "時間異質性" },
    ],
    "mutation": [ // 基因體變異與體細胞突變
        { value: "g1", text: "酪胺酸激酶受體 (RTK) 異常" },
        { value: "g2", text: "NF1 缺失與失活" },
        { value: "g3", text: "PTEN 缺失" }
    ],
    "microenvironment": [ // 腫瘤微環境
        { value: "tme1", text: "低氧(Hypoxia）與代謝重編程 (Metabolic Reprogramming）" },
        { value: "tme2", text: "骨髓源性抑制細胞(MDSCs)、調節性 T 細胞（Tregs）" },
        { value: "tme3", text: "血管周圍 (Perivascular)" },
        { value: "tme4", text: "巨噬細胞/小膠質細胞(TAMs)" },
        { value: "tme5", text: "細胞外基質(ECM)" }
    ],
    "protective": [ // 系統性免疫屏障
        { value: "p1", text: "血腦屏障(BBB)/血腫瘤屏障(BTB)" },
        { value: "p2", text: "S1PR1內化" }
    ],
    "immunotherapy": [ // 免疫檢查點
        { value: "i1", text: "PD-1/PD-L1 通路" },
        { value: "i2", text: "CTLA-4 通路" }
    ],
    "clinical": [ // 臨床因素
        { value: "c1", text: "類固醇治療" },
        { value: "c2", text: "TMZ放化療" }
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
            { type: 'recurrence', url: '/analysis/tumor_recurrence.php?view=spatial', title: '空間取樣差異', desc: '比對不同取樣位置的亞型組成與代謝物差異。' },
            { type: 'immunity',   url: '/analysis/Immune_Cell_Correlation.php?view=spatial', title: '區域免疫浸潤', desc: '檢視各取樣區域的免疫細胞組成。' }
        ]
    },
    h2: {
        field: null, title: '腫瘤內異質性：時間異質性',
        modules: [
            { type: 'recurrence', url: '/analysis/tumor_recurrence.php?transition=PMT', title: 'Primary vs. Recurrent', desc: '追蹤復發前後亞型與代謝物的變化。' },
            { type: 'drug',       url: '/analysis/Drug_Response.php?stage=recurrent', title: '復發後治療反應', desc: '復發腫瘤對 TMZ 的反應差異。' }
        ]
    },
    g1: {
        field: null, title: '基因變異：RTK 異常 (EGFR / PDGFRA)',
        modules: [
            { type: 'drug', url: '/analysis/Drug_Response.php?driver=EGFR', title: 'EGFR 擴增與抑制劑反應', desc: 'Classical 亞型常見 EGFR 擴增，檢視藥物敏感性。' }
        ]
    },
    g2: {
        field: null, title: '基因變異：NF1 缺失與失活',
        modules: [
            { type: 'immunity', url: '/analysis/Immune_Cell_Correlation.php?subtype=MES&cell=TAMs', title: 'NF1 缺失與 TAMs 浸潤', desc: 'MES 亞型常伴隨 NF1 缺失與巨噬細胞富集。' },
            { type: 'drug',     url: '/analysis/Drug_Response.php?driver=NF1', title: 'NF1 與 TMZ 耐藥', desc: 'NF1 失活相關的化療抗性。' }
        ]
    },
    g3: {
        field: null, title: '基因變異：PTEN 缺失',
        modules: [
            { type: 'drug', url: '/analysis/Drug_Response.php?driver=EGFR_PTEN', title: 'PTEN 缺失與 TMZ 耐藥', desc: 'PTEN 缺失樣本的存活差異與代謝易感性。' }
        ]
    },
    tme1: {
        field: 'HALLMARK_HYPOXIA',
        title: 'Hypoxia scatter plot across different molecular subtypes',
        yName: 'HALLMARK_HYPOXIA', yMin: 0.6,
        insight: `
            <div class="fw-bold text-dark mb-2">PMT（Proneural-to-Mesenchymal Transition）機制解析：</div>
            <ul class="mb-0 ps-3 lh-base" style="font-size: 0.9rem;">
                <li class="mb-2">
                    <strong class="text-primary">淘汰 Proneural：</strong>
                    Proneural 對缺氧與酸中毒環境耐受力差，在低氧核心區會被選擇性清除或被迫改變表型。
                </li>
                <li class="mb-2">
                    <strong class="text-primary">誘導間質化（Mesenchymal）：</strong>
                    組織極度缺氧使 HIF-1α 蛋白穩定入核，直接激活間質核心轉錄因子 C/EBPβ 與 STAT3，同時抑制神經前驅標記 OLIG2 與 SOX2。
                </li>
                <li>
                    <strong class="text-primary">酸化解離 TGF-β 固化間質表型：</strong>
                    細胞因代謝重編程轉向劇烈的無氧糖解（Warburg Effect），透過 MCT4 外排大量乳酸使組織酸化（pH 6.0–6.5）。強酸微環境會物理性解離細胞外潛伏的 TGF-β 複合物，釋放高活性游離 TGF-β，進一步全面解鎖間質超級增強子，使腫瘤細胞錨定為高侵襲性的 Mesenchymal。
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
            { type: 'immunity', url: '/analysis/Immune_Cell_Correlation.php?cell=MDSC_Treg', title: 'MDSCs / Tregs 浸潤', desc: '免疫抑制細胞與代謝物表現量的關聯。' }
        ]
    },
    tme3: {
        field: null, title: '腫瘤微環境：血管周圍 (Perivascular)',
        modules: [
            { type: 'subtypes', url: '/analysis/hub_subtypes.php?from=perivascular', title: '血管周圍與亞型關聯', desc: '血管周圍區域的亞型分布。' },
            { type: 'drug',    url: '/analysis/Drug_Response.php?niche=perivascular', title: '血管周圍微環境與藥物', desc: '血管周圍龕位的治療抗性。' }
        ]
    },
    tme4: {
        field: null, title: '腫瘤微環境：巨噬細胞 / 小膠質細胞 (TAMs)',
        modules: [
            { type: 'immunity', url: '/analysis/Immune_Cell_Correlation.php?subtype=MES&cell=TAMs', title: 'MES 亞型與 TAMs', desc: 'M2 型巨噬細胞與免疫檢查點表現量。' }
        ]
    },
    tme5: {
        field: null, title: '腫瘤微環境：細胞外基質 (ECM)',
        modules: [
            { type: 'recurrence', url: '/analysis/tumor_recurrence.php?transition=PMT', title: 'ECM 與間質化轉化', desc: 'ECM 重塑與 PMT 轉化的關係。' },
            { type: 'immunity',   url: '/analysis/Immune_Cell_Correlation.php?view=ecm', title: 'ECM 與免疫浸潤', desc: '基質硬度與免疫細胞排除。' }
        ]
    },
    p1: {
        field: null, title: '表型可塑性：動態細胞狀態',
        modules: [
            { type: 'recurrence', url: '/analysis/tumor_recurrence.php?transition=PMT', title: 'PMT 轉化', desc: 'Proneural 轉 Mesenchymal 的樣本比對。' },
            { type: 'subtypes',    url: '/analysis/hub_hypoxia.php?from=subtypes', title: '缺氧與間質表型可塑性', desc: '檢視 HIF-1α 與缺氧分數關聯。' }
        ]
    },
    p2: {
        field: null, title: '表型可塑性：動態細胞狀態',
        modules: [
            { type: 'recurrence', url: '/analysis/tumor_recurrence.php?transition=PMT', title: 'PMT 轉化', desc: 'Proneural 轉 Mesenchymal 的樣本比對。' },
            { type: 'subtypes',    url: '/analysis/hub_hypoxia.php?from=subtypes', title: '缺氧與間質表型可塑性', desc: '檢視 HIF-1α 與缺氧分數關聯。' }
        ]
    },
    i1: {
        field: null, title: '表型可塑性：動態細胞狀態',
        modules: [
            { type: 'recurrence', url: '/analysis/tumor_recurrence.php?transition=PMT', title: 'PMT 轉化', desc: 'Proneural 轉 Mesenchymal 的樣本比對。' },
            { type: 'subtypes',    url: '/analysis/hub_hypoxia.php?from=subtypes', title: '缺氧與間質表型可塑性', desc: '檢視 HIF-1α 與缺氧分數關聯。' }
        ]
    },
    i2: {
        field: null, title: '表型可塑性：動態細胞狀態',
        modules: [
            { type: 'recurrence', url: '/analysis/tumor_recurrence.php?transition=PMT', title: 'PMT 轉化', desc: 'Proneural 轉 Mesenchymal 的樣本比對。' },
            { type: 'subtypes',    url: '/analysis/hub_hypoxia.php?from=subtypes', title: '缺氧與間質表型可塑性', desc: '檢視 HIF-1α 與缺氧分數關聯。' }
        ]
    },
    c1: {
        field: null, title: '表型可塑性：動態細胞狀態',
        modules: [
            { type: 'recurrence', url: '/analysis/tumor_recurrence.php?transition=PMT', title: 'PMT 轉化', desc: 'Proneural 轉 Mesenchymal 的樣本比對。' },
            { type: 'subtypes',    url: '/analysis/hub_hypoxia.php?from=subtypes', title: '缺氧與間質表型可塑性', desc: '檢視 HIF-1α 與缺氧分數關聯。' }
        ]
    },
    c2: {
        field: null, title: '表型可塑性：動態細胞狀態',
        modules: [
            { type: 'recurrence', url: '/analysis/tumor_recurrence.php?transition=PMT', title: 'PMT 轉化', desc: 'Proneural 轉 Mesenchymal 的樣本比對。' },
            { type: 'subtypes',    url: '/analysis/hub_hypoxia.php?from=subtypes', title: '缺氧與間質表型可塑性', desc: '檢視 HIF-1α 與缺氧分數關聯。' }
        ]
    },
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

// // 綁定類別切換事件
// categorySelect.addEventListener("change", updateTargetOptions);

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
                const [, score, group, id] = params.data;
                return `<strong>${id}</strong><br/>` +
                       `分子亞型：${group}<br/>` +
                       `${cfg.yName}：<strong>${score.toFixed(4)}</strong>`;
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
                name: '樣本低氧分數',
                type: 'custom',
                renderItem: (params, api) => {
                    const colors = ['#0d6efd', '#ffc107', '#dc3545']; // PN: 藍, CL: 黃, MES: 紅
                    const coord = api.coord([api.value(0), api.value(1)]);
                    const jitterOffset = api.value(4);
                    const groupIndex = api.value(0);

                    return {
                        type: 'circle',
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
            }
        ]
    };

    myChart.setOption(option);
}

// 選單切換時的統一入口：圖表 + 右側模組 + 洞察文字
function renderTarget() {
    const cfg = targetConfig[targetSelect.value];
    if (!cfg) return;
    if (cfg.field) renderSubtypeChart(cfg); else renderPlaceholder(cfg);
    renderModules(cfg);
    document.getElementById('insight-text').innerHTML = cfg.insight || '此目標的統計觀察待補充。';
}

// 計算 KM 階梯存活曲線與統計指標
function renderKMChart() {
    // 1. 篩選有完整存活資料的樣本並排序
    const validData = dbHypoxiaData
        .filter(d => d.OS_Time !== null && d.OS_Status !== null && !isNaN(parseFloat(d.OS_Time)))
        .map(d => ({
            id: d['Case ID'],
            hypoxia: parseFloat(d.HALLMARK_HYPOXIA),
            time: parseFloat(d.OS_Time),
            status: parseInt(d.OS_Status, 10)
        }));

    if (validData.length === 0) {
        alert("無可用於 KM 分析的臨床存活期數據！");
        return;
    }

    // 2. 取中位數分為 Hypoxia-High 與 Hypoxia-Low 兩組
    const scores = validData.map(d => d.hypoxia).sort((a, b) => a - b);
    const median = scores[Math.floor(scores.length / 2)];

    const highGroup = validData.filter(d => d.hypoxia >= median);
    const lowGroup = validData.filter(d => d.hypoxia < median);

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
            text: '整體存活期 Kaplan-Meier 分析 (Hypoxia-High vs. Hypoxia-Low)',
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
            data: [`Hypoxia-High (n=${highGroup.length})`, `Hypoxia-Low (n=${lowGroup.length})`],
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
                name: `Hypoxia-High (n=${highGroup.length})`,
                type: 'line',
                step: 'end',
                data: kmHigh,
                lineStyle: { width: 2.5, color: '#dc3545' },
                itemStyle: { color: '#dc3545' }
            },
            {
                name: `Hypoxia-Low (n=${lowGroup.length})`,
                type: 'line',
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
        `以低氧中位數（${median.toFixed(3)}）分組，Hypoxia-High 組在整體存活期（OS）之預後表現與 Low 組相比（HR: <strong>${hr}</strong>, Log-rank p-value: <strong>${pValue}</strong>）。`;
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
    const strat = targetSelect.value;
    if (strat === "tme1") {
        renderKMChart(); // 跑出 KM 圖
    }
}

// // 當第二層選單切換時，自動顯示散布圖
// targetSelect.addEventListener("change", () => {
//     if (targetSelect.value === "tme1") {
//         myChart.clear();
//         renderSubtypeChart(); // 切換時自動切回散布圖
//     }
// });

// // 頁面載入完成時先同步選單，並直接呈現散布圖
// document.addEventListener("DOMContentLoaded", () => {
//     updateTargetOptions();
//     renderSubtypeChart();
// });

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

// 視窗縮放時自動調整圖表大小
window.addEventListener('resize', () => myChart.resize());