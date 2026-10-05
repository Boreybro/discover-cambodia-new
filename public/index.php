<?php
require __DIR__ . '/../src/bootstrap.php';
require ROOT . '/src/view.php';

$pcols = 'p.id,p.province_id,p.name_en,p.name_kh,p.image_url,p.images,c.color cat_color,c.name_en cat_en,c.name_kh cat_kh, coalesce((select views from item_views v where v.item_type=\'place\' and v.item_id=p.id),0) views';
$provinces = rows('select * from provinces order by sort_order,id');
$places    = rows("select $pcols from places p left join categories c on c.id=p.category_id where p.is_active=1 order by p.sort_order,p.id");
$top       = rows("select $pcols from top_places t join places p on p.id=t.place_id left join categories c on c.id=p.category_id where p.is_active=1 order by t.sort_order,p.id");
$sponsors  = rows("select id,title,image_urls,link_url from sponsors where is_active=1 and (starts_on is null or starts_on<=current_date) and (ends_on is null or ends_on>=current_date) order by sort_order,id");
$fests     = rows('select * from festivals where is_active=1 order by sort_order,id');
$stats     = [
    t('stat_prov')  => count($provinces),
    t('stat_attr')  => count($places) . '+',
    t('stat_users') => (int)val('select count(*) from users'),
    t('stat_cat')   => (int)val('select count(*) from categories'),
    t('stat_lang')  => 2,
];
$byProv = []; $bySlug = [];
foreach ($places as $p) $byProv[$p['province_id']][] = $p;
foreach ($provinces as $pv) $bySlug[$pv['slug']] = $pv;
$regions = [];
foreach ($provinces as $pv) $regions[$pv['region'] ?: 'Other'][] = $pv;

$points = ['battambang'=>[28,35,22],'phnom-penh'=>[52,67,14],'siem-reap'=>[37,25,17],'kampot'=>[46,88,14],'kep'=>[51,94,10],'preah-sihanouk'=>[34,91,14],'kampong-thom'=>[52,36,20],'mondulkiri'=>[85,40,18],'ratanakiri'=>[88,18,15],'kratie'=>[72,42,17],'pursat'=>[34,47,17],'kampong-cham'=>[61,55,15],'kandal'=>[55,70,13],'takeo'=>[56,86,13],'koh-kong'=>[22,74,17],'kampong-speu'=>[43,65,13],'prey-veng'=>[62,67,13],'svay-rieng'=>[72,76,11],'preah-vihear'=>[51,15,14],'stung-treng'=>[75,23,15],'oddar-meanchey'=>[36,9,13],'banteay-meanchey'=>[24,20,14],'pailin'=>[22,40,9],'tboung-khmum'=>[68,59,12],'kampong-chhnang'=>[48,56,12]];
$settings = array_column(rows("select key,value from app_settings where key like 'contact_%'"), 'value', 'key');

if (!$top) {
    $top = rows("select $pcols from places p left join categories c on c.id=p.category_id where p.is_active=1 order by p.is_featured desc, views desc, p.sort_order, p.id limit 12");
}
$GLOBALS['popular'] = array_map('intval', array_column(rows("select item_id from item_views where item_type='place' and views > 0 order by views desc limit 3"), 'item_id'));
$slidesS = [];
foreach ($sponsors as $s) foreach ((images_of(['images' => $s['image_urls']]) ?: ['']) as $pic) $slidesS[] = ['pic' => $pic, 'title' => $s['title'], 'link' => $s['link_url']];
page_head('Discover Cambodia — Complete Travel Guide');
nav();
?>
<main id="top">
<section class="hero"><i class="orb o1"></i><i class="orb o2"></i><div>
  <p class="kicker upper"><?= e(t('kicker')) ?></p>
  <h1><?= e(t('hero_a')) ?><em><?= e(t('hero_b')) ?></em></h1>
  <p class="lead"><?= e(t('hero_text')) ?></p>
  <div class="hero-cta">
    <a href="#provinces"><button class="btn-gold upper"><?= e(t('explore')) ?></button></a>
    <a href="<?= u('planner.php') ?>"><button class="btn-ghost upper"><?= e(t('planner')) ?></button></a>
  </div>
  <div class="stats"><?php foreach ($stats as $label => $n): ?><div><b data-count="<?= e(is_numeric($n) ? $n : rtrim((string)$n, '+')) ?>"><?= e($n) ?></b><span class="upper"><?= e($label) ?></span></div><?php endforeach ?></div>
  <a class="scroll-down" href="#map" aria-label="Scroll down">⌄</a>
</div></section>

<?php if ($slidesS): ?>
<section class="wrap reveal" style="padding-top:20px"><div class="sponsor" id="sponsor">
  <?php foreach ($slidesS as $k => $s): ?>
    <a class="<?= $k ? '' : 'on' ?>" href="<?= e($s['link'] ?: '#') ?>" target="_blank" rel="noopener sponsored" style="background-image:url('<?= e(img($s['pic'])) ?>')"><span class="upper">Sponsored · <?= e($s['title']) ?></span></a>
  <?php endforeach ?>
  <?php if (count($slidesS) > 1): ?>
    <button class="sarrow prev" aria-label="Previous">‹</button><button class="sarrow next" aria-label="Next">›</button>
    <div class="sdots"><?php foreach ($slidesS as $k => $s): ?><i class="<?= $k ? '' : 'on' ?>"></i><?php endforeach ?></div>
  <?php endif ?>
</div></section>
<?php endif ?>

<section class="section reveal" id="map"><div class="wrap maprow">
  <div class="mapcard">
    <p class="label upper"><?= e(t('map_label')) ?></p>
    <h2 class="sec-title"><?= e(t('map_a')) ?> <em><?= e(t('map_b')) ?></em></h2>
    <p><?= e(t('map_text')) ?></p>
    <div class="chips"><?php foreach (['battambang', 'siem-reap', 'phnom-penh'] as $s) if (isset($bySlug[$s])): ?>
      <button class="chip" data-prov="<?= e($s) ?>"><?= e(pick($bySlug[$s])) ?></button><?php endif ?></div>
  </div>
  <div class="mapbox">
    <div class="mapimg"><img src="<?= u('assets/img/cambodia-province-map.png') ?>" alt="Map of Cambodia">
      <?php foreach ($points as $slug => [$x, $y, $sz]) if (isset($bySlug[$slug])): ?>
        <button class="mdot" style="--x:<?= $x ?>%;--y:<?= $y ?>%;--s:<?= $sz ?>px" data-prov="<?= e($slug) ?>" data-name="<?= e(pick($bySlug[$slug])) ?>" aria-label="<?= e($bySlug[$slug]['name_en']) ?>"></button>
      <?php endif ?>
    </div>
    <div class="msel"><small class="upper"><?= e(t('selected')) ?></small><b id="mselName"><?= e(t('choose')) ?></b><span id="mselHint"><?= e(t('tap')) ?></span><button class="chip" id="mselOpen" data-prov="" disabled><?= e(t('open')) ?></button></div>
  </div>
</div></section>

<?php if ($top): ?>
<section class="section reveal" id="featured">
  <div class="wrap">
    <p class="label upper"><?= e(t('must')) ?></p>
    <h2 class="sec-title"><?= e(t('top_a')) ?> <em><?= e(t('top_b')) ?></em></h2>
  </div>
  <?php $set = $top; while (count($set) < 8) $set = array_merge($set, $top); $seen = []; ?>
  <div class="marquee"><div class="mtrack" style="--dur:<?= count($set) * 8 ?>s">
    <?php for ($c = 0; $c < 3; $c++) foreach ($set as $p): ?><div class="mitem"><?php place_card($p, $c === 0 && !isset($seen[$p['id']]) && ($seen[$p['id']] = 1)); ?></div><?php endforeach ?>
  </div></div>
</section>
<?php endif ?>

<section class="section reveal" id="provinces"><div class="wrap">
  <p class="label upper"><?= e(t('all_prov')) ?></p>
  <h2 class="sec-title"><?= e(t('choose_a')) ?> <em><?= e(t('choose_b')) ?></em></h2>
  <?php foreach ($regions as $region => $list): ?>
    <p class="region upper"><?= e($region) ?></p>
    <div class="grid">
    <?php foreach ($list as $pv): $c = $pv['cover_color'] ?: '#7A3B10'; ?>
      <div class="card reveal" data-prov="<?= e($pv['slug']) ?>" style="background:linear-gradient(160deg,<?= e($c) ?> 0%,<?= e($c) ?>cc 100%)">
        <div class="skeleton"></div>
        <?php slides($pv); ?><div class="grad"></div>
        <?php if ($pv['is_featured']): ?><span class="pill upper">Featured</span><?php endif ?>
        <div class="txt"><b><?= e($pv['name_en']) ?></b><span><?= e($pv['name_kh']) ?></span><span class="cnt upper"><?= (int)($byProv[$pv['id']] ? count($byProv[$pv['id']]) : 0) ?> <?= e(t('attractions')) ?></span></div>
        <div class="cta"><button class="upper"><?= e(t('open')) ?></button></div>
      </div>
    <?php endforeach ?>
    </div>
  <?php endforeach ?>
</div></section>

<section class="section reveal" id="all-places"><div class="wrap">
  <p class="label upper"><?= e(t('explore_all')) ?></p>
  <h2 class="sec-title"><?= e(t('all_a')) ?> <em><?= e(t('all_b')) ?></em></h2>
  <?php foreach ($provinces as $pv) if (!empty($byProv[$pv['id']])): ?>
    <div class="pgroup">
      <p class="region upper"><?= e(pick($pv)) ?> · <?= count($byProv[$pv['id']]) ?></p>
      <div class="grid"><?php foreach ($byProv[$pv['id']] as $p) place_card($p); ?></div>
      <button class="more upper" hidden></button>
    </div>
  <?php endif ?>
</div></section>

<?php if ($fests): ?>
<section class="section reveal" id="festivals"><div class="wrap">
  <p class="label upper"><?= e(t('fest_label')) ?></p>
  <h2 class="sec-title"><?= e(t('fest_a')) ?> <em><?= e(t('fest_b')) ?></em></h2>
  <div class="grid fest">
  <?php foreach ($fests as $f): ?>
    <div class="fcard reveal" <?= $f['place_id'] ? 'data-place="' . (int)$f['place_id'] . '"' : '' ?> style="border-top:3px solid <?= e($f['accent_color'] ?: 'var(--gold)') ?>;<?= $f['place_id'] ? 'cursor:pointer' : '' ?>">
      <span class="ficon"><?= e($f['icon']) ?></span><b><?= e($f['name_en']) ?></b><span><?= e($f['name_kh']) ?></span>
      <em class="upper"><?= e(fest_when($f)) ?></em>
      <p><?= e(mb_strimwidth((string)(lang() === 'kh' && $f['description_kh'] ? $f['description_kh'] : $f['description_en']), 0, 130, '…')) ?></p>
    </div>
  <?php endforeach ?>
  </div>
</div></section>
<?php endif ?>

<section class="section reveal" id="services"><div class="wrap">
  <p class="label upper"><?= e(t('svc_label')) ?></p>
  <h2 class="sec-title"><?= e(t('svc_title')) ?></h2>
  <div class="grid svc">
  <?php foreach ([1, 2, 3, 4, 5] as $n): ?>
    <div class="scard reveal"><h3><?= e(t("svc$n")) ?></h3><p><?= e(t("svc{$n}d")) ?></p>
      <a class="btn-gold upper" href="<?= [1 => u('guides.php'), 2 => u('apply.php?type=guide'), 3 => u('transport.php'), 4 => u('apply.php?type=transport'), 5 => u('contact.php')][$n] ?>"><?= e(t("svc{$n}b")) ?></a></div>
  <?php endforeach ?>
  </div>
</div></section>

<section class="section donate reveal"><div class="wrap"><h2 class="sec-title"><?= e(t('donate_t')) ?></h2><a class="btn-gold upper" href="<?= u('donate.php') ?>"><?= e(t('donate_b')) ?></a></div></section>
</main>

<footer id="contact">
  <div class="foot-grid">
    <div>
      <h4>Discover Cambodia</h4>
      <p><?= e(t('footer')) ?></p>
      <p><?= e(t('built')) ?> · ខ្មែរ</p>
    </div>
    <div>
      <h4><?= e(t('places')) ?></h4>
      <a href="#provinces"><?= e(t('provinces')) ?></a>
      <a href="#map"><?= e(t('map')) ?></a>
      <a href="#featured"><?= e(t('featured')) ?></a>
      <a href="#festivals"><?= e(t('festivals')) ?></a>
    </div>
    <div>
      <h4><?= e(t('services')) ?></h4>
      <a href="<?= u('guides.php') ?>"><?= e(t('svc1')) ?></a>
      <a href="<?= u('transport.php') ?>"><?= e(t('svc3')) ?></a>
      <a href="<?= u('planner.php') ?>"><?= e(t('planner')) ?></a>
      <a href="<?= u('donate.php') ?>"><?= e(t('donate_b')) ?></a>
    </div>
    <div>
      <h4><?= e(t('contact')) ?></h4>
      <a href="<?= u('contact.php') ?>"><?= e(t('contact')) ?></a>
      <?php if (!empty($settings['contact_telegram'])): ?><a href="<?= e($settings['contact_telegram']) ?>" target="_blank" rel="noopener">Telegram</a><?php endif ?>
      <?php if (!empty($settings['contact_email'])): ?><a href="mailto:<?= e($settings['contact_email']) ?>"><?= e($settings['contact_email']) ?></a><?php endif ?>
    </div>
  </div>
  <div class="flag-strip"><i></i><i></i><i></i></div>
  <p class="foot-copy">© <?= date('Y') ?> Discover Cambodia</p>
</footer>
<?php page_foot();