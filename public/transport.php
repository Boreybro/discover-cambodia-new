<?php
require __DIR__ . '/../src/bootstrap.php';
require ROOT . '/src/view.php';
$type = $_GET['type'] ?? '';
$types = ['taxi', 'tuk_tuk', 'van', 'bus', 'other'];
$sql = "select id,name,vehicle_type,vehicle_model,vehicle_capacity,base_province,price_per_day,vehicle_photo from transport_partners where status='approved' and is_active=1";
$args = [];
if (in_array($type, $types, true)) { $sql .= ' and vehicle_type=?'; $args[] = $type; }
$list = rows($sql . ' order by sort_order,id', $args);
page_head('Transport — Discover Cambodia'); nav(); ?>
<main class="wrap section">
  <p class="label upper"><?= e(t('svc_label')) ?></p>
  <h2 class="sec-title"><?= e(t('svc3')) ?></h2>
  <div class="chips" style="margin-bottom:20px"><a class="chip <?= $type === '' ? 'on' : '' ?>" href="?">All</a>
    <?php foreach ($types as $tp): ?><a class="chip <?= $type === $tp ? 'on' : '' ?>" href="?type=<?= $tp ?>"><?= e(str_replace('_', ' ', $tp)) ?></a><?php endforeach ?></div>
  <div class="grid pcards">
  <?php foreach ($list as $p): ?>
    <div class="gcard"><div class="gimg"><?php if ($p['vehicle_photo']): ?><img src="<?= e(img($p['vehicle_photo'])) ?>" alt="" loading="lazy" onerror="this.remove()"><?php endif ?></div>
      <div class="gbody"><b><?= e($p['vehicle_model'] ?: str_replace('_', ' ', $p['vehicle_type'])) ?></b>
        <small><span class="tag"><?= e(str_replace('_', ' ', $p['vehicle_type'])) ?></span> <?= $p['vehicle_capacity'] ? (int)$p['vehicle_capacity'] . ' seats' : '' ?></small>
        <small><?= e($p['base_province']) ?> · <?= e($p['name']) ?></small>
        <div class="gfoot"><span class="price"><?= $p['price_per_day'] !== null ? '$' . number_format((float)$p['price_per_day'], 0) . ' / day' : 'Ask' ?></span>
        <a class="btn-gold upper" href="<?= u('transport_book.php?id=' . (int)$p['id']) ?>">Book</a></div></div></div>
  <?php endforeach ?>
  </div>
  <?php if (!$list): ?><p class="muted">No approved transport partners found yet.</p><?php endif ?>
</main>
<?php page_foot();
