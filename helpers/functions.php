<?php

/**
 * Creates a static file named ".cached.env.php" on the root project dir, to avoid loading the .env file on every request.
 *
 * @param string $rootDir
 *
 * @return void
 */
function loadConfig(string $rootDir)
{
    $cacheConfigFile = "{$rootDir}.cached.env.php";
    if (file_exists($cacheConfigFile)) {
        require_once $cacheConfigFile;

        return;
    }

    // fail if we have no .env file
    if (! file_exists("{$rootDir}.env")) {
        error(null, -3);
    }

    // load .env file
    try {
        $dotenv = \Dotenv\Dotenv::createUnsafeImmutable($rootDir);
        $dotenv->load();
        $dotenv->required([
            'SOURCE_ORIGIN',
            'CHARSET',
            'TIMEZONE',
            'LOCALE',
            'CACHEABLE_EXTENSIONS',
            'WEBP_API_SERVER',
            'WEBP_API_KEY',
        ]);
    } catch (\Exception $e) {
        error(null, -4);
    }

    $defineKeys = [
        'DEBUG' => (array_key_exists('DEBUG', $_ENV) && in_array($_ENV['DEBUG'], [true, 'true', 1])) ? true : false,
        'SOURCE_ORIGIN' => (array_key_exists('SOURCE_ORIGIN', $_ENV) && $_ENV['SOURCE_ORIGIN'] !== 'null') ? rtrim($_ENV['SOURCE_ORIGIN'], '/') : null,
        'CHARSET' => (array_key_exists('CHARSET', $_ENV) && $_ENV['CHARSET'] !== 'null') ? $_ENV['CHARSET'] : 'UTF-8',
        'TIMEZONE' => (array_key_exists('TIMEZONE', $_ENV) && $_ENV['TIMEZONE'] !== 'null') ? $_ENV['TIMEZONE'] : date_default_timezone_get(),
        'LOCALE' => (array_key_exists('LOCALE', $_ENV) && $_ENV['LOCALE'] !== 'null') ? $_ENV['LOCALE'] : 'en_US',
        'CACHEABLE_EXTENSIONS' => (array_key_exists('CACHEABLE_EXTENSIONS', $_ENV) && $_ENV['CACHEABLE_EXTENSIONS'] !== 'null') ? $_ENV['CACHEABLE_EXTENSIONS'] : null,
        'CACHE_EXPIRY' => (array_key_exists('CACHE_EXPIRY', $_ENV) && $_ENV['CACHE_EXPIRY'] !== 'null') ? $_ENV['CACHE_EXPIRY'] : 86400,
        'WEBP_API_SERVER' => (array_key_exists('WEBP_API_SERVER', $_ENV) && $_ENV['WEBP_API_SERVER'] !== 'null') ? $_ENV['WEBP_API_SERVER'] : null,
        'WEBP_API_KEY' => (array_key_exists('WEBP_API_KEY', $_ENV) && $_ENV['WEBP_API_KEY'] !== 'null') ? $_ENV['WEBP_API_KEY'] : null,
    ];

    $configStrings = array_map(function ($key, $value) {
        if ($value === null) {
            return "define('{$key}', null);";
        } elseif (is_bool($value)) {
            return "define('{$key}', ".($value ? 'true' : 'false').');';
        } elseif (is_numeric($value)) {
            return "define('{$key}', {$value});";
        } elseif (is_string($value)) {
            return "define('{$key}', '{$value}');";
        } else {
            return "define('{$key}', null);";
        }
    }, array_keys($defineKeys), array_values($defineKeys));

    $configContent = '<?php'.PHP_EOL.'// cached config file on '.date('Y-m-d H:i:s').PHP_EOL;
    $configContent .= implode(PHP_EOL, $configStrings);

    @file_put_contents($cacheConfigFile, $configContent);

    // check if the cached config file is created
    if (! file_exists($cacheConfigFile) || filesize($cacheConfigFile) < strlen($configContent)) {
        error(null, -2);
    }

    // load the cached config file
    require_once $cacheConfigFile;
}

/**
 * Simple helper to send a 404 error, with an optional message, based on a Request File and optionally an Error Type.
 *
 * @param string|null $request_file
 * @param int $error_type
 *
 * @return void
 */
function error(?string $request_file = null, int $error_type = 0)
{
    $err_message = '';
    match ($error_type) {
        -4 => $err_message = 'ENV_FILE_LOAD_ERR',
        -3 => $err_message = 'ENV_FILE_NOT_FOUND',
        -2 => $err_message = 'CACHED_CONFIG_FILE_NOT_CREATED',
        -1 => $err_message = 'Cannot serve this request',
        0 => $err_message = 'Generic error',
        1 => $err_message = 'Cannot create cache directory',
        2 => $err_message = 'Cannot create cache file',
        3 => $err_message = 'Cannot read remote origin file',
        4 => $err_message = 'Request cannot be processed',
        default => $err_message = 'Unknown error',
    };

    http_response_code(404);
    $hash = ($request_file) ? md5($request_file) : md5(microtime(true));
    echo "The request could not be processed: {$err_message}. Ray ID: {$hash}";
    exit();
}

/**
 * Fetches the origin_url file with cURL, and returns an array with the response code, headers and body.
 *
 * @param string $cdn_server
 * @param string $origin_url
 *
 * @return array|null
 */
function getRemoteFile(string $cdn_server, string $origin_url) : ?array
{
    $opts = [];
    $http_headers = [];
    $http_headers[] = 'Expect:';
    $http_headers[] = 'Referer: '.$cdn_server;
    $http_headers[] = 'Pragma: no-cache';
    $http_headers[] = 'Cache-Control: no-cache';
    $http_headers[] = 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:81.0) Gecko/20100101 Firefox/81.0';

    $opts[CURLOPT_URL] = $origin_url;
    $opts[CURLOPT_HTTPHEADER] = $http_headers;
    $opts[CURLOPT_CONNECTTIMEOUT] = 5;
    $opts[CURLOPT_TIMEOUT] = 60;
    $opts[CURLOPT_HEADER] = true;
    $opts[CURLOPT_VERBOSE] = false;
    $opts[CURLOPT_SSL_VERIFYPEER] = false;
    $opts[CURLOPT_SSL_VERIFYHOST] = 2;
    $opts[CURLOPT_RETURNTRANSFER] = true;
    $opts[CURLOPT_FOLLOWLOCATION] = true;
    $opts[CURLOPT_MAXREDIRS] = 4;
    $opts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;

    // Initialize PHP/CURL handle
    $ch = curl_init();
    curl_setopt_array($ch, $opts);
    $response = @curl_exec($ch);
    $header_size = @curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $response_code = intval(@curl_getinfo($ch, CURLINFO_RESPONSE_CODE));
    $error = curl_errno($ch);
    $errstr = curl_error($ch);
    curl_close($ch);

    if ($error === 0 && $response) {
        $header = substr($response, 0, $header_size);
        $headers = array_values(array_filter(explode(chr(10), trim(str_replace([chr(13).chr(10), chr(13)], chr(10), $header))), function ($h) {
            // make sure this header has a colon
            return strlen($h) && strpos($h, ':') !== false;
        }));
        $body = substr($response, $header_size);

        // flag some header keys to be removed
        $headerRemoveKeys = [
            'content-encoding',
            'transfer-encoding',
            'connection',
            'keep-alive',
            'proxy-authenticate',
            'proxy-authorization',
            'te',
            'trailers',
            'upgrade',
            'server',
            'date',
        ];

        // map each header so that we split its name and value.
        // also, make sure the names are lowercased
        $headers = array_filter(mapassoc(function ($key, $value) use ($headerRemoveKeys) {
            list($name, $value) = explode(':', $value, 2);

            $key = strtolower(trim($name));
            $val = trim($value);

            if (! in_array($key, $headerRemoveKeys)) {
                return [$key, $val];
            }

            return null;
        }, $headers));

        return [
            'response_code' => $response_code,
            'headers' => $headers,
            'body' => $body,
        ];
    }

    return null;
}

/**
 * Associative array map.
 * Similar to array_map, but works on associative arrays
 * Example: mapassoc(function($key, $value) use($something) { return [ $key, $value ]; });.
 *
 * @param callable $func
 * @param array $arr
 *
 * @return array|null
 */
function mapassoc(callable $func, ?array $arr): ?array
{
    if (is_array($arr)) {
        return array_column(array_map($func, array_keys($arr), $arr), 1, 0);
    }

    return null;
}

/**
 * Converts an image to webp, using the WEBP_API.
 *
 * @see https://github.com/mauriziofonte/php-image-to-webp-conversion-api
 *
 * @param string $api_url
 * @param string $api_key
 * @param array $images
 *
 * @return array
 */
function imageToWebpApi(string $api_url, string $api_key, array $images) : array
{
    // Create an array of files to post via cUrl and the file descriptors
    $postData = $descriptors = [];
    foreach ($images as $index => $file_data) {
        if (is_file($file_data['path'])) {
            $realpath = realpath($file_data['path']);
            $mime = mime_content_type($file_data['path']);
            $basename = basename($file_data['path']);

            $postData['images['.$index.']'] = curl_file_create(
                $realpath,
                $mime,
                $basename
            );

            unset($file_data['path']);
            $file_data['filename'] = $basename;
            $descriptors[] = $file_data;
        }
    }

    // append the descriptors json object to POST data
    $postData['descriptors'] = json_encode($descriptors);

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        '-x-api-key: '.$api_key,
        'User-Agent: PHP cUrl connector for MWEBP',
    ]);
    $ret = curl_exec($ch);
    $status_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if (
        ($decoded = @json_decode($ret, true)) !== null &&
        is_array($decoded) &&
        $status_code === 200 &&
        array_key_exists('response', $decoded)
    ) {
        return [
            'status' => true,
            'response' => $decoded['response'],
        ];
    } else {
        if (is_array($decoded) && array_key_exists('message', $decoded)) {
            $error_message = $decoded['message'];
        } else {
            $error_message = 'Unknown error with HTTP_RESPONSE_CODE="'.$status_code.'"';
        }

        return [
            'status' => false,
            'error' => $error_message,
        ];
    }
}
