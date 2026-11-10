<?php
// Страница раздела: список тем.
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$id = (int)($_GET['id'] ?? 0);
$cat = dbFetchRow("SELECT * FROM forum_categories WHERE id = ?", 'i', [$id]);
if (!$cat) { http_response_code(404); die('Раздел не найден'); }

$perPage = (int)dbFetchValue("SELECT svalue FROM forum_settings WHERE skey='topics_per_page'") ?: 25;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$totalTopics = (int)dbFetchValue("SELECT COUNT(*) FROM forum_topics WHERE category_id = ?", 'i', [$id]);
$pages = totalPages($totalTopics, $perPage);

// Прикреплённые всегда сверху, затем по дате последнего сообщения
$topics = dbFetchAll(
    "SELECT t.*, acc.username AS author_name,
            (SELECT COUNT(*) FROM forum_posts p WHERE p.topic_id = t.id) AS reply_count,
            (SELECT username FROM forum_posts p JOIN accounts a ON a.id = p.account_id
               WHERE p.topic_id = t.id ORDER BY p.created_at DESC LIMIT 1) AS last_author
       FROM forum_topics t
       JOIN accounts acc ON acc.id = t.account_id
      WHERE t.category_id = ?
      ORDER BY t.sticky DESC, COALESCE(t.last_post_at, t.created_at) DESC
      LIMIT ? OFFSET ?",
    'iii', [$id, $perPage, $offset]
);

$canPost = $user ? !isUserBanned((int)$user['id']) : false;

$pageTitle = $cat['title'];
layout_header($pageTitle, $user, getFlashes());
?>
<div class="page-head">
  <div>
    <h1><?= e($cat['title']) ?></h1>
    <p class="muted"><?= e($cat['description']) ?></p>
  </div>
  <?php if ($user): ?>
    <a class="btn btn-primary" href="newtopic.php?cat=<?= $id ?>">+ Новая тема</a>
  <?php else: ?>
    <div class="muted">Войдите, чтобы создать тему</div>
  <?php endif; ?>
</div>

<?php if ($totalTopics === 0): ?>
  <div class="empty">В этом разделе пока нет тем.</div>
<?php else: ?>
<table class="topic-list">
  <thead>
    <tr><th>Тема</th><th>Автор</th><th>Ответы</th><th>Последнее</th></tr>
  </thead>
  <tbody>
  <?php foreach ($topics as $t): ?>
    <tr>
      <td class="topic-title-cell">
        <?php if ($t['sticky']): ?><span class="pin" title="Прикреплено">📌</span><?php endif; ?>
        <?php if ($t['locked']): ?><span class="pin" title="Закрыто">🔒</span><?php endif; ?>
        <a class="topic-title" href="topic.php?id=<?= (int)$t['id'] ?>"><?= e($t['title']) ?></a>
        <div class="topic-meta muted"><?= date('d.m.Y H:i', strtotime($t['created_at'])) ?></div>
      </td>
      <td class="topic-author"><?= e($t['author_name']) ?></td>
      <td class="topic-replies"><?= (int)$t['reply_count'] ?></td>
      <td class="topic-last">
        <?php if ($t['last_author']): ?>
          <span class="muted"><?= e($t['last_author']) ?></span><br>
          <span class="time"><?= date('d.m.Y H:i', strtotime($t['last_post_at'] ?? $t['created_at'])) ?></span>
        <?php else: ?>—<?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php if ($pages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <?php if ($i === $page): ?>
        <span class="pg cur"><?= $i ?></span>
      <?php else: ?>
        <a class="pg" href="category.php?id=<?= $id ?>&page=<?= $i ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </div>
<?php endif; ?>
<?php endif; ?>

<?php layout_footer(); ?>