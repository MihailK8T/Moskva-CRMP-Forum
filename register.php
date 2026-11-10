<?php
// Регистрация участника форума.
// Создаёт запись в accounts (та же таблица, что используется игровым сервером SAMP).
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

if ($user) { header('Location: index.php'); exit; }

// Если регистрация закрыта
$regOpen = (int)dbFetchValue("SELECT svalue FROM forum_settings WHERE skey='registration_open'");
if ($regOpen === 0) { die('Регистрация временно закрыта.'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf($_POST['csrf'] ?? null)) {
        $errors[] = 'Неверный CSRF-токен.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $password2 = (string)($_POST['password2'] ?? '');

        // Валидация ника: 3-20 символов, буквы/цифры/символы подчёркивания
        if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
            $errors[] = 'Логин должен быть 3-20 символов: латиница, цифры, _.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'Пароль не короче 6 символов.';
        }
        if ($password !== $password2) {
            $errors[] = 'Пароли не совпадают.';
        }

        if (!$errors) {
            // Ник не зарегистрирован в игре? Обычно форум и игра делят accounts.
            // Проверяем уникальность
            $exists = dbFetchRow("SELECT id FROM accounts WHERE username = ?", 's', [$username]);
            if ($exists) {
                $errors[] = 'Этот ник уже зарегистрирован. Войдите.';
            } else {
                $hash = hashPassword($password); // bcrypt
                dbExecute(
                    "INSERT INTO accounts (username, password, admin_level) VALUES (?,?,0)",
                    'ss', [$username, $hash]
                );
                $newId = dbLastId();
                dbExecute("INSERT INTO forum_profiles (account_id) VALUES (?)", 'i', [$newId]);
                // Сразу логин
                session_regenerate_id(true);
                $_SESSION['uid'] = $newId;
                flash('success', 'Регистрация успешна! Добро пожаловать.');
                header('Location: index.php');
                exit;
            }
        }
    }
}

$pageTitle = 'Регистрация';
layout_header($pageTitle, null, getFlashes());
?>
<div class="auth-box">
  <h1>Регистрация</h1>
  <?php foreach ($errors as $er): ?><div class="flash flash-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" class="panel-form">
    <?= csrfField() ?>
    <div class="form-group"><label>Логин</label>
      <input type="text" name="username" class="input" required minlength="3" maxlength="20" pattern="[A-Za-z0-9_]+"></div>
    <div class="form-group"><label>Пароль</label>
      <input type="password" name="password" class="input" required minlength="6"></div>
    <div class="form-group"><label>Повторите пароль</label>
      <input type="password" name="password2" class="input" required minlength="6"></div>
    <button type="submit" class="btn btn-primary btn-block">Зарегистрироваться</button>
  </form>
  <p class="muted center">Уже есть аккаунт? <a href="login.php">Вход</a></p>
</div>
<?php layout_footer(); ?>