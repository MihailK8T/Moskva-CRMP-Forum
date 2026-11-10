<?php
// Страница темы: показ постов, ответ, реакции, жалоба.
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$id = (int)($_GET['id'] ?? 0);
$topic = dbFetchRow(
    "SELECT t.*, c.title AS cat_title, c.id AS cat_id, a.username AS author_name
       FROM forum_topics t
       JOIN forum_categories c ON c.id = t.category_id
       JOIN accounts a ON a.id = t.account_id
      WHERE t.id = ?", 'i', [$id]
);
if (!$topic) { http_response_code(404); die('Тема не найдена'); }

bumpTopicView($id);

$isBanned = $user ? isUserBanned((int)$user['id']) : null;

// Постинг ответа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'], $_POST['body'])) {
    if (!$user) { flash('error', 'Войдите, чтобы отвечать'); }
    elseif ($isBanned) { flash('error', 'Вы забанены и не можете отвечать.'); }
    elseif ((int)$topic['locked'] && $user['role'] !== 'admin') { flash('error', 'Тема закрыта.'); }
    elseif (!checkCsrf($_POST['csrf'] ?? null)) { flash('error', 'Неверный CSRF-токен.'); }
    else {
        $body = trim((string)$_POST['body']);
        if ($body === '') { flash('error', 'Сообщение пустое.'); }
        else {
            dbExecute(
                "INSERT INTO forum_posts (topic_id, account_id, body, ip) VALUES (?,?,?,?)",
                'iiss', [$id, (int)$user['id'], $body, $_SERVER['REMOTE_ADDR']]
            );
            $pid = dbLastId();
            // Обновить счётчик постов пользователя
            dbExecute("UPDATE forum_profiles SET post_count = post_count + 1 WHERE account_id = ?", 'i', [(int)$user['id']]);
            // Если профиля нет — создать
            dbExecute("INSERT IGNORE INTO forum_profiles (account_id) VALUES (?)", 'i', [(int)$user['id']]);
            // Обновить last_post в теме
            dbExecute(
                "UPDATE forum_topics SET last_post_id = ?, last_post_at = NOW() WHERE id = ?",
                'ii', [$pid, $id]
            );
            // Подписка по умолчанию (уведомления)
            dbExecute(
                "INSERT IGNORE INTO forum_topic_subscriptions (topic_id, account_id) VALUES (?,?)",
                'ii', [$id, (int)$user['id']]
            );
            handlePostNotifications($id, (int)$user['id'], $body, (string)$topic['title']);
            flash('success', 'Ответ добавлен.');
            header('Location: topic.php?id=' . $id . '#post-' . $pid);
            exit;
        }
    }
}

// Лайк / реакция (AJAX-подобный POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['react'], $_POST['post_id'])) {
    $pid = (int)$_POST['post_id'];
    if ($user && !$isBanned) {
        $exists = dbFetchRow(
            "SELECT * FROM forum_post_reactions WHERE post_id = ? AND account_id = ?",
            'ii', [$pid, (int)$user['id']]
        );
        if ($exists) {
            dbExecute("DELETE FROM forum_post_reactions WHERE post_id = ? AND account_id = ?", 'ii', [$pid, (int)$user['id']]);
        } else {
            dbExecute(
                "INSERT INTO forum_post_reactions (post_id, account_id, reaction) VALUES (?,?,?)",
                'iis', [$pid, (int)$user['id'], 'like']
            );
        }
    }
    header('Location: topic.php?id=' . $id . '#post-' . $pid);
    exit;
}

// Жалоба (модератору)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report'], $_POST['post_id'], $_POST['reason'])) {
    $reportedPost = (int)$_POST['post_id'];
    $reason = trim((string)$_POST['reason']);
    if ($user && $reason !== '') {
        dbExecute(
            "INSERT INTO forum_reports (post_id, reporter_id, reason) VALUES (?,?,?)",
            'iis', [$reportedPost, (int)$user['id'], $reason]
        );
        flash('success', 'Жалоба отправлена модераторам.');
    } else {
        flash('error', 'Укажите причину жалобы.');
    }
    header('Location: topic.php?id=' . $id . '#post-' . $reportedPost);
    exit;
}

// Пагинация постов
$perPage = (int)dbFetchValue("SELECT svalue FROM forum_settings WHERE skey='posts_per_page'") ?: 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$totalPosts = (int)dbFetchValue("SELECT COUNT(*) FROM forum_posts WHERE topic_id = ?", 'i', [$id]);
$pages = totalPages($totalPosts, $perPage);

$posts = dbFetchAll(
    "SELECT p.*, a.username, a.admin_level, fp.avatar, fp.post_count, fp.signature,
            (SELECT COUNT(*) FROM forum_post_reactions r WHERE r.post_id = p.id) AS likes
       FROM forum_posts p
       JOIN accounts a ON a.id = p.account_id
       LEFT JOIN forum_profiles fp ON fp.account_id = a.id
      WHERE p.topic_id = ?
      ORDER BY p.created_at ASC, p.id ASC
      LIMIT ? OFFSET ?",
    'iii', [$id, $perPage, $offset]
);

// Какие посты уже лайкнул пользователь
$myLikes = [];
if ($user) {
    $rows = dbFetchAll(
        "SELECT post_id FROM forum_post_reactions WHERE account_id = ? AND post_id IN (SELECT id FROM forum_posts WHERE topic_id = ?)",
        'ii', [(int)$user['id'], $id]
    );
    foreach ($rows as $r) $myLikes[(int)$r['post_id']] = true;
}

$pageTitle = $topic['title'];
layout_header($pageTitle, $user, getFlashes());
?>
<div class="breadcrumbs">
  <a href="index.php">Форум</a> &raquo;
  <a href="category.php?id=<?= (int)$topic['cat_id'] ?>"><?= e($topic['cat_title']) ?></a> &raquo;
  <span><?= e($topic['title']) ?></span>
</div>

<div class="page-head">
  <h1>
    <?php if ($topic['sticky']): ?>📌<?php endif; ?>
    <?php if ($topic['locked']): ?>🔒<?php endif; ?>
    <?= e($topic['title']) ?>
  </h1>
  <?php if (($user && !$isBanned)): ?>
    <a class="btn btn-primary" href="#reply-form">Ответить</a>
  <?php endif; ?>
</div>

<?php if ($isBanned): ?>
  <div class="flash flash-error">⚠ Вы забанены. Причина: <?= e($isBanned['reason'] ?? 'не указана') ?></div>
<?php endif; ?>

<?php if (!empty($_SESSION['flash'])): foreach (getFlashes() as $f): ?>
  <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach; endif; ?>

<div class="post-list" id="posts">
  <?php foreach ($posts as $post): ?>
    <?php $role = adminRole((int)$post['admin_level']); ?>
    <div class="post" id="post-<?= (int)$post['id'] ?>">
      <div class="post-side">
        <div class="avatar av-lg"><?= e(strtoupper(substr($post['username'],0,1))) ?></div>
        <div class="post-author"><?= e($post['username']) ?></div>
        <?php if ($role !== 'user'): ?><div class="role-badge role-<?= e($role) ?>"><?= $role === 'admin' ? 'Администратор' : 'Модератор' ?></div><?php endif; ?>
        <div class="post-count muted">Сообщений: <?= (int)$post['post_count'] ?></div>
      </div>
      <div class="post-main">
        <div class="post-meta">
          <span class="post-date"><?= date('d.m.Y H:i', strtotime($post['created_at'])) ?></span>
          <?php if ($post['edited_at']): ?><span class="muted">(изменено)</span><?php endif; ?>
        </div>
        <div class="post-body"><?= bbcode($post['body']) ?></div>
        <?php if ($post['signature']): ?>
          <div class="post-signature"><?= bbcode($post['signature']) ?></div>
        <?php endif; ?>
        <div class="post-actions">
          <span class="like-btn">
            <a href="?id=<?= $id ?>&react=<?= (int)$post['id'] ?>"
               onclick="event.preventDefault(); react(<?= (int)$post['id'] ?>, <?= $user ? 'true' : 'false' ?>)">👍</a>
            <span id="like-<?= (int)$post['id'] ?>" class="like-count"><?= (int)$post['likes'] ?></span>
          </span>
          <?php if ($user): ?>
            <a class="btn btn-xs" href="#reply-form" onclick="quotePost(<?= (int)$post['id'] ?>, <?= e(json_encode($post['username'])) ?>)">Цитата</a>
            <a class="btn btn-xs btn-danger" href="#report-<?= (int)$post['id'] ?>" onclick="toggleReport(<?= (int)$post['id'] ?>)">Пожаловаться</a>
          <?php endif; ?>
          <?php if ($user && ((int)$user['id'] === (int)$post['account_id'] || $user['role'] !== 'user')): ?>
            <a class="btn btn-xs" href="editpost.php?id=<?= (int)$post['id'] ?>">Изменить</a>
          <?php endif; ?>
        </div>
        <!-- Жалоба -->
        <form class="report-form" id="report-<?= (int)$post['id'] ?>" method="post" style="display:none">
          <?= csrfField() ?>
          <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
          <input type="text" name="reason" placeholder="Причина жалобы..." class="input" required>
          <button type="submit" name="report" class="btn btn-sm">Отправить</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($pages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <?php if ($i === $page): ?><span class="pg cur"><?= $i ?></span>
      <?php else: ?><a class="pg" href="topic.php?id=<?= $id ?>&page=<?= $i ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
  </div>
<?php endif; ?>

<?php if (!$topic['locked'] || ($user && $user['role'] === 'admin')): ?>
  <?php if ($user && !$isBanned): ?>
    <div class="reply-form" id="reply-form">
      <h3>Ответить в тему</h3>
      <form method="post">
        <?= csrfField() ?>
        <textarea name="body" id="reply-body" rows="6" placeholder="Ваш ответ... BB-код разрешён ([b], [i], [quote], [code], [img], [url])" class="input"></textarea>
        <div class="form-row">
          <button type="submit" name="reply" class="btn btn-primary">Отправить</button>
          <span class="muted">Чтобы упомянуть: <code>@Ник</code></span>
        </div>
      </form>
    </div>
  <?php endif; ?>
<?php else: ?>
  <div class="flash flash-error">Тема закрыта для новых ответов.</div>
<?php endif; ?>

<script>
function react(postId, isLogged) {
  if (!isLogged) { alert('Войдите, чтобы ставить реакции'); return; }
  fetch('topic.php?id=<?= $id ?>', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'react=1&post_id=' + postId + '&csrf=<?= urlencode(csrfToken()) ?>'
  }).then(r => r.text()).then(() => location.reload());
}
function quotePost(postId, username) {
  // вставить в форму ответа цитату (примерно)
  document.getElementById('reply-body').value += '[quote=' + username + ' постид=' + postId + ']Цитированный текст...[/quote]\n';
  document.getElementById('reply-body').focus();
}
function toggleReport(id) {
  var f = document.getElementById('report-' + id);
  f.style.display = f.style.display === 'none' ? 'block' : 'none';
}
</script>
<?php layout_footer(); ?>