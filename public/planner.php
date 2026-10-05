<?php
require __DIR__ . '/../src/bootstrap.php';
require ROOT . '/src/view.php';

function km(array $a, array $b): float {
    if ($a['latitude'] === null || $b['latitude'] === null) return 0.0;
    [$la1, $lo1, $la2, $lo2] = array_map('deg2rad', [(float)$a['latitude'], (float)$a['longitude'], (float)$b['latitude'], (float)$b['longitude']]);
    $h = sin(($la2 - $la1) / 2) ** 2 + cos($la1) * cos($la2) * sin(($lo2 - $lo1) / 2) ** 2;
    return 12742 * asin(sqrt($h));
}
/** Visit order inside one day: nearest-neighbour walk from the first stop. */
function route(array $stops): array {
    if (count($stops) < 3) return $stops;
    $out = [array_shift($stops)];
    while ($stops) {
        $last = end($out); $best = 0; $bd = INF;
        foreach ($stops as $i => $s) { $d = km($last, $s); if ($d < $bd) { $bd = $d; $best = $i; } }
        $out[] = $stops[$best]; array_splice($stops, $best, 1);
    }
    return $out;
}
function opt(string $k, array $allowed, string $def): string { $v = $_REQUEST[$k] ?? ''; return in_array($v, $allowed, true) ? $v : $def; }

$provs = rows('select id,slug,name_en,name_kh from provinces order by name_en');
$slugOf = array_column($provs, 'slug', 'id');
$sel = array_map('strval', (array)($_REQUEST['provs'] ?? []));
foreach (explode(',', (string)($_REQUEST['provinces'] ?? '')) as $s) if (trim($s) !== '') $sel[] = trim($s);
if (!empty($_REQUEST['province']) && isset($slugOf[(int)$_REQUEST['province']])) $sel[] = $slugOf[(int)$_REQUEST['province']];
$sel = array_values(array_unique(array_intersect($sel, array_values($slugOf))));
$days = max(1, min(14, (int)($_REQUEST['days'] ?? 3)));
$budget = max(10, (float)($_REQUEST['budget'] ?? 120));
$transport = opt('transport', ['walk', 'tuk_tuk', 'car'], 'tuk_tuk');
$intensity = opt('intensity', ['relaxed', 'normal', 'packed'], 'normal');
$group = opt('group', ['solo', 'friends', 'family'], 'friends');
$cats = rows('select slug,name_en,name_kh from categories order by sort_order,id');
$tags = array_values(array_intersect(array_map('strval', (array)($_REQUEST['tags'] ?? [])), array_column($cats, 'slug')));

$plan = null; $total = 0.0; $saved = false;
if ($sel) {
    $slots = ['relaxed' => 2, 'normal' => 3, 'packed' => 4][$intensity];
    $maxEntry = ($budget / $days) * 0.4;
    $zone = $transport === 'walk' ? " and (p.zone = 'city' or p.zone is null)" : ($transport === 'tuk_tuk' ? " and (p.zone in ('city','near','mid') or p.zone is null)" : '');
    $kids = $group === 'family' ? ' and (p.kids_friendly = 1 or p.kids_friendly is null)' : '';
    $args = [...$sel, $maxEntry]; $tagSql = '';
    if ($tags) {
        $tagSql = ' and (' . implode(' or ', array_fill(0, count($tags), '(p.planner_tags ilike ? or c.slug ilike ?)')) . ')';
        foreach ($tags as $t) { $args[] = "%$t%"; $args[] = "%$t%"; }
    }
    $ph = implode(',', array_fill(0, count($sel), '?'));
    $cand = rows("select p.*, c.slug cat_slug, c.name_en cat_en, c.name_kh cat_kh, c.color cat_color,
                  (select round(avg(rating),1) from reviews r where r.place_id=p.id) avg_rating
                  from places p join provinces v on v.id=p.province_id join categories c on c.id=p.category_id
                  where v.slug in ($ph) and p.is_active=1 and (p.entry_fee_usd is null or p.entry_fee_usd <= ?) $zone $kids $tagSql", $args);
    foreach ($cand as &$p) {                                             // score
        $sc = 0;
        foreach ($tags as $t) if (stripos((string)$p['planner_tags'], $t) !== false) $sc += 15;
        if ($p['is_featured']) $sc += 20;
        if ($p['avg_rating']) $sc += (int)($p['avg_rating'] * 4);
        if ((float)$p['entry_fee_usd'] == 0) $sc += 8;
        $p['_s'] = $sc;
    } unset($p);
    usort($cand, fn($a, $b) => $b['_s'] <=> $a['_s']);
    $plan = []; $used = [];
    for ($d = 1; $d <= $days; $d++) {                                     // fill each day: city -> near -> mid -> far
        $stops = [];
        foreach (['city', 'near', 'mid', 'far'] as $z) foreach ($cand as $p) {
            if (count($stops) >= $slots) break 2;
            if (isset($used[$p['id']]) || ($p['zone'] ?: 'city') !== $z) continue;
            $cost = (float)$p['entry_fee_usd'] + (float)$p['estimated_food_usd'];
            if ($total + $cost > $budget) continue;
            $stops[] = $p; $used[$p['id']] = 1; $total += $cost;
        }
        if ($stops) $plan[$d] = route($stops);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save']) && user() && $plan) {
        check_csrf();
        $tid = val('insert into trip_plans (user_id,title) values (?,?) returning id', [user()['id'], sprintf('Trip · %d days · %s', $days, date('j M Y'))]);
        foreach ($plan as $d => $stops) foreach ($stops as $s) q('insert into trip_places (trip_id,place_id,day_number) values (?,?,?)', [$tid, $s['id'], $d]);
        $saved = true;
    }
}
$hidden = function () use ($sel, $days, $budget, $transport, $intensity, $group, $tags) {
    foreach ($sel as $s) echo '<input type="hidden" name="provs[]" value="' . e($s) . '">';
    foreach ($tags as $s) echo '<input type="hidden" name="tags[]" value="' . e($s) . '">';
    foreach (compact('days', 'budget', 'transport', 'intensity', 'group') as $k => $v) echo '<input type="hidden" name="' . $k . '" value="' . e($v) . '">';
};
page_head(t('planner') . ' — Discover Cambodia');
nav(); ?>
<main class="wrap section">
  <p class="label upper">Smart planner</p>
  <h2 class="sec-title"><?= e(t('planner')) ?></h2>
  <form method="get" class="panelbox">
    <p class="muted"><?= e(t('provinces')) ?> *</p>
    <div class="chips"><?php foreach ($provs as $p): ?><label class="chip"><input type="checkbox" name="provs[]" value="<?= e($p['slug']) ?>" <?= in_array($p['slug'], $sel, true) ? 'checked' : '' ?>> <?= e(pick($p)) ?></label><?php endforeach ?></div>
    <div class="formgrid">
      <label><?= e(t('days')) ?> (1–14)<input type="number" name="days" min="1" max="14" value="<?= $days ?>"></label>
      <label><?= e(t('budget')) ?><input type="number" name="budget" min="10" step="5" value="<?= $budget ?>"></label>
      <label>Transport<select name="transport"><?php foreach (['walk' => 'Walking (city only)', 'tuk_tuk' => 'Tuk tuk (city + nearby)', 'car' => 'Car / van (anywhere)'] as $k => $l): ?><option value="<?= $k ?>" <?= $transport === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></label>
      <label>Pace<select name="intensity"><?php foreach (['relaxed' => 'Relaxed (2 stops/day)', 'normal' => 'Normal (3 stops/day)', 'packed' => 'Packed (4 stops/day)'] as $k => $l): ?><option value="<?= $k ?>" <?= $intensity === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></label>
      <label>Travelling as<select name="group"><?php foreach (['solo' => 'Solo', 'friends' => 'Friends / couple', 'family' => 'Family with kids'] as $k => $l): ?><option value="<?= $k ?>" <?= $group === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></label>
    </div>
    <p class="muted"><?= e(t('interests')) ?></p>
    <div class="chips"><?php foreach ($cats as $c): ?><label class="chip"><input type="checkbox" name="tags[]" value="<?= e($c['slug']) ?>" <?= in_array($c['slug'], $tags, true) ? 'checked' : '' ?>> <?= e(pick($c)) ?></label><?php endforeach ?></div>
    <div class="row"><button class="btn-gold upper"><?= e(t('make_plan')) ?></button></div>
  </form>

  <?php if ($plan !== null): ?>
    <?php if (!$plan): ?><p class="muted" style="margin-top:20px">No places match. Try more budget, a faster transport mode or fewer interests.</p><?php endif ?>
    <?php foreach ($plan as $d => $stops): $dc = array_sum(array_map(fn($s) => (float)$s['entry_fee_usd'] + (float)$s['estimated_food_usd'], $stops)); ?>
      <h3 class="sec-title" style="font-size:1.6rem;margin:28px 0 12px">Day <?= $d ?> <small class="muted" style="font-size:.9rem">≈ $<?= number_format($dc, 2) ?></small></h3>
      <div class="grid"><?php foreach ($stops as $s) place_card($s); ?></div>
    <?php endforeach ?>
    <?php if ($plan): ?>
      <p class="big" style="margin-top:20px">Estimated cost: $<?= number_format($total, 2) ?> <small class="muted">(entry fees + food, per person) · remaining budget $<?= number_format(max(0, $budget - $total), 2) ?></small></p>
      <?php if (user()): ?>
        <form method="post" style="margin-top:10px"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><?php $hidden(); ?><button class="chip" name="save" value="1">💾 <?= e(t('save')) ?></button>
        <?php if ($saved): ?> <span class="ok"><?= e(t('saved')) ?> — <a href="<?= u('profile.php#trips') ?>">My trips</a></span><?php endif ?></form>
      <?php else: ?><p class="muted"><a href="<?= u('login.php?next=' . urlencode($_SERVER['REQUEST_URI'])) ?>"><?= e(t('login')) ?></a> to save this plan.</p><?php endif ?>
    <?php endif ?>
  <?php endif ?>
</main>
<?php page_foot();
