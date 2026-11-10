<?php
// Подключение к БД. Используем mysqli с prepared statements.
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function db(): mysqli {
    static $conn = null;
    if ($conn instanceof mysqli) return $conn;
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_errno) {
        http_response_code(500);
        die('Ошибка подключения к базе данных. Проверьте форум/inc/config.php');
    }
    $conn->set_charset(DB_CHARSET);
    return $conn;
}

// Выполнить prepared query: dbQuery("SELECT ... WHERE id = ?", 'i', [5]) => result
function dbQuery(string $sql, string $types = '', array $args = []): mysqli_stmt {
    $stmt = db()->prepare($sql);
    if ($stmt === false) {
        error_log('DB prepare failed: ' . db()->error);
        http_response_code(500);
        die('Ошибка запроса к база данных.');
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$args);
    }
    if (!$stmt->execute()) {
        error_log('DB execute failed: ' . $stmt->error);
        http_response_code(500);
        die('Ошибка выполнения запроса.');
    }
    return $stmt;
}

// Получить result->fetch_all(MYSQLI_ASSOC)
function dbFetchAll(string $sql, string $types = '', array $args = []): array {
    $stmt = dbQuery($sql, $types, $args);
    $res  = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

// Получить одну строку или null
function dbFetchRow(string $sql, string $types = '', array $args = []): ?array {
    $rows = dbFetchAll($sql, $types, $args);
    return $rows[0] ?? null;
}

// Получить одно скалярное значение
function dbFetchValue(string $sql, string $types = '', array $args = []): mixed {
    $rows = dbFetchAll($sql, $types, $args);
    return $rows[0][array_key_first($rows[0] ?? [])] ?? null;
}

// Выполнить INSERT/UPDATE/DELETE, вернуть affected rows
function dbExecute(string $sql, string $types = '', array $args = []): int {
    $stmt = dbQuery($sql, $types, $args);
    return $stmt->affected_rows;
}

function dbLastId(): int {
    return (int) db()->insert_id;
}