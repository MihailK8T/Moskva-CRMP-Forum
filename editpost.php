<?php
// Редактирование поста (автором или модератором).
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

if (!$user) { header('Location: login.php'); exit; }
$pid = (int)($_GET['id'] ?? 0);
$post = dbFetchRow(
    "SELECT p.*, t.id AS topic_id FROM forum_posts p JOIN forum_topics t ON t.id = p.topic_id WHERE p.id = ?",
    'i', [$pid]
);
if (!$post) { http_response_code(404); die('Пост не найден'); }

$canEdit = ((int)$user['id'] === (int)$post['account_id']) || $user['role'] !== 'user';
if (!$canEdit) { die('Нет прав.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf($_POST['csrf'] ?? null)) {
        flash('error', 'Неверный CSRF.');
    } else {
        $body = trim((string)$_POST['body'] ?? '');
        if ($body === '') { flash('error', 'Сообщение пустое.'); }
        else {
            dbExecute(
                "UPDATE forum_posts SET body = ?, edited_at = NOW(), edited_by = ? WHERE id = ?",
                'sii', [$body, (int)$user['id'], $pid]
            );
            flash('success', 'Пост изменён.');
            header('Location: topic.php?id=' . (int)$post['topic_id'] . '#post-' . $pid);
            exit;
        }
    }
}

$pageTitle = 'Редактировать пост';
layout_header($pageTitle, $user, getFlashes());
?>
<div class="page-head"><h1>Редактировать пост</h1></div>
<form method="post" class="panel-form">
  <?= csrfField() ?>
  <div class="form-group"><label>Сообщение</label>
    <textarea name="body" rows="10" class="input" required><?= e($post['body']) ?></textarea></div>
  <button type="submit" class="btn btn-primary">Сохранить</button>
</form>
<?php layout_footer(); ?>