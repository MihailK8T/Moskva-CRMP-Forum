<?php
// Админ-панель: дашборд.
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$stats = [
    'users'  => (int)dbFetchValue("SELECT COUNT(*) FROM accounts"),
    'topics' => (int)dbFetchValue("SELECT COUNT(*) FROM forum_topics"),
    'posts'  => (int)dbFetchValue("SELECT COUNT(*) FROM forum_posts"),
    'reports'=> (int)dbFetchValue("SELECT COUNT(*) FROM forum_reports WHERE status='open'"),
    'bans'   => (int)dbFetchValue("SELECT COUNT(*) FROM bans WHERE expire_at IS NULL OR expire_at > NOW()"),
];

$recentUsers = dbFetchAll("SELECT id, username, admin_level, registered_at FROM accounts ORDER BY registered_at DESC LIMIT 5");
$recentReports = dbFetchAll(
    "SELECT r.*, a.username AS reporter FROM forum_reports r JOIN accounts a ON a.id = r.reporter_id
      WHERE r.status='open' ORDER BY r.created_at DESC LIMIT 5"
);

admin_header('Панель управления');
foreach (agetFlashes() as $f): ?>
  <div class="aflash aflash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>

<div class="stat-grid">
  <div class="stat-card"><div class="stat-num"><?= $stats['users'] ?></div><div>Пользователей</div></div>
  <div class="stat-card"><div class="stat-num"><?= $stats['topics'] ?></div><div>Тем</div></div>
  <div class="stat-card"><div class="stat-num"><?= $stats['posts'] ?></div><div>Сообщений</div></div>
  <div class="stat-card <?= $stats['reports'] ? 'warn' : '' ?>"><div class="stat-num"><?= $stats['reports'] ?></div><div>Открытых жалоб</div></div>
  <div class="stat-card"><div class="stat-num"><?= $stats['bans'] ?></div><div>Активных банов</div></div>
</div>

<div class="grid-2">
  <div class="panel">
    <h3>Новые пользователи</h3>
    <table class="atable">
      <tbody>
      <?php foreach ($recentUsers as $u): ?>
        <tr><td><a href="users.php?view=<?= (int)$u['id'] ?>"><?= e($u['username']) ?></a></td>
            <td class="muted"><?= date('d.m.Y', strtotime($u['registered_at'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="panel">
    <h3>Открытые жалобы</h3>
    <?php if (!$recentReports): ?><p class="muted">Нет открытых жалоб.</p>
    <?php else: ?>
    <table class="atable">
      <tbody>
      <?php foreach ($recentReports as $r): ?>
        <tr><td><a href="reports.php">Жалоба #<?= (int)$r['id'] ?></a> от <?= e($r['reporter']) ?></td>
            <td class="muted"><?= date('d.m.Y', strtotime($r['created_at'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php admin_footer(); ?>