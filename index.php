<?php

// ================= CONFIG =================

// Your upstream 3x-ui panels (sub base URLs)
$upstreams = [
    "https://YourDomain.com:2096/cp/"
];

$timeout = 8;
$cacheTTL = 3600 * 4; // seconds (4 hour)
$rateLimit = 3;  // requests per minute per IP

$cacheDir = __DIR__ . "/cache/";
$tmpDir   = __DIR__ . "/tmp/";

// ================= OUTPUT MODE =================

$mode = $_GET['format'] ?? 'auto'; 
// ?format=raw OR ?format=base64

$userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
$badBots = [
    'bot', 'crawler', 'spider', 'curl', 'wget', 'python',
    'scrapy', 'httpclient', 'go-http-client'
];

foreach ($badBots as $bot) {
    if (strpos($userAgent, $bot) !== false) {
        http_response_code(403);
        exit("Forbidden");
    }
}

function detectMode($ua) {
    if (strpos($ua, 'clash') !== false) return 'base64';
    if (strpos($ua, 'v2rayn') !== false) return 'base64';
    if (strpos($ua, 'shadowrocket') !== false) return 'base64';
    if (strpos($ua, 'sing-box') !== false) return 'base64';
    if (strpos($ua, 'mozilla') !== false) return 'raw';
    return 'base64';
}
if ($mode === 'auto') {
    $mode = detectMode($userAgent);
}

// ==========================================

@mkdir($cacheDir, 0755, true);
@mkdir($tmpDir, 0755, true);

$path = $_GET['path'] ?? '';
$segments = array_values(array_filter(explode('/', $path)));

if (empty($segments)) {
    http_response_code(400);
    exit("Bad request");
}

$subKey = end($segments);
$configName = count($segments) > 1 ? $segments[0] : "default";

// ================= RATE LIMIT =================

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateFile = $tmpDir . md5($ip);

$now = time();
$window = 60;

$requests = [];

if (file_exists($rateFile)) {
    $requests = json_decode(file_get_contents($rateFile), true) ?: [];
}

// clean old
$requests = array_filter($requests, fn($t) => ($now - $t) < $window);

if (count($requests) >= $rateLimit) {
    http_response_code(429);
    exit("Too many requests, please wait moment");
}

$requests[] = $now;
file_put_contents($rateFile, json_encode($requests), LOCK_EX);

// ================= CACHE =================

$cacheKey = md5($subKey . "_" . $configName);
$cacheFile = $cacheDir . $cacheKey;

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
    header("Content-Type: text/plain");
    echo file_get_contents($cacheFile);
    exit;
}

// ================= FETCH =================

function fetchSub($url, $timeout) {
    $ctx = stream_context_create([
        "http" => [
            "header" => "Host: YourDomain.com\r\n",
            "timeout" => $timeout,
            //"header" => "User-Agent: SubGateway/1.0\r\n"
        ],
        "ssl" => [
        "verify_peer" => false,
        "verify_peer_name" => false,
        "peer_name" => "YourDomain.com", // IMPORTANT
        ]
    ]);

    return @file_get_contents($url, false, $ctx);
}

$data = false;

foreach ($upstreams as $base) {
    $url = rtrim($base, '/') . '/' . $subKey;

    $data = fetchSub($url, $timeout);

    if ($data !== false && strlen($data) > 0) {
        break;
    }
}

// Fail if all upstreams failed
if ($data === false) {
    http_response_code(502);
    exit("All upstreams failed");
}

// ================= PROCESS =================

$decoded = base64_decode($data, true);

if ($decoded === false) {
    $decoded = $data;
}

$lines = explode("\n", trim($decoded));
$output = [];

foreach ($lines as $line) {

    if (empty($line)) continue;

    if ($configName === "tls") {
        if (strpos($line, "security=tls") === false) continue;
    }

    if ($configName === "ws") {
        if (strpos($line, "type=ws") === false) continue;
    }

    if ($configName === "grpc") {
        if (strpos($line, "type=grpc") === false) continue;
    }

    $line .= urlencode(" | {$configName}");

    $output[] = $line;
}

// ================= OUTPUT =================

$rawOutput = implode("\n", $output);

if ($mode === 'raw') {
    $result = $rawOutput;
} else {
    $result = base64_encode($rawOutput);
}

// ================= STORE CACHE =================

file_put_contents($cacheFile, $result, LOCK_EX);

// ================= HEADERS =================

header("Content-Type: text/plain; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header("X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");
header("X-Frame-Options: DENY");
$etag = md5($result);
header("ETag: \"$etag\"");

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) &&
    trim($_SERVER['HTTP_IF_NONE_MATCH']) === "\"$etag\"") {
    http_response_code(304);
    exit;
}

echo $result;