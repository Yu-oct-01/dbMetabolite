<?php
// includes/navbar.php  —  共用導航欄
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar">
    <div class="navbar-container">
        <a href="index.php" class="logo">Metabolite Database</a>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="statistics.php" class="nav-link <?= $currentPage === 'statistics.php' ? 'active' : '' ?>">Statistic</a>
            </li>
            <li class="nav-item">
                <a href="search.php" class="nav-link <?= $currentPage === 'search.php' ? 'active' : '' ?>">Search</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= in_array($currentPage, ['browse.php']) ? 'active' : '' ?>">Browse ▼</a>
                <div class="dropdown-menu">
                    <a href="browse.php">Metabolites</a>
                    <a href="#">file2</a>
                    <a href="#">file3</a>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= in_array($currentPage, ['analysis.php']) ? 'active' : '' ?>">Analysis ▼</a>
                <div class="dropdown-menu">
                    <a href="analysis.php">Data analysis</a>
                    <a href="#">Trend analysis</a>
                    <a href="#">Survival analysis</a>
                </div>
            </li>
            <li class="nav-item">
                <a href="download.php" class="nav-link <?= $currentPage === 'download.php' ? 'active' : '' ?>">Download</a>
            </li>
        </ul>
    </div>
</nav>
