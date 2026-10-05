<?php
require __DIR__ . '/../../src/bootstrap.php';
unset($_SESSION['admin']); redirect(u('admin/login.php'));
