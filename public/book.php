<?php
require __DIR__ . '/../src/bootstrap.php';
require ROOT . '/src/view.php';
$me = require_user();

/* confirmation page */
if (isset($_GET['done'])) {
    $b = row('select * from hotel_restaurant_reservations where id=? and user_id=?', [(int)$_GET['done'], $me['id']]);
    if (!$b) redirect(u('profile.php'));
    page_head('Booking sent — Discover Cambodia'); nav(); ?>
    <main class="wrap section"><div class="panelbox" style="max-width:560px">
      <h2 class="sec-title" style="margin-bottom:6px">Booking sent ✓</h2>
      <p><b><?= e($b['item_name']) ?></b> · <?= e($b['reserve_date']) ?><?= $b['check_out_date'] ? ' → ' . e($b['check_out_date']) : '' ?></p>
      <p class="muted"><?= e($b['booking_details']) ?></p>
      <?php if ((float)$b['estimated_price_usd'] > 0): ?>
        <p>Estimated price: <b class="big">$<?= number_format((float)$b['estimated_price_usd'], 2) ?></b></p>
      <?php else: ?><p class="muted">The venue will confirm the price.</p><?php endif ?>
      <p class="muted">Status: pending. We will contact you on <?= e($b['phone']) ?>.</p>
      <a class="btn-gold upper" href="<?= u('profile.php') ?>"><?= e(t('profile')) ?></a></div></main>
    <?php page_foot(); exit;
}

$type = ($_GET['type'] ?? '') === 'restaurant' ? 'restaurant' : 'hotel';
$item = $type === 'hotel'
    ? row('select id,province_id,name,type,price_range,address from hotels where id=? and is_active=1', [(int)($_GET['id'] ?? 0)])
    : row('select id,province_id,restaurant_name_en as name,cuisine_type as type,price_range,opening_hours as address from place_restaurants where id=?', [(int)($_GET['id'] ?? 0)]);
if (!$item) { http_response_code(404); exit('Not found'); }

preg_match('/\d+(?:\.\d+)?/', (string)$item['price_range'], $m);
$low = isset($m[0]) ? (float)$m[0] : 0.0;           // lowest price in the range, e.g. "$5–15" -> 5
$spent = (float)$me['total_spent'];
$lv = loyalty($spent);
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $P = fn($k) => trim((string)($_POST[$k] ?? ''));
    $date = $P('reserve_date'); $out = $P('check_out_date');
    $guests = max(1, min(50, (int)$P('guests') ?: 1)); $rooms = max(1, min(10, (int)$P('rooms') ?: 1));
    $ts = strtotime($date); $nights = 0;
    if ($P('tourist_name') === '' || $P('phone') === '') $err = 'Name and phone are required.';
    elseif (!$ts || $date < date('Y-m-d')) $err = 'Choose a valid date from today onwards.';
    elseif ($type === 'hotel' && (!strtotime($out) || strtotime($out) <= $ts)) $err = 'Check-out must be after check-in.';
    if (!$err) {
        if ($type === 'hotel') { $nights = (int)round((strtotime($out) - $ts) / 86400); $base = $low * $nights * $rooms; }
        else $base = $low * $guests;
        $final = apply_discount($base, $spent);
        $details = ($base > 0 ? sprintf('Base $%.2f', $base) : 'Price to be confirmed') . ($lv['pct'] > 0 ? sprintf(' · loyalty level %d: -%s%%', $lv['level'], rtrim(rtrim(number_format($lv['pct'], 2), '0'), '.')) : '');
        $upfront = $type === 'restaurant' ? 5.00 : ($final > 0 ? round($final * 0.20, 2) : 0.00); // same rule as the old site
        $dueSql = $upfront > 0 ? "now() + interval '24 hours'" : 'null';
        $id = val('insert into hotel_restaurant_reservations
            (user_id,item_type,hotel_id,restaurant_id,item_name,province_id,tourist_name,phone,email,reserve_date,reserve_time,check_out_date,guests,rooms,room_type,meal_type,table_request,
             payment_method,price_range,estimated_price_usd,estimated_commission_usd,notes,status,payment_status,payment_due_at,payment_amount_usd,price_per_night_usd,nights,booking_details)
            values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'pending\',?,' . $dueSql . ',?,?,?,?) returning id', [
            $me['id'], $type, $type === 'hotel' ? $item['id'] : null, $type === 'restaurant' ? $item['id'] : null, $item['name'], $item['province_id'],
            $P('tourist_name'), $P('phone'), $P('email') ?: null, $date, $P('reserve_time') ?: null, $type === 'hotel' ? $out : null,
            $guests, $type === 'hotel' ? $rooms : null, $P('room_type') ?: null, $P('meal_type') ?: null, $P('table_request') ?: null,
            $P('payment_method') ?: null, $item['price_range'], $final, round($final * 0.10, 2), $P('notes') ?: null,
            $upfront > 0 ? 'unpaid' : 'not_due', $upfront, $type === 'hotel' ? $low : null, $type === 'hotel' ? $nights : null, $details]);
        redirect(u($upfront > 0 ? 'pay.php?type=reservation&ref=' . (int)$id : 'book.php?done=' . (int)$id));
    }
}
$v = fn($k, $d = '') => e($_POST[$k] ?? $d);
page_head('Book ' . $item['name'] . ' — Discover Cambodia'); nav(); ?>
<main class="wrap section">
  <p class="label upper"><?= $type === 'hotel' ? 'Hotel booking' : 'Table reservation' ?></p>
  <h2 class="sec-title"><?= e($item['name']) ?></h2>
  <p class="muted" style="margin-bottom:16px"><?= e($item['type']) ?> · <?= e($item['address']) ?> · <?= e($item['price_range']) ?></p>
  <?php if ($lv['pct'] > 0): ?><p class="ok">Loyalty level <?= $lv['level'] ?>: <?= rtrim(rtrim(number_format($lv['pct'], 2), '0'), '.') ?>% discount is applied automatically.</p><?php endif ?>
  <form method="post" class="panelbox"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <div class="formgrid">
      <label>Your name *<input name="tourist_name" required value="<?= $v('tourist_name', $me['name']) ?>"></label>
      <label>Phone *<input name="phone" required value="<?= $v('phone', $me['phone']) ?>"></label>
      <label>Email<input type="email" name="email" value="<?= $v('email', $me['email']) ?>"></label>
      <label><?= $type === 'hotel' ? 'Check-in' : 'Date' ?> *<input type="date" name="reserve_date" required min="<?= date('Y-m-d') ?>" value="<?= $v('reserve_date') ?>"></label>
      <?php if ($type === 'hotel'): ?>
        <label>Check-out *<input type="date" name="check_out_date" required min="<?= date('Y-m-d') ?>" value="<?= $v('check_out_date') ?>"></label>
        <label>Rooms<input type="number" name="rooms" min="1" max="10" value="<?= $v('rooms', '1') ?>"></label>
        <label>Room type<input name="room_type" placeholder="Single, Double, Fan, Air-con…" value="<?= $v('room_type') ?>"></label>
      <?php else: ?>
        <label>Time<input type="time" name="reserve_time" value="<?= $v('reserve_time') ?>"></label>
        <label>Meal<select name="meal_type"><?php foreach (['Breakfast', 'Lunch', 'Dinner'] as $o): ?><option <?= ($_POST['meal_type'] ?? '') === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach ?></select></label>
        <label>Table request<input name="table_request" placeholder="Window, outdoor, quiet…" value="<?= $v('table_request') ?>"></label>
      <?php endif ?>
      <label>Guests<input type="number" name="guests" min="1" max="50" value="<?= $v('guests', '2') ?>"></label>
      <label>Payment<select name="payment_method"><?php foreach (['Pay at the venue', 'Bank transfer', 'ABA Pay', 'Telegram arrangement'] as $o): ?><option <?= ($_POST['payment_method'] ?? '') === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach ?></select></label>
      <label class="wide">Notes<textarea name="notes" rows="2"><?= $v('notes') ?></textarea></label>
    </div>
    <?php if ($err): ?><p class="err"><?= e($err) ?></p><?php endif ?>
    <button class="btn-gold upper">Send booking</button>
    <p class="muted">The price is an estimate from the venue's lowest listed price; the venue confirms the final amount.</p>
  </form>
</main>
<?php page_foot();
