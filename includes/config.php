<?php
/**
 * Family Care Hospital – core configuration.
 * Edit these values for your server. Everything else (hospital phone,
 * address, fees, booking window) is edited from the admin panel → Settings.
 */

date_default_timezone_set('Asia/Colombo');

// Database: 'sqlite' needs no setup. Switch to 'mysql' and fill the details if preferred.
define('DB_DRIVER', getenv('FC_DB_DRIVER') ?: 'sqlite');
define('DB_SQLITE_PATH', __DIR__ . '/../data/family_care.sqlite');
define('DB_HOST', getenv('FC_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('FC_DB_NAME') ?: 'family_care');
define('DB_USER', getenv('FC_DB_USER') ?: 'root');
define('DB_PASS', getenv('FC_DB_PASS') ?: '');

// Where doctor photos are stored (must be writable by PHP)
define('UPLOAD_DIR', __DIR__ . '/../uploads/doctors');
define('UPLOAD_URL', 'uploads/doctors');

// Set to true while developing to see PHP errors in the browser
define('APP_DEBUG', getenv('FC_DEBUG') === '1');

ini_set('display_errors', APP_DEBUG ? '1' : '0');
error_reporting(E_ALL);
