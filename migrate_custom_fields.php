<?php
// Simple script to migrate custom_fields json structures
$configRoot = __DIR__;
while ($configRoot && !file_exists($configRoot . '/api.php')) {
    $configParent = dirname($configRoot);
    if ($configParent === $configRoot) {
        break;
    }
    $configRoot = $configParent;
}
$isTesting = (strpos($configRoot, '/testing') !== false);
$configName = $isTesting ? 'config-testing.php' : 'config.php';

if (file_exists($configRoot . '/' . $configName)) {
    require_once $configRoot . '/' . $configName;
} elseif (file_exists(dirname($configRoot) . '/' . $configName)) {
    require_once dirname($configRoot) . '/' . $configName;
} else {
    require_once $configRoot . '/config.php';
}
// wait, config.php is probably needed but let's just use raw mysql if possible or write a script we can run
