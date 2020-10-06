<?php
    if (! is_file('load.php') || ! is_file('functions.php')) {
        die('Cannot find global loader or global functions. Re-sync this repository, as it won\'t work without the application loader.');
    }

    require_once 'load.php';
    require_once 'functions.php';
    
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;

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
            'Content-Length' => 0
        ]);
        $response->send();
        exit();
    }

    // initialize per-request variables
    $server = $request->getScheme() . '://' . $request->getHttpHost();
    $request_file = $request->getPathInfo();
    $extension = strtolower(pathinfo($request_file, PATHINFO_EXTENSION));
    $cache_dir = rtrim(RD, DS) . dirname($request_file) . DS;
    $cache_file = rtrim(RD, DS) . $request_file;
    
    // can we work with the file extension arrived in the request?
    if (in_array($extension, $CACHEABLE_EXTENSIONS)) {
        
        // transparent proxy
        $result = getRemoteFile($server, $SOURCE_HOST . $request_file);
        if ($result !== null) {
            
            // response stream
            $stream = $result['body'];
            
            // rewrite absolute paths in CSS and JS
            if (in_array($extension, ['css', 'js'])) {
                $stream = str_replace('url(/', 'url(' . rtrim($server, '/') . '/', $stream);
                $stream = str_replace('url(\'/', 'url(\'' . rtrim($server, '/') . '/', $stream);
            }
            
            // recursively create the cache dir in this repo
            if (! is_dir($cache_dir)) {
                @mkdir($cache_dir, 0755, true);
            }
            file_put_contents($cache_file, $stream);

            // initialize the headers accordingly to response header received from the origin server
            $etag = $last_modified = $content_type = $content_length = null;
            foreach ($result['headers'] as $header) {
                $parts = explode(':', $header);
                if (array_key_exists(0, $parts) && array_key_exists(1, $parts)) {
                    $header_name = trim($parts[0]);
                    $header_value = trim($parts[1]);
                    if (stripos($header_name, 'etag') !== false) {
                        $etag = $header_value;
                    }
                    if (stripos($header_name, 'last-modified') !== false) {
                        $last_modified = $header_value;
                    }
                    if (stripos($header_name, 'content-type') !== false) {
                        $content_type = $header_value;
                    }
                    if (stripos($header_name, 'content-length') !== false) {
                        $content_length = $header_value;
                    }
                }
            }

            // is this an image?
            // if yes, we rebuild it to webp, with the same filename but with the webp extension
            // to perform the conversion, we're going to rely on the WEBP_API
            // compatible with https://github.com/mauriziofonte/php-image-to-webp-conversion-api
            if (in_array($extension, ['jpg', 'jpeg', 'gif', 'png', 'bmp']) && $WEBP_API_SERVER && $WEBP_API_KEY) {
                $images = [['path' => $cache_file, 'file_id' => basename($cache_file)]];
                $webp_api_response = imageToWebpApi($WEBP_API_SERVER, $WEBP_API_KEY, $images);
                if ($webp_api_response['status'] === true && $webp_api_response['response'][0]['status'] === true) {
                    $conversion_data = $webp_api_response['response'][0];
                    if (($decoded = @base64_decode($conversion_data['webp_image_base64'])) !== false) {
                        $new_filename =
                            pathinfo($cache_file, PATHINFO_DIRNAME) .
                            DS .
                            pathinfo($cache_file, PATHINFO_FILENAME) .
                            '.webp';
                        file_put_contents($new_filename, $decoded);
                        $content_type = 'image/webp';
                        $content_length = filesize($new_filename);
                        $stream = $decoded;
                    }
                }
            }

            $response = new Response();
            $response->setStatusCode($result['response_code']);
            $response->setPublic();
            $response->setMaxAge($CACHE_EXPIRY);
            $response->headers->addCacheControlDirective('must-revalidate', true);
            if ($etag) {
                $response->headers->set('ETag', $etag);
            }
            if ($last_modified) {
                $response->headers->set('Last-Modified', $last_modified);
            }
            if ($content_type) {
                $response->headers->set('Content-Type', $content_type);
            }
            if ($content_length) {
                $response->headers->set('Content-Length', $content_length);
            }

            // response
            $response->setContent($stream);
            $response->send();
            exit();
        }
    }

    // fallback: can't serve this request. Simply reply with a 404 error
    http_response_code(404);
    echo 'The request could not be processed. Ray ID: ' . md5($request_file);