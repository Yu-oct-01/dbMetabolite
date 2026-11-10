<?php
// analysis/Maximum_Expression.php
include_once __DIR__ . '/../config.php';
include_once __DIR__ . '/../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maximum Expression Analysis</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/expression.css">
    <script src="https://cdn.plot.ly/plotly-2.35.2.min.js"></script>

    <style>
        /* 捲軸容器設定 */
        .chart-scroll-wrapper {
            width: 100%;
            max-width: 1150px;       /* 剛好呈現約 20 個代謝物的視窗寬度 */
            margin: 20px auto;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow-x: auto;        /* 產生水平滑桿 */
            overflow-y: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        /* 美化滑桿樣式（類似 macOS / 現代瀏覽器風格） */
        .chart-scroll-wrapper::-webkit-scrollbar {
            height: 10px;
        }
        .chart-scroll-wrapper::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 5px;
        }
        .chart-scroll-wrapper::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 5px;
        }
        .chart-scroll-wrapper::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }
        .btn-back {
            background: #95a5a6;
            color: white;
            border: none;
            padding: 0.5rem 1.2rem;
            border-radius: 4px;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
            white-space: nowrap;
        }

        .btn-back:hover {
            background: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="container" style="max-width: 1200px; margin: 30px auto; padding: 0 20px;">
        <!-- 頁首標題與按鈕容器 -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
            <!-- 左側按鈕區（留有左邊距與固定寬度） -->
            <div style="flex: 1; text-align: left;">
                <a href="javascript:history.back();" class="btn-back">
                    <i class="fa-solid fa-arrow-left me-1"></i>← Previous page
                </a>
            </div>

            <!-- 中間標題區（絕對置中） -->
            <div style="flex: 2; text-align: center;">
                <h2 style="margin: 0;">Metabolite Maximum Expression</h2>
            </div>

            <!-- 右側等寬平衡區 -->
            <div style="flex: 1;"></div>
        </div>

        <!-- 註解文字置中 -->
        <p style="color: #64748b; margin-bottom: 20px; text-align: center;">
            Drag the slider below to view all metabolites.
        </p>

        <!-- 水平滾動區塊 -->
        <div class="chart-scroll-wrapper">
            <?php
            $chart_file = __DIR__ . '/expression_chart.html';
            if (file_exists($chart_file)) {
                include $chart_file;
            } else {
                echo '<p style="color: red; padding: 20px;">尚未生成圖表檔案，請先執行 Python 腳本產生圖表。</p>';
            }
            ?>
        </div>
    </div>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>