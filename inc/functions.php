<?php
// Вспомогательные функции (конвертирование, вывод, роли, уведомления)
declare(strict_types=1);
require_once __DIR__ . '/db.php';

// Подключение пользователя через account_id
// Возвращает данные accounts + форум-роли
function currentUser(): ?array {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['uid'])) return null;
    $uid = (int)$_SESSION['uid'];
    $user = dbFetchRow("SELECT id, username, admin_level, registered_at FROM accounts WHERE id = ?", 'i', [$uid]);
    if (!$user) { unset($_SESSION['uid']); return null; }
    $user['role'] = adminRole((int)$user['admin_level']);
    $cached = $_SESSION['ban_cache'][$uid] ?? null;
    if ($cached) {
        $user['cache_until'] = $cached['until'];
        $user['cache_cur_ban'] = $cached['cur'];
    }
    return $user;
}

// Проверка, забанен ли пользователь прямо сейчас (интеграция с bans)
function isUserBanned(int $uid): ?array {
    $ban = dbFetchRow(
        "SELECT b.id, b.username, b.reason, b.expire_at, b.admin_name, b.created_at
           FROM bans b
           JOIN accounts a ON a.username = b.username
          WHERE a.id = ? AND (b.expire_at IS NULL OR b.expire_at > NOW())
          ORDER BY b.expire_at IS NULL DESC, b.created_at DESC LIMIT 1",
        'i', [$uid]
    );
    return $ban ?: null;
}

// Хэш пароля (form: SHA-256, как в гейм-моде, но на форуме используем password_hash)
function hashPassword(string $plain): string {
    return password_hash($plain, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
}

function verifyPassword(string $plain, string $hash): bool {
    return password_verify($plain, $hash);
}

// CSRF
function csrfToken(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}
function csrfField(): string {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrfToken()) . '">';
}
function checkCsrf(?string $token): bool {
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

// Экранирование
function e(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// Уведомления (flash)
function flash(string $type, string $msg): void {
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}
function getFlashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// Создание уведомления для пользователя
function notifyUser(int $uid, string $type, string $text, ?string $link = null): void {
    dbExecute(
        "INSERT INTO forum_notifications (account_id, type, text, link) VALUES (?,?,?,?)",
        'isss', [$uid, $type, $text, $link]
    );
}

// Количество не прочитанных уведомлений
function unreadNotifications(int $uid): int {
    return (int) dbFetchValue(
        "SELECT COUNT(*) FROM forum_notifications WHERE account_id = ? AND read_at IS NULL", 'i', [$uid]
    );
}

// Обработка упоминаний @ник и уведомления подписчиков темы
function handlePostNotifications(int $topicId, int $authorId, string $body, string $topicTitle): void {
    // упоминания: @Username
    if (preg_match_all('/@([A-Za-z0-9_]{1,24})/', $body, $m)) {
        foreach (array_unique($m[1]) as $name) {
            $t = dbFetchRow("SELECT id FROM accounts WHERE username = ?", 's', [$name]);
            if ($t && (int)$t['id'] !== $authorId) {
                notifyUser((int)$t['id'], 'mention',
                    "@{$name}, вас упомянули в теме «{$topicTitle}»",
                    'topic.php?id=' . $topicId);
            }
        }
    }
    // подписки
    $subs = dbFetchAll(
        "SELECT account_id FROM forum_topic_subscriptions WHERE topic_id = ? AND account_id != ?",
        'ii', [$topicId, $authorId]
    );
    foreach ($subs as $s) {
        notifyUser((int)$s['account_id'], 'reply',
            'Новый ответ в теме «' . $topicTitle . '»',
            'topic.php?id=' . $topicId);
    }
}

// Дозапись о просмотре (счётчик тем)
function bumpTopicView(int $topicId): void {
    dbExecute("UPDATE forum_topics SET views = views + 1 WHERE id = ?", 'i', [$topicId]);
}

// Простой генератор слага для URL
function makeSlug(string $title): string {
    $title = strtolower(trim($title));
    $title = preg_replace('/[^a-z0-9]+/i', '-', $title);
    return trim($title, '-');
}

// Сколько страниц в пагинации
function totalPages(int $total, int $perPage): int {
    return max(1, (int)ceil($total / $perPage));
}