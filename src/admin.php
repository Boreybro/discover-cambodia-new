<?php
require_once ROOT . '/src/admin_config.php';
function admin_required(): void { if (empty($_SESSION['admin'])) redirect(u('admin/login.php')); }
function admin_badges(): array {
    static $b;
    if ($b !== null) return $b;
    $b = [];
    foreach (admin_resources() as $slug => $r) if (!empty($r['badge'])) {
        try { $n = (int)val('select count(*) from "' . $r['table'] . '" where ' . $r['badge']); if ($n) $b[$slug] = $n; } catch (Throwable $e) { /* table not created yet */ }
    }
    return $b;
}
function admin_head(string $title, string $active = ''): void {
    $groups = []; $bd = admin_badges();
    foreach (admin_resources() as $slug => $r) $groups[$r['group']][$slug] = $r['title']; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> · Dashboard</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,600;0,700;1,600&family=DM+Sans:wght@400;500;700&display=swap">
<link rel="stylesheet" href="<?= u('assets/css/admin.css') ?>"></head><body class="adm">
<aside>
  <a href="<?= u('admin/') ?>" class="adm-logo">Discover Cambodia<small>Dashboard</small></a>
  <a href="<?= u('admin/') ?>" class="<?= $active === '' ? 'on' : '' ?>">Overview</a>
  <?php foreach ($groups as $g => $items): ?><p><?= e($g) ?></p>
    <?php foreach ($items as $slug => $tt): ?><a href="<?= u('admin/crud.php?r=' . $slug) ?>" class="<?= $active === $slug ? 'on' : '' ?>"><?= e($tt) ?><?php if (!empty($bd[$slug])): ?><em class="bdg"><?= (int)$bd[$slug] ?></em><?php endif ?></a><?php endforeach ?>
  <?php endforeach ?>
  <p>&nbsp;</p><a href="<?= u('index.php') ?>" target="_blank">View website ↗</a><a href="<?= u('admin/logout.php') ?>">Sign out</a>
</aside><main>
<?php }
function admin_foot(): void {
    echo '<div id="toast" hidden></div><script>window.toast=function(m,bad){var t=document.getElementById("toast");t.textContent=m;t.className=bad?"bad":"";t.hidden=false;clearTimeout(window._tt);window._tt=setTimeout(function(){t.hidden=true},2600)}</script></main></body></html>';
}
