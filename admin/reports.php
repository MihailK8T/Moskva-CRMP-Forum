<?php
// Админ-панель: жалобы на посты.
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

// Резолв жалобы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve'])) {
    if (!checkCsrf($_POST['csrf'] ?? null)) { aflash('error', 'CSRF.'); }
    else {
        $rid = (int)($_POST['report_id'] ?? 0);
        $action = (string)($_POST['action'] ?? 'resolved'); // resolved|rejected
        dbExecute(
            "UPDATE forum_reports SET status=?, resolved_by=?, resolved_at=NOW() WHERE id=?",
            'sii', [$action === 'rejected' ? 'rejected' : 'resolved', (int)$adminUser['id'], $rid]
        );
        aflash('success', 'Жалоба обработана.');
    }
}

// Статус-фильтр
$status = $_GET['status'] ?? 'open';
$allowed = ['open', 'resolved', 'rejected'];
if (!in_array($status, $allowed)) $status = 'open';

$reports = dbFetchAll(
    "SELECT r.*, a.username AS reporter, p.body AS post_body, t.title AS topic_title,
            t.id AS topic_id, p.id AS post_id
       FROM forum_reports r
       JOIN accounts a ON a.id = r.reporter_id
       JOIN forum_posts p ON p.id = r.post_id
       JOIN forum_topics t ON t.id = p.topic_id
      WHERE r.status = ?
      ORDER BY r.created_at DESC", 's', [$status]
);

admin_header('Жалобы');
foreach (agetFlashes() as $f): ?><div class="aflash aflash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>

<div class="tabs">
  <?php foreach ($allowed as $s): ?>
    <a class="tab <?= $status === $s ? 'active' : '' ?>" href="?status=<?= $s ?>">
      <?= match($s){'open'=>'Открытые','resolved'=>'Решённые','rejected'=>'Отклонённые'} ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if (!$reports): ?><p class="muted">Нет жалоб в этом статусе.</p><?php endif; ?>

<?php foreach ($reports as $r): ?>
  <div class="report-card">
    <div class="report-head">
      <span>Жалоба #<?= (int)$r['id'] ?> от <b><?= e($r['reporter']) ?></b></span>
      <span class="muted time"><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></span>
    </div>
    <div class="report-reason"><b>Причина:</b> <?= e($r['reason']) ?></div>
    <div class="report-post">
      <a class="atitle" href="../topic.php?id=<?= (int)$r['topic_id'] ?>#post-<?= (int)$r['post_id'] ?>"><?= e($r['topic_title']) ?></a>
      <p><?= e(mb_substr(strip_tags(bbcode($r['post_body'])),0,300)) ?>…</p>
    </div>
    <?php if ($status === 'open'): ?>
      <div class="report-actions">
        <form method="post" class="inline-form">
          <?= csrfField() ?>
          <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn-sm" name="action" value="resolved">✓ Решено</button>
          <button class="btn btn-sm btn-danger" name="action" value="rejected">✗ Отклонить</button>
        </form>
        <a class="btn btn-sm btn-sm-outline" href="../topic.php?id=<?= (int)$r['topic_id'] ?>">Открыть тему</a>
      </div>
    <?php else: ?>
      <div class="muted">Обработано: <?= e($r['resolved_by'] ? (string)dbFetchValue("SELECT username FROM accounts WHERE id=?", 'i', [$r['resolved_by']]) : '') ?>, <?= date('d.m.Y H:i', strtotime($r['resolved_at'])) ?></div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<?php admin_footer(); ?>