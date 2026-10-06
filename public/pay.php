<?php
require __DIR__ . '/../src/bootstrap.php';
require ROOT . '/src/view.php';
require ROOT . '/src/upload.php';
$me = require_user();
$type = in_array($_GET['type'] ?? '', ['guide', 'transport', 'reservation'], true) ? $_GET['type'] : 'guide';
$table = ['guide' => 'guide_bookings', 'transport' => 'transport_bookings', 'reservation' => 'hotel_restaurant_reservations'][$type];
$load = function () use ($type, $table, $me) {
    if ($type === 'reservation')
        return row("select *, 'R' || id as reference, estimated_price_usd as total_usd, payment_amount_usd as deposit_usd,
                    coalesce(estimated_price_usd,0) - coalesce(payment_amount_usd,0) as balance_usd, status as booking_status
                    from $table where id=? and user_id=?", [(int)($_GET['ref'] ?? 0), $me['id']]);
    return row("select * from $table where reference=? and user_id=?", [(string)($_GET['ref'] ?? ''), $me['id']]);
};
$b = $load();
if (!$b) redirect(u('profile.php'));
$open = $type === 'reservation' ? ['unpaid', 'pending_review'] : ['pending', 'pending_review'];
$info = (string)val("select value from app_settings where key='payment_info'");
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($b['payment_status'], $open, true)) {
    check_csrf();
    try {
        $path = save_upload('proof', 'payments', true);
        if (!$path) throw new RuntimeException('Choose your payment receipt (JPG, PNG or PDF).');
        q("update $table set payment_proof=?, payment_reference=?, payment_status='pending_review' where id=?", [$path, trim((string)($_POST['payment_reference'] ?? '')) ?: null, $b['id']]);
        $b = $load();
        $msg = 'Receipt sent ✓ We will confirm your deposit shortly.';
    } catch (RuntimeException $ex) { $err = $ex->getMessage(); }
}
$labels = ['pending' => 'Waiting for deposit', 'unpaid' => 'Waiting for deposit', 'pending_review' => 'Receipt received – under review', 'deposit_paid' => 'Deposit paid ✓',
           'paid' => 'Paid ✓', 'fully_paid' => 'Fully paid ✓', 'not_due' => 'No deposit needed', 'refunded' => 'Refunded', 'expired' => 'Expired'];
page_head('Payment — Discover Cambodia'); nav(); ?>
<main class="wrap section">
  <p class="label upper">Booking <?= e($b['reference']) ?></p>
  <h2 class="sec-title">Pay your deposit</h2>
  <div class="two">
    <div class="panelbox">
      <p>Total <b>$<?= number_format((float)$b['total_usd'], 2) ?></b></p>
      <p class="big">Deposit now: $<?= number_format((float)$b['deposit_usd'], 2) ?></p>
      <p class="muted">Balance later: $<?= number_format((float)$b['balance_usd'], 2) ?></p>
      <p>Status: <b><?= e($labels[$b['payment_status']] ?? $b['payment_status']) ?></b> · Booking: <b class="upper"><?= e($b['booking_status']) ?></b></p>
      <?php if ($b['payment_due_at'] && in_array($b['payment_status'], ['pending', 'unpaid'], true)): ?><p class="muted">Please pay before <?= e(substr((string)$b['payment_due_at'], 0, 16)) ?>.</p><?php endif ?>
      <h4>How to pay</h4><p><?= nl2br(e($info)) ?></p><p>Reference to write in the transfer: <b><?= e($b['reference']) ?></b></p><?php qr_block($qrs); ?>
    </div>
    <div class="panelbox">
      <?php if (in_array($b['payment_status'], $open, true)): ?>
        <form method="post" enctype="multipart/form-data" style="display:grid;gap:12px"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
          <label>Payment receipt (JPG/PNG/PDF)<input type="file" name="proof" required accept="image/jpeg,image/png,image/webp,application/pdf"></label>
          <label>Bank transaction number (optional)<input name="payment_reference" value="<?= e($b['payment_reference']) ?>"></label>
          <button class="btn-gold upper"><?= $b['payment_proof'] ? 'Replace receipt' : 'Send receipt' ?></button></form>
      <?php else: ?><p class="muted">Nothing more to pay online right now.</p><?php endif ?>
      <?php if ($msg): ?><p class="ok"><?= e($msg) ?></p><?php endif ?><?php if ($err): ?><p class="err"><?= e($err) ?></p><?php endif ?>
      <a class="chip" href="<?= u('profile.php') ?>"><?= e(t('profile')) ?></a>
    </div>
  </div>
</main>
<?php page_foot();
