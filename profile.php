<?php
// Профиль пользователя.
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$id = (int)($_GET['id'] ?? ($user['id'] ?? 0));
if (!$id) { header('Location: login.php'); exit; }

$profile = dbFetchRow(
    "SELECT a.id, a.username, a.admin_level, a.registered_at, a.last_seen,
            fp.display_name, fp.avatar, fp.signature, fp.about, fp.post_count,
            (SELECT COUNT(*) FROM forum_topics t WHERE t.account_id = a.id) AS topic_count
       FROM accounts a
       LEFT JOIN forum_profiles fp ON fp.account_id = a.id
      WHERE a.id = ?", 'i', [$id]
);
if (!$profile) { http_response_code(404); die('Пользователь не найден'); }

$role = adminRole((int)$profile['admin_level']);
$isSelf = $user && (int)$user['id'] === $id;

// Обновление профиля (своего)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isSelf) {
    if (!checkCsrf($_POST['csrf'] ?? null)) {
        flash('error', 'Неверный CSRF.');
    } else {
        $display = trim((string)$_POST['display_name'] ?? '');
        $about   = trim((string)$_POST['about'] ?? '');
        $sig     = trim((string)$_POST['signature'] ?? '');
        dbExecute(
            "INSERT INTO forum_profiles (account_id, display_name, about, signature)
             VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name), about=VALUES(about), signature=VALUES(signature)",
            'isss', [$id, $display, $about, $sig]
        );
        flash('success', 'Профиль обновлён.');
        header('Location: profile.php?id=' . $id);
        exit;
    }
}

$pageTitle = 'Профиль: ' . $profile['username'];
layout_header($pageTitle, $user, getFlashes());
?>
<div class="profile-page">
  <div class="profile-card">
    <div class="avatar av-xl"><?= e(strtoupper(substr($profile['username'],0,1))) ?></div>
    <div class="profile-info">
      <h1><?= e($profile['display_name'] ?: $profile['username']) ?></h1>
      <div class="muted">Ник: <b><?= e($profile['username']) ?></b></div>
      <?php if ($role !== 'user'): ?><div class="role-badge role-<?= e($role) ?>"><?= $role === 'admin' ? 'Администратор' : 'Модератор' ?></div><?php endif; ?>
      <table class="profile-stats">
        <tr><td>Регистрация</td><td><?= date('d.m.Y', strtotime($profile['registered_at'])) ?></td></tr>
        <tr><td>Сообщений</td><td><?= (int)$profile['post_count'] ?></td></tr>
        <tr><td>Тем</td><td><?= (int)$profile['topic_count'] ?></td></tr>
      </table>
      <?php if ($profile['about']): ?><div class="profile-about"><?= bbcode($profile['about']) ?></div><?php endif; ?>
    </div>
  </div>

  <?php if ($isSelf): ?>
    <div class="panel">
      <h2>Редактировать профиль</h2>
      <form method="post" class="panel-form">
        <?= csrfField() ?>
        <div class="form-group"><label>Отображаемое имя</label>
          <input type="text" name="display_name" class="input" value="<?= e($profile['display_name'] ?? '') ?>" maxlength="48"></div>
        <div class="form-group"><label>О себе</label>
          <textarea name="about" rows="3" class="input"><?= e($profile['about'] ?? '') ?></textarea></div>
        <div class="form-group"><label>Подпись</label>
          <textarea name="signature" rows="2" class="input"><?= e($profile['signature'] ?? '') ?></textarea></div>
        <button class="btn btn-primary">Сохранить</button>
      </form>
    </div>
  <?php endif; ?>
</div>
<?php layout_footer(); ?>