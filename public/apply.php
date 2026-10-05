<?php
require __DIR__ . '/../src/bootstrap.php';
require ROOT . '/src/view.php';
require ROOT . '/src/upload.php';
$me = require_user();

// [key, label, kind, required, options]
$guarantor = [['guarantor_name', 'Guarantor name', 'text', 0], ['guarantor_phone', 'Guarantor phone', 'text', 0], ['guarantor_id', 'Guarantor ID no.', 'text', 0], ['guarantor_rel', 'Relationship to guarantor', 'text', 0]];
$forms = [
  'guide' => ['table' => 'guide_applications', 'title' => 'Become a Guide', 'fields' => array_merge([
     ['name', 'Full name', 'text', 1], ['phone', 'Phone', 'text', 1], ['email', 'Email', 'email', 1], ['telegram', 'Telegram', 'text', 0],
     ['dob', 'Date of birth', 'date', 0], ['gender', 'Gender', 'select', 0, ['male', 'female', 'other']],
     ['id_card_no', 'ID card number', 'text', 1], ['id_card_photo', 'ID card photo (JPG/PNG/PDF)', 'file', 1], ['home_address', 'Home address', 'textarea', 1],
     ['provinces', 'Provinces you guide in', 'text', 0], ['languages', 'Languages', 'text', 0], ['years_exp', 'Years of experience', 'number', 0],
     ['daily_rate_usd', 'Daily rate (USD)', 'number', 0], ['bio', 'About you', 'textarea', 0], ['profile_photo', 'Profile photo', 'file', 0],
     ['warranty_letter', 'Warranty letter', 'file', 0]], $guarantor)],
  'transport' => ['table' => 'transport_applications', 'title' => 'Partner With Us (Transport)', 'fields' => array_merge([
     ['name', 'Full name', 'text', 1], ['phone', 'Phone', 'text', 1], ['email', 'Email', 'email', 1], ['telegram', 'Telegram', 'text', 0],
     ['home_address', 'Home address', 'textarea', 1], ['id_card_no', 'ID card number', 'text', 1], ['id_card_photo', 'ID card photo (JPG/PNG/PDF)', 'file', 1],
     ['vehicle_type', 'Vehicle type', 'select', 1, ['taxi', 'tuk_tuk', 'van', 'bus', 'other']], ['vehicle_model', 'Vehicle model', 'text', 0],
     ['vehicle_capacity', 'Passenger capacity', 'number', 0], ['license_plate', 'License plate', 'text', 0], ['driving_license', 'Driving license no.', 'text', 0],
     ['vehicle_photo', 'Vehicle photo', 'file', 0], ['base_province', 'Base province', 'text', 0], ['price_per_day', 'Price per day (USD)', 'number', 0],
     ['warranty_letter', 'Warranty letter', 'file', 0]], $guarantor)],
];
$type = $_GET['type'] ?? 'guide';
if (!isset($forms[$type])) redirect(u('index.php'));
$cfg = $forms[$type];
$last = row("select status,admin_notes from {$cfg['table']} where user_id=? order by id desc limit 1", [$me['id']]);
$blocked = $last && in_array($last['status'], ['pending', 'approved'], true);
$err = ''; $done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$blocked) {
    check_csrf();
    try {
        $cols = ['user_id']; $vals = [$me['id']];
        foreach ($cfg['fields'] as $f) {
            [$k, $label, $kind, $req] = $f;
            $v = $kind === 'file' ? save_upload($k, 'applications', true) : (trim((string)($_POST[$k] ?? '')) ?: null);
            if ($req && $v === null) throw new RuntimeException("$label is required.");
            if ($v !== null && $kind === 'select' && !in_array($v, $f[4], true)) throw new RuntimeException("$label is not valid.");
            if ($v !== null && $kind === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid email.');
            if ($v !== null) { $cols[] = $k; $vals[] = $kind === 'number' ? $v + 0 : $v; }
        }
        q("insert into {$cfg['table']} (" . implode(',', $cols) . ') values (' . implode(',', array_fill(0, count($cols), '?')) . ')', $vals);
        $done = true; $blocked = true; $last = ['status' => 'pending', 'admin_notes' => null];
    } catch (RuntimeException $ex) { $err = $ex->getMessage(); }
    catch (PDOException $ex) { error_log($ex->getMessage()); $err = 'Could not save your application. Please check the values.'; }
}
page_head($cfg['title'] . ' — Discover Cambodia');
nav(); ?>
<main class="wrap section">
  <p class="label upper"><?= e(t('svc_label')) ?></p>
  <h2 class="sec-title"><?= e($cfg['title']) ?></h2>
  <?php if ($blocked): ?>
    <div class="panelbox"><h3><?= $done ? 'Application sent ✓' : 'Your application' ?></h3>
      <p>Status: <b class="upper"><?= e($last['status']) ?></b></p>
      <p class="muted"><?= $last['status'] === 'approved' ? 'You are approved. Thank you for joining us!' : 'We will review it and send you a notification (🔔) when it is approved or rejected.' ?></p>
      <a class="chip" href="<?= u('profile.php') ?>"><?= e(t('profile')) ?></a></div>
  <?php else: ?>
    <?php if ($last && $last['status'] === 'rejected'): ?><p class="err">Your last application was rejected<?= $last['admin_notes'] ? ': ' . e($last['admin_notes']) : '' ?>. You can apply again.</p><?php endif ?>
    <form method="post" enctype="multipart/form-data" class="panelbox">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <div class="formgrid">
      <?php foreach ($cfg['fields'] as $f): [$k, $label, $kind, $req] = $f;
            $v = $_POST[$k] ?? ($k === 'name' ? $me['name'] : ($k === 'email' ? $me['email'] : ($k === 'phone' ? $me['phone'] : ''))); ?>
        <label class="<?= $kind === 'textarea' ? 'wide' : '' ?>"><?= e($label) ?><?= $req ? ' *' : '' ?>
          <?php if ($kind === 'textarea'): ?><textarea name="<?= $k ?>" rows="3"><?= e($v) ?></textarea>
          <?php elseif ($kind === 'select'): ?><select name="<?= $k ?>"><option value="">—</option><?php foreach ($f[4] as $o): ?><option <?= $v === $o ? 'selected' : '' ?>><?= e($o) ?></option><?php endforeach ?></select>
          <?php elseif ($kind === 'file'): ?><input type="file" name="<?= $k ?>" accept="image/jpeg,image/png,image/webp,application/pdf">
          <?php else: ?><input type="<?= $kind ?>" name="<?= $k ?>" value="<?= e($v) ?>" step="any"><?php endif ?></label>
      <?php endforeach ?>
      </div>
      <?php if ($err): ?><p class="err"><?= e($err) ?></p><?php endif ?>
      <p class="muted">Documents are stored privately and only our admin can open them.</p>
      <button class="btn-gold upper">Send application</button>
    </form>
  <?php endif ?>
</main>
<?php page_foot();
