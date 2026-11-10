<?php
// Поиск по темам и сообщениям.
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$q = trim((string)($_GET['q'] ?? ''));
$results = [];
$searched = ($q !== '');

if ($searched) {
    // Поиск по темам (заголовок)
    $topics = dbFetchAll(
        "SELECT t.id, t.title, t.created_at, c.title AS cat_title
           FROM forum_topics t JOIN forum_categories c ON c.id = t.category_id
          WHERE t.title LIKE ?
          ORDER BY t.created_at DESC LIMIT 50",
        's', ['%' . $q . '%']
    );
    // Поиск по постам (текст)
    $posts = dbFetchAll(
        "SELECT p.body, p.created_at, t.id AS topic_id, t.title AS topic_title, a.username
           FROM forum_posts p
           JOIN forum_topics t ON t.id = p.topic_id
           JOIN accounts a ON a.id = p.account_id
          WHERE p.body LIKE ?
          ORDER BY p.created_at DESC LIMIT 50",
        's', ['%' . $q . '%']
    );
    $results = ['topics' => $topics, 'posts' => $posts];
}

$pageTitle = 'Поиск';
layout_header($pageTitle, $user, getFlashes());
?>
<div class="page-head"><h1>Поиск</h1></div>
<form method="get" class="search-form">
  <input type="text" name="q" value="<?= e($q) ?>" class="input" placeholder="Поиск по темам и сообщениям..." required>
  <button type="submit" class="btn btn-primary">Найти</button>
</form>

<?php if ($searched): ?>
  <?php if (!$results['topics'] && !$results['posts']): ?>
    <div class="empty">Ничего не найдено.</div>
  <?php else: ?>
    <?php if ($results['topics']): ?>
      <h3>Темы</h3>
      <table class="topic-list">
        <tbody>
        <?php foreach ($results['topics'] as $t): ?>
          <tr><td><a class="topic-title" href="topic.php?id=<?= (int)$t['id'] ?>"><?= e($t['title']) ?></a>
            <div class="muted"><?= e($t['cat_title']) ?> <span class="time"><?= date('d.m.Y', strtotime($t['created_at'])) ?></span></div></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
    <?php if ($results['posts']): ?>
      <h3>Сообщения</h3>
      <?php foreach ($results['posts'] as $p): ?>
        <div class="search-result">
          <a class="topic-title" href="topic.php?id=<?= (int)$p['topic_id'] ?>"><?= e($p['topic_title']) ?></a>
          <div class="snippet muted"><?= e(mb_substr(strip_tags(bbcode($p['body'])), 0, 220)) ?>...</div>
          <div class="muted"><?= e($p['username']) ?> <span class="time"><?= date('d.m.Y H:i', strtotime($p['created_at'])) ?></span></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>
<?php layout_footer(); ?>