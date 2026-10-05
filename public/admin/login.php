<?php
require __DIR__ . '/../../src/bootstrap.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $user = trim((string)$_POST['username']);
    $ident = client_ip() . '|' . $user;
    if (too_many_fails('admin', $ident)) $err = 'Too many attempts. Please wait 15 minutes.';
    else {
        $a = row('select id,password_hash from admin_users where username=?', [$user]);
        if ($a && verify_pw((string)$_POST['password'], (string)$a['password_hash'])) {
            clear_fails('admin', $ident);
            if (!str_starts_with((string)$a['password_hash'], '$')) // old plain-text password: upgrade to a hash now
                q('update admin_users set password_hash=? where id=?', [password_hash((string)$_POST['password'], PASSWORD_DEFAULT), $a['id']]);
            session_regenerate_id(true); $_SESSION['admin'] = (int)$a['id'];
            redirect(u('admin/'));
        }
        note_fail('admin', $ident); $err = 'Wrong username or password.';
    }
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin login</title>
<link rel="stylesheet" href="<?= u('assets/css/admin.css') ?>"></head><body class="adm login">
<form method="post" class="adm-form" style="width:340px;margin:auto">
  <h2>Admin login</h2><input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
  <label>Username<input name="username" required autofocus></label><label>Password<input name="password" type="password" required></label>
  <?php if ($err): ?><p class="adm-msg"><?= e($err) ?></p><?php endif ?><button class="adm-btn">Log in</button>
</form></body></html>
