<?php
// Личные сообщения (конверсации).
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

if (!$user) { header('Location: login.php'); exit; }
$uid = (int)$user['id'];
$action = $_GET['action'] ?? 'list';

// -------- Новое ЛС --------
if ($action === 'new' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf($_POST['csrf'] ?? null)) { flash('error', 'CSRF.'); }
    else {
        $toName = trim((string)($_POST['to'] ?? ''));
        $body   = trim((string)($_POST['body'] ?? ''));
        $target = dbFetchRow("SELECT id FROM accounts WHERE username = ?", 's', [$toName]);
        if (!$target) { flash('error', 'Пользователь не найден.'); }
        elseif ((int)$target['id'] === $uid) { flash('error', 'Нельзя писать самому себе.'); }
        elseif ($body === '') { flash('error', 'Сообщение пустое.'); }
        else {
            dbExecute("INSERT INTO forum_convos (initiator_id, title) VALUES (?,?)", 'is', [$uid, 'ЛС: ' . $toName]);
            $cid = dbLastId();
            dbExecute("INSERT INTO forum_convo_participants (convo_id, account_id) VALUES (?,?)", 'ii', [$cid, $uid]);
            dbExecute("INSERT INTO forum_convo_participants (convo_id, account_id) VALUES (?,?)", 'ii', [$cid, (int)$target['id']]);
            dbExecute("INSERT INTO forum_convo_messages (convo_id, account_id, body) VALUES (?,?,?)", 'iis', [$cid, $uid, $body]);
            dbExecute("UPDATE forum_convo_participants SET last_read_id = LAST_INSERT_ID() WHERE convo_id = ? AND account_id = ?", 'ii', [$cid, $uid]);
            notifyUser((int)$target['id'], 'pm', 'Новое личное сообщение от ' . $user['username'], 'pm.php?action=view&id=' . $cid);
            flash('success', 'Сообщение отправлено.');
            header('Location: pm.php?action=view&id=' . $cid);
            exit;
        }
    }
}

// -------- Просмотр конверсации --------
if ($action === 'view') {
    $cid = (int)($_GET['id'] ?? 0);
    $convo = dbFetchRow(
        "SELECT c.*, u.username AS owner FROM forum_convos c
          JOIN forum_convo_participants p ON p.convo_id = c.id
          JOIN accounts u ON u.id = c.initiator_id
         WHERE c.id = ? AND p.account_id = ? AND p.left_at IS NULL",
        'ii', [$cid, $uid]
    );
    if (!$convo) { die('Переписка не найдена'); }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'], $_POST['body'])) {
        if (!checkCsrf($_POST['csrf'] ?? null)) { flash('error', 'CSRF.'); }
        else {
            $body = trim((string)$_POST['body']);
            if ($body === '') { flash('error', 'Пустое сообщение.'); }
            else {
                dbExecute("INSERT INTO forum_convo_messages (convo_id, account_id, body) VALUES (?,?,?)", 'iis', [$cid, $uid, $body]);
                // Обновить last_read для себя
                dbExecute("UPDATE forum_convo_participants SET last_read_id = ? WHERE convo_id = ? AND account_id = ?", 'iii', [dbLastId(), $cid, $uid]);
                // Уведомить остальных
                $others = dbFetchAll(
                    "SELECT account_id FROM forum_convo_participants WHERE convo_id = ? AND account_id != ? AND left_at IS NULL",
                    'ii', [$cid, $uid]
                );
                foreach ($others as $o) notifyUser((int)$o['account_id'], 'pm', 'Новое сообщение в переписке', 'pm.php?action=view&id=' . $cid);
                flash('success', 'Отправлено.');
                header('Location: pm.php?action=view&id=' . $cid);
                exit;
            }
        }
    }

    $messages = dbFetchAll(
        "SELECT m.*, a.username FROM forum_convo_messages m JOIN accounts a ON a.id = m.account_id
          WHERE m.convo_id = ? ORDER BY m.created_at ASC, m.id ASC",
        'i', [$cid]
    );
    // Отметить прочитанными: последнее прочитанное = последнее сообщение
    if ($messages) {
        $lastMsg = (int)$messages[count($messages)-1]['id'];
        dbExecute("UPDATE forum_convo_participants SET last_read_id = ? WHERE convo_id = ? AND account_id = ?", 'iii', [$lastMsg, $cid, $uid]);
    }

    $pageTitle = 'ЛС';
    layout_header($pageTitle, $user, getFlashes());
    ?>
    <div class="breadcrumbs"><a href="pm.php">Сообщения</a> &raquo; <?= e($convo['title']) ?></div>
    <div class="page-head"><h1><?= e($convo['title']) ?></h1></div>
    <div class="pm-list">
      <?php foreach ($messages as $m): ?>
        <div class="pm-item <?= (int)$m['account_id'] === $uid ? 'pm-me' : '' ?>">
          <div class="pm-meta muted"><b><?= e($m['username']) ?></b> · <?= date('d.m.Y H:i', strtotime($m['created_at'])) ?></div>
          <div class="pm-body"><?= bbcode($m['body']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="reply-form" id="reply-form">
      <form method="post">
        <?= csrfField() ?>
        <textarea name="body" rows="4" class="input" placeholder="Ваш ответ..." required></textarea>
        <button class="btn btn-primary" name="reply">Ответить</button>
      </form>
    </div>
    <?php layout_footer(); exit;
}

// -------- Список переписок --------
if ($action === 'to') {
    // предзаполнить поле 'to'
    $_GET['to'] ??= '';
}

$convos = dbFetchAll(
    "SELECT c.id, c.title, c.created_at,
            (SELECT m2.body FROM forum_convo_messages m2 WHERE m2.convo_id = c.id ORDER BY m2.id DESC LIMIT 1) AS last_body,
            (SELECT m2.created_at FROM forum_convo_messages m2 WHERE m2.convo_id = c.id ORDER BY m2.id DESC LIMIT 1) AS last_time
       FROM forum_convos c
       JOIN forum_convo_participants p ON p.convo_id = c.id
      WHERE p.account_id = ? AND p.left_at IS NULL
      ORDER BY c.created_at DESC",
    'i', [$uid]
);

$pageTitle = 'Личные сообщения';
layout_header($pageTitle, $user, getFlashes());
?>
<div class="page-head">
  <h1>Личные сообщения</h1>
  <a class="btn btn-primary" href="pm.php?action=new">+ Новое сообщение</a>
</div>

<?php if ($action === 'new' || $action === 'to'): ?>
  <form method="post" class="panel-form">
    <?= csrfField() ?>
    <div class="form-group"><label>Кому (ник)</label>
      <input type="text" name="to" class="input" required value="<?= e($_GET['to'] ?? '') ?>"></div>
    <div class="form-group"><label>Сообщение</label>
      <textarea name="body" rows="5" class="input" required></textarea></div>
    <button class="btn btn-primary">Отправить</button>
  </form>
<?php endif; ?>

<?php if (!$convos): ?>
  <div class="empty">Нет переписок.</div>
<?php else: ?>
  <table class="topic-list">
    <tbody>
    <?php foreach ($convos as $c): ?>
      <tr>
        <td><a class="topic-title" href="pm.php?action=view&id=<?= (int)$c['id'] ?>"><?= e($c['title']) ?></a>
          <div class="muted"><?= e(mb_substr(strip_tags($c['last_body'] ?? ''),0,80)) ?></div></td>
        <td class="muted time" style="white-space:nowrap"><?= $c['last_time'] ? date('d.m.Y H:i', strtotime($c['last_time'])) : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php layout_footer(); ?>