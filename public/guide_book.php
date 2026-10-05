<?php
require __DIR__ . '/../src/bootstrap.php';
require ROOT . '/src/view.php';
require ROOT . '/src/upload.php';
$me = require_user();
$g = row("select id,name,daily_rate_usd,languages,provinces from guides where id=? and status='approved' and is_active=1", [(int)($_GET['id'] ?? 0)]);
if (!$g) { http_response_code(404); exit('Guide not found'); }
$pct = (float)(val("select value from app_settings where key='deposit_pct'") ?: 50);
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $P = fn($k) => trim((string)($_POST[$k] ?? ''));
    try {
        $s = strtotime($P('trip_start')); $en = strtotime($P('trip_end'));
        if ($P('tourist_name') === '' || $P('phone') === '' || $P('home_address') === '' || $P('trip_plan') === '') throw new RuntimeException('Please fill in name, phone, address and trip plan.');
        if (!filter_var($P('email'), FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid email.');
        if ($P('passport_no') === '' && $P('id_card_no') === '') throw new RuntimeException('Enter your passport number or ID card number.');
        if (!$s || !$en || $P('trip_start') < date('Y-m-d') || $en < $s) throw new RuntimeException('Check the trip dates.');
        $days = (int)round(($en - $s) / 86400) + 1;
        $total = round((float)$g['daily_rate_usd'] * $days, 2);
        $deposit = round($total * $pct / 100, 2);
        $pass = save_upload('passport_photo', 'bookings', true); $idp = save_upload('id_card_photo', 'bookings', true);
        $ref = 'GB' . strtoupper(bin2hex(random_bytes(4)));
        q("insert into guide_bookings (user_id,reference,guide_id,tourist_name,nationality,email,phone,home_address,passport_no,passport_photo,id_card_no,id_card_photo,trip_start,trip_end,num_people,trip_plan,special_requests,total_usd,deposit_usd,balance_usd,payment_status,payment_due_at,booking_status)
           values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending',now() + interval '24 hours','pending')", [
            $me['id'], $ref, $g['id'], $P('tourist_name'), $P('nationality') ?: null, $P('email'), $P('phone'), $P('home_address'), $P('passport_no') ?: null, $pass,
            $P('id_card_no') ?: null, $idp, $P('trip_start'), $P('trip_end'), max(1, min(50, (int)$P('num_people') ?: 1)), $P('trip_plan'), $P('special_requests') ?: null,
            $total, $deposit, round($total - $deposit, 2)]);
        redirect(u('pay.php?type=guide&ref=' . $ref));
    } catch (RuntimeException $ex) { $err = $ex->getMessage(); }
}
$v = fn($k, $d = '') => e($_POST[$k] ?? $d);
page_head('Book ' . $g['name'] . ' — Discover Cambodia'); nav(); ?>
<main class="wrap section">
  <p class="label upper">Guide booking</p>
  <h2 class="sec-title"><?= e($g['name']) ?></h2>
  <p class="muted" style="margin-bottom:16px"><?= e($g['languages']) ?> · <?= e($g['provinces']) ?> · $<?= number_format((float)$g['daily_rate_usd'], 0) ?> / day · <?= rtrim(rtrim(number_format($pct, 2), '0'), '.') ?>% deposit to confirm</p>
  <form method="post" enctype="multipart/form-data" class="panelbox"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <div class="formgrid">
      <label>Your name *<input name="tourist_name" required value="<?= $v('tourist_name', $me['name']) ?>"></label>
      <label>Nationality<input name="nationality" value="<?= $v('nationality') ?>"></label>
      <label>Email *<input type="email" name="email" required value="<?= $v('email', $me['email']) ?>"></label>
      <label>Phone *<input name="phone" required value="<?= $v('phone', $me['phone']) ?>"></label>
      <label class="wide">Home address *<textarea name="home_address" rows="2" required><?= $v('home_address') ?></textarea></label>
      <label>Passport number<input name="passport_no" value="<?= $v('passport_no') ?>"></label>
      <label>Passport photo<input type="file" name="passport_photo" accept="image/jpeg,image/png,image/webp,application/pdf"></label>
      <label>ID card number<input name="id_card_no" value="<?= $v('id_card_no') ?>"></label>
      <label>ID card photo<input type="file" name="id_card_photo" accept="image/jpeg,image/png,image/webp,application/pdf"></label>
      <label>Trip start *<input type="date" name="trip_start" required min="<?= date('Y-m-d') ?>" value="<?= $v('trip_start') ?>"></label>
      <label>Trip end *<input type="date" name="trip_end" required min="<?= date('Y-m-d') ?>" value="<?= $v('trip_end') ?>"></label>
      <label>Number of people<input type="number" name="num_people" min="1" max="50" value="<?= $v('num_people', '1') ?>"></label>
      <label class="wide">Trip plan * (places, days, pickup)<textarea name="trip_plan" rows="3" required><?= $v('trip_plan') ?></textarea></label>
      <label class="wide">Special requests<textarea name="special_requests" rows="2"><?= $v('special_requests') ?></textarea></label>
    </div>
    <?php if ($err): ?><p class="err"><?= e($err) ?></p><?php endif ?>
    <button class="btn-gold upper">Continue to deposit</button>
    <p class="muted">Total = daily rate × number of days. Passport/ID files are stored privately.</p>
  </form>
</main>
<?php page_foot();
