<?php
// Админ-панель: модерация тем (закрепить, закрыть, удалить).
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

// Действия с темами — модератор может закреплять/закрывать, удалять только админ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['topic_action'])) {
    if (!checkCsrf($_POST['csrf'] ?? null)) { aflash('error', 'CSRF.'); }
    else {
        $tid = (int)($_POST['topic_id'] ?? 0);
        $act = (string)($_POST['topic_action'] ?? '');
        $topic = dbFetchRow("SELECT * FROM forum_topics WHERE id=?", 'i', [$tid]);
        if (!$topic) { aflash('error', 'Тема не найдена.'); }
        else {
            switch ($act) {
                case 'sticky':
                    dbExecute("UPDATE forum_topics SET sticky = 1 - sticky WHERE id=?", 'i', [$tid]);
                    aflash('success', 'Закрепление изменено.');
                    break;
                case 'toggle_lock':
                    dbExecute("UPDATE forum_topics SET locked = 1 - locked WHERE id=?", 'i', [$tid]);
                    aflash('success', 'Статус блокировки изменён.');
                    break;
                case 'delete':
                    if (!$isFullAdmin) { aflash('error', 'Только админ.'); }
                    else {
                        dbExecute("DELETE FROM forum_topics WHERE id=?", 'i', [$tid]);
                        aflash('success', 'Тема удалена.');
                    }
                    break;
                default:
                    aflash('error', 'Неизвестное действие.');
            }
        }
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$where = '';
$types = '';
$args  = [];
if ($q !== '') {
    $where = "WHERE t.title LIKE ?";
    $types = 's';
    $args  = ['%' . $q . '%'];
}
$topics = dbFetchAll(
    "SELECT t.*, a.username, c.title AS cat_title
       FROM forum_topics t
       JOIN accounts a ON a.id = t.account_id
       JOIN forum_categories c ON c.id = t.category_id
       $where
       ORDER BY t.created_at DESC LIMIT 200",
    $types, $args
);

admin_header('Темы');
foreach (agetFlashes() as $f): ?><div class="aflash aflash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>

<form method="get" class="search-bar">
  <input type="text" name="q" value="<?= e($q) ?>" class="input" placeholder="Поиск по названию темы...">
  <button class="btn">Поиск</button>
</form>

<div class="panel">
  <table class="atable full">
    <thead><tr><th>Тема</th><th>Автор</th><th>Раздел</th><th>Просм.</th><th>Статус</th><th>Действия</th></tr></thead>
    <tbody>
    <?php foreach ($topics as $t): ?>
      <tr>
        <td><a href="../topic.php?id=<?= (int)$t['id'] ?>"><?= e($t['title']) ?></a>
          <div class="muted time"><?= date('d.m.Y H:i', strtotime($t['created_at'])) ?></div></td>
        <td><?= e($t['username']) ?></td>
        <td><?= e($t['cat_title']) ?></td>
        <td><?= (int)$t['views'] ?></td>
        <td>
          <?= $t['sticky'] ? '<span class="badge">📌</span> ' : '' ?>
          <?= $t['locked'] ? '<span class="badge">🔒</span>' : 'открыта' ?>
        </td>
        <td class="nowrap">
          <form method="post" class="inline-form">
            <?= csrfField() ?>
            <input type="hidden" name="topic_id" value="<?= (int)$t['id'] ?>">
            <button class="btn btn-sm" name="topic_action" value="sticky"><?= $t['sticky'] ? 'Открепить' : 'Закрепить' ?></button>
            <button class="btn btn-sm" name="topic_action" value="toggle_lock"><?= $t['locked'] ? 'Открыть' : 'Закрыть' ?></button>
            <?php if ($isFullAdmin): ?>
              <button class="btn btn-sm btn-danger" name="topic_action" value="delete"
                      onclick="return confirm('Удалить тему со всеми постами?')">🗑</button>
            <?php endif; ?>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php admin_footer(); ?>