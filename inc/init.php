<?php
// Общий вход для всех страниц форума.
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/bbcode.php';
require_once __DIR__ . '/layout.php';

session_start();

$user = currentUser();

// Блокируем форум, если закрыт
$forumOpen = (int)dbFetchValue("SELECT svalue FROM forum_settings WHERE skey='forum_open'");
if ($forumOpen === 0) {
    http_response_code(503);
    echo '<h1>Форум временно закрыт</h1>';
    exit;
}