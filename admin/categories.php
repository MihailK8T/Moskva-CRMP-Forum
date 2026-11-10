<?php
// Админ-панель: разделы (категории).
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

// Создание/редактирование делаем только админ
$actions = ['add', 'edit', 'delete', 'up', 'down'];

// Создание
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add']) && $isFullAdmin) {
    if (!checkCsrf($_POST['csrf'] ?? null)) { aflash('error', 'CSRF.'); }
    else {
        $title = trim((string)($_POST['title'] ?? ''));
        $desc  = trim((string)($_POST['description'] ?? ''));
        if ($title === '') { aflash('error', 'Название обязательно.'); }
        else {
            $maxPos = (int)dbFetchValue("SELECT COALESCE(MAX(position),0)+10 FROM forum_categories");
            dbExecute("INSERT INTO forum_categories (title, description, position) VALUES (?,?,?)", 'ssi', [$title, $desc, $maxPos]);
            aflash('success', 'Раздел добавлен.');
        }
    }
}

// Редактирование
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit']) && $isFullAdmin) {
    if (!checkCsrf($_POST['csrf'] ?? null)) { aflash('error', 'CSRF.'); }
    else {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $desc  = trim((string)($_POST['description'] ?? ''));
        $pos   = (int)($_POST['position'] ?? 0);
        if ($id && $title !== '') {
            dbExecute("UPDATE forum_categories SET title=?, description=?, position=? WHERE id=?", 'ssii', [$title, $desc, $pos, $id]);
            aflash('success', 'Раздел обновлён.');
        }
    }
}

// Удаление (с переносом тем в корень? просто каскад или отклонение)
if (isset($_GET['delete']) && $isFullAdmin) {
    $id = (int)$_GET['delete'];
    $cnt = (int)dbFetchValue("SELECT COUNT(*) FROM forum_topics WHERE category_id = ?", 'i', [$id]);
    if ($cnt > 0) {
        aflash('error', 'Нельзя удалить: в разделе ' . $cnt . ' тем. Сначала удалите/перенесите темы.');
    } else {
        dbExecute("DELETE FROM forum_categories WHERE id=?", 'i', [$id]);
        aflash('success', 'Раздел удалён.');
    }
    header('Location: categories.php'); exit;
}

// Сменить порядок
if (isset($_GET['move']) && $isFullAdmin) {
    $id = (int)$_GET['move'];
    $dir = $_GET['dir'] === 'up' ? -1 : 1;
    $cur = dbFetchRow("SELECT id, position FROM forum_categories WHERE id=?", 'i', [$id]);
    if ($cur) {
        $neighbor = dbFetchRow(
            "SELECT id, position FROM forum_categories
              WHERE position " . ($dir < 0 ? '<' : '>') . " ?
              ORDER BY position " . ($dir < 0 ? 'DESC' : 'ASC') . " LIMIT 1",
            'i', [(int)$cur['position']]
        );
        if ($neighbor) {
            dbExecute("UPDATE forum_categories SET position=? WHERE id=?", 'ii', [(int)$neighbor['position'], $id]);
            dbExecute("UPDATE forum_categories SET position=? WHERE id=?", 'ii', [(int)$cur['position'], (int)$neighbor['id']]);
            aflash('success', 'Порядок изменён.');
        }
    }
    header('Location: categories.php'); exit;
}

$cats = dbFetchAll("SELECT c.*, (SELECT COUNT(*) FROM forum_topics t WHERE t.category_id=c.id) AS topics FROM forum_categories c ORDER BY c.position, c.id");

admin_header('Разделы форума');
foreach (agetFlashes() as $f): ?><div class="aflash aflash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>

<?php if ($isFullAdmin): ?>
<div class="panel">
  <h3>Добавить раздел</h3>
  <form method="post" class="grid-form">
    <?= csrfField() ?>
    <input type="text" name="title" class="input" placeholder="Название раздела" required>
    <input type="text" name="description" class="input" placeholder="Описание (необязательно)">
    <button class="btn btn-primary" name="add">Добавить</button>
  </form>
</div>
<?php endif; ?>

<div class="panel">
  <table class="atable full">
    <thead><tr><th>Порядок</th><th>Раздел</th><th>Тем</th><?php if ($isFullAdmin): ?><th>Действия</th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($cats as $c): ?>
      <tr>
        <td><?= (int)$c['position'] ?></td>
        <td>
          <b><?= e($c['title']) ?></b>
          <?php if ($c['description']): ?><div class="muted"><?= e($c['description']) ?></div><?php endif; ?>
          <?php if ($isFullAdmin): ?>
            <form method="post" class="inline-edit" style="margin-top:4px">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <input type="text" name="title" class="input input-sm" value="<?= e($c['title']) ?>" required>
              <input type="text" name="description" class="input input-sm" value="<?= e($c['description'] ?? '') ?>">
              <input type="number" name="position" class="input input-sm" value="<?= (int)$c['position'] ?>" style="width:60px">
              <button class="btn btn-sm" name="edit">Сохранить</button>
            </form>
          <?php endif; ?>
        </td>
        <td><?= (int)$c['topics'] ?></td>
        <?php if ($isFullAdmin): ?>
          <td class="nowrap">
            <a class="btn btn-sm" href="?move=<?= (int)$c['id'] ?>&dir=up">↑</a>
            <a class="btn btn-sm" href="?move=<?= (int)$c['id'] ?>&dir=down">↓</a>
            <a class="btn btn-sm btn-danger" href="?delete=<?= (int)$c['id'] ?>"
               onclick="return confirm('Удалить раздел?')">🗑</a>
          </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php admin_footer(); ?>