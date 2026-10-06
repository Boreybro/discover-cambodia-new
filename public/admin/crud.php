<?php
require __DIR__ . '/../../src/bootstrap.php';
require ROOT . '/src/admin.php';
require ROOT . '/src/upload.php';
admin_required();

$all = admin_resources();
$slug = $_GET['r'] ?? 'places';
$r = $all[$slug] ?? null;
if (!$r) { http_response_code(404); exit('Unknown section'); }
$pk = $r['pk'] ?? 'id';
$tbl = '"' . $r['table'] . '"';
$self = u('admin/crud.php?r=' . $slug);
$ro = !empty($r['readonly']);
$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
$formRow = null;

function collect_images(array $f, string $table): ?string {
    $k = $f['key']; $paths = $_POST[$k . '_path'] ?? []; $del = $_POST[$k . '_del'] ?? []; $files = $_FILES[$k . '_file'] ?? null; $out = [];
    foreach ($paths as $i => $p) {
        if (!empty($del[$i])) continue;
        $path = trim((string)$p);
        if ($files && ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $one = ['name' => $files['name'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
            $path = store_upload($one, 'admin-images/' . $table) ?? $path;
        }
        if ($path !== '') $out[] = $path;
        if (!empty($f['single'])) break;
    }
    return $out ? implode('|', $out) : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$ro) {
    check_csrf();
    $act = $_POST['act'] ?? '';
    $pkval = (string)($_POST['_pk'] ?? '');

    if ($act === 'quick') {
        $f = null; foreach ($r['fields'] as $x) if ($x['key'] === ($_POST['field'] ?? '')) $f = $x;
        $type = $f['type'] ?? 'text'; $val = (string)($_POST['value'] ?? '');
        if (!$f || !empty($f['createOnly']) || !in_array($type, ['select', 'flag'], true) || isset($f['ref'])) json_out(['ok' => false, 'error' => 'Not allowed'], 400);
        if ($type === 'flag') $val = $val === '1' ? 1 : 0;
        elseif (!in_array($val, $f['options'] ?? [], true)) json_out(['ok' => false, 'error' => 'Bad value'], 400);
        try { q("update $tbl set \"{$f['key']}\"=? where \"$pk\"=?", [$val, $pkval]); json_out(['ok' => true]); }
        catch (Throwable $ex) { json_out(['ok' => false, 'error' => $ex->getMessage()], 400); }
    }

    try {
        if ($act === 'delete' && empty($r['noDelete'])) {
            q("delete from $tbl where \"$pk\"=?", [$pkval]);
            $_SESSION['flash'] = 'Deleted ✓';
        } elseif ($act === 'save') {
            $isNew = $pkval === '';
            if ($isNew && !empty($r['noCreate'])) exit('Not allowed');
            foreach ($r['fields'] as $f) {
                $t = $f['type'] ?? 'text';
                if (!empty($f['req']) && ($isNew || empty($f['createOnly'])) && !in_array($t, ['flag', 'images'], true) && trim((string)($_POST[$f['key']] ?? '')) === '')
                    throw new RuntimeException($f['label'] . ' is required.');
            }
            if ($isNew && !empty($r['keyPrefix']) && !str_starts_with(trim((string)($_POST[$pk] ?? '')), $r['keyPrefix'])) throw new RuntimeException('The name must start with ' . $r['keyPrefix']);
            $cols = []; $vals = [];
            foreach ($r['fields'] as $f) {
                $k = $f['key']; $type = $f['type'] ?? 'text';
                if (!$isNew && !empty($f['createOnly'])) continue;
                if ($type === 'images') {
                    $v = collect_images($f, $r['table']);
                    if ($isNew && $v === null) continue;
                    $cols[] = $k; $vals[] = $v;
                    if (!empty($f['sync'])) { $cols[] = $f['sync']; $vals[] = $v === null ? null : explode('|', $v)[0]; }
                    continue;
                }
                if ($type === 'flag') $v = isset($_POST[$k]) ? 1 : 0;
                else {
                    $v = trim((string)($_POST[$k] ?? ''));
                    if ($v === '') { if ($isNew) continue; $v = null; }
                    elseif ($type === 'number' || isset($f['ref'])) $v = $v + 0;
                }
                $cols[] = $k; $vals[] = $v;
            }
            if ($isNew) {
                $sql = $cols ? "insert into $tbl (" . implode(',', array_map(fn($c) => "\"$c\"", $cols)) . ') values (' . implode(',', array_fill(0, count($cols), '?')) . ')' : "insert into $tbl default values";
                q($sql, $vals);
            } else {
                q("update $tbl set " . implode(',', array_map(fn($c) => "\"$c\"=?", $cols)) . " where \"$pk\"=?", [...$vals, $pkval]);
            }
            $_SESSION['flash'] = 'Saved ✓';
        }
        redirect($self);
    } catch (RuntimeException $ex) {
        $flash = $ex->getMessage(); $formRow = $_POST; $formRow[$pk] = $pkval;
    } catch (Throwable $ex) {
        $flash = 'Error: ' . $ex->getMessage(); $formRow = $_POST; $formRow[$pk] = $pkval;
    }
}

$refs = [];
foreach ($r['fields'] as $f) if (isset($f['ref']))
    $refs[$f['key']] = array_column(rows('select id, "' . $f['ref'][1] . '" as label from "' . $f['ref'][0] . '" order by 2 limit 1000'), 'label', 'id');

$editing = !$ro && ($formRow !== null || isset($_GET['new']) || isset($_GET['id']));
admin_head($r['title'], $slug);
?>
<div class="adm-bar"><h1><?= e($r['title']) ?></h1>
<?php if (!$editing): ?><span class="live">● live</span><input id="flt" placeholder="Filter…"><?php if (empty($r['noCreate']) && !$ro): ?><a class="adm-btn addbtn" href="<?= $self ?>&new=1">+ Add</a><?php endif ?><?php endif ?></div>
<?php if ($flash): ?><p class="adm-msg"><?= e($flash) ?></p><?php endif ?>

<?php if ($editing):
    $row = $formRow ?? (isset($_GET['id']) ? row("select * from $tbl where \"$pk\"=?", [$_GET['id']]) : []);
    $isNew = !isset($_GET['id']) && !($formRow && ($formRow['_pk'] ?? '') !== ''); ?>
  <form method="post" class="adm-form" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="act" value="save">
    <input type="hidden" name="_pk" value="<?= $isNew ? '' : e($row[$pk] ?? $_GET['id'] ?? '') ?>">
    <?php foreach ($r['fields'] as $f):
        $k = $f['key']; $type = $f['type'] ?? 'text'; $v = $row[$k] ?? ($type === 'flag' ? ($isNew ? ($f['def'] ?? 0) : 0) : '');
        $dis = (!$isNew && !empty($f['createOnly'])) ? 'disabled' : '';
        $reqA = (!empty($f['req']) && !$dis) ? 'required' : '';
        $star = !empty($f['req']) ? ' <span class="rq">*</span>' : '';

        if ($type === 'images'):
            if ($formRow !== null) $v = implode('|', array_filter(array_map('trim', (array)($_POST[$k . '_path'] ?? []))));
            $list = array_values(array_filter(array_map('trim', explode('|', (string)$v))));
            $n = !empty($f['single']) ? 1 : count($list) + 2;
            $slot = function (int $i, string $p) use ($k) { ?>
              <div class="slot">
                <b>Img <?= $i + 1 ?><?= $i === 0 ? ' · main' : '' ?></b>
                <div class="thumb"><?php if ($p !== ''): ?><img src="<?= e(img($p)) ?>" alt="" onerror="this.replaceWith(document.createTextNode('?'))"><?php else: ?><span>+</span><?php endif ?></div>
                <div class="slotf">
                  <input type="text" name="<?= e($k) ?>_path[]" value="<?= e($p) ?>" placeholder="image path or URL (images/..., https://...)">
                  <input type="file" name="<?= e($k) ?>_file[]" accept="image/jpeg,image/png,image/webp">
                  <label class="rm"><input type="checkbox" name="<?= e($k) ?>_del[<?= $i ?>]" value="1"> remove this picture</label>
                </div>
              </div>
            <?php }; ?>
      <div class="fld"><span class="fl"><?= e($f['label']) ?></span>
        <div class="slots" id="slots-<?= e($k) ?>" data-n="<?= $n ?>"><?php for ($i = 0; $i < $n; $i++) $slot($i, $list[$i] ?? ''); ?></div>
        <?php if (empty($f['single'])): ?><button type="button" class="adm-ghost addslot" data-for="<?= e($k) ?>">+ Add another picture</button>
        <template id="tpl-<?= e($k) ?>"><?php $slot(9999, ''); ?></template><?php endif ?>
      </div>
    <?php continue; endif; ?>
      <label><?= e($f['label']) ?><?= $star ?>
      <?php if ($type === 'textarea'): ?><textarea name="<?= e($k) ?>" rows="3" <?= $reqA ?>><?= e($v) ?></textarea>
      <?php elseif ($type === 'flag'): ?><input type="checkbox" name="<?= e($k) ?>" value="1" <?= $v ? 'checked' : '' ?>>
      <?php elseif ($type === 'date'): ?><input type="date" name="<?= e($k) ?>" value="<?= e(substr((string)$v, 0, 10)) ?>" <?= $reqA ?>>
      <?php elseif ($type === 'select'): ?><select name="<?= e($k) ?>" <?= $dis ?> <?= $reqA ?>><option value="">—</option>
          <?php if (isset($f['ref'])) foreach ($refs[$k] as $id => $lab): ?><option value="<?= e($id) ?>" <?= (string)$v === (string)$id ? 'selected' : '' ?>><?= e($lab) ?></option><?php endforeach ?>
          <?php $opts = $f['options'] ?? []; if (!isset($f['ref']) && $v !== '' && !in_array((string)$v, $opts, true)) $opts[] = (string)$v;
                foreach ($opts as $o): ?><option <?= (string)$v === $o ? 'selected' : '' ?>><?= e($o) ?></option><?php endforeach ?></select>
      <?php else: ?><input name="<?= e($k) ?>" type="<?= $type === 'number' ? 'number' : 'text' ?>" step="any" value="<?= e($v) ?>" <?= $dis ?> <?= $reqA ?>>
      <?php endif ?>
      <?php if (is_string($v) && str_starts_with($v, 'private:')): ?><a href="<?= u('admin/file.php?f=' . urlencode(substr($v, 8))) ?>" target="_blank">Open file ↗</a><?php endif ?></label>
    <?php endforeach ?>
    <div class="adm-row"><button class="adm-btn">Save</button><a class="adm-ghost" href="<?= $self ?>">Cancel</a></div>
  </form>
  <script>
  document.querySelectorAll('.addslot').forEach(function (b) {
    b.addEventListener('click', function () {
      var box = document.getElementById('slots-' + b.dataset.for), n = +box.dataset.n;
      var html = document.getElementById('tpl-' + b.dataset.for).innerHTML.replace(/\[9999\]/g, '[' + n + ']').replace(/Img 10000/, 'Img ' + (n + 1));
      box.insertAdjacentHTML('beforeend', html); box.dataset.n = n + 1;
    });
  });
  </script>
<?php else:
    $dir = !empty($r['desc']) ? 'desc' : 'asc';
    $data = rows($r['select'] ?? ("select * from $tbl" . (!empty($r['where']) ? ' where ' . $r['where'] : '') . " order by \"" . ($r['order'] ?? $pk) . "\" $dir limit 1000"));
    $cols = array_values(array_filter($r['fields'], fn($f) => !empty($f['list']))); ?>
  <div class="adm-table"><table id="tbl"><thead><tr><?php foreach ($cols as $c): ?><th><?= e($c['label']) ?></th><?php endforeach ?><?php if (!$ro): ?><th></th><?php endif ?></tr></thead><tbody>
  <?php foreach ($data as $d): ?><tr data-id="<?= e($d[$pk] . ($d['item_type'] ?? '')) ?>">
    <?php foreach ($cols as $c): $v = $d[$c['key']] ?? ''; $t = $c['type'] ?? 'text'; ?>
      <td>
      <?php if (!$ro && empty($c['createOnly']) && !isset($c['ref']) && $t === 'flag'): ?>
        <input type="checkbox" data-quick="<?= e($c['key']) ?>" data-pk="<?= e($d[$pk]) ?>" data-prev="<?= $v ? 1 : 0 ?>" <?= $v ? 'checked' : '' ?>>
      <?php elseif (!$ro && empty($c['createOnly']) && !isset($c['ref']) && $t === 'select'): $opts = $c['options'] ?? []; if ($v !== '' && !in_array((string)$v, $opts, true)) $opts[] = (string)$v; ?>
        <select class="qs" data-quick="<?= e($c['key']) ?>" data-pk="<?= e($d[$pk]) ?>" data-prev="<?= e($v) ?>"><?php foreach ($opts as $o): ?><option <?= (string)$v === $o ? 'selected' : '' ?>><?= e($o) ?></option><?php endforeach ?></select>
      <?php else:
          if (isset($c['ref'])) $v = $refs[$c['key']][$v] ?? $v; elseif ($t === 'flag') $v = $v ? '✓' : '—'; ?>
        <?php if (is_string($v) && str_starts_with($v, 'private:')): ?><a href="<?= u('admin/file.php?f=' . urlencode(substr($v, 8))) ?>" target="_blank">Open receipt ↗</a>
        <?php else: ?><?= e(mb_strimwidth((string)$v, 0, 48, '…')) ?>
        <?php endif ?>
      <?php endif ?>
      </td>
    <?php endforeach ?>
    <?php if (!$ro): ?><td class="adm-act"><a href="<?= $self ?>&id=<?= urlencode((string)$d[$pk]) ?>">Edit</a>
    <?php if (empty($r['noDelete'])): ?><form method="post" style="display:inline" onsubmit="return confirm('Delete this item?')">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="act" value="delete"><input type="hidden" name="_pk" value="<?= e($d[$pk]) ?>"><button class="danger">Delete</button></form><?php endif ?></td><?php endif ?>
  </tr><?php endforeach ?></tbody></table></div>
  <script>
  (function () {
    var slug = <?= json_encode($slug) ?>, CSRF = <?= json_encode(csrf()) ?>, flt = document.getElementById('flt'), key = 'adm-' + slug;
    function applyFilter() { var q = flt.value.toLowerCase(); document.querySelectorAll('#tbl tbody tr').forEach(function (r) { r.hidden = q && r.textContent.toLowerCase().indexOf(q) < 0; }); }
    flt.addEventListener('input', applyFilter);

    history.scrollRestoration = 'manual';
    var saved = sessionStorage.getItem(key);
    if (saved) { try { var o = JSON.parse(saved); flt.value = o.f || ''; applyFilter(); window.scrollTo(0, o.y || 0); } catch (e) {} sessionStorage.removeItem(key); }
    function remember() { sessionStorage.setItem(key, JSON.stringify({ y: window.scrollY, f: flt.value })); }
    document.addEventListener('click', function (e) { if (e.target.closest('.adm-act a, .addbtn')) remember(); });
    document.addEventListener('submit', function (e) { if (e.target.closest('.adm-act')) remember(); });
    <?php if ($flash): ?>if (window.toast) toast(<?= json_encode($flash) ?>);<?php endif ?>

    document.addEventListener('change', function (e) {
      var el = e.target.closest('[data-quick]'); if (!el) return;
      var box = el.type === 'checkbox', fd = new FormData(), tr = el.closest('tr');
      fd.append('csrf', CSRF); fd.append('act', 'quick'); fd.append('field', el.dataset.quick); fd.append('_pk', el.dataset.pk);
      fd.append('value', box ? (el.checked ? '1' : '0') : el.value);
      function undo(msg) { if (box) el.checked = el.dataset.prev === '1'; else el.value = el.dataset.prev; toast(msg, true); }
      fetch(location.pathname + location.search, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
        if (!j.ok) return undo(j.error || 'Error');
        el.dataset.prev = box ? (el.checked ? '1' : '0') : el.value;
        tr.classList.add('flash'); setTimeout(function () { tr.classList.remove('flash'); }, 1300); toast('Saved ✓');
      }).catch(function () { undo('Network error'); });
    });

    var busy = false;
    function ids(root) { return [].map.call(root.querySelectorAll('tr[data-id]'), function (r) { return r.dataset.id; }); }
    setInterval(function () {
      if (document.hidden || busy || (document.activeElement && document.activeElement.matches('[data-quick]'))) return;
      busy = true;
      fetch(location.href, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (html) {
        var d = new DOMParser().parseFromString(html, 'text/html'), nt = d.querySelector('#tbl tbody'), cur = document.querySelector('#tbl tbody');
        if (nt && cur && nt.innerHTML !== cur.innerHTML) {
          var old = ids(cur); cur.innerHTML = nt.innerHTML; applyFilter();
          var fresh = ids(cur).filter(function (i) { return old.indexOf(i) < 0; });
          cur.querySelectorAll('tr[data-id]').forEach(function (r) { if (fresh.indexOf(r.dataset.id) >= 0) r.classList.add('flash'); });
          if (fresh.length) toast('New: ' + fresh.length);
        }
        var na = d.querySelector('aside'), ca = document.querySelector('aside');
        if (na && ca) { var st = ca.scrollTop; ca.innerHTML = na.innerHTML; ca.scrollTop = st; }
      }).catch(function () {}).then(function () { busy = false; });
    }, 8000);
  })();
  </script>
<?php endif;
admin_foot();