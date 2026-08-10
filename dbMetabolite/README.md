config.php 檔的 pass 要改成自己的密碼
http://dbmetabolite.test/index.php

dbMetabolite/
├──metabolite/
│   ├── dmtdb000001/
│         ├──index.php
│   ├── dmtdb000002/
│         ├──index.php
│   ˙˙˙˙˙
├──clinicaldata/
│   ├── C1230738/
│         ├──index.php
│   ├── C1245129/
│         ├──index.php
│   ˙˙˙˙˙
├── README.md
├── config.php (資料庫連線設定)
├── index.php
├── statistics.php
├── search/          
│   ├── search.php 
│   ├── search_pathway.php
│   ├── search_tme.php
│   ├── results_name.php
│   ├── results_id.php
│   ├── results_pathway.php
│   └── results_tme.php
├── clinicaldata.php
├── metabolites.php
├── immunedata.php
├── download.php
├── downloads/          
│   └── KEGG_keggID_pathwayID_pathwayName.xlsx
├── tutorial.php
├── includes/          
│   ├── footer.php (網站底部的頁尾)
│   ├── navbar.php (網站頂部的導航欄)
│   └── pagination.php (分頁元件)
├── css/
│   ├── browse.css
│   ├── clinicaldata.css (臨床資料個別頁面樣式)
│   ├── metabolite.css (代謝物個別頁面樣式)
│   ├── download.css
│   ├── search.css
│   ├── pathway.css
│   └── style.css
└── js/
    └── main.js