<?php
require __DIR__ . '/../src/bootstrap.php';
require ROOT . '/src/view.php';
$signup = isset($_GET['signup']) || ($_POST['mode'] ?? '') === 'signup';
$next = $_GET['next'] ?? $_POST['next'] ?? u('index.php');
if (!str_starts_with($next, '/')) $next = u('index.php'); // only local redirects
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = strtolower(trim((string)$_POST['email']));
    $pw = (string)$_POST['password'];
    if ($signup) {
        $name = trim((string)$_POST['name']);
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < 6) $err = 'Enter a name, a valid email and a password of 6+ characters.';
        elseif (val('select 1 from users where lower(email)=?', [$email])) $err = 'That email is already registered.';
        else {
            $id = val('insert into users (name,email,password_hash) values (?,?,?) returning id', [$name, $email, password_hash($pw, PASSWORD_DEFAULT)]);
            session_regenerate_id(true); $_SESSION['uid'] = (int)$id;
            stat_bump('signup_done'); if (($_GET['from'] ?? '') === 'prompt') stat_bump('signup_from_prompt');
            redirect($next);
        }
    } else {
        $ident = client_ip() . '|' . $email;
        if (too_many_fails('login', $ident)) $err = 'Too many attempts. Please wait 15 minutes and try again.';
        else {
            $u = row('select id,password_hash,preferred_lang from users where lower(email)=?', [$email]);
            if ($u && verify_pw($pw, (string)$u['password_hash'])) {
                clear_fails('login', $ident);
                if (in_array($u['preferred_lang'], ['en', 'kh'], true)) setcookie('lang', $u['preferred_lang'], time() + 31536000, '/');
                session_regenerate_id(true); $_SESSION['uid'] = (int)$u['id'];
                if (($_GET['from'] ?? '') === 'prompt') stat_bump('login_from_prompt');
                redirect($next);
            }
            note_fail('login', $ident); $err = 'Wrong email or password.';
        }
    }
}
page_head(($signup ? 'Sign Up' : 'Log In') . ' — Discover Cambodia');
nav(); ?>
<main class="authwrap"><form method="post" class="authbox">
  <h1><?= e($signup ? t('signup') : t('login')) ?></h1>
  <input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="next" value="<?= e($next) ?>"><input type="hidden" name="mode" value="<?= $signup ? 'signup' : 'login' ?>">
  <?php if ($signup): ?><input name="name" required placeholder="<?= e(t('name')) ?>" value="<?= e($_POST['name'] ?? '') ?>"><?php endif ?>
  <input name="email" type="email" required placeholder="<?= e(t('email')) ?>" value="<?= e($_POST['email'] ?? '') ?>">
  <input name="password" type="password" required minlength="6" placeholder="<?= e(t('password')) ?>">
  <?php if ($err): ?><p class="err"><?= e($err) ?></p><?php endif ?>
  <button class="btn-gold upper"><?= e($signup ? t('signup') : t('login')) ?></button>
  <a class="chip" href="?<?= $signup ? '' : 'signup=1&' ?>next=<?= urlencode($next) ?>"><?= $signup ? e(t('login')) : e(t('signup')) ?></a>
</form></main>
<?php page_foot();
