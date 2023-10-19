<?php

if (function_exists('get_included_files') && count(get_included_files()) == 1) {
    exit();
}

// check for availability of curl_ functions
if (! function_exists('curl_init')) {
    die('cURL extension is not installed');
}

define('DS', DIRECTORY_SEPARATOR);
define('ROOT_DIR', rtrim(__DIR__, DS).DS);
define('CONF_FILE', ROOT_DIR.'.env');
define('HELP_DIR', ROOT_DIR.'helpers'.DS);
define('VENDOR_DIR', ROOT_DIR.'vendor'.DS);
define('PUBLIC_DIR', ROOT_DIR.'public'.DS);
define(
    'IS_HTTPS',
    (
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
        (isset($_SERVER['SERVER_PORT']) && (int) ($_SERVER['SERVER_PORT']) === 443) ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https')
    ) ? true : false
);
define('FROM_CLI', (php_sapi_name() === 'cli' || defined('STDIN')) ? true : false);

if (! is_file(CONF_FILE)) {
    die('Cannot find application .env. Please, read the attached README.md for instructions on how to install this application');
}
if (! is_file(VENDOR_DIR.'autoload.php')) {
    die('Cannot find application vendor autoload. Please, read the attached README.md for instructions on how to install this application');
}
if (! is_file(HELP_DIR.'functions.php')) {
    die('Cannot find application helpers/functions.php. Please, read the attached README.md for instructions on how to install this application');
}

// require autoload and helpers
require_once VENDOR_DIR.'autoload.php';
require_once HELP_DIR.'functions.php';

// load (and eventually cache) the .env config
loadConfig(ROOT_DIR);

// debug mode?
if (DEBUG) {
    @error_reporting(E_ALL);
    @ini_set('display_errors', 1);
} else {
    error_reporting(0);
    @ini_set('display_errors', 'Off');
}

// charset, timezone, locale
@ini_set('default_charset', CHARSET);
date_default_timezone_set(TIMEZONE);
setlocale(LC_TIME, LOCALE);
setlocale(LC_MONETARY, implode('.', [LOCALE, CHARSET]));

/*

$dotenv = Dotenv\Dotenv::createUnsafeImmutable(__DIR__);
$dotenv->load();
try {
    $dotenv->required([
        'SOURCE_HOST',
        'CHARSET',
        'TIMEZONE',
        'LOCALE',
        'CACHEABLE_EXTENSIONS',
        'WEBP_API_SERVER',
        'WEBP_API_KEY',
    ]);
} catch (Exception $ex) {
    die('Error in .env variables: '.$ex->getMessage());
}

$APP_DEBUG = (in_array(getenv('DEBUG'), [true, 'true', 1])) ? true : false;
if ($APP_DEBUG) {
    @error_reporting(E_ALL);
    @ini_set('display_errors', 1);
} else {
    error_reporting(0);
    @ini_set('display_errors', 'Off');
}

@ini_set('default_charset', getenv('CHARSET'));
date_default_timezone_set(getenv('TIMEZONE'));
setlocale(LC_TIME, getenv('LOCALE'));
setlocale(LC_MONETARY, getenv('LOCALE').'.'.getenv('CHARSET'));

$SOURCE_HOST = rtrim(getenv('SOURCE_HOST'), '/');
$CACHEABLE_EXTENSIONS = array_filter(array_map(function ($v) {
    $v = trim(strtolower($v));
    if ($v) {
        return $v;
    }

    return null;
}, explode(',', getenv('CACHEABLE_EXTENSIONS'))));
$CACHE_EXPIRY = getenv('CACHE_EXPIRY');
$WEBP_API_SERVER = getenv('WEBP_API_SERVER');
$WEBP_API_KEY = getenv('WEBP_API_KEY');
*/
