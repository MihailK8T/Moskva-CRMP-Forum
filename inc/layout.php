<?php
// Общий каркас страниц форума: header + footer.
// Ожидает переменные: $user (текущий пользователь или null), $pageTitle, $flash (массив)
declare(strict_types=1);

$pageTitle = $pageTitle ?? '';
$user      = $user      ?? null;
$flashes   = getFlashes();

function layout_header(string $title, ?array $user, array $flashes): void {
    $siteName = htmlspecialchars((string)dbFetchValue("SELECT svalue FROM forum_settings WHERE skey='site_name'"));
    // Счётчики бейджей считаем тут — $user уже доступен как аргумент
    $unreadNotifs = 0; $unreadPms = 0;
    if ($user) {
        $unreadNotifs = unreadNotifications((int)$user['id']);
        $unreadPms    = (int)dbFetchValue(
            "SELECT COUNT(*) FROM forum_convo_participants p
              JOIN forum_convo_messages m ON m.convo_id = p.convo_id
             WHERE p.account_id = ? AND m.id > p.last_read_id AND p.left_at IS NULL",
            'i', [(int)$user['id']]
        );
    }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title ? $title . ' — ' : '') ?><?= e($siteName) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
  <div class="container header-inner">
    <a class="logo" href="index.php"><?= e($siteName) ?></a>
    <nav class="main-nav">
      <a href="index.php">Форум</a>
      <a href="search.php">Поиск</a>
      <?php if ($user): ?>
        <a href="pm.php">ЛС <span class="badge"><?= $unreadPms ?></span></a>
        <a href="notifications.php">Уведомления <span class="badge"><?= $unreadNotifs ?></span></a>
      <?php endif; ?>
    </nav>
    <div class="userbox">
      <?php if ($user): ?>
        <?php $role = (string)$user['role']; ?>
        <a class="user-link" href="profile.php?id=<?= (int)$user['id'] ?>">
          <span class="avatar av-sm"><?= e(strtoupper(substr((string)$user['username'],0,1))) ?></span>
          <span class="uname"><?= e($user['username']) ?></span>
          <?php if ($role !== 'user'): ?><span class="role-badge role-<?= e($role) ?>"><?= $role === 'admin' ? 'Админ' : 'Модер' ?></span><?php endif; ?>
        </a>
        <?php if (isAdminRole($role)): ?><a class="btn btn-sm" href="admin/index.php">Админка</a><?php endif; ?>
        <a class="btn btn-sm" href="logout.php">Выход</a>
      <?php else: ?>
        <a class="btn btn-sm" href="login.php">Вход</a>
        <a class="btn btn-sm btn-primary" href="register.php">Регистрация</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<main class="container">
<?php foreach ($flashes as $f): ?>
  <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
<?php
}

function layout_footer(): void {
?>
</main>
<footer class="site-footer">
  <div class="container">
    &copy; <?= date('Y') ?> Москва CRMP. Форум связан с игровым сервером.
  </div>
</footer>
</body>
</html>
<?php
}