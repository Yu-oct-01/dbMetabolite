<?php
define("BASE_PATH", __DIR__ . "/");
define("PER_PAGE", 20);
$host   = "127.0.0.1";
$port   = "3306";
$user   = "root";
$pass   = "";
$dbname = "metabolites";
function getDB(): PDO {
    global $host, $user, $pass, $dbname;
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}