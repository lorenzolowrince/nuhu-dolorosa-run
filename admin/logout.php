<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once APP_PATH . '/database.php';
require_once APP_PATH . '/functions.php';
require_once APP_PATH . '/auth.php';

Auth::logout();
setFlash('success', 'You have been logged out successfully.');
redirect(APP_URL . '/admin/login.php');
