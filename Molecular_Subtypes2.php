<?php
// =============================================
// analysis/Molecular_Subtypes.php  —  分析頁
// =============================================
require_once '../config.php';
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metabolite Database - Analysis</title>
    <link rel="stylesheet" href="../../css/style.css">
    <style>
        #metab-tabs {
            display: flex;
            gap: 32px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }

        #metab-tabs .tab {
            background: none;
            border: none;
            padding: 12px 0;
            font-size: 0.95rem;
            color: #718096;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px; /* 蓋住外層的 border-bottom */
        }

        #metab-tabs .tab.active {
            color: #1a202c;
            font-weight: 700;
            border-bottom-color: #1a202c;
        }

        #metab-tabs .tab:hover {
            color: #2d3748;
        }

        .analysis-container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #2c3e50;
        }

        .page-header {
            margin-bottom: 24px;
            border-bottom: 2px solid #edf2f7;
            padding-bottom: 16px;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1a202c;
            margin: 0 0 8px 0;
        }

        .page-subtitle {
            color: #718096;
            font-size: 0.95rem;
            margin: 0;
        }

        .card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 30px;
        }

        .image-wrapper {
            background-color: #fafbfc;
            padding: 24px;
            text-align: center;
            border-bottom: 1px solid #edf2f7;
        }

        .analysis-img {
            max-width: 100%;
            height: auto;
            border-radius: 6px;
            display: inline-block;
        }

        .caption-section {
            padding: 28px 32px;
        }

        .caption-header {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            margin-bottom: 16px;
            gap: 8px;
        }

        .figure-title {
            font-size: 1.15rem;
            font-weight: 600;
            color: #2d3748;
            margin: 0;
        }

        .tag-group {
            display: flex;
            align-items: center;
            gap: 8px; /* 標籤之間的間距 */
            flex-wrap: wrap;
        }

        .tag {
            background-color: #ebf8ff;
            color: #3182ce;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .caption-body {
            font-size: 0.95rem;
            line-height: 1.7;
            color: #4a5568;
            margin-bottom: 20px;
        }

        .key-points {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px dashed #e2e8f0;
        }

        .point-item {
            background: #f7fafc;
            padding: 12px 16px;
            border-radius: 8px;
            border-left: 4px solid #3182ce;
        }

        .point-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #a0aec0;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .point-val {
            font-size: 0.95rem;
            font-weight: 600;
            color: #2d3748;
        }

        .tab-panel {
            display: none;
        }

        .tab-panel.active {
            display: block;
        }

        .empty-panel {
            background: #ffffff;
            border-radius: 12px;
            border: 1px dashed #e2e8f0;
            padding: 60px 20px;
            text-align: center;
            color: #a0aec0;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>

<?php include BASE_PATH . 'includes/navbar.php'; ?>

<main class="analysis-container">

    <nav class="nav-tabs" id="metab-tabs">
        <button class="tab" data-target="sec-identification">Browse</button>
        <button class="tab active" data-target="sec-molecular">Analysis</button>
        <button class="tab"        data-target="sec-prognosis">References</button>
    </nav>

    <!-- Browse：先留空 -->
    <section id="sec-identification" class="tab-panel">
        <div class="empty-panel">Browse 內容尚未加入</div>
    </section>

    <!-- Analysis：目前的內容 -->
    <section id="sec-molecular" class="tab-panel active">
        <h1 class="page-title">GSVA Score Analysis</h1>
        <div class="card">
            <div class="image-wrapper">
                <?php
                    $imageName = "analysis_result//Figure_6_boxplot_mesenchymal.png";
                    echo '<img src="' . $imageName . '" alt="GSVA Mesenchymal Subtype Boxplot" class="analysis-img">';
                ?>
            </div>

            <section class="caption-section">
                <div class="caption-header">
                    <h2 class="figure-title">Distribution of GSVA Scores for the Three Molecular Subtypes Among Samples Classified as Mesenchymal</h2>
                    <div class="tag-group">
                        <span class="tag">GSVA Score</span>
                    </div>
                </div>

                <p class="caption-body">
                    As illustrated in the boxplots, samples classified as the Mesenchymal subtype exhibited the highest Mesenchymal GSVA enrichment scores. 
                    Nevertheless, samples assigned to the Classical and Proneural subtypes also displayed long-tailed distributions, indicating considerable variation in subtype enrichment. 
                    These extended distributions likely reflect the <strong>intrinsic intratumoral heterogeneity</strong> of GBM and may represent transitional transcriptional states associated with malignant evolution.
                </p>

                <div class="key-points">
                    <div class="point-item">
                        <div class="point-label">Highest Score</div>
                        <div class="point-val">Mesenchymal (~0.44)</div>
                    </div>
                    <div class="point-item">
                        <div class="point-label">Intermediate</div>
                        <div class="point-val">Classical (~0.38)</div>
                    </div>
                    <div class="point-item">
                        <div class="point-label">Lowest Score</div>
                        <div class="point-val">Proneural (~0.33)</div>
                    </div>
                </div>
            </section>
        </div>
    </section>

    <!-- References：先留空 -->
    <section id="sec-prognosis" class="tab-panel">
        <div class="empty-panel">References 內容尚未加入</div>
    </section>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tabs = document.querySelectorAll('#metab-tabs .tab');
        const panels = document.querySelectorAll('.tab-panel');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                const targetId = tab.getAttribute('data-target');

                tabs.forEach(function (t) { t.classList.remove('active'); });
                tab.classList.add('active');

                panels.forEach(function (panel) {
                    panel.classList.toggle('active', panel.id === targetId);
                });
            });
        });
    });
</script>

<?php include BASE_PATH . 'includes/footer.php'; ?>

</body>
</html>