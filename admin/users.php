<?php
// Админ-панель: пользователи.
// Раздача прав (admin_level), просмотр профилей.
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$uid = (int)($_GET['view'] ?? 0);

// Смена уровня прав
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_level']) && $isFullAdmin) {
    if (!checkCsrf($_POST['csrf'] ?? null)) { aflash('error', 'CSRF.'); }
    else {
        $target = (int)($_POST['user_id'] ?? 0);
        $level  = (int)($_POST['level'] ?? 0);
        if ($target && $level >= 0 && $level <= 5) {
            dbExecute("UPDATE accounts SET admin_level = ? WHERE id = ?", 'ii', [$level, $target]);
            aflash('success', 'Уровень прав обновлён.');
        }
    }
}

// Просмотр конкретного пользователя
if ($uid) {
    $u = dbFetchRow(
        "SELECT a.*, fp.display_name, fp.post_count, fp.about, fp.avatar
           FROM accounts a LEFT JOIN forum_profiles fp ON fp.account_id = a.id WHERE a.id = ?",
        'i', [$uid]
    );
    if (!$u) { aflash('error', 'Пользователь не найден.'); header('Location: users.php'); exit; }
    $posts = dbFetchAll(
        "SELECT p.id, p.body, p.created_at, t.title AS topic_title, t.id AS tid
           FROM forum_posts p JOIN forum_topics t ON t.id = p.topic_id WHERE p.account_id = ?
           ORDER BY p.created_at DESC LIMIT 10", 'i', [$uid]
    );

    admin_header('Пользователь: ' . $u['username']);
    foreach (agetFlashes() as $f): ?><div class="aflash aflash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>
    <div class="page-head"><h1><?= e($u['username']) ?></h1><a class="btn" href="users.php">← Назад</a></div>
    <table class="atable profile-tbl">
      <tr><td>ID</td><td><?= (int)$u['id'] ?></td></tr>
      <tr><td>Регистрация</td><td><?= date('d.m.Y H:i', strtotime($u['registered_at'])) ?></td></tr>
      <tr><td>Уровень админки (игра)</td><td><?= (int)$u['admin_level'] ?> (<?= e(adminRole((int)$u['admin_level'])) ?>)</td></tr>
      <tr><td>Постов на форуме</td><td><?= (int)$u['post_count'] ?></td></tr>
      <tr><td>Отображаемое имя</td><td><?= e($u['display_name'] ?? '—') ?></td></tr>
      <tr><td>О себе</td><td><?= $u['about'] ? bbcode($u['about']) : '—' ?></td></tr>
    </table>

    <?php if ($isFullAdmin): ?>
      <div class="panel">
        <h3>Изменить уровень прав</h3>
        <form method="post" class="inline-form">
          <?= csrfField() ?>
          <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
          <select name="level" class="input">
            <?php for ($l = 0; $l <= 5; $l++): ?>
              <option value="<?= $l ?>" <?= (int)$u['admin_level'] === $l ? 'selected' : '' ?>><?= $l ?> — <?= adminRole($l) ?></option>
            <?php endfor; ?>
          </select>
          <button class="btn btn-primary" name="set_level">Сохранить</button>
        </form>
      </div>
    <?php endif; ?>

    <div class="panel">
      <h3>Последние посты</h3>
      <?php if (!$posts): ?><p class="muted">Постов нет.</p>
      <?php else: foreach ($posts as $p): ?>
        <div class="post-row">
          <a href="../topic.php?id=<?= (int)$p['tid'] ?>" class="atitle"><?= e($p['topic_title']) ?></a>
          <p><?= e(mb_substr(strip_tags(bbcode($p['body'])),0,160)) ?>…</p>
          <span class="muted time"><?= date('d.m.Y H:i', strtotime($p['created_at'])) ?></span>
        </div>
      <?php endforeach; endif; ?>
    </div>
    <?php admin_footer(); exit;
}

// Список пользователей
$q = trim((string)($_GET['q'] ?? ''));
$where = '';
$types = '';
$args  = [];
if ($q !== '') {
    $where = "WHERE a.username LIKE ?";
    $types = 's';
    $args  = ['%' . $q . '%'];
}
$users = dbFetchAll(
    "SELECT a.id, a.username, a.admin_level, a.registered_at, a.last_seen,
            (SELECT COUNT(*) FROM forum_posts p WHERE p.account_id = a.id) AS post_count
       FROM accounts a $where
       ORDER BY a.registered_at DESC LIMIT 200",
    $types, $args
);

admin_header('Пользователи');
foreach (agetFlashes() as $f): ?><div class="aflash aflash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>

<form method="get" class="search-bar">
  <input type="text" name="q" value="<?= e($q) ?>" class="input" placeholder="Поиск по нику...">
  <button class="btn">Поиск</button>
</form>

<?php if ($isFullAdmin): ?><p class="muted">Вы можете менять уровни прав. Модераторы смотрят список только для чтения.</p><?php endif; ?>

<table class="atable full">
  <thead><tr><th>ID</th><th>Ник</th><th>Роль</th><th>Посты</th><th>Регистрация</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): ?>
    <tr>
      <td><?= (int)$u['id'] ?></td>
      <td><?= e($u['username']) ?></td>
      <td><span class="role-badge role-<?= e(adminRole((int)$u['admin_level'])) ?>"><?= e(adminRole((int)$u['admin_level'])) ?><?= (int)$u['admin_level'] > 0 ? ' (L' . (int)$u['admin_level'] . ')' : '' ?></span></td>
      <td><?= (int)$u['post_count'] ?></td>
      <td><?= date('d.m.Y', strtotime($u['registered_at'])) ?></td>
      <td><a class="btn btn-sm" href="users.php?view=<?= (int)$u['id'] ?>">Профиль</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php admin_footer(); ?>