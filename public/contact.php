<?php
require __DIR__ . '/../src/bootstrap.php';
require ROOT . '/src/view.php';
$me = user();
$set = array_column(rows("select key,value from app_settings where key like 'contact_%'"), 'value', 'key');
$err = ''; $done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $name = trim((string)($_POST['name'] ?? '')); $info = trim((string)($_POST['info'] ?? '')); $msg = trim((string)($_POST['message'] ?? ''));
    $ip = client_ip();
    if (($_POST['website'] ?? '') !== '') { $done = true; }                                  // hidden spam trap
    elseif (too_many_fails('contact', $ip, 5, 60)) $err = 'Too many messages. Please try again later.';
    elseif ($name === '' || $info === '' || mb_strlen($msg) < 3) $err = 'Please fill in your name, how to reach you, and a message.';
    else {
        q('insert into contact_messages (name,contact,message) values (?,?,?)', [mb_substr($name, 0, 120), mb_substr($info, 0, 160), mb_substr($msg, 0, 3000)]);
        note_fail('contact', $ip);                                                           // counts toward the hourly limit
        $done = true;
    }
}
page_head(t('contact_title') . ' — Discover Cambodia');
nav(); ?>
<main class="wrap section" style="max-width:760px">
  <p class="label upper"><?= e(t('contact')) ?></p>
  <h2 class="sec-title"><?= e(t('contact_title')) ?></h2>
  <p class="muted" style="margin-bottom:18px"><?= e(t('contact_text')) ?></p>
  <?php if ($done): ?>
    <div class="panelbox"><h3>✓</h3><p><?= e(t('contact_ok')) ?></p><a class="chip" href="<?= u('index.php') ?>">← <?= e(t('contact')) ?></a></div>
  <?php else: ?>
    <form method="post" class="panelbox"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
      <label><?= e(t('contact_name')) ?> *<input name="name" required value="<?= e($_POST['name'] ?? ($me['name'] ?? '')) ?>"></label>
      <label><?= e(t('contact_info')) ?> *<input name="info" required value="<?= e($_POST['info'] ?? ($me['email'] ?? '')) ?>"></label>
      <label><?= e(t('contact_msg')) ?> *<textarea name="message" rows="5" required><?= e($_POST['message'] ?? '') ?></textarea></label>
      <?php if ($err): ?><p class="err"><?= e($err) ?></p><?php endif ?>
      <button class="btn-gold upper"><?= e(t('contact_send')) ?></button>
    </form>
  <?php endif ?>
  <?php $links = array_filter([
        'Telegram' => $set['contact_telegram'] ?? '', 'Email' => !empty($set['contact_email']) ? 'mailto:' . $set['contact_email'] : '',
        'Phone' => !empty($set['contact_phone']) ? 'tel:' . $set['contact_phone'] : '', 'Facebook' => $set['contact_facebook'] ?? '']);
        if ($links): ?>
    <h3 class="sec-title" style="font-size:1.4rem;margin:28px 0 12px"><?= e(t('contact_channels')) ?></h3>
    <div class="chips"><?php foreach ($links as $label => $href): ?><a class="chip" target="_blank" rel="noopener" href="<?= e($href) ?>"><?= e($label) ?></a><?php endforeach ?></div>
  <?php endif ?>
</main>
<?php page_foot();
