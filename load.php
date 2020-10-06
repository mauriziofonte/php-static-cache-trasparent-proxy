<?php
    if (function_exists('get_included_files') && count(get_included_files()) == 1) {
        exit();
    }
    
    define('DS', DIRECTORY_SEPARATOR);
    define('RD', rtrim(dirname(__FILE__), DS) . DS);
    define('CONF', RD . '.env');
    define('VENDOR', RD . 'vendor' . DS . 'autoload.php');
    define(
        'IS_HTTPS',
        (
            (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
            (isset($_SERVER['SERVER_PORT']) && intval($_SERVER['SERVER_PORT']) === 443) ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https')
        ) ? true : false
    );
    define('FROM_CLI', (php_sapi_name() === 'cli' or defined('STDIN')) ? true : false);

    if (!is_file(VENDOR)) {
        die('Cannot find application vendor autoload. Please, read the attached README.md for instructions on how to install this application');
    }
    if (!is_file(CONF)) {
        die('Cannot find application .env. Please, read the attached README.md for instructions on how to install this application');
    }

    require_once VENDOR;

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
            'WEBP_API_KEY'
        ]);
    } catch (Exception $ex) {
        die('Error in .env variables: ' . $ex -> getMessage());
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
    setlocale(LC_MONETARY, getenv('LOCALE') . '.' . getenv('CHARSET'));

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