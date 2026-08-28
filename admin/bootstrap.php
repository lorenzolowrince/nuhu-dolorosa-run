<?php
// Resolves project root whether admin/ sits directly in it (local)
// or inside public_html/ (Hostinger shared hosting).
$_root = is_dir(dirname(__DIR__) . '/app') ? dirname(__DIR__) : dirname(dirname(__DIR__));
require_once $_root . '/app/config.php';
unset($_root);
