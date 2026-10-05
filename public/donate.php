<?php
require __DIR__ . '/../src/bootstrap.php';
require ROOT . '/src/view.php';
$me = user();
$info = (string)val("select value from app_settings where key='payment_info'");
$err = ''; $done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $amt = round((float)($_POST['amount_usd'] ?? 0), 2);
    if ($amt < 1) $err = 'The minimum donation is $1.';
    else {
        q("insert into donations (user_id,donor_name,amount_usd,message,status) values (?,?,?,?,'pending')",
          [$me['id'] ?? null, trim((string)($_POST['donor_name'] ?? '')) ?: 'Anonymous', $amt, mb_substr(trim((string)($_POST['message'] ?? '')), 0, 500) ?: null]);
        $done = true;
    }
}
page_head('Donate — Discover Cambodia'); nav(); ?>
<main class="wrap section" style="max-width:640px">
  <p class="label upper">Support us</p>
  <h2 class="sec-title"><?= e(t('donate_t')) ?></h2>
  <?php if ($done): ?>
    <div class="panelbox"><h3>Thank you! 🙏</h3><p>Your pledge is saved. Please send it using the details below — we mark it as received once we see it.</p><p><?= nl2br(e($info)) ?></p><a class="chip" href="<?= u('index.php') ?>">← Home</a></div>
  <?php else: ?>
    <form method="post" class="panelbox"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <label>Amount (USD) *<input type="number" name="amount_usd" min="1" step="1" required value="<?= e($_POST['amount_usd'] ?? '5') ?>"></label>
      <label>Your name<input name="donor_name" value="<?= e($_POST['donor_name'] ?? ($me['name'] ?? '')) ?>" placeholder="Anonymous"></label>
      <label>Message<textarea name="message" rows="3"><?= e($_POST['message'] ?? '') ?></textarea></label>
      <?php if ($err): ?><p class="err"><?= e($err) ?></p><?php endif ?>
      <button class="btn-gold upper"><?= e(t('donate_b')) ?></button>
    </form>
  <?php endif ?>
</main>
<?php page_foot();
