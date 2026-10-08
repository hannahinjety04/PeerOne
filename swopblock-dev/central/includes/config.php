<?php
/**
 * Central node configuration.
 * Copy to config.local.php and override values if needed (optional).
 *
 * Production (cloud/XAMPP MySQL): db_driver => mysql
 * Local development package:      db_driver => sqlite
 */

$defaults = [
    'db_driver' => 'mysql',
    'db_host' => 'localhost',
    'db_name' => 'server_schema',
    'db_user' => 'root',
    'db_pass' => '',
    // Used when db_driver is sqlite (local development package)
    'db_path' => dirname(__DIR__) . '/data/central.db',
    'package_version' => '1.0.0',
    // Admin must exist in Clients table with this email
    'admin_email' => 'owner@central.local',
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $overrides = require $local;
    if (is_array($overrides)) {
        $defaults = array_merge($defaults, $overrides);
    }
}

return $defaults;
