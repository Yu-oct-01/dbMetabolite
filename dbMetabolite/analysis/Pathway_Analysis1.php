<?php
// =============================================
// analysis/Pathway_Analysis.php  —  分析頁
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
    </style>
</head>
<body>

<?php include BASE_PATH . 'includes/navbar.php'; ?>

<main class="analysis-container">
    <header class="page-header">
        <h1 class="page-title">CD8+ Tumor Correlation Analysis</h1>
    </header>

    <div class="card">
        <div class="image-wrapper">
            <?php
                $imageName = "analysis_result/Figure_11_CD8plus_tumor_surrounding_genes_Correlations_num2.png";
                echo '<img src="' . $imageName . '" alt="CD8+ Tumor Surrounding Genes Correlation Heatmap Matrix" class="analysis-img">';
            ?>
        </div>

        <section class="caption-section">
            <div class="caption-header">
                <h2 class="figure-title">Spearman Correlation Analysis between CD8+ T Cells and Polyamine Pathway Genes</h2>
                <div class="tag-group">
                    <span class="tag">CD8+ T Cell Infiltration</span>
                    <span class="tag">Oncometabolite</span>
                </div>
            </div>

            <p class="caption-body">
                Spearman correlation analysis was conducted to investigate the associations between genes involved in the polyamine pathway and CD8+ T cells.
                ODC1, SRM, and SMS exhibited <strong>strong co-expression</strong>, reflecting coordinated activation of the polyamine biosynthetic program. 
                In contrast, SMOX was negatively correlated with ODC1, suggesting the existence of a <strong>metabolic balance or reciprocal regulatory mechanism</strong> between polyamine biosynthesis and catabolism. 
                CD8+ T-cell infiltration was negatively correlated with SRM, ODC1, and SMOX. 
                Since ODC1, SRM, and SMOX are involved in the production and metabolism of spermidine, these findings suggest that elevated spermidine levels within the tumor microenvironment may <strong>suppress CD8+ T-cell infiltration</strong>.
            </p>

            <div class="key-points">
                <div class="point-item">
                    <div class="point-label">Biosynthesis Co-expression</div>
                    <div class="point-val">ODC1 vs. SRM (r = 0.32)<br>ODC1 vs. SMS (r = 0.28)</div>
                </div>
                <div class="point-item">
                    <div class="point-label">Metabolic Balance</div>
                    <div class="point-val">ODC1 vs. SMOX <br>(r = -0.26)</div>
                </div>
                <div class="point-item">
                    <div class="point-label">T-cell Suppression</div>
                    <div class="point-val">CD8+ T vs. SMOX (r = -0.17)<br>CD8+ T vs. ODC1 (r = -0.06)<br>CD8+ T vs. SRM (r = -0.04)</div>
                </div>
            </div>
        </section>
    </div>
</main>

<?php include BASE_PATH . 'includes/footer.php'; ?>

</body>
</html>