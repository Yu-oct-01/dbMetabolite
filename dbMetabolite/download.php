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
    <style>
        /* ── Download page styles ── */
        .download-hero {
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            color: #fff;
            padding: 3.5rem 1.5rem 2.5rem;
            text-align: center;
        }
        .download-hero h1 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0 0 .5rem;
            letter-spacing: .03em;
        }
        .download-hero p {
            font-size: 1rem;
            opacity: .8;
            margin: 0;
        }

        .download-container {
            max-width: 860px;
            margin: 2.5rem auto;
            padding: 0 1.25rem;
        }

        /* Alert */
        .alert-error {
            background: #fff0f0;
            border-left: 4px solid #e74c3c;
            color: #c0392b;
            padding: .9rem 1.2rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            font-size: .95rem;
        }

        /* Section heading */
        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1a2f45;
            margin: 0 0 1rem;
            padding-bottom: .45rem;
            border-bottom: 2px solid #e2e8f0;
        }

        /* Download card */
        .download-card {
            background: #fff;
            border: 1px solid #dde4ee;
            border-radius: 12px;
            padding: 1.5rem 1.75rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
            transition: box-shadow .2s, transform .2s;
        }
        .download-card:hover {
            box-shadow: 0 6px 20px rgba(0,0,0,.1);
            transform: translateY(-2px);
        }

        .file-icon {
            flex-shrink: 0;
            width: 52px;
            height: 52px;
            background: #e8f5e9;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .file-icon svg {
            width: 28px;
            height: 28px;
        }

        .file-info {
            flex: 1;
            min-width: 0;
        }
        .file-info h3 {
            font-size: 1rem;
            font-weight: 700;
            color: #1a2f45;
            margin: 0 0 .25rem;
            word-break: break-all;
        }
        .file-info p {
            font-size: .875rem;
            color: #5a6a7e;
            margin: 0 0 .45rem;
            line-height: 1.5;
        }
        .file-meta {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
        }
        .badge {
            display: inline-block;
            font-size: .75rem;
            font-weight: 600;
            padding: .2rem .6rem;
            border-radius: 20px;
            letter-spacing: .02em;
        }
        .badge-xlsx {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .badge-size {
            background: #e3f2fd;
            color: #1565c0;
        }

        .btn-download {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            background: #1a6fc4;
            color: #fff;
            font-size: .9rem;
            font-weight: 600;
            padding: .65rem 1.3rem;
            border-radius: 8px;
            text-decoration: none;
            transition: background .2s, transform .15s;
            white-space: nowrap;
        }
        .btn-download:hover {
            background: #155da0;
            transform: scale(1.03);
            color: #fff;
            text-decoration: none;
        }
        .btn-download svg {
            width: 17px;
            height: 17px;
        }

        /* Description box */
        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.25rem 1.5rem;
            margin-top: 2rem;
        }
        .info-box h4 {
            font-size: .95rem;
            font-weight: 700;
            color: #1a2f45;
            margin: 0 0 .75rem;
        }
        .info-box ul {
            margin: 0;
            padding-left: 1.3rem;
            color: #4a5568;
            font-size: .9rem;
            line-height: 1.8;
        }

        @media (max-width: 600px) {
            .download-card {
                flex-wrap: wrap;
            }
            .btn-download {
                width: 100%;
                justify-content: center;
            }
        }
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
