<?php
require __DIR__ . '/../src/bootstrap.php';
try { val('select 1'); json_out(['ok' => true, 'db' => true]); }
catch (Throwable $e) { json_out(['ok' => false, 'db' => false], 503); }
