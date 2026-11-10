<?php
// Создание новой темы.
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

if (!$user) { header('Location: login.php'); exit; }
if (isUserBanned((int)$user['id'])) { die('Вы забанены.'); }

// Роль для доступа к созданию темы (обычные в общих разделах)
$catId = (int)($_GET['cat'] ?? ($_POST['category_id'] ?? 0));
if (!$catId) { die('Не выбран раздел'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string)($_POST['title'] ?? ''));
    $body  = trim((string)($_POST['body'] ?? ''));
    $categoryId = (int)($_POST['category_id'] ?? 0);
    if (!checkCsrf($_POST['csrf'] ?? null)) {
        flash('error', 'Неверный CSRF-токен.');
    } elseif ($title === '' || $body === '') {
        flash('error', 'Заполните заголовок и текст.');
    } elseif (!$categoryId) {
        flash('error', 'Не выбран раздел.');
    } else {
        dbExecute(
            "INSERT INTO forum_topics (category_id, account_id, title, slug) VALUES (?,?,?,?)",
            'iiss', [$categoryId, (int)$user['id'], $title, makeSlug($title)]
        );
        $topicId = dbLastId();
        dbExecute(
            "INSERT INTO forum_posts (topic_id, account_id, body, ip) VALUES (?,?,?,?)",
            'iiss', [$topicId, (int)$user['id'], $body, $_SERVER['REMOTE_ADDR']]
        );
        dbExecute("INSERT IGNORE INTO forum_profiles (account_id) VALUES (?)", 'i', [(int)$user['id']]);
        dbExecute("UPDATE forum_profiles SET post_count = post_count + 1 WHERE account_id = ?", 'i', [(int)$user['id']]);
        dbExecute("UPDATE forum_topics SET last_post_id = ?, last_post_at = NOW() WHERE id = ?", 'ii', [dbLastId(), $topicId]);
        flash('success', 'Тема создана.');
        header('Location: topic.php?id=' . $topicId);
        exit;
    }
}

$categories = dbFetchAll("SELECT id, title FROM forum_categories ORDER BY position");

$pageTitle = 'Новая тема';
layout_header($pageTitle, $user, getFlashes());
?>
<div class="page-head"><h1>Новая тема</h1></div>
<form method="post" class="panel-form">
  <?= csrfField() ?>
  <div class="form-group">
    <label>Раздел</label>
    <select name="category_id" class="input" required>
      <?php foreach ($categories as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $catId == $c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group">
    <label>Заголовок</label>
    <input type="text" name="title" class="input" required maxlength="150" placeholder="Тема">
  </div>
  <div class="form-group">
    <label>Сообщение</label>
    <textarea name="body" rows="8" class="input" required placeholder="Текст... BB-код разрешён"></textarea>
  </div>
  <button type="submit" class="btn btn-primary">Создать тему</button>
</form>
<?php layout_footer(); ?>