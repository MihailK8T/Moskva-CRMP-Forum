<?php
// Уведомления пользователя.
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

if (!$user) { header('Location: login.php'); exit; }
$uid = (int)$user['id'];

// Просмотр всех может пометить уведомления прочитанными
$notifs = dbFetchAll(
    "SELECT n.*, DATE_FORMAT(n.created_at,'%d.%m.%Y %H:%i') AS dt
       FROM forum_notifications n
      WHERE n.account_id = ?
      ORDER BY n.created_at DESC LIMIT 100",
    'i', [$uid]
);
// Отметить как прочитанные
dbExecute("UPDATE forum_notifications SET read_at = NOW() WHERE account_id = ? AND read_at IS NULL", 'i', [$uid]);

$pageTitle = 'Уведомления';
layout_header($pageTitle, $user, getFlashes());
?>
<div class="page-head"><h1>Уведомления</h1></div>
<?php if (!$notifs): ?>
  <div class="empty">Уведомлений нет.</div>
<?php else: ?>
  <div class="notif-list">
    <?php foreach ($notifs as $n): ?>
      <div class="notif-item">
        <?php if ($n['link']): ?>
          <a href="<?= e($n['link']) ?>"><?= e($n['text']) ?></a>
        <?php else: ?>
          <?= e($n['text']) ?>
        <?php endif; ?>
        <div class="muted time"><?= e($n['dt']) ?> · <?= e($n['type']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php layout_footer(); ?>