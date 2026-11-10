<?php
// Авторизация пользователя.
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

if ($user) { header('Location: index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf($_POST['csrf'] ?? null)) {
        $error = 'Неверный CSRF-токен.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $acc = dbFetchRow("SELECT * FROM accounts WHERE username = ?", 's', [$username]);
        if (!$acc) {
            $error = 'Неверный логин или пароль.';
        } else {
            $ok = false;
            $hash = (string)$acc['password'];
            // Поддержка двух форматов: bcrypt (форум) и SHA-256 (гейм-мод)
            if (str_starts_with($hash, '$2')) {
                $ok = password_verify($password, $hash);
            } else {
                // SHA-256 без соли — такой же, как в гейм-моде
                $ok = hash('sha256', $password) === $hash;
            }
            if (!$ok) {
                $error = 'Неверный логин или пароль.';
            } else {
                session_regenerate_id(true);
                $_SESSION['uid'] = (int)$acc['id'];
                flash('success', 'Добро пожаловать, ' . $acc['username'] . '!');
                header('Location: index.php');
                exit;
            }
        }
    }
}

$pageTitle = 'Вход';
layout_header($pageTitle, null, getFlashes());
?>
<div class="auth-box">
  <h1>Вход на форум</h1>
  <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="panel-form">
    <?= csrfField() ?>
    <div class="form-group"><label>Логин</label>
      <input type="text" name="username" class="input" required autocomplete="username"></div>
    <div class="form-group"><label>Пароль</label>
      <input type="password" name="password" class="input" required autocomplete="current-password"></div>
    <button type="submit" class="btn btn-primary btn-block">Войти</button>
  </form>
  <p class="muted center">Нет аккаунта? <a href="register.php">Регистрация</a></p>
</div>
<?php layout_footer(); ?>