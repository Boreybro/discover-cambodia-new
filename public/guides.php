<?php
require __DIR__ . '/../src/bootstrap.php';
require ROOT . '/src/view.php';
$q = trim((string)($_GET['q'] ?? ''));
$sql = "select id,name,languages,provinces,daily_rate_usd,years_exp,profile_photo,bio from guides where status='approved' and is_active=1";
$args = [];
if ($q !== '') { $sql .= ' and (provinces ilike ? or languages ilike ? or name ilike ?)'; $args = array_fill(0, 3, '%' . $q . '%'); }
$guides = rows($sql . ' order by sort_order,id', $args);
page_head('Guides — Discover Cambodia'); nav(); ?>
<main class="wrap section">
  <p class="label upper"><?= e(t('svc_label')) ?></p>
  <h2 class="sec-title"><?= e(t('svc1')) ?></h2>
  <form class="row" style="margin-bottom:20px"><input name="q" value="<?= e($q) ?>" placeholder="Province, language or name…" style="max-width:320px;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:8px;padding:10px 12px"><button class="btn-gold upper">Search</button></form>
  <div class="grid pcards">
  <?php foreach ($guides as $g): ?>
    <div class="gcard"><div class="gimg"><?php if ($g['profile_photo']): ?><img src="<?= e(img($g['profile_photo'])) ?>" alt="" loading="lazy" onerror="this.remove()"><?php endif ?></div>
      <div class="gbody"><b><?= e($g['name']) ?></b>
        <small><?= e($g['languages']) ?></small><small><?= e($g['provinces']) ?></small>
        <small><?= $g['years_exp'] !== null ? (int)$g['years_exp'] . ' yrs experience' : '' ?></small>
        <p><?= e(mb_strimwidth((string)$g['bio'], 0, 110, '…')) ?></p>
        <div class="gfoot"><span class="price"><?= $g['daily_rate_usd'] !== null ? '$' . number_format((float)$g['daily_rate_usd'], 0) . ' / day' : 'Ask' ?></span>
        <a class="btn-gold upper" href="<?= u('guide_book.php?id=' . (int)$g['id']) ?>">Book</a></div></div></div>
  <?php endforeach ?>
  </div>
  <?php if (!$guides): ?><p class="muted">No approved guides found yet.</p><?php endif ?>
</main>
<?php page_foot();
