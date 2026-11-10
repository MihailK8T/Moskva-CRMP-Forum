<?php
// Главная страница форума: список разделов с количеством тем/постов и последней темой.
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

// Разделы (категории), 1 уровень
$categories = dbFetchAll(
    "SELECT c.*,
        (SELECT COUNT(*) FROM forum_topics t WHERE t.category_id = c.id) AS topics,
        (SELECT COUNT(*) FROM forum_posts p
           JOIN forum_topics t ON t.id = p.topic_id AND t.category_id = c.id) AS posts
     FROM forum_categories c
     ORDER BY c.position ASC, c.id ASC"
);

// Последние активности по разделам
$catsWithLast = [];
foreach ($categories as &$c) {
    $last = dbFetchRow(
        "SELECT t.id, t.title, t.last_post_at, t.last_post_id,
                a.username AS last_author
           FROM forum_topics t
           LEFT JOIN accounts a ON a.id = (
                SELECT p2.account_id FROM forum_posts p2
                 WHERE p2.topic_id = t.id ORDER BY p2.created_at DESC LIMIT 1
           )
          WHERE t.category_id = ?
          ORDER BY COALESCE(t.last_post_at, t.created_at) DESC LIMIT 1",
        'i', [(int)$c['id']]
    );
    $c['last'] = $last;
}
unset($c);

$pageTitle = '';
layout_header($pageTitle, $user, getFlashes());
?>

<div class="cat-list">
  <?php if (!$categories): ?>
    <div class="empty">Разделы ещё не созданы.</div>
  <?php endif; ?>
  <?php foreach ($categories as $cat): ?>
    <div class="category-block">
      <div class="cat-header">
        <h2 class="cat-title"><a href="category.php?id=<?= (int)$cat['id'] ?>"><?= e($cat['title']) ?></a></h2>
        <span class="cat-desc"><?= e($cat['description']) ?></span>
      </div>
      <div class="cat-rows">
        <div class="row-labels">
          <div>Темы</div><div>Ответы</div><div>Последнее</div>
        </div>
        <?php if ($cat['last']): ?>
          <div class="row-stats">
            <div><span class="stat-count"><?= (int)$cat['topics'] ?></span></div>
            <div><span class="stat-count"><?= (int)$cat['posts'] ?></span></div>
            <div>
              <a class="last-topic" href="topic.php?id=<?= (int)$cat['last']['id'] ?>"><?= e($cat['last']['title']) ?></a>
              <div class="last-meta">
                от <?= e($cat['last']['last_author']) ?>
                <span class="time"><?= date('d.m.Y H:i', strtotime($cat['last']['last_post_at'] ?? $cat['last']['created_at'] ?? '')) ?></span>
              </div>
            </div>
          </div>
        <?php else: ?>
          <div class="row-stats"><div>0</div><div>0</div><div class="muted">Нет тем</div></div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php layout_footer(); ?>