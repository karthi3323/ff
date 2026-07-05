<?php
// Read server configuration from sysconfig.txt
if(file_exists('sysconfig.txt')){
    $server = trim(file_get_contents('sysconfig.txt', true));
} else if(file_exists('./sysconfig.txt')){
    $server = trim(file_get_contents('./sysconfig.txt', true));
} else if(file_exists('../sysconfig.txt')){
    $server = trim(file_get_contents('../sysconfig.txt', true));
} else if(file_exists('../../sysconfig.txt')){
    $server = trim(file_get_contents('../../sysconfig.txt', true));
} else {
    // Fallback to localhost if file not found
    $server = 'localhost';
}
$server = 'localhost';
// Define constants
define('SITE_NAME', 'Billing Software');
define('COMPANY_NAME', '');
define('CURRENCY', 'Rs.');
define('DIR_WS_SERVER', 'http://' . $server . '/ff/');
define('BASE_URL', DIR_WS_SERVER);
define('ASSETS_URL', BASE_URL . 'assets');
/* define('SITE_NAME', 'Billing Software');
define('COMPANY_NAME', 'Store');
define('CURRENCY', '₹'); */

?>
