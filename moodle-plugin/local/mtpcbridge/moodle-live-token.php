<?php
/** Issue a one-use Gemini Live token to the authenticated Moodle user. */
define('AJAX_SCRIPT', true);
require_once(dirname(dirname(__DIR__)) . '/config.php');

require_login();
require_sesskey();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function mtpc_moodle_live_response($status, $payload) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mtpc_moodle_live_response(405, array('ok' => false, 'error' => 'Method not allowed.'));
}

$apikey = getenv('GEMINI_API_KEY');
$privateconfig = '/home/mtpc/private/gemini-config.php';
if (!$apikey && is_file($privateconfig)) {
    require $privateconfig;
    $apikey = isset($GEMINI_API_KEY) ? $GEMINI_API_KEY : '';
}
if (!$apikey) mtpc_moodle_live_response(500, array('ok' => false, 'error' => 'Máy chủ chưa cấu hình Gemini Live.'));

$payload = json_encode(array(
    'uses' => 1,
    'expireTime' => gmdate('Y-m-d\TH:i:s\Z', time() + 1800),
    'newSessionExpireTime' => gmdate('Y-m-d\TH:i:s\Z', time() + 60),
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$curl = curl_init('https://generativelanguage.googleapis.com/v1beta/auth_tokens');
curl_setopt_array($curl, array(
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'x-goog-api-key: ' . trim((string)$apikey)),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 6,
    CURLOPT_TIMEOUT => 20,
));
$raw = curl_exec($curl);
$status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlerror = curl_error($curl);
curl_close($curl);
$response = is_string($raw) ? json_decode($raw, true) : null;

if ($status < 200 || $status >= 300 || !is_array($response) || empty($response['name'])) {
    $detail = is_array($response) && !empty($response['error']['message']) ? $response['error']['message'] : ($curlerror !== '' ? $curlerror : 'Không có phản hồi.');
    mtpc_moodle_live_response(502, array('ok' => false, 'error' => 'Gemini Live chưa cấp được phiên âm thanh.', 'detail' => $detail));
}

mtpc_moodle_live_response(200, array(
    'ok' => true,
    'token' => $response['name'],
    'model' => 'gemini-3.1-flash-live-preview',
    'voice' => 'Zephyr',
));
