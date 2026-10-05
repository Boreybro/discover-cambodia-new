<?php
require __DIR__ . '/../../src/bootstrap.php';
require ROOT . '/src/admin.php';
admin_required();
$f = (string)($_GET['f'] ?? '');
if (!preg_match('~^(applications|payments|bookings)/[a-f0-9]{24}\.(jpg|png|webp|pdf)$~', $f) || !is_file(ROOT . '/storage/' . $f)) { http_response_code(404); exit('Not found'); }
header('Content-Type: ' . mime_content_type(ROOT . '/storage/' . $f));
header('X-Content-Type-Options: nosniff');
readfile(ROOT . '/storage/' . $f);
