<?php
// Админ-панель: настройки форума.
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isFullAdmin) {
    if (!checkCsrf($_POST['csrf'] ?? null)) { aflash('error', 'CSRF.'); }
    else {
        $fields = ['site_name','site_desc','forum_open','registration_open','posts_per_page','topics_per_page'];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) {
                $val = trim((string)$_POST[$f]);
                dbExecute(
                    "INSERT INTO forum_settings (skey, svalue) VALUES (?,?) ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)",
                    'ss', [$f, $val]
                );
            }
        }
        aflash('success', 'Настройки сохранены.');
        header('Location: settings.php'); exit;
    }
}

// Читаем настройки в ассоциативный массив
$settings = [];
foreach (dbFetchAll("SELECT skey, svalue FROM forum_settings") as $s) {
    $settings[$s['skey']] = $s['svalue'];
}

admin_header('Настройки');
foreach (agetFlashes() as $f): ?><div class="aflash aflash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>

<?php if (!$isFullAdmin): ?>
  <p class="muted">Настройки доступны только администратору.</p>
<?php else: ?>
<form method="post" class="panel-form settings-form">
  <?= csrfField() ?>
  <div class="form-group"><label>Название сайта</label>
    <input type="text" name="site_name" class="input" value="<?= e($settings['site_name'] ?? '') ?>"></div>
  <div class="form-group"><label>Описание</label>
    <input type="text" name="site_desc" class="input" value="<?= e($settings['site_desc'] ?? '') ?>"></div>
  <div class="form-group"><label>Сообщений на страницу</label>
    <input type="number" name="posts_per_page" class="input" min="5" max="100" value="<?= (int)($settings['posts_per_page'] ?? 20) ?>"></div>
  <div class="form-group"><label>Тем на страницу</label>
    <input type="number" name="topics_per_page" class="input" min="5" max="100" value="<?= (int)($settings['topics_per_page'] ?? 25) ?>"></div>
  <div class="form-group checkbox">
    <label><input type="checkbox" name="forum_open" value="1" <?= ($settings['forum_open'] ?? '1') === '1' ? 'checked' : '' ?>> Форум открыт для просмотра</label>
  </div>
  <div class="form-group checkbox">
    <label><input type="checkbox" name="registration_open" value="1" <?= ($settings['registration_open'] ?? '1') === '1' ? 'checked' : '' ?>> Регистрация открыта</label>
  </div>
  <button class="btn btn-primary">Сохранить настройки</button>
</form>
<?php endif; ?>

<?php admin_footer(); ?>