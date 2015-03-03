<?php

/*
 * Ideal proxy for GIS ;)
 * 
 * Author Sergey Voyteshonok <info@svoyt.com>
 * Based on Simple Proxy by Rob Thomson <rob@marotori.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2 as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 */

$config = json_decode(file_get_contents('proxy.config'));

function findUrlInConfig($url, $configArray) {
    foreach ($configArray as $urlItem) {
        if (strpos($urlItem->{'url'}, $url) !== FALSE) {
            return $urlItem;
            break;
        }
    }
}

if (!function_exists('getallheaders')) {

    function getallheaders() {
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }

}

session_start();
ob_start();

$url = $_SERVER['QUERY_STRING'];
$parsed = parse_url($url);

$base = $parsed['scheme'] . '://' . $parsed['host'] . (isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '');

$origin = findUrlInConfig($base, $config->{'urls'});

//if url is not in config -> Forbidden
if (!$origin) {
    header('Status: 403', true, 403);
    print 'Forbidden';
    exit();
}

$curlSession = curl_init();

curl_setopt($curlSession, CURLOPT_URL, $url);
curl_setopt($curlSession, CURLOPT_HEADER, 1);

$headers = getallheaders();
$headersArray = array();

if ($origin->{'login'}) {
    $credentials = $origin->{'login'} . ':' . $origin->{'password'};
    array_push($headersArray, "Authorization:Basic " . base64_encode($credentials));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = @file_get_contents('php://input');
    curl_setopt($curlSession, CURLOPT_POST, TRUE);
    curl_setopt($curlSession, CURLOPT_POSTFIELDS, $data);
}

// we push all headers except Authorization
foreach ($headers as $k => $v) {
    if ($k != 'Authorization') {
        array_push($headersArray, $k . ':' . $v);
    }
}

curl_setopt($curlSession, CURLOPT_HTTPHEADER, $headersArray);
curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($curlSession, CURLOPT_TIMEOUT, 30);
curl_setopt($curlSession, CURLOPT_SSL_VERIFYHOST, 1);

$response = curl_exec($curlSession);

if (curl_error($curlSession)) {
    print curl_error($curlSession);
} else {
    $response = str_replace("HTTP/1.1 100 Continue\r\n\r\n", "", $response);

    $ar = explode("\r\n\r\n", $response, 2);

    $header = $ar[0];
    $body = $ar[1];

    $header_ar = split(chr(10), $header);
    foreach ($header_ar as $k => $v) {
        if (!preg_match("/^Transfer-Encoding/", $v)) {
            header(trim($v));
        }
    }

    print $body;
}
curl_close($curlSession);
?>