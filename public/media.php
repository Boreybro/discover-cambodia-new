<?php
// Serves a private photo ONLY if it belongs to an approved, active guide / transport partner.
require __DIR__ . '/../src/bootstrap.php';
$f = (string)($_GET['f'] ?? '');
if (!preg_match('~^applications/[a-f0-9]{24}\.(jpg|png|webp)$~', $f) || !is_file(ROOT . '/storage/' . $f)) { http_response_code(404); exit; }
$v = 'private:' . $f;
$ok = val("select 1 from guides where profile_photo=? and status='approved' and is_active=1 union all select 1 from transport_partners where vehicle_photo=? and status='approved' and is_active=1 limit 1", [$v, $v]);
if (!$ok) { http_response_code(404); exit; }
header('Content-Type: ' . mime_content_type(ROOT . '/storage/' . $f));
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile(ROOT . '/storage/' . $f);
