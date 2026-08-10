<?php
// =============================================
// download.php  —  下載頁
// =============================================
require_once 'config.php';

// 處理檔案下載請求
if (isset($_GET['file'])) {
    $allowed_files = [
        'kegg_pathway' => [
            'path'     => BASE_PATH . 'downloads/KEGG_keggID_pathwayID_pathwayName.xlsx',
            'filename' => 'KEGG_keggID_pathwayID_pathwayName.xlsx',
            'mime'     => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
    ];

    $key = $_GET['file'];

    if (array_key_exists($key, $allowed_files)) {
        $file = $allowed_files[$key];

        if (file_exists($file['path'])) {
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $file['mime']);
            header('Content-Disposition: attachment; filename="' . $file['filename'] . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file['path']));
            flush();
            readfile($file['path']);
            exit;
        } else {
            $download_error = '檔案目前無法使用，請稍後再試。';
        }
    } else {
        $download_error = '無效的檔案請求。';
    }
}

// 取得 KEGG 檔案大小（若存在）
$kegg_file_path = BASE_PATH . 'downloads/KEGG_keggID_pathwayID_pathwayName.xlsx';
$kegg_file_size = file_exists($kegg_file_path)
    ? round(filesize($kegg_file_path) / 1024, 1) . ' KB'
    : 'N/A';
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metabolite Database - Download</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/download.css">
    <style>
    </style>
</head>
<body>

<?php include BASE_PATH . 'includes/navbar.php'; ?>

<div class="download-container">

    <?php if (!empty($download_error)): ?>
        <div class="alert-error">
            ⚠️ <?php echo htmlspecialchars($download_error); ?>
        </div>
    <?php endif; ?>

    <div class="download-card">
        <!-- Excel icon -->
        <div class="file-icon">
            <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="6" y="4" width="36" height="40" rx="3" fill="#e8f5e9"/>
                <rect x="6" y="4" width="36" height="40" rx="3" stroke="#43a047" stroke-width="2"/>
                <path d="M14 16h20M14 23h20M14 30h12" stroke="#43a047" stroke-width="2" stroke-linecap="round"/>
                <path d="M30 28l6 8M36 28l-6 8" stroke="#e53935" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </div>

        <div class="file-info">
            <h3>KEGG_keggID_pathwayID_pathwayName.xlsx</h3>
            <p>The KEGG ID corresponding to the metabolite and the corresponding pathway ID.</p>
            <div class="file-meta">
                <span class="badge badge-xlsx">XLSX</span>
                <span class="badge badge-size">
                    <?php echo htmlspecialchars($kegg_file_size); ?>
                </span>
            </div>
        </div>

        <a href="download.php?file=kegg_pathway" class="btn-download">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            download
        </a>
    </div>

</div>

<?php include BASE_PATH . 'includes/footer.php'; ?>

</body>
</html>