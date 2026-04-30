<?php
if (session_status() === PHP_SESSION_NONE) session_start();

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'spendsmart');

function getDB() {
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO(
            "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

function e($s)          { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function uid()          { return $_SESSION['user_id']; }
function tk($n)         { return '৳ ' . number_format($n, 2); }
function requireLogin() { if (!isset($_SESSION['user_id'])) { header('Location: auth.php'); exit; } }

function flash($msg, $type = 'success') {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}
