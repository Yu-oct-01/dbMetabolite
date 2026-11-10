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
                <a class="nav-link <?= strpos($currentPage, 'analysis') !== false ? 'active' : '' ?>">Analysis ▼</a>
                <div class="dropdown-menu">
                    <div class="dropdown-submenu">
                        <a class="dropdown-item" href="#">Glioma (GBM) »</a>
                        <div class="dropdown-menu sub-menu">
                            <!-- 1. 全景關聯導航（放 ECharts/Cytoscape 網絡圖） -->
                            <a class="dropdown-item font-weight-bold" href="/analysis/overview.php">★ Network Overview (全景網絡)</a>
                            <div class="dropdown-divider"></div>

                            <!-- 2. 四大主要維度 -->
                            <a class="dropdown-item" href="/analysis/molecular_subtypes.php">Molecular Subtypes</a>
                            <a class="dropdown-item" href="/analysis/immune_correlation.php">Immune Correlation</a>
                            <a class="dropdown-item" href="/analysis/drug_response.php">Drug Response & Resistance</a>
                            <a class="dropdown-item" href="/analysis/tumor_recurrence.php">Primary vs. Recurrent</a>

                            <div class="dropdown-divider"></div>
                            <!-- 3. 跨維度熱門分析（Cross-cutting Hubs） -->
                            <a class="dropdown-item text-muted" style="font-size: 0.85em; pointer-events: none;">Cross-Module Hubs:</a>
                            <a class="dropdown-item" href="/analysis/hypoxia_analysis.php">⠀⠀⠀↳ Hypoxia & Metabolism</a>
                            <!-- <a class="dropdown-item" href="/analysis/hub_stemness.php">⠀⠀⠀↳ Stemness (GSC) Analysis</a> -->
                            <!-- <a class="dropdown-item" href="/analysis/metabolite_detail.php">↳ Metabolite Detail</a> -->
                        </div>
                    </div>
                    <a class="dropdown-item disabled" href="#">Disease 2</a>
                    <a class="dropdown-item disabled" href="#">Disease 3</a>
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