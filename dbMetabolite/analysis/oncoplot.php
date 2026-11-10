<?php
set_time_limit(180);

$allowed = ['ALL', 'Proneural', 'Classical', 'Mesenchymal'];   // 白名單，避免指令注入
$subtype = $_GET['subtype'] ?? 'ALL';
if (!in_array($subtype, $allowed, true)) { http_response_code(400); exit('Invalid subtype'); }

$top      = 15;
$cacheDir = __DIR__ . '/oncoplot_cache';
if (!is_dir($cacheDir)) mkdir($cacheDir, 0775, true);
$out = "$cacheDir/oncoplot_{$subtype}_top{$top}.png";

// 沒有快取才執行 R（第一次會比較慢）
if (!file_exists($out)) {
    $rscript = '/usr/local/bin/Rscript';   // 請用終端機 `which Rscript` 確認實際路徑
    $cmd = $rscript . ' ' . escapeshellarg(__DIR__ . '/OncoplotAnalysis.r')
         . ' ' . escapeshellarg($subtype)
         . ' ' . escapeshellarg($out)
         . ' ' . (int)$top . ' 2>&1';
    exec($cmd, $output, $code);

    if ($code !== 0 || !file_exists($out)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo implode("\n", $output);   // 除錯用，上線前可拿掉
        exit;
    }
}

header('Content-Type: image/png');
readfile($out);