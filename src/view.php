<?php
function slides(array $r): void {
    $im = images_of($r);
    if (!$im) return;
    echo '<div class="slides">';
    foreach ($im as $i => $s) echo '<img src="' . e(img($s)) . '" alt="" loading="lazy" class="' . ($i ? '' : 'on') . '" onerror="this.remove()">';
    echo '</div>';
}

function place_card(array $p, bool $withId = true): void { $c = $p['cat_color'] ?: '#7A3B10'; ?>
<div class="card reveal" <?= $withId ? 'id="place-' . (int)$p['id'] . '"' : '' ?> data-place="<?= (int)$p['id'] ?>" style="background:linear-gradient(160deg,<?= e($c) ?>,<?= e($c) ?>88)">
  <div class="skeleton"></div>
  <?php slides($p); ?><div class="grad"></div>
  <?php if (!empty($p['cat_en'])): ?><span class="pill upper"><?= e(pick(['name_en' => $p['cat_en'], 'name_kh' => $p['cat_kh']])) ?></span><?php endif ?>
  <?php if (!empty($GLOBALS['popular']) && in_array((int)$p['id'], $GLOBALS['popular'], true)): ?><span class="hot">🔥 <?= e(t('popular')) ?></span><?php endif ?>
  <?php if (isset($p['views'])): ?><span class="views">👁 <?= e(fmt_views($p['views'])) ?></span><?php endif ?>
  <div class="txt"><b><?= e($p['name_en']) ?></b><span><?= e($p['name_kh']) ?></span></div>
  <div class="cta"><button class="upper"><?= e(t('open')) ?></button></div>
</div>
<?php }

function kh_post(string $html): string {
    $a = i18n_all(); $ph = $a['phrases']; $rules = $a['rules'];
    $tr = function (string $raw) use ($ph, $rules): string {
        if (!preg_match('/^(\s*)(.*?)(\s*)$/su', $raw, $m)) return $raw;
        [, $lead, $core, $trail] = $m;
        if ($core === '') return $raw;
        $star = '';
        if (str_ends_with($core, ' *')) { $star = ' *'; $core = substr($core, 0, -2); }
        if (isset($ph[$core])) return $lead . $ph[$core] . $star . $trail;
        foreach ($rules as [$re, $rep]) if (preg_match($re, $core)) return $lead . preg_replace($re, $rep, $core) . $star . $trail;
        return $raw;
    };
    $parts = preg_split('~(<script\b.*?</script>|<style\b.*?</style>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) return $html;
    foreach ($parts as $i => $p) if ($i % 2 === 0) $parts[$i] = preg_replace_callback('~>([^<>]+)<~u', fn($m) => '>' . $tr($m[1]) . '<', $p) ?? $p;
    return implode('', $parts);
}
function fest_when(array $f): string {
    $w = pick($f, 'when'); $d = pick($f, 'duration');
    $s = $w . ($d !== '' ? ' · ' . $d : '');
    if (lang() !== 'kh' || !empty($f['when_kh'])) return $s;
    $m = ['January'=>'មករា','February'=>'កុម្ភៈ','March'=>'មីនា','April'=>'មេសា','May'=>'ឧសភា','June'=>'មិថុនា','July'=>'កក្កដា','August'=>'សីហា','September'=>'កញ្ញា','October'=>'តុលា','November'=>'វិច្ឆិកា','December'=>'ធ្នូ',
          'Jan'=>'មករា','Feb'=>'កុម្ភៈ','Mar'=>'មីនា','Apr'=>'មេសា','Jun'=>'មិថុនា','Jul'=>'កក្កដា','Aug'=>'សីហា','Sep'=>'កញ្ញា','Oct'=>'តុលា','Nov'=>'វិច្ឆិកា','Dec'=>'ធ្នូ'];
    $s = preg_replace(['/\bnights?\b/i', '/\bdays?\b/i'], ['យប់', 'ថ្ងៃ'], $s);
    return preg_replace_callback('/\b(' . implode('|', array_keys($m)) . ')\b/', fn($x) => $m[$x[1]], $s);
}
function lang_href(string $l): string { return '?' . http_build_query(array_merge($_GET, ['lang' => $l])); }

function page_head(string $title): void {
    if (lang() === 'kh') ob_start('kh_post');
    $cfg = ['base' => base(), 'lang' => lang(), 'user' => (bool)user(), 'csrf' => csrf(), 't' => dict()]; ?>
<!doctype html>
<html lang="<?= lang() === 'kh' ? 'km' : 'en' ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= e($title) ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:wght@300;400;500;700&family=Noto+Serif+Khmer:wght@400;600;700&display=swap">
<link rel="stylesheet" href="<?= u('assets/css/site.css') ?>">
<script>window.CFG=<?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
</head><body class="lang-<?= lang() ?>">
<script>if(localStorage.theme==='light')document.body.classList.add('theme-light')</script>
<?php }

function nav(): void {
    $h = u('index.php'); $u = user();
    $links = [['provinces', '#provinces'], ['map', '#map'], ['featured', '#featured'], ['places', '#all-places'], ['festivals', '#festivals'], ['services', '#services'], ['contact', 'contact.php']]; ?>
<header class="nav" id="topnav"><div class="wrap">
  <a class="logo" href="<?= $h ?>">Discover Cambodia<small class="upper">Complete Travel Guide</small></a>
  <div class="navpanel" id="navpanel">
    <nav class="links">
      <?php foreach ($links as [$k, $a]): ?><a class="upper" href="<?= $a[0] === '#' ? $h . $a : u($a) ?>"><?= e(t($k)) ?></a><?php endforeach ?>
    </nav>
    <div class="tools">
      <a class="chip <?= lang() === 'en' ? 'on' : '' ?>" href="<?= e(lang_href('en')) ?>">EN</a>
      <a class="chip <?= lang() === 'kh' ? 'on' : '' ?>" href="<?= e(lang_href('kh')) ?>">ខ្មែរ</a>
      <button class="chip" id="theme" aria-label="Theme">◐</button>
      <?php if ($u): ?>
        <a class="chip auth upper" href="<?= u('profile.php') ?>"><?= e($u['name']) ?></a>
      <?php else: ?>
        <a class="chip auth upper" href="<?= u('login.php') ?>"><?= e(t('login')) ?></a>
        <a class="chip on auth upper" href="<?= u('login.php?signup=1') ?>"><?= e(t('signup')) ?></a>
      <?php endif ?>
    </div>
  </div>
  <div class="barbtns">
    <div class="search popwrap"><input id="q" autocomplete="off" placeholder="<?= e(t('search')) ?>" aria-label="Search"><div class="pop" id="qres" hidden></div></div>
    <div class="popwrap bellwrap"><button class="chip" id="bell" aria-label="Notifications">🔔<i class="dot" id="dot" hidden></i></button><div class="pop" id="notes" hidden></div></div>
    <button class="chip burger" id="burger" aria-label="Menu" aria-expanded="false">☰</button>
  </div>
</div></header>
<?php }

function page_foot(): void { ?>
<div class="modal" id="modal" hidden><div class="sheet" id="sheet"></div></div>
<div class="chat">
  <div class="chat-panel" id="chatPanel" hidden>
    <div class="chat-head"><button class="chat-x" id="chatX" aria-label="Close">✕</button><div class="chat-ht"><b>Discover Cambodia (DC)</b><small><?= e(t('chat_sub')) ?></small></div></div>
    <div class="chat-log" id="chatLog"></div>
    <div class="chat-opts" id="chatOpts"></div>
    <div class="chat-foot"><button id="chatReset"><?= e(t('chat_reset')) ?></button><button id="chatContact"><?= e(t('chat_contact')) ?></button><button id="sosPolice" aria-label="Police" title="Police">👮</button><button id="sosFire" aria-label="Firefighter" title="Firefighter">🚒</button></div>
  </div>
  <button class="chat-fab" id="chatBtn" aria-label="Chat">
    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#1B1008" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/></svg>
  </button>
</div>
<script src="<?= u('assets/js/site.js') ?>"></script>
</body></html>
<?php }

function xbtn(): void {
    echo '<div class="xrow"><button type="button" class="xbtn" aria-label="Back" onclick="history.length>1?history.back():location.href=\'' . e(u('index.php')) . '\'">✕</button></div>';
}
function qr_rows(): array { return rows("select key,value from app_settings where key like 'qr\\_%' and coalesce(value,'') <> '' order by key"); }
function qr_block(array $qrs): void {
    if (!$qrs) return;
    echo '<div class="qrs">';
    foreach ($qrs as $qr) echo '<figure><img src="' . e(img(explode('|', (string)$qr['value'])[0])) . '" alt="QR"><figcaption>' . e(ucwords(str_replace('_', ' ', substr($qr['key'], 3)))) . '</figcaption></figure>';
    echo '</div>';
}