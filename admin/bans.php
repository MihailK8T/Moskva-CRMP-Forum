<?php
// Админ-панель: баны.
// Работает с таблицей bans — той же, что использует игровой сервер.
// Бан/разбан на форуме = бан/разбан в игре (интеграция максимум).
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

// Только полные админы выдают баны
if (!isset($GLOBALS['isFullAdmin']) || !$GLOBALS['isFullAdmin']) {
    // bootstrap определяет $isFullAdmin в global scope; перечитаем
    global $isFullAdmin;
}

$isFullAdmin = $isFullAdmin ?? (currentUser()['role'] ?? '') === 'admin';

// Выдать бан
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ban']) && $isFullAdmin) {
    if (!checkCsrf($_POST['csrf'] ?? null)) { aflash('error', 'CSRF.'); }
    else {
        $username = trim((string)$_POST['username'] ?? '');
        $reason   = trim((string)$_POST['reason'] ?? '');
        $hours    = (int)($_POST['hours'] ?? 0); // 0 = навсегда
        $user2    = dbFetchRow("SELECT id, username FROM accounts WHERE username = ?", 's', [$username]);
        if (!$user2) { aflash('error', 'Пользователь не найден в accounts.'); }
        else {
            $expire = null;
            if ($hours > 0) {
                $expire = date('Y-m-d H:i:s', time() + $hours * 3600);
            }
            dbExecute(
                "INSERT INTO bans (username, admin_name, reason, expire_at) VALUES (?,?,?,?)",
                'ssss', [$user2['username'], $adminUser['username'], $reason ?: 'Без причины', $expire]
            );
            aflash('success', 'Пользователь ' . $user2['username'] . ' забанен.' . ($hours > 0 ? " на $hours ч." : ' навсегда.'));
        }
    }
}

// Снять бан
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unban']) && $isFullAdmin) {
    if (!checkCsrf($_POST['csrf'] ?? null)) { aflash('error', 'CSRF.'); }
    else {
        $banId = (int)($_POST['ban_id'] ?? 0);
        dbExecute("DELETE FROM bans WHERE id = ?", 'i', [$banId]);
        aflash('success', 'Бан снят.');
    }
}

// Проверить статус пользователя
$checkName = trim((string)($_GET['check'] ?? ''));
$checkBan = null;
if ($checkName !== '') {
    $checkBan = dbFetchRow(
        "SELECT b.* FROM bans b JOIN accounts a ON a.username = b.username
          WHERE a.username = ? AND (b.expire_at IS NULL OR b.expire_at > NOW())
          ORDER BY b.created_at DESC LIMIT 1", 's', [$checkName]
    );
}

// Активные баны
$bans = dbFetchAll(
    "SELECT b.*, a.username AS uname FROM bans b
      LEFT JOIN accounts a ON a.username = b.username
      WHERE b.expire_at IS NULL OR b.expire_at > NOW()
      ORDER BY b.created_at DESC LIMIT 200"
);

admin_header('Баны');
foreach (agetFlashes() as $f): ?><div class="aflash aflash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>

<?php if ($isFullAdmin): ?>
  <div class="panel">
    <h3>Выдать бан</h3>
    <form method="post" class="grid-form">
      <?= csrfField() ?>
      <input type="text" name="username" class="input" placeholder="Ник игрока" required>
      <input type="number" name="hours" class="input" placeholder="Срок (часов, 0 = навсегда)" min="0" value="0">
      <input type="text" name="reason" class="input" placeholder="Причина" required>
      <button class="btn btn-danger" name="ban">Забанить</button>
    </form>
    <form method="get" class="search-bar" style="margin-top:10px">
      <input type="text" name="check" class="input" placeholder="Проверить статус по нику..." value="<?= e($checkName) ?>">
      <button class="btn">Проверить</button>
    </form>
    <?php if ($checkBan): ?>
      <div class="aflash aflash-error">Забанен: <?= e($checkBan['reason']) ?> до <?= $checkBan['expire_at'] ?? 'навсегда' ?></div>
    <?php elseif ($checkName !== ''): ?>
      <div class="aflash aflash-success"><?= e($checkName) ?> не забанен.</div>
    <?php endif; ?>
  </div>
<?php else: ?>
  <p class="muted">Выдавать и снимать баны может только администратор.</p>
<?php endif; ?>

<div class="panel">
  <h3>Активные баны (<?= count($bans) ?>)</h3>
  <?php if (!$bans): ?><p class="muted">Нет активных банов.</p>
  <?php else: ?>
  <table class="atable full">
    <thead><tr><th>Ник</th><th>Админ</th><th>Причина</th><th>До</th><th>Дата</th><?php if ($isFullAdmin): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($bans as $b): ?>
      <tr>
        <td><?= e($b['uname'] ?? $b['username']) ?></td>
        <td><?= e($b['admin_name'] ?? '—') ?></td>
        <td><?= e($b['reason'] ?? '') ?></td>
        <td><?= $b['expire_at'] ? date('d.m.Y H:i', strtotime($b['expire_at'])) : 'навсегда' ?></td>
        <td class="muted"><?= date('d.m.Y', strtotime($b['created_at'])) ?></td>
        <?php if ($isFullAdmin): ?>
          <td>
            <form method="post" onsubmit="return confirm('Снять бан?')">
              <?= csrfField() ?>
              <input type="hidden" name="ban_id" value="<?= (int)$b['id'] ?>">
              <button class="btn btn-sm btn-danger" name="unban">Разбанить</button>
            </form>
          </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php admin_footer(); ?>