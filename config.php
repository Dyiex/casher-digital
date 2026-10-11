<?php
declare(strict_types=1);

/* ---------- Database ---------- */
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'pos_system');
define('DB_USER', 'root');
define('DB_PASS', '');

/* ---------- Store settings ---------- */
define('STORE_NAME', 'Corner Market');
define('STORE_ADDRESS', 'Your store address here');
define('STORE_PHONE', '0900 000 0000');
define('CURRENCY', '₱');
define('TAX_RATE', 0.00);          // 0.12 = 12% tax added on top of the price
define('RECEIPT_FOOTER', 'Thank you for shopping with us!');

date_default_timezone_set('Asia/Manila');

/* ---------- Paths ---------- */
define('ROOT_PATH', dirname(__DIR__));
define('PUBLIC_PATH', ROOT_PATH . '/public');

/*
 * BASE_URL is detected automatically, so the app works whether you run
 *   php -S localhost:8000 -t public      (BASE_URL = '')
 * or put the folder in htdocs/www          (BASE_URL = '/pos-system/public')
 */
$docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
$pubRoot = realpath(PUBLIC_PATH) ?: '';
$base = '';
if ($docRoot !== '' && str_starts_with($pubRoot, $docRoot)) {
    $base = str_replace('\\', '/', substr($pubRoot, strlen($docRoot)));
}
define('BASE_URL', rtrim($base, '/'));
