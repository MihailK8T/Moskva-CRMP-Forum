<?php
// КОНФИГУРАЦИЯ ФОРУМА — скопируйте этот файл в inc/config.php и заполните значения.
// ВАЖНО: inc/config.php исключён из git (.gitignore) и не попадает в репозиторий,
// чтобы не утекали пароли.
declare(strict_types=1);

// --- База данных (общая с SAMP RP / CRMP: samp_rp) ---
const DB_HOST   = '127.0.0.1';
const DB_USER   = 'samp';
const DB_PASS   = 'ваш_пароль';
const DB_NAME   = 'samp_rp';
const DB_CHARSET = 'utf8mb4';

// --- Сайт ---
const SITE_URL  = 'http://localhost/forum/'; // с закрывающим слэшем
define('BASE_DIR', __DIR__ . '/');

// --- Криптография ---
const SECRET_KEY = 'сгенерируйте_длинную_случайную_строку';
const PASSWORD_COST = 12;

// Уровни админки в игре -> роль на форуме
// 0 = обычный, 1-2 = модератор, 3+ = администратор
function adminRole(int $level): string {
    if ($level >= 3) return 'admin';
    if ($level >= 1) return 'mod';
    return 'user';
}

// Роли, которым доступна админ-панель
function isAdminRole(string $role): bool {
    return $role === 'admin' || $role === 'mod';
}