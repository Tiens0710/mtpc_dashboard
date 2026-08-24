<?php
/* MTPC Admin read-only IMAP gateway. PHP 5.6 compatible. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');
date_default_timezone_set('Asia/Ho_Chi_Minh');

function mtpc_mail_response($status, $payload) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mtpc_mail_response(405, array('ok' => false, 'error' => 'Chỉ hỗ trợ yêu cầu POST.'));
}

/* Block cross-origin calls even when the browser already has an admin session. */
if (!empty($_SERVER['HTTP_ORIGIN'])) {
    $originHost = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
    $requestHost = isset($_SERVER['HTTP_HOST']) ? preg_replace('/:\\d+$/', '', $_SERVER['HTTP_HOST']) : '';
    if (!$originHost || strcasecmp($originHost, $requestHost) !== 0) {
        mtpc_mail_response(403, array('ok' => false, 'error' => 'Nguồn yêu cầu không hợp lệ.'));
    }
}

$configPath = '/home/mtpc/private/email-config.php';
if (!is_file($configPath)) {
    mtpc_mail_response(503, array('ok' => false, 'error' => 'Chưa cấu hình hộp thư. Hãy tạo /home/mtpc/private/email-config.php.'));
}
require $configPath;

$requireAuth = isset($MTPC_EMAIL_REQUIRE_AUTH) ? (bool)$MTPC_EMAIL_REQUIRE_AUTH : true;
if ($requireAuth && empty($_SERVER['REMOTE_USER'])) {
    mtpc_mail_response(403, array('ok' => false, 'error' => 'Hãy bật Directory Privacy cho thư mục admin trước khi sử dụng công cụ email.'));
}
if (!function_exists('imap_open')) {
    mtpc_mail_response(503, array('ok' => false, 'error' => 'PHP IMAP chưa được bật trên hosting. Hãy bật extension imap trong Select PHP Version.'));
}

$host = isset($MTPC_EMAIL_IMAP_HOST) ? trim((string)$MTPC_EMAIL_IMAP_HOST) : '';
$port = isset($MTPC_EMAIL_IMAP_PORT) ? (int)$MTPC_EMAIL_IMAP_PORT : 993;
$encryption = isset($MTPC_EMAIL_IMAP_ENCRYPTION) ? strtolower(trim((string)$MTPC_EMAIL_IMAP_ENCRYPTION)) : 'ssl';
$username = isset($MTPC_EMAIL_USERNAME) ? trim((string)$MTPC_EMAIL_USERNAME) : '';
$password = isset($MTPC_EMAIL_PASSWORD) ? (string)$MTPC_EMAIL_PASSWORD : '';
$folder = isset($MTPC_EMAIL_FOLDER) ? trim((string)$MTPC_EMAIL_FOLDER) : 'INBOX';
$validateCertificate = isset($MTPC_EMAIL_VALIDATE_CERT) ? (bool)$MTPC_EMAIL_VALIDATE_CERT : true;

if ($host === '' || $username === '' || $password === '') {
    mtpc_mail_response(503, array('ok' => false, 'error' => 'Cấu hình IMAP còn thiếu host, username hoặc password.'));
}
if (!preg_match('/^[a-z0-9.-]+$/i', $host) || $port < 1 || $port > 65535 || !in_array($encryption, array('ssl', 'tls', 'none'), true)) {
    mtpc_mail_response(500, array('ok' => false, 'error' => 'Cấu hình máy chủ IMAP không hợp lệ.'));
}

$flags = '/imap';
if ($encryption === 'ssl') $flags .= '/ssl';
if ($encryption === 'tls') $flags .= '/tls';
if (!$validateCertificate) $flags .= '/novalidate-cert';
$mailbox = '{' . $host . ':' . $port . $flags . '}' . $folder;
$imap = @imap_open($mailbox, $username, $password, OP_READONLY, 1);
if (!$imap) {
    mtpc_mail_response(502, array('ok' => false, 'error' => 'Không kết nối được hộp thư IMAP. Hãy kiểm tra máy chủ, tài khoản và mật khẩu.'));
}

function mtpc_mail_body() {
    $body = json_decode(file_get_contents('php://input'), true);
    return is_array($body) ? $body : array();
}

function mtpc_mail_utf8($value) {
    $value = (string)$value;
    if ($value === '') return '';
    if (function_exists('imap_mime_header_decode')) {
        $parts = @imap_mime_header_decode($value);
        if (is_array($parts)) {
            $result = '';
            foreach ($parts as $part) {
                $charset = isset($part->charset) ? strtoupper($part->charset) : 'DEFAULT';
                $text = isset($part->text) ? $part->text : '';
                if ($charset !== 'DEFAULT' && $charset !== 'UTF-8' && function_exists('iconv')) {
                    $converted = @iconv($charset, 'UTF-8//IGNORE', $text);
                    if ($converted !== false) $text = $converted;
                }
                $result .= $text;
            }
            return trim($result);
        }
    }
    return trim($value);
}

function mtpc_mail_part_charset($part) {
    $groups = array();
    if (isset($part->parameters) && is_array($part->parameters)) $groups[] = $part->parameters;
    if (isset($part->dparameters) && is_array($part->dparameters)) $groups[] = $part->dparameters;
    foreach ($groups as $params) {
        foreach ($params as $param) {
            if (isset($param->attribute, $param->value) && strtoupper($param->attribute) === 'CHARSET') return (string)$param->value;
        }
    }
    return 'UTF-8';
}

function mtpc_mail_decode_part($raw, $encoding, $charset, $isHtml) {
    if ((int)$encoding === 3) $raw = base64_decode($raw);
    elseif ((int)$encoding === 4) $raw = quoted_printable_decode($raw);
    if ($charset && strtoupper($charset) !== 'UTF-8' && strtoupper($charset) !== 'US-ASCII' && function_exists('iconv')) {
        $converted = @iconv($charset, 'UTF-8//IGNORE', $raw);
        if ($converted !== false) $raw = $converted;
    }
    if ($isHtml) {
        $raw = preg_replace('/<script\\b[^>]*>.*?<\\/script>/is', ' ', $raw);
        $raw = preg_replace('/<style\\b[^>]*>.*?<\\/style>/is', ' ', $raw);
        $raw = html_entity_decode(strip_tags($raw), ENT_QUOTES, 'UTF-8');
    }
    $raw = preg_replace('/[\\t ]+/', ' ', $raw);
    $raw = preg_replace('/\\r?\\n(?:\\s*\\r?\\n)+/', "\n\n", $raw);
    return trim($raw);
}

function mtpc_mail_collect_parts($structure, $prefix, &$plain, &$html) {
    if (isset($structure->parts) && is_array($structure->parts)) {
        foreach ($structure->parts as $index => $child) {
            $number = $prefix === '' ? (string)($index + 1) : $prefix . '.' . ($index + 1);
            mtpc_mail_collect_parts($child, $number, $plain, $html);
        }
        return;
    }
    if (isset($structure->type) && (int)$structure->type === 0) {
        $subtype = isset($structure->subtype) ? strtoupper($structure->subtype) : 'PLAIN';
        $item = array('part' => $prefix, 'encoding' => isset($structure->encoding) ? (int)$structure->encoding : 0, 'charset' => mtpc_mail_part_charset($structure));
        if ($subtype === 'PLAIN') $plain[] = $item;
        elseif ($subtype === 'HTML') $html[] = $item;
    }
}

function mtpc_mail_extract_text($imap, $uid) {
    $structure = @imap_fetchstructure($imap, $uid, FT_UID);
    if (!$structure) return '';
    $plain = array(); $html = array();
    mtpc_mail_collect_parts($structure, '', $plain, $html);
    $candidates = count($plain) ? $plain : $html;
    foreach ($candidates as $item) {
        $raw = $item['part'] === '' ? @imap_body($imap, $uid, FT_UID | FT_PEEK) : @imap_fetchbody($imap, $uid, $item['part'], FT_UID | FT_PEEK);
        if ($raw !== false && $raw !== '') return mtpc_mail_decode_part($raw, $item['encoding'], $item['charset'], !count($plain));
    }
    $raw = @imap_body($imap, $uid, FT_UID | FT_PEEK);
    return $raw === false ? '' : mtpc_mail_decode_part($raw, isset($structure->encoding) ? $structure->encoding : 0, mtpc_mail_part_charset($structure), isset($structure->subtype) && strtoupper($structure->subtype) === 'HTML');
}

function mtpc_mail_slice($value, $length) {
    if (function_exists('mb_substr')) return mb_substr($value, 0, $length, 'UTF-8');
    return substr($value, 0, $length);
}

function mtpc_mail_date($value) {
    if (!is_string($value) || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) return false;
    $date = DateTime::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Ho_Chi_Minh'));
    return $date && $date->format('Y-m-d') === $value ? $date : false;
}

function mtpc_mail_range($body) {
    $zone = new DateTimeZone('Asia/Ho_Chi_Minh');
    $today = new DateTime('today', $zone);
    $mode = isset($body['date_mode']) ? (string)$body['date_mode'] : 'today';
    if ($mode === 'today') return array(clone $today, (clone $today)->modify('+1 day'), 'hôm nay');
    if ($mode === 'yesterday') return array((clone $today)->modify('-1 day'), clone $today, 'hôm qua');
    if ($mode === 'recent') return array((clone $today)->modify('-6 days'), (clone $today)->modify('+1 day'), 'trong 7 ngày gần đây');
    if ($mode === 'date') {
        $date = mtpc_mail_date(isset($body['date']) ? $body['date'] : '');
        if (!$date) return false;
        return array($date, (clone $date)->modify('+1 day'), 'ngày ' . $date->format('d/m/Y'));
    }
    if ($mode === 'range') {
        $from = mtpc_mail_date(isset($body['from_date']) ? $body['from_date'] : '');
        $to = mtpc_mail_date(isset($body['to_date']) ? $body['to_date'] : '');
        if (!$from || !$to || $from > $to) return false;
        if ((int)$from->diff($to)->format('%a') > 31) return false;
        return array($from, (clone $to)->modify('+1 day'), 'từ ' . $from->format('d/m/Y') . ' đến ' . $to->format('d/m/Y'));
    }
    return false;
}

$body = mtpc_mail_body();
$action = isset($_GET['action']) ? (string)$_GET['action'] : '';

if ($action === 'list') {
    $range = mtpc_mail_range($body);
    if (!$range) { imap_close($imap); mtpc_mail_response(422, array('ok' => false, 'error' => 'Khoảng ngày không hợp lệ hoặc dài quá 31 ngày.')); }
    $limit = isset($body['limit']) ? max(1, min(20, (int)$body['limit'])) : 10;
    $criteria = 'SINCE "' . $range[0]->format('d-M-Y') . '" BEFORE "' . $range[1]->format('d-M-Y') . '"';
    if (!empty($body['unread_only'])) $criteria .= ' UNSEEN';
    $uids = @imap_search($imap, $criteria, SE_UID, 'UTF-8');
    if (!is_array($uids)) $uids = array();
    $items = array();
    foreach ($uids as $uid) {
        $overview = @imap_fetch_overview($imap, (string)$uid, FT_UID);
        if (!is_array($overview) || !isset($overview[0])) continue;
        $row = $overview[0];
        $timestamp = isset($row->udate) ? (int)$row->udate : (isset($row->date) ? strtotime($row->date) : 0);
        $items[] = array(
            'uid' => (string)$uid,
            'from' => mtpc_mail_utf8(isset($row->from) ? $row->from : ''),
            'subject' => mtpc_mail_utf8(isset($row->subject) ? $row->subject : '(Không có tiêu đề)'),
            'date' => $timestamp ? date('c', $timestamp) : '',
            'unread' => empty($row->seen),
            '_timestamp' => $timestamp
        );
    }
    usort($items, function($a, $b) { return $a['_timestamp'] === $b['_timestamp'] ? 0 : ($a['_timestamp'] < $b['_timestamp'] ? 1 : -1); });
    $items = array_slice($items, 0, $limit);
    foreach ($items as &$item) {
        $preview = mtpc_mail_extract_text($imap, $item['uid']);
        $item['preview'] = mtpc_mail_slice(preg_replace('/\\s+/', ' ', $preview), 500);
        unset($item['_timestamp']);
    }
    unset($item);
    imap_close($imap);
    mtpc_mail_response(200, array('ok' => true, 'range_label' => $range[2], 'count' => count($items), 'emails' => $items));
}

if ($action === 'read') {
    $uid = isset($body['uid']) ? trim((string)$body['uid']) : '';
    if (!preg_match('/^\\d+$/', $uid)) { imap_close($imap); mtpc_mail_response(422, array('ok' => false, 'error' => 'UID email không hợp lệ.')); }
    $overview = @imap_fetch_overview($imap, $uid, FT_UID);
    if (!is_array($overview) || !isset($overview[0])) { imap_close($imap); mtpc_mail_response(404, array('ok' => false, 'error' => 'Không tìm thấy email.')); }
    $row = $overview[0];
    $timestamp = isset($row->udate) ? (int)$row->udate : (isset($row->date) ? strtotime($row->date) : 0);
    $email = array(
        'uid' => $uid,
        'from' => mtpc_mail_utf8(isset($row->from) ? $row->from : ''),
        'to' => mtpc_mail_utf8(isset($row->to) ? $row->to : ''),
        'subject' => mtpc_mail_utf8(isset($row->subject) ? $row->subject : '(Không có tiêu đề)'),
        'date' => $timestamp ? date('c', $timestamp) : '',
        'unread' => empty($row->seen),
        'body' => mtpc_mail_slice(mtpc_mail_extract_text($imap, $uid), 16000)
    );
    imap_close($imap);
    mtpc_mail_response(200, array('ok' => true, 'email' => $email));
}

imap_close($imap);
mtpc_mail_response(400, array('ok' => false, 'error' => 'Thao tác email không hợp lệ.'));
