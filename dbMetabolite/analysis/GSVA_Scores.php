<?php
// =============================================
// analysis/GSVA Scores.php  —  分析頁
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
</head>
<body>

<?php include BASE_PATH . 'includes/navbar.php'; ?>

    <?php
        $imageName = "Figure_6_boxplot_mesenchymal.png";
        echo '<img src="' . $imageName . '" alt="動態圖片" width="1050">';
    ?>

<?php include BASE_PATH . 'includes/footer.php'; ?>

</body>

