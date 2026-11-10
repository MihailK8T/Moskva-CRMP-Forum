<?php
// Общий вход в админ-панель.
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/bbcode.php';

$adminUser = currentUser();
if (!$adminUser || !isAdminRole($adminUser['role'])) {
    http_response_code(403);
    die('Доступ запрещён. Только модераторы и администраторы.');
}

// Роль для жёстких операций (бан, удаление) — только admin
$isFullAdmin = ($adminUser['role'] === 'admin');

function admin_header(string $title): void {
    global $adminUser;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> — Панель</title>
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<header class="admin-top">
  <div class="container">
    <a href="index.php" class="alogo">⚙ Панель форума</a>
    <nav>
      <a href="index.php">Панель</a>
      <a href="users.php">Пользователи</a>
      <a href="bans.php">Баны</a>
      <a href="categories.php">Разделы</a>
      <a href="reports.php">Жалобы</a>
      <a href="settings.php">Настройки</a>
    </nav>
    <div class="aright">
      <span><?= e($adminUser['username']) ?> (<?= e($adminUser['role']) ?>)</span>
      <?php if ($isFullAdmin): ?><span class="full-admin">полный доступ</span><?php endif; ?>
      <a href="../index.php">↩ Форум</a>
    </div>
  </div>
</header>
<main class="container">
<?php
}

function admin_footer(): void {
?>
</main>
</body>
</html>
<?php
}

// Flash для админ-панели (другая сессия - переиспользуем те же ключи)
function aflash(string $type, string $msg): void {
    $_SESSION['admin_flash'][] = ['type' => $type, 'msg' => $msg];
}
function agetFlashes(): array {
    $f = $_SESSION['admin_flash'] ?? [];
    unset($_SESSION['admin_flash']);
    return $f;
}