<?php
/**
 * Moodle-hosted Orb chat endpoint.
 *
 * The browser only sends text and Moodle's sesskey. Gemini and the Moodle
 * service token remain on the server. This endpoint is intentionally limited
 * to Moodle site administrators because the shared agent can perform writes.
 */
define('AJAX_SCRIPT', true);
require_once(dirname(dirname(__DIR__)) . '/config.php');

require_login();
require_sesskey();
require_capability('moodle/site:config', context_system::instance());

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function mtpc_moodle_orb_response($status, $payload) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function mtpc_moodle_orb_repo_file($file) {
    global $CFG;
    $candidates = array(
        dirname(dirname(dirname(__DIR__))) . '/admin/api/' . $file,
        dirname(dirname(dirname(dirname(__DIR__)))) . '/admin/api/' . $file,
    );
    foreach ($candidates as $candidate) if (is_readable($candidate)) return $candidate;
    throw new moodle_exception('invalidrecord', 'error', '', null, 'Không tìm thấy thành phần Orb trên máy chủ.');
}

function mtpc_moodle_orb_read_body() {
    $body = json_decode(file_get_contents('php://input'), true);
    return is_array($body) ? $body : $_POST;
}

function mtpc_moodle_orb_history() {
    global $SESSION;
    return isset($SESSION->mtpc_moodle_orb_history) && is_array($SESSION->mtpc_moodle_orb_history)
        ? $SESSION->mtpc_moodle_orb_history : array();
}

function mtpc_moodle_orb_save_history($history) {
    global $SESSION;
    $SESSION->mtpc_moodle_orb_history = array_slice($history, -16);
}

function mtpc_moodle_orb_tool() {
    foreach (mtpc_orb_agent_tools() as $tool) {
        if (isset($tool['name']) && $tool['name'] === 'moodle_action') return $tool;
    }
    throw new moodle_exception('invalidrecord', 'error', '', null, 'Công cụ Moodle của Orb chưa được nạp.');
}

function mtpc_moodle_orb_call_gemini($contents) {
    $key = getenv('GEMINI_API_KEY');
    $path = '/home/mtpc/private/gemini-config.php';
    if (!$key && is_file($path)) {
        require $path;
        $key = isset($GEMINI_API_KEY) ? $GEMINI_API_KEY : '';
    }
    if (!$key) throw new moodle_exception('serverconnection', 'error', '', null, 'Chưa cấu hình GEMINI_API_KEY trên máy chủ.');

    $payload = array(
        'systemInstruction' => array('parts' => array(array('text' =>
            'Bạn là Nhi, trợ lý quản trị Moodle của Trường Trung cấp Miền Tây, đang trò chuyện trực tiếp trong Moodle. '
            . 'Hiểu yêu cầu tiếng Việt tự nhiên và dùng công cụ Moodle khi cần. Tự tra khóa học, tài khoản, bài tập, quiz và hoạt động theo tên; '
            . 'không bắt quản trị viên nhớ ID. Trả lời tiếng Việt ngắn gọn, không bịa dữ liệu. '
            . 'Các thao tác tạo, sửa, xóa, ghi danh, chấm điểm hoặc gửi tin phải chờ hệ thống yêu cầu XÁC NHẬN; không nói đã thực hiện trước khi có kết quả.'
        ))),
        'contents' => $contents,
        'tools' => array(array('functionDeclarations' => array(mtpc_moodle_orb_tool()))),
        'generationConfig' => array('maxOutputTokens' => 700, 'temperature' => 0.2),
    );
    $curl = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent');
    curl_setopt_array($curl, array(
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'x-goog-api-key: ' . trim((string)$key)),
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ));
    $raw = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if ($raw === false || $status < 200 || $status >= 300) {
        throw new moodle_exception('serverconnection', 'error', '', null, 'Gemini không phản hồi.' . ($error ? ' ' . $error : ''));
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['candidates'][0]['content']['parts'])) {
        throw new moodle_exception('serverconnection', 'error', '', null, 'Gemini trả về dữ liệu không hợp lệ.');
    }
    return $data['candidates'][0]['content'];
}

try {
    require_once(mtpc_moodle_orb_repo_file('zalo-admin.php'));
    require_once(mtpc_moodle_orb_repo_file('orb-agent.php'));
    $body = mtpc_moodle_orb_read_body();
    $text = trim(isset($body['text']) ? (string)$body['text'] : '');
    if ($text === '') mtpc_moodle_orb_response(422, array('ok' => false, 'error' => 'Bạn chưa nhập yêu cầu.'));

    global $USER, $SESSION;
    $operator = array('user_id' => (string)$USER->id, 'user_name' => fullname($USER), 'role' => 'admin');
    $normalized = mtpc_orb_agent_normalize($text);
    $pending = isset($SESSION->mtpc_moodle_orb_pending) && is_array($SESSION->mtpc_moodle_orb_pending)
        ? $SESSION->mtpc_moodle_orb_pending : null;
    if ($pending) {
        if (in_array($normalized, array('xac nhan', 'xacnhan', 'dong y', 'ok', 'thuc hien'), true)) {
            $result = mtpc_orb_agent_execute_pending($operator, $pending['intent'], array(), '', '');
            unset($SESSION->mtpc_moodle_orb_pending);
            mtpc_moodle_orb_response(200, array('ok' => true, 'reply' => isset($result['message']) ? $result['message'] : 'Đã thực hiện xong thao tác Moodle.'));
        }
        if (in_array($normalized, array('huy', 'bo qua', 'khong', 'cancel'), true)) {
            unset($SESSION->mtpc_moodle_orb_pending);
            mtpc_moodle_orb_response(200, array('ok' => true, 'reply' => 'Đã hủy thao tác đang chờ.'));
        }
        mtpc_moodle_orb_response(200, array('ok' => true, 'pending' => true, 'reply' => 'Mình đang chờ anh/chị “XÁC NHẬN” hoặc “HỦY” thao tác trước đó.'));
    }

    $contents = mtpc_moodle_orb_history();
    $contents[] = array('role' => 'user', 'parts' => array(array('text' => mtpc_zalo_admin_text($text, 4000))));
    for ($round = 0; $round < 3; $round++) {
        $content = mtpc_moodle_orb_call_gemini($contents);
        $contents[] = $content;
        $calls = array();
        $reply = '';
        foreach ((array)$content['parts'] as $part) {
            if (isset($part['functionCall'])) $calls[] = $part['functionCall'];
            if (isset($part['text'])) $reply .= $part['text'];
        }
        if (!$calls) {
            mtpc_moodle_orb_save_history($contents);
            mtpc_moodle_orb_response(200, array('ok' => true, 'reply' => mtpc_orb_agent_plain_text(trim($reply))));
        }
        $responses = array();
        foreach ($calls as $call) {
            $name = isset($call['name']) ? $call['name'] : '';
            $args = isset($call['args']) && is_array($call['args']) ? $call['args'] : array();
            try {
                $result = mtpc_orb_agent_execute_tool($name, $args, $operator, array(), '', '', false);
            } catch (Exception $error) {
                $result = array('ok' => false, 'error' => $error->getMessage());
            }
            if (is_array($result) && !empty($result['pending'])) {
                $SESSION->mtpc_moodle_orb_pending = array('intent' => $result['intent'], 'expires_at' => time() + 600);
                mtpc_moodle_orb_save_history($contents);
                mtpc_moodle_orb_response(200, array('ok' => true, 'pending' => true, 'reply' => 'Mình đã chuẩn bị: ' . $result['intent']['summary'] . "\nNhấn “XÁC NHẬN” để thực hiện hoặc “HỦY” để bỏ qua."));
            }
            $encoded = json_encode(array('result' => $result), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (strlen($encoded) > 14000) $encoded = substr($encoded, 0, 14000) . '...';
            $responses[] = array('functionResponse' => array('name' => $name, 'response' => array('content' => $encoded)));
        }
        $contents[] = array('role' => 'user', 'parts' => $responses);
    }
    mtpc_moodle_orb_save_history($contents);
    mtpc_moodle_orb_response(200, array('ok' => true, 'reply' => 'Kết quả hơi dài. Anh/chị thu hẹp yêu cầu giúp mình nhé.'));
} catch (Exception $error) {
    mtpc_moodle_orb_response(500, array('ok' => false, 'error' => $error->getMessage()));
}
