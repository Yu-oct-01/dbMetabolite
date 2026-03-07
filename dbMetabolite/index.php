<?php
// =============================================
// index.php  —  首頁
// =============================================
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metabolite Database - Home</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php include BASE_PATH . 'includes/navbar.php'; ?>

<div class="container">
    <!-- 公告 -->
    <div class="announcement">
        <strong>Breaking news:</strong> The database system version 2026 has been released!
    </div>

    <!-- 更新歷史 -->
    <div class="update-history">
        <h2>Recent Updates</h2>
        <div class="update-item">
            <h4>.....</h4>
            <p>。。。。。</p>
            <p class="time">Time: 6:00 PM, January 15, 2025</p>
        </div>
        <div class="update-item">
            <h4>.....</h4>
            <p>。。。。。</p>
            <p class="time">Time: 3:00 PM, December 20, 2024</p>
        </div>
    </div>

    <!-- 介紹 -->
    <div class="intro-section">
        <h2>Introduction of metabolites</h2>
        <p>
            Metabolites are small molecules produced or used by organisms during metabolism.<br>
            They originate from various chemical reactions within cells and are essential for maintaining life activities.
        </p>
        <p>
                Metabolites possess several important characteristics: <br>
                &emsp; 1.Small molecular size and diverse structures: <br>
                &emsp;&emsp;&emsp; Most are small molecule compounds, but their chemical structures vary greatly, such as carbohydrates, lipids, organic acids, and amino acids.<br>
                &emsp; 2.Highly dynamic: <br>
                &emsp;&emsp;&emsp; Metabolite concentrations change rapidly with environmental factors, nutrition, disease, stress, or time, reflecting physiological states in real time.<br>
                &emsp; 3.Closely related to physiological functions: <br>
                &emsp;&emsp;&emsp; They directly participate in energy production, substance synthesis, signal transduction, and the regulation of cellular activities.<br>
                &emsp; 4.Species and tissue specificity: <br>
                &emsp;&emsp;&emsp; The composition of metabolites may differ among different species, tissues, and even cell types.<br>
                &emsp; 5.Influenced by both genes and environment: <br>
                &emsp;&emsp;&emsp; Genes determine metabolic pathways, but diet, lifestyle, and the external environment also significantly affect metabolite expression.<br>
                &emsp; 6.Can serve as biomarkers: <br>
                &emsp;&emsp;&emsp; Some metabolites can be used for disease diagnosis, prognostic assessment, or tracking of treatment effectiveness.<br>
            </p>
    </div>
</div>

<?php include BASE_PATH . 'includes/footer.php'; ?>

<script src="js/main.js"></script>
</body>
</html>
