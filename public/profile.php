<?php
require __DIR__ . '/../src/bootstrap.php';
require ROOT . '/src/view.php';
require ROOT . '/src/upload.php';
$me = require_user();
$msg = ''; $err = ''; $rmsg = ''; $rerr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $act = $_POST['act'] ?? 'save';
    if ($act === 'delete_trip') {                                   // only the owner can delete a trip
        q('delete from trip_places where trip_id=? and exists (select 1 from trip_plans where id=? and user_id=?)', [(int)$_POST['trip_id'], (int)$_POST['trip_id'], $me['id']]);
        q('delete from trip_plans where id=? and user_id=?', [(int)$_POST['trip_id'], $me['id']]);
    } elseif ($act === 'redeem') {                                   // exchange coins for a prize
        $rw = row('select id,name_en,cost_points from point_rewards where id=? and is_active=1', [(int)($_POST['reward_id'] ?? 0)]);
        if (!$rw) $rerr = 'Prize not found.';
        else {
            $pdo = db(); $pdo->beginTransaction();
            try {
                $bal = (int)val('select points_balance from users where id=? for update', [$me['id']]);
                $cost = (int)$rw['cost_points'];
                if ($bal < $cost) { $pdo->rollBack(); $rerr = 'Not enough coins yet.'; }
                else {
                    $rid = val("insert into point_redemptions (user_id,reward_id,reward_name,cost_points,status) values (?,?,?,?,'pending') returning id", [$me['id'], $rw['id'], $rw['name_en'], $cost]);
                    q('update users set points_balance = points_balance - ? where id=?', [$cost, $me['id']]);
                    q("insert into user_points (user_id,points,amount_usd,source_type,source_id,description) values (?,?,null,'reward_redeem',?,?)", [$me['id'], -$cost, $rid, 'Redeemed: ' . $rw['name_en']]);
                    $pdo->commit(); $rmsg = 'Prize requested ✓ We will confirm it soon.';
                }
            } catch (Throwable $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log($ex->getMessage()); $rerr = 'Could not redeem. Please try again.'; }
        }
    } elseif ($act === 'remove_stop') {
        q('delete from trip_places tp using trip_plans t where tp.id=? and t.id=tp.trip_id and t.user_id=?', [(int)$_POST['stop_id'], $me['id']]);
    } else {
        try {
            $avatar = save_upload('avatar', 'avatars');
            q('update users set name=?, phone=?, bio=?, preferred_lang=?' . ($avatar ? ', avatar=?' : '') . ' where id=?', array_filter([
                trim((string)$_POST['name']) ?: $me['name'], trim((string)$_POST['phone']) ?: null, trim((string)$_POST['bio']) ?: null,
                in_array($_POST['preferred_lang'] ?? '', ['en', 'kh'], true) ? $_POST['preferred_lang'] : 'en', $avatar, $me['id']], fn($v) => $v !== false));
            $msg = t('saved');
        } catch (RuntimeException $ex) { $err = $ex->getMessage(); }
    }
    $me = row('select id,name,email,phone,bio,avatar,preferred_lang,total_spent,points_balance from users where id=?', [$me['id']]);
}
$spent = (float)$me['total_spent'];
$lv = loyalty($spent);
$next = $lv['next'];
$pct = $next ? min(100, max(0, ($spent - $lv['min']) / max(1, (float)$next['min_spent'] - $lv['min']) * 100)) : 100;
$fmt = fn($n) => rtrim(rtrim(number_format((float)$n, 2), '0'), '.');

$marks = rows('select p.id,p.name_en,p.name_kh,p.image_url,p.images,c.color cat_color,c.name_en cat_en,c.name_kh cat_kh from bookmarks b join places p on p.id=b.place_id left join categories c on c.id=p.category_id where b.user_id=? order by b.id desc', [$me['id']]);
$tripRows = rows('select t.id,t.title,tp.id stop_id,tp.day_number,p.id pid,p.name_en,p.name_kh from trip_plans t left join trip_places tp on tp.trip_id=t.id left join places p on p.id=tp.place_id where t.user_id=? order by t.id desc, tp.day_number, tp.id', [$me['id']]);
$trips = [];
foreach ($tripRows as $r) { $trips[$r['id']]['title'] = $r['title']; if ($r['stop_id']) $trips[$r['id']]['stops'][] = $r; }
$reservations = rows('select id,item_type,item_name,reserve_date,status,payment_status,estimated_price_usd,payment_amount_usd,booking_details from hotel_restaurant_reservations where user_id=? order by id desc limit 20', [$me['id']]);
$pb = rows("select 'guide' kind, reference, trip_start d, total_usd, deposit_usd, payment_status, booking_status from guide_bookings where user_id=? union all select 'transport', reference, trip_date, total_usd, deposit_usd, payment_status, booking_status from transport_bookings where user_id=? order by d desc limit 20", [$me['id'], $me['id']]);
$coins = (int)$me['points_balance'];
$prizes = rows('select id,name_en,name_kh,description_en,cost_points from point_rewards where is_active=1 order by sort_order,id');
$coinLog = rows('select points,description,created_at from user_points where user_id=? order by id desc limit 8', [$me['id']]);
$redeemed = rows('select reward_name,cost_points,status,admin_notes from point_redemptions where user_id=? order by id desc limit 8', [$me['id']]);
$apps = rows("select 'guide' kind, id, status, admin_notes from guide_applications where user_id=? union all select 'transport', id, status, admin_notes from transport_applications where user_id=? order by id desc", [$me['id'], $me['id']]);

page_head(t('profile') . ' — Discover Cambodia');
nav(); ?>
<main class="wrap section">
  <p class="label upper"><?= e(t('profile')) ?></p>
  <h2 class="sec-title"><?= e($me['name']) ?></h2>
  <div class="two">
    <form method="post" enctype="multipart/form-data" class="panelbox">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="act" value="save">
      <?php if ($me['avatar']): ?><img class="avatar" src="<?= e(img($me['avatar'])) ?>" alt=""><?php endif ?>
      <label>Photo (JPG/PNG/WEBP)<input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"></label>
      <label><?= e(t('name')) ?><input name="name" value="<?= e($me['name']) ?>" required></label>
      <label><?= e(t('email')) ?><input value="<?= e($me['email']) ?>" disabled></label>
      <label><?= e(t('phone')) ?><input name="phone" value="<?= e($me['phone']) ?>"></label>
      <label><?= e(t('bio')) ?><textarea name="bio" rows="3"><?= e($me['bio']) ?></textarea></label>
      <label>Language<select name="preferred_lang"><option value="en" <?= $me['preferred_lang'] === 'en' ? 'selected' : '' ?>>English</option><option value="kh" <?= $me['preferred_lang'] === 'kh' ? 'selected' : '' ?>>ខ្មែរ</option></select></label>
      <?php if ($msg): ?><p class="ok"><?= e($msg) ?></p><?php endif ?><?php if ($err): ?><p class="err"><?= e($err) ?></p><?php endif ?>
      <div class="row"><button class="btn-gold upper"><?= e(t('save')) ?></button><a class="chip" href="<?= u('logout.php') ?>"><?= e(t('logout')) ?></a></div>
    </form>
    <div class="panelbox">
      <h3>Loyalty level <?= $lv['level'] ?></h3>
      <p class="big"><?= $fmt($lv['pct']) ?>% discount on hotels &amp; restaurants</p>
      <div class="bar"><i style="width:<?= round($pct) ?>%"></i></div>
      <p class="muted">Spent $<?= number_format($spent, 2) ?><?= $next ? ' · next level at $' . number_format((float)$next['min_spent']) . ' (' . $fmt($next['discount_pct']) . '%)' : ' · top level reached' ?></p>
      <p class="muted"><small><?= e(t('spend_hint')) ?></small></p>
      <div class="row"><a class="btn-gold upper" href="<?= u('planner.php') ?>"><?= e(t('planner')) ?></a><a class="chip" href="<?= u('guides.php') ?>"><?= e(t('svc1')) ?></a><a class="chip" href="<?= u('transport.php') ?>"><?= e(t('svc3')) ?></a></div>
      <?php if ($apps): ?><h4>My applications</h4>
        <?php foreach ($apps as $a): ?><p><span class="tag"><?= e($a['kind']) ?></span> <b class="upper"><?= e($a['status']) ?></b><?= $a['admin_notes'] ? ' · ' . e($a['admin_notes']) : '' ?></p><?php endforeach ?>
      <?php else: ?><p class="muted" style="margin-top:12px"><a href="<?= u('apply.php?type=guide') ?>"><?= e(t('svc2')) ?></a> · <a href="<?= u('apply.php?type=transport') ?>"><?= e(t('svc4')) ?></a></p><?php endif ?>
    </div>
  </div>

  <h3 class="sec-title" id="rewards" style="margin-top:40px"><?= e(t('rewards')) ?></h3>
  <div class="panelbox">
    <p class="coins">🪙 <b><?= $coins ?></b> <?= e(t('coins')) ?></p>
    <p class="muted"><?= e(t('earn_hint')) ?></p>
    <?php if ($rmsg): ?><p class="ok"><?= e($rmsg) ?></p><?php endif ?><?php if ($rerr): ?><p class="err"><?= e($rerr) ?></p><?php endif ?>
    <div class="grid prizes">
      <?php foreach ($prizes as $pz): $can = $coins >= (int)$pz['cost_points']; ?>
        <form method="post" class="prize"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="act" value="redeem"><input type="hidden" name="reward_id" value="<?= (int)$pz['id'] ?>">
          <b><?= e(pick($pz)) ?></b><small><?= e($pz['description_en']) ?></small>
          <span class="price">🪙 <?= (int)$pz['cost_points'] ?></span>
          <button class="btn-gold upper" <?= $can ? '' : 'disabled' ?>><?= $can ? e(t('redeem')) : e(t('need_more')) . ' ' . ((int)$pz['cost_points'] - $coins) ?></button></form>
      <?php endforeach ?>
    </div>
    <?php if ($redeemed): ?><h4><?= e(t('my_prizes')) ?></h4>
      <?php foreach ($redeemed as $rd): ?><p><b><?= e($rd['reward_name']) ?></b> · 🪙 <?= (int)$rd['cost_points'] ?> · <span class="tag"><?= e($rd['status']) ?></span><?= $rd['admin_notes'] ? ' · ' . e($rd['admin_notes']) : '' ?></p><?php endforeach ?><?php endif ?>
    <?php if ($coinLog): ?><h4><?= e(t('coin_history')) ?></h4>
      <?php foreach ($coinLog as $cl): ?><p class="coinrow"><b class="<?= (float)$cl['points'] >= 0 ? 'ok' : 'err' ?>"><?= (float)$cl['points'] >= 0 ? '+' : '' ?><?= (int)$cl['points'] ?></b> <?= e($cl['description']) ?> <small class="muted"><?= e(substr((string)$cl['created_at'], 0, 10)) ?></small></p><?php endforeach ?><?php endif ?>
  </div>

  <h3 class="sec-title" id="trips" style="margin-top:40px">My trips</h3>
  <?php foreach ($trips as $tid => $tr): ?>
    <div class="panelbox" style="margin-bottom:12px"><div class="row" style="align-items:center"><b><?= e($tr['title']) ?></b>
      <form method="post" onsubmit="return confirm('Delete this trip?')" style="margin-left:auto"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="act" value="delete_trip"><input type="hidden" name="trip_id" value="<?= (int)$tid ?>"><button class="chip">Delete trip</button></form></div>
      <?php foreach ($tr['stops'] ?? [] as $s): ?>
        <form method="post" class="stop"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="act" value="remove_stop"><input type="hidden" name="stop_id" value="<?= (int)$s['stop_id'] ?>">
          <span class="tag">Day <?= (int)$s['day_number'] ?></span> <a href="#" data-place="<?= (int)$s['pid'] ?>"><?= e($s['name_en']) ?></a> <small class="muted"><?= e($s['name_kh']) ?></small> <button class="chip" title="Remove">✕</button></form>
      <?php endforeach ?>
      <?php if (empty($tr['stops'])): ?><p class="muted">No places yet — add some with the 🧭 Trip button on any place.</p><?php endif ?></div>
  <?php endforeach ?>
  <?php if (!$trips): ?><p class="muted">No trips yet. <a href="<?= u('planner.php') ?>"><?= e(t('make_plan')) ?></a></p><?php endif ?>

  <h3 class="sec-title" style="margin-top:40px"><?= e(t('my_bookings')) ?></h3>
  <?php if ($reservations || $pb): ?><div class="adm-wrap"><table class="simple">
    <?php foreach ($reservations as $b): ?>
      <tr><td class="upper"><?= e($b['item_type']) ?></td><td><b><?= e($b['item_name']) ?></b><br><small class="muted"><?= e($b['booking_details']) ?></small></td><td><?= e($b['reserve_date']) ?></td>
      <td>$<?= number_format((float)$b['estimated_price_usd'], 2) ?></td><td class="upper"><?= e($b['status']) ?> · <?= e(str_replace('_', ' ', $b['payment_status'])) ?></td>
      <td><?php if (in_array($b['payment_status'], ['unpaid', 'pending_review'], true)): ?><a class="chip" href="<?= u('pay.php?type=reservation&ref=' . (int)$b['id']) ?>"><?= $b['payment_status'] === 'unpaid' ? 'Pay deposit' : 'Details' ?></a><?php endif ?></td></tr>
    <?php endforeach ?>
    <?php foreach ($pb as $b): ?>
      <tr><td class="upper"><?= e($b['kind']) ?></td><td><b><?= e($b['reference']) ?></b></td><td><?= e($b['d']) ?></td><td>$<?= number_format((float)$b['total_usd'], 2) ?></td>
      <td class="upper"><?= e($b['booking_status']) ?> · <?= e(str_replace('_', ' ', $b['payment_status'])) ?></td>
      <td><a class="chip" href="<?= u('pay.php?type=' . $b['kind'] . '&ref=' . urlencode($b['reference'])) ?>"><?= $b['payment_status'] === 'pending' ? 'Pay deposit' : 'Details' ?></a></td></tr>
    <?php endforeach ?></table></div>
  <?php else: ?><p class="muted">—</p><?php endif ?>

  <h3 class="sec-title" style="margin-top:40px"><?= e(t('bookmark')) ?></h3>
  <div class="grid"><?php foreach ($marks as $p) place_card($p); ?></div>
  <?php if (!$marks): ?><p class="muted">—</p><?php endif ?>
</main>
<?php page_foot();
