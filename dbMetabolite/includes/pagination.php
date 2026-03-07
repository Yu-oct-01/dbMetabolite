<?php
// =============================================
// includes/pagination.php
// 需要外部變數：$page, $totalPages, $_GET
// =============================================
if (!isset($totalPages) || $totalPages <= 1) return;

$windowSize = 2; // 目前頁左右各顯示幾頁
$first = max(1, $page - $windowSize);
$last  = min($totalPages, $page + $windowSize);
?>
<div class="pagination">
    <?php if ($page > 1): ?>
        <a href="<?= pageUrl(1) ?>">⏮</a>
        <a href="<?= pageUrl($page - 1) ?>">◀</a>
    <?php endif; ?>

    <?php if ($first > 1): ?>
        <a href="<?= pageUrl(1) ?>">1</a>
        <?php if ($first > 2): ?><span>…</span><?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $first; $i <= $last; $i++): ?>
        <?php if ($i === $page): ?>
            <span class="current"><?= $i ?></span>
        <?php else: ?>
            <a href="<?= pageUrl($i) ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($last < $totalPages): ?>
        <?php if ($last < $totalPages - 1): ?><span>…</span><?php endif; ?>
        <a href="<?= pageUrl($totalPages) ?>"><?= $totalPages ?></a>
    <?php endif; ?>

    <?php if ($page < $totalPages): ?>
        <a href="<?= pageUrl($page + 1) ?>">▶</a>
        <a href="<?= pageUrl($totalPages) ?>">⏭</a>
    <?php endif; ?>
</div>
