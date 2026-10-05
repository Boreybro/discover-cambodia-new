<?php
require __DIR__ . '/../src/bootstrap.php';
require ROOT . '/src/view.php';
$me = require_user();
$p = row("select id,name,vehicle_type,vehicle_model,vehicle_capacity,base_province,price_per_day from transport_partners where id=? and status='approved' and is_active=1", [(int)($_GET['id'] ?? 0)]);
if (!$p) { http_response_code(404); exit('Not found'); }
$pct = (float)(val("select value from app_settings where key='deposit_pct'") ?: 50);
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $P = fn($k) => trim((string)($_POST[$k] ?? ''));
    try {
        if ($P('tourist_name') === '' || $P('phone') === '' || $P('pickup_location') === '' || $P('dropoff_location') === '') throw new RuntimeException('Please fill in name, phone, pickup and drop-off.');
        if (!filter_var($P('email'), FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid email.');
        if (!strtotime($P('trip_date')) || $P('trip_date') < date('Y-m-d') || !preg_match('/^\d\d:\d\d$/', $P('trip_time'))) throw new RuntimeException('Check the date and time.');
        $pax = max(1, min(60, (int)$P('num_passengers') ?: 1)); $days = max(1, min(30, (int)$P('days') ?: 1));
        if ($p['vehicle_capacity'] && $pax > (int)$p['vehicle_capacity']) throw new RuntimeException('This vehicle seats up to ' . (int)$p['vehicle_capacity'] . ' passengers.');
        $total = round((float)$p['price_per_day'] * $days, 2); $deposit = round($total * $pct / 100, 2);
        $ref = 'TB' . strtoupper(bin2hex(random_bytes(4)));
        q("insert into transport_bookings (user_id,reference,partner_id,tourist_name,passport_id_no,email,phone,vehicle_type,pickup_location,dropoff_location,trip_date,trip_time,num_passengers,notes,total_usd,deposit_usd,balance_usd,payment_status,payment_due_at,booking_status)
           values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending',now() + interval '24 hours','pending')", [
            $me['id'], $ref, $p['id'], $P('tourist_name'), $P('passport_id_no') ?: null, $P('email'), $P('phone'), $p['vehicle_type'], $P('pickup_location'), $P('dropoff_location'),
            $P('trip_date'), $P('trip_time'), $pax, "Days: $days" . ($P('notes') !== '' ? ' · ' . $P('notes') : ''), $total, $deposit, round($total - $deposit, 2)]);
        redirect(u('pay.php?type=transport&ref=' . $ref));
    } catch (RuntimeException $ex) { $err = $ex->getMessage(); }
}
$v = fn($k, $d = '') => e($_POST[$k] ?? $d);
page_head('Book transport — Discover Cambodia'); nav(); ?>
<main class="wrap section">
  <p class="label upper">Transport booking</p>
  <h2 class="sec-title"><?= e($p['vehicle_model'] ?: str_replace('_', ' ', $p['vehicle_type'])) ?></h2>
  <p class="muted" style="margin-bottom:16px"><?= e(str_replace('_', ' ', $p['vehicle_type'])) ?> · <?= $p['vehicle_capacity'] ? (int)$p['vehicle_capacity'] . ' seats · ' : '' ?><?= e($p['base_province']) ?> · $<?= number_format((float)$p['price_per_day'], 0) ?> / day · <?= rtrim(rtrim(number_format($pct, 2), '0'), '.') ?>% deposit</p>
  <form method="post" class="panelbox"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <div class="formgrid">
      <label>Your name *<input name="tourist_name" required value="<?= $v('tourist_name', $me['name']) ?>"></label>
      <label>Passport / ID number<input name="passport_id_no" value="<?= $v('passport_id_no') ?>"></label>
      <label>Email *<input type="email" name="email" required value="<?= $v('email', $me['email']) ?>"></label>
      <label>Phone *<input name="phone" required value="<?= $v('phone', $me['phone']) ?>"></label>
      <label class="wide">Pickup location *<input name="pickup_location" required value="<?= $v('pickup_location') ?>"></label>
      <label class="wide">Drop-off location *<input name="dropoff_location" required value="<?= $v('dropoff_location') ?>"></label>
      <label>Date *<input type="date" name="trip_date" required min="<?= date('Y-m-d') ?>" value="<?= $v('trip_date') ?>"></label>
      <label>Time *<input type="time" name="trip_time" required value="<?= $v('trip_time', '08:00') ?>"></label>
      <label>Passengers<input type="number" name="num_passengers" min="1" max="60" value="<?= $v('num_passengers', '1') ?>"></label>
      <label>Number of days<input type="number" name="days" min="1" max="30" value="<?= $v('days', '1') ?>"></label>
      <label class="wide">Notes<textarea name="notes" rows="2"><?= $v('notes') ?></textarea></label>
    </div>
    <?php if ($err): ?><p class="err"><?= e($err) ?></p><?php endif ?>
    <button class="btn-gold upper">Continue to deposit</button>
  </form>
</main>
<?php page_foot();
