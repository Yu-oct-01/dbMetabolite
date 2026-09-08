<?php
// =============================================
// analysis/Stemness Score.php  —  分析頁
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
        <h1 class="page-title">Stemness Score Analysis</h1>
    </header>

    <div class="card">
        <div class="image-wrapper">
            <?php
                $imageName = "analysis_result/Figure_7_stemness_score_Correlations_spearman.png";
                echo '<img src="' . $imageName . '" alt="Stemness Score Correlation Heatmap Matrix" class="analysis-img">';
            ?>
        </div>

        <section class="caption-section">
            <div class="caption-header">
                <h2 class="figure-title">Spearman Correlation Matrix of Stemness Gene Signatures</h2>
                <div class="tag-group">
                    <span class="tag">Stemness Score</span>
                </div>
            </div>

            <p class="caption-body">
                Spearman correlation analysis was performed to evaluate relationships among different stemness scores. 
                The results demonstrated that <strong>BENPORATH_ES_1</strong>, <strong>BENPORATH_ES_2</strong>, and <strong>WONG_EMBRYONIC_STEM_CELL_CORE</strong> were highly correlated, indicating that these gene sets capture similar stemness characteristics. 
                Overall, the correlation patterns suggested the presence of two distinct biological dimensions of tumor stemness.
            </p>

            <div class="key-points">
                <div class="point-item">
                    <div class="point-label">Highest Correlation</div>
                    <div class="point-val">Wong Core vs. Benporath ES 1/2 <br>(r = 0.90)</div>
                </div>
                <div class="point-item">
                    <div class="point-label">ES-like Cluster</div>
                    <div class="point-val">Benporath ES 1 vs. ES 2 <br>(r = 0.79)</div>
                </div>
                <div class="point-item">
                    <div class="point-label">Distinct Dimension</div>
                    <div class="point-val">Ivanova HSC vs. Benporath ES 1/2 <br>(r ≈ 0)</div>
                </div>
            </div>
        </section>
    </div>
</main>

<?php include BASE_PATH . 'includes/footer.php'; ?>

</body>
</html>