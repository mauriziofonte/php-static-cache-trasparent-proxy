<?php

    function getRemoteFile(string $origin_server, string $remote_url) : ?array
    {
        $opts                                   = array();
        $http_headers                           = array();
        $http_headers[]                         = 'Expect:';
        $http_headers[]                         = 'Referer: ' . $origin_server;
        $http_headers[]                         = 'Pragma: no-cache';
        $http_headers[]                         = 'Cache-Control: no-cache';
        $http_headers[]                         = 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:81.0) Gecko/20100101 Firefox/81.0';

        $opts[CURLOPT_URL]                      = $remote_url;
        $opts[CURLOPT_HTTPHEADER]               = $http_headers;
        $opts[CURLOPT_CONNECTTIMEOUT]           = 5;
        $opts[CURLOPT_TIMEOUT]                  = 60;
        $opts[CURLOPT_HEADER]                   = true;
        $opts[CURLOPT_BINARYTRANSFER]           = true;
        $opts[CURLOPT_VERBOSE]                  = false;
        $opts[CURLOPT_SSL_VERIFYPEER]           = false;
        $opts[CURLOPT_SSL_VERIFYHOST]           = 2;
        $opts[CURLOPT_RETURNTRANSFER]           = true;
        $opts[CURLOPT_FOLLOWLOCATION]           = true;
        $opts[CURLOPT_MAXREDIRS]                = 2;
        $opts[CURLOPT_IPRESOLVE]                = CURL_IPRESOLVE_V4;

        # Initialize PHP/CURL handle
        $ch = curl_init();
        curl_setopt_array($ch, $opts);
        $response = @curl_exec($ch);
        $header_size = @curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $response_code = intval(@curl_getinfo($ch, CURLINFO_RESPONSE_CODE));
        $error = curl_errno($ch);
        curl_close($ch);
        
        if ($error === 0 && $response) {
            $header = substr($response, 0, $header_size);
            $headers = explode(chr(10), trim(str_replace(chr(13).chr(10), chr(10), $header)));
            $body = substr($response, $header_size);
            return array( 'body' => $body, 'response_code' => $response_code, 'headers' => $headers );
        }
        
        return null;
    }

    function imageToWebpApi(string $api_url, string $api_key, array $images) : array
    {
        // Create an array of files to post via cUrl and the file descriptors
        $postData = $descriptors = [];
        foreach ($images as $index => $file_data) {
            if (is_file($file_data['path'])) {
                $realpath = realpath($file_data['path']);
                $mime = mime_content_type($file_data['path']);
                $basename = basename($file_data['path']);

                $postData['images[' . $index . ']'] = curl_file_create(
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
            '-x-api-key: ' . $api_key,
            'User-Agent: PHP cUrl connector for MWEBP'
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
                'response' => $decoded['response']
            ];
        } else {
            if (is_array($decoded) && array_key_exists('message', $decoded)) {
                $error_message = $decoded['message'];
            } else {
                $error_message = 'Unknown error with HTTP_RESPONSE_CODE="' . $status_code . '"';
            }

            return [
                'status' => false,
                'error' => $error_message
            ];
        }
    }