<?php
define("BASE_PATH", __DIR__ . "/");
define("PER_PAGE", 20);
$host   = "127.0.0.1";
$user   = "root";
$pass   = "88888888";
$dbname = "metabolites";
function getDB() {
    global $host, $user, $pass, $dbname;
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli($host, $user, $pass, $dbname);
        $conn->set_charset("utf8mb4");
        if ($conn->connect_error) {
            die("資料庫連線失敗: " . $conn->connect_error);
        }
    }
    return $conn;
}