<?php
set_time_limit(180);

$allowedSub = ['ALL', 'Proneural', 'Classical', 'Mesenchymal'];
$gene    = $_GET['gene'] ?? '';
$subtype = $_GET['subtype'] ?? 'ALL';

// 白名單 / 格式檢查，避免指令注入
if (!preg_match('/^[A-Za-z0-9._-]{1,20}$/', $gene) || !in_array($subtype, $allowedSub, true)) {
    http_response_code(400); exit('Invalid parameters');
}

$cacheDir = __DIR__ . '/oncoplot_cache';
if (!is_dir($cacheDir)) mkdir($cacheDir, 0775, true);
$out = "$cacheDir/km_{$gene}_{$subtype}.png";

if (!file_exists($out)) {
    $rscript = '/usr/local/bin/Rscript';   // 與 oncoplot.php 相同，請填實際路徑
    $cmd = $rscript . ' ' . escapeshellarg(__DIR__ . '/MutationSurvival.r')
         . ' ' . escapeshellarg($gene)
         . ' ' . escapeshellarg($subtype)
         . ' ' . escapeshellarg($out) . ' 2>&1';
    exec($cmd, $output, $code);

    if ($code === 2) {                         // 樣本數不足
        http_response_code(422);
        header('Content-Type: text/plain; charset=utf-8');
        echo preg_replace('/^.*INSUFFICIENT:\s*/s', '', implode("\n", $output));
        exit;
    }
    if ($code !== 0 || !file_exists($out)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        error_log(implode("\n", $output));     // 詳細錯誤寫入 log
        echo 'R 分析失敗，請查看伺服器 log。';
        exit;
    }
}

header('Content-Type: image/png');
readfile($out);