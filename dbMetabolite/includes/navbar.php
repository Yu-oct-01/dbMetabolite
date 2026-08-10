<?php
// includes/navbar.php  —  共用導航欄
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar">
    <div class="navbar-container">
        <a href="/index.php" class="logo">Disease Metabolite Database</a>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="/statistics.php" class="nav-link <?= $currentPage === 'statistics.php' ? 'active' : '' ?>">Statistics</a>
            </li>
            <li class="nav-item">
                <a href="/search/search.php" class="nav-link <?= $currentPage === 'search.php' ? 'active' : '' ?>">Search</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= in_array($currentPage, ['metabolites.php']) ? 'active' : '' ?>">Browse ▼</a>
                <div class="dropdown-menu">
                    <a href="/metabolites.php">Metabolites</a>
                    <a href="/clinicaldata.php">Clinical Data</a>
                    <a href="/immunedata.php">Immune Data</a>
                </div>
            </li>
           <li class="nav-item">
                <a class="nav-link <?= in_array($currentPage, ['analysis.php']) ? 'active' : '' ?>">Analysis ▼</a>
                <div class="dropdown-menu">
                    <div class="dropdown-submenu">
                        <a class="dropdown-item" href="#">Glioma(GBM) »</a>
                        <div class="dropdown-menu sub-menu">
                            <a class="dropdown-item" href="/analysis/GSVA_Scores.php">GSVA Scores</a>
                            <a class="dropdown-item" href="#">腫瘤幹性</a>
                            <a class="dropdown-item" href="#">其他項目</a>
                        </div>
                    </div>
                    
                    <a class="dropdown-item" href="#">file2</a>
                    <a class="dropdown-item" href="#">file3</a>
                </div>
            </li>
            <li class="nav-item">
                <a href="/download.php" class="nav-link <?= $currentPage === 'download.php' ? 'active' : '' ?>">Download</a>
            </li>
            <li class="nav-item">
                <a href="/tutorial.php" class="nav-link <?= $currentPage === 'tutorial.php' ? 'active' : '' ?>">Tutorial</a>
            </li>
        </ul>
    </div>
</nav>