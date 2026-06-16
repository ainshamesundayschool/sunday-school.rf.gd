<?php
// Simple script to migrate custom_fields json structures
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    require_once __DIR__ . '/../config.php';
}
// wait, config.php is probably needed but let's just use raw mysql if possible or write a script we can run
