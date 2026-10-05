<?php
require __DIR__ . '/../../src/bootstrap.php';
require ROOT . '/src/admin.php';
admin_required();
$bd = admin_badges();
admin_head('Overview'); ?>
<div class="adm-bar"><h1>Overview</h1><span class="live">● live</span></div>
<div class="adm-cards" id="cards">
<?php foreach (admin_resources() as $slug => $r): if (!empty($r['select'])) continue; ?>
  <a href="<?= u('admin/crud.php?r=' . $slug) ?>"><b><?= (int)val('select count(*) from "' . $r['table'] . '"') ?></b><span><?= e($r['title']) ?></span>
    <?php if (!empty($bd[$slug])): ?><em class="bdg"><?= (int)$bd[$slug] ?> new</em><?php endif ?></a>
<?php endforeach ?>
</div>
<script>
// refresh the numbers every 10 seconds without moving the page
setInterval(function () {
  if (document.hidden) return;
  fetch(location.href, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (html) {
    var d = new DOMParser().parseFromString(html, 'text/html'), nc = d.getElementById('cards'), na = d.querySelector('aside');
    if (nc) document.getElementById('cards').innerHTML = nc.innerHTML;
    var ca = document.querySelector('aside'); if (na && ca) { var s = ca.scrollTop; ca.innerHTML = na.innerHTML; ca.scrollTop = s; }
  }).catch(function () {});
}, 10000);
</script>
<?php admin_foot();
