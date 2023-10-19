<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

define('PARENT_DIR', rtrim(realpath(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);

if (! is_file(PARENT_DIR.'bootstrap.php')) {
    die('Cannot find app boostrapper. Re-sync this repository, as it won\'t work without the application boostrapper.');
}

require_once PARENT_DIR.'bootstrap.php';

// global variables
$SOURCE_ORIGIN = rtrim(SOURCE_ORIGIN, '/');
$CACHEABLE_EXTENSIONS = array_filter(array_map(function ($v) {
    $v = trim(strtolower($v));
    if ($v) {
        return $v;
    }

    return null;
}, explode(',', CACHEABLE_EXTENSIONS)));

// init the request with Symfony's HTTP Foundation
$request = Request::createFromGlobals();

// reply to OPTIONS request
if ($request->getMethod() === 'OPTIONS') {
    $response = new Response('', 204, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Credentials' => 'true',
        'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
        'Access-Control-Allow-Headers' => 'DNT, X-User-Token, Keep-Alive, User-Agent, X-Requested-With, If-Modified-Since, Cache-Control, Content-Type',
        'Access-Control-Max-Age' => $CACHE_EXPIRY,
        'Content-Type' => 'text/plain charset=UTF-8',
        'Content-Length' => 0,
    ]);
    $response->send();
    exit();
}

// initialize per-request variables
$server = $request->getScheme().'://'.$request->getHttpHost();
$request_file = $request->getPathInfo();

// avoid directory traversal: if the request file contains a "/.." in the path, we're going to reply with a 404 error
if (strpos($request_file, '/..') !== false) {
    error($request_file, -1);
}

$extension = trim(strtolower(pathinfo($request_file, PATHINFO_EXTENSION)));
$cache_dir = PUBLIC_DIR.rtrim(dirname($request_file), DS).DS;
$cache_file = PUBLIC_DIR.$request_file;

// can we work with the file extension arrived in the request?
if ($extension && in_array($extension, $CACHEABLE_EXTENSIONS)) {
    // transparent proxy
    $result = getRemoteFile($server, $SOURCE_ORIGIN.$request_file);
    if ($result !== null) {
        // response stream
        $stream = $result['body'];
        $headers = $result['headers'];

        // rewrite absolute paths in CSS and JS
        if (in_array($extension, ['css', 'js'])) {
            $stream = str_replace('url(/', 'url('.rtrim($server, '/').'/', $stream);
            $stream = str_replace('url(\'/', 'url(\''.rtrim($server, '/').'/', $stream);
        }

        // create the cache dir in the public folder, based on the folder name of the requested file
        if (! is_dir($cache_dir)) {
            @mkdir($cache_dir, 0755, true);
            if (! is_dir($cache_dir)) {
                error($request_file, 1);
            }
        }

        // create the file in the public folder
        @file_put_contents($cache_file, $stream);
        if (! is_file($cache_file)) {
            error($request_file, 2);
        }

        // is this an image?
        // if yes, we rebuild it to webp, with the same filename but with the webp extension
        // to perform the conversion, we're going to rely on the WEBP_API
        // compatible with https://github.com/mauriziofonte/php-image-to-webp-conversion-api
        if (in_array($extension, ['jpg', 'jpeg', 'gif', 'png', 'bmp']) && WEBP_API_SERVER && WEBP_API_KEY) {
            $images = [['path' => $cache_file, 'file_id' => basename($cache_file)]];
            $webp_api_response = imageToWebpApi(WEBP_API_SERVER, WEBP_API_KEY, $images);
            if ($webp_api_response['status'] === true && $webp_api_response['response'][0]['status'] === true) {
                $conversion_data = $webp_api_response['response'][0];
                if (($decoded = @base64_decode($conversion_data['webp_image_base64'])) !== false) {
                    $new_filename =
                        pathinfo($cache_file, PATHINFO_DIRNAME).
                        DS.
                        pathinfo($cache_file, PATHINFO_FILENAME).
                        '.webp';
                    file_put_contents($new_filename, $decoded);
                    $headers['content-type'] = 'image/webp';
                    $headers['content-length'] = filesize($new_filename);
                    $stream = $decoded;
                }
            }
        }

        // craft the response
        $response = new Response();
        $response->setStatusCode($result['response_code']);

        // pingback the response headers from the origin server
        $response->headers->add($headers);

        // owerwrite some headers
        $response->setPublic();
        $response->setMaxAge(CACHE_EXPIRY);
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->headers->set('x-cache-status', 'miss');

        // response
        $response->setContent($stream);
        $response->send();

        // eventually purge the cached file, if the origin file reported an "expires" header that is in the past
        if (array_key_exists('expires', $headers) && strtotime($headers['expires']) < time()) {
            @unlink($cache_file);
        }

        // exit the script
        exit();
    } else {
        // cURL error while fetching the origin file
        error($request_file, 3);
    }
}

// fallback: can't serve this request. Simply reply with a 404 error
error($request_file, 4);
