<?php
/* MTPC AI approval and audit API. PHP 5.6 compatible. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

function mtpc_ops_response($status, $payload) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$storageDir = '/home/mtpc/private/mtpc-admin';
if (!is_dir($storageDir) && !@mkdir($storageDir, 0750, true)) {
    mtpc_ops_response(500, array('ok' => false, 'error' => 'Không thể tạo thư mục dữ liệu quản trị.'));
}

$approvalPath = $storageDir . '/approvals.json';
$auditPath = $storageDir . '/audit.json';

function mtpc_ops_read($path) {
    if (!is_file($path)) return array();
    $raw = @file_get_contents($path);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : array();
}

function mtpc_ops_write($path, $data) {
    $encoded = json_encode(array_values($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    return @file_put_contents($path, $encoded, LOCK_EX) !== false;
}

function mtpc_ops_body() {
    $body = json_decode(file_get_contents('php://input'), true);
    return is_array($body) ? $body : array();
}

function mtpc_ops_text($value, $maxLength) {
    $value = trim((string)$value);
    if (function_exists('mb_substr')) return mb_substr($value, 0, $maxLength, 'UTF-8');
    return substr($value, 0, $maxLength);
}

function mtpc_ops_id($prefix) {
    return $prefix . '-' . gmdate('YmdHis') . '-' . substr(sha1(uniqid('', true)), 0, 10);
}

function mtpc_ops_audit(&$audit, $action, $status, $summary, $details, $actor) {
    array_unshift($audit, array(
        'id' => mtpc_ops_id('audit'),
        'actor' => mtpc_ops_text($actor, 60),
        'action' => mtpc_ops_text($action, 100),
        'status' => mtpc_ops_text($status, 30),
        'summary' => mtpc_ops_text($summary, 500),
        'details' => is_array($details) ? $details : array(),
        'created_at' => gmdate('c')
    ));
    $audit = array_slice($audit, 0, 500);
}

$action = isset($_GET['action']) ? (string)$_GET['action'] : 'list';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $approvals = mtpc_ops_read($approvalPath);
    $audit = mtpc_ops_read($auditPath);
    if ($action === 'approvals') {
        mtpc_ops_response(200, array('ok' => true, 'approvals' => $approvals));
    }
    if ($action === 'audit') {
        $limit = isset($_GET['limit']) ? max(1, min(500, (int)$_GET['limit'])) : 200;
        mtpc_ops_response(200, array('ok' => true, 'audit' => array_slice($audit, 0, $limit)));
    }
    mtpc_ops_response(200, array('ok' => true, 'approvals' => $approvals, 'audit' => array_slice($audit, 0, 200)));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mtpc_ops_response(405, array('ok' => false, 'error' => 'Phương thức không được hỗ trợ.'));
}

$body = mtpc_ops_body();
$approvals = mtpc_ops_read($approvalPath);
$audit = mtpc_ops_read($auditPath);

if ($action === 'queue') {
    $tool = mtpc_ops_text(isset($body['tool']) ? $body['tool'] : '', 100);
    $summary = mtpc_ops_text(isset($body['summary']) ? $body['summary'] : '', 500);
    $args = isset($body['args']) && is_array($body['args']) ? $body['args'] : array();
    $risk = mtpc_ops_text(isset($body['risk']) ? $body['risk'] : 'write', 30);
    if ($tool === '' || $summary === '') mtpc_ops_response(422, array('ok' => false, 'error' => 'Thiếu tên tool hoặc nội dung xác nhận.'));
    $fingerprint = sha1($tool . '|' . json_encode($args));
    foreach ($approvals as $existing) {
        if (isset($existing['status'], $existing['fingerprint']) && $existing['status'] === 'pending' && $existing['fingerprint'] === $fingerprint) {
            mtpc_ops_response(200, array('ok' => true, 'approval' => $existing, 'message' => 'Yêu cầu này đã nằm trong hàng chờ phê duyệt.'));
        }
    }
    $record = array(
        'id' => mtpc_ops_id('approval'),
        'tool' => $tool,
        'args' => $args,
        'summary' => $summary,
        'risk' => $risk,
        'status' => 'pending',
        'fingerprint' => $fingerprint,
        'created_at' => gmdate('c'),
        'resolved_at' => null,
        'result' => null
    );
    array_unshift($approvals, $record);
    $approvals = array_slice($approvals, 0, 300);
    mtpc_ops_audit($audit, $tool, 'pending', $summary, array('approval_id' => $record['id']), 'Nhi AI');
    if (!mtpc_ops_write($approvalPath, $approvals) || !mtpc_ops_write($auditPath, $audit)) {
        mtpc_ops_response(500, array('ok' => false, 'error' => 'Không thể lưu yêu cầu phê duyệt.'));
    }
    mtpc_ops_response(201, array('ok' => true, 'approval' => $record, 'message' => 'Đã đưa thao tác vào hàng chờ phê duyệt.'));
}

if ($action === 'resolve') {
    $id = mtpc_ops_text(isset($body['id']) ? $body['id'] : '', 100);
    $decision = mtpc_ops_text(isset($body['decision']) ? $body['decision'] : '', 20);
    $result = isset($body['result']) && is_array($body['result']) ? $body['result'] : array();
    if ($id === '' || !in_array($decision, array('approved', 'rejected', 'failed'), true)) {
        mtpc_ops_response(422, array('ok' => false, 'error' => 'Quyết định phê duyệt không hợp lệ.'));
    }
    $found = -1;
    for ($index = 0; $index < count($approvals); $index++) {
        if (isset($approvals[$index]['id']) && $approvals[$index]['id'] === $id) { $found = $index; break; }
    }
    if ($found < 0) mtpc_ops_response(404, array('ok' => false, 'error' => 'Không tìm thấy yêu cầu phê duyệt.'));
    if ($approvals[$found]['status'] !== 'pending') mtpc_ops_response(409, array('ok' => false, 'error' => 'Yêu cầu này đã được xử lý.'));
    $approvals[$found]['status'] = $decision;
    $approvals[$found]['resolved_at'] = gmdate('c');
    $approvals[$found]['result'] = $result;
    $summary = $decision === 'approved' ? 'Đã phê duyệt: ' : ($decision === 'rejected' ? 'Đã từ chối: ' : 'Thực hiện thất bại: ');
    $summary .= isset($approvals[$found]['summary']) ? $approvals[$found]['summary'] : $id;
    mtpc_ops_audit($audit, $approvals[$found]['tool'], $decision, $summary, array('approval_id' => $id, 'result' => $result), 'Quản trị viên');
    if (!mtpc_ops_write($approvalPath, $approvals) || !mtpc_ops_write($auditPath, $audit)) {
        mtpc_ops_response(500, array('ok' => false, 'error' => 'Không thể cập nhật yêu cầu phê duyệt.'));
    }
    mtpc_ops_response(200, array('ok' => true, 'approval' => $approvals[$found], 'message' => $summary));
}

if ($action === 'log') {
    $eventAction = mtpc_ops_text(isset($body['event_action']) ? $body['event_action'] : 'ai_action', 100);
    $status = mtpc_ops_text(isset($body['status']) ? $body['status'] : 'success', 30);
    $summary = mtpc_ops_text(isset($body['summary']) ? $body['summary'] : '', 500);
    $details = isset($body['details']) && is_array($body['details']) ? $body['details'] : array();
    mtpc_ops_audit($audit, $eventAction, $status, $summary, $details, 'Nhi AI');
    if (!mtpc_ops_write($auditPath, $audit)) mtpc_ops_response(500, array('ok' => false, 'error' => 'Không thể lưu nhật ký AI.'));
    mtpc_ops_response(201, array('ok' => true, 'message' => 'Đã ghi nhật ký AI.'));
}

mtpc_ops_response(400, array('ok' => false, 'error' => 'Thao tác không hợp lệ.'));
