<?php
/**
 * Moodle-hosted Orb chat endpoint.
 *
 * The browser only sends text and Moodle's sesskey. Gemini and the Moodle
 * service token remain on the server. This Moodle surface is always a
 * student-safe, read-only assistant, including when an administrator tests it.
 */
define('AJAX_SCRIPT', true);
require_once(dirname(dirname(__DIR__)) . '/config.php');
require_once($CFG->dirroot . '/lib/enrollib.php');

require_login();
require_sesskey();

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
    throw new Exception('Không tìm thấy thành phần Orb trên máy chủ: admin/api/' . basename($file));
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
    return mtpc_orb_agent_student_moodle_tool();
}

function mtpc_moodle_orb_enrolled_courses() {
    global $USER;
    $rows = array();
    foreach ((array)enrol_get_all_users_courses((int)$USER->id, true) as $course) {
        if (empty($course->id) || (isset($course->visible) && !$course->visible)) continue;
        $rows[] = array(
            'id' => (int)$course->id,
            'fullname' => isset($course->fullname) ? format_string($course->fullname) : '',
            'shortname' => isset($course->shortname) ? format_string($course->shortname) : '',
        );
    }
    return $rows;
}

function mtpc_moodle_orb_course_from_session($args, $courses) {
    $courseid = isset($args['course_id']) ? (int)$args['course_id'] : 0;
    if ($courseid > 0) {
        foreach ($courses as $course) if ((int)$course['id'] === $courseid) return $course;
        throw new Exception('Em chưa được ghi danh trong khóa học này.');
    }
    $name = trim(isset($args['course_name']) ? (string)$args['course_name'] : '');
    if ($name === '' && count($courses) === 1) return $courses[0];
    if ($name === '') throw new Exception('Hãy nói tên khóa học cần xem.');
    $needle = mtpc_orb_agent_normalize($name);
    $matches = array();
    foreach ($courses as $course) {
        $fullname = isset($course['fullname']) ? (string)$course['fullname'] : '';
        $shortname = isset($course['shortname']) ? (string)$course['shortname'] : '';
        $haystack = mtpc_orb_agent_normalize($fullname . ' ' . $shortname);
        if ($needle !== '' && (strpos($haystack, $needle) !== false || strpos($needle, mtpc_orb_agent_normalize($fullname)) !== false)) $matches[] = $course;
    }
    if (count($matches) === 1) return $matches[0];
    if (!$matches) throw new Exception('Không tìm thấy khóa học đã ghi danh có tên “' . $name . '”.');
    $labels = array();
    foreach (array_slice($matches, 0, 5) as $course) $labels[] = $course['fullname'];
    throw new Exception('Có nhiều khóa học phù hợp: ' . implode(', ', $labels) . '. Hãy nói rõ tên hơn.');
}

function mtpc_moodle_orb_courses_reply($courses) {
    if (!$courses) return 'Tài khoản này hiện chưa được ghi danh vào khóa học nào.';
    $names = array();
    foreach (array_slice($courses, 0, 20) as $course) $names[] = $course['fullname'] !== '' ? $course['fullname'] : ('Khóa học ID ' . $course['id']);
    return 'Hiện em có ' . count($courses) . ' khóa học: ' . implode(', ', $names) . '.';
}

function mtpc_moodle_orb_call_gemini($contents) {
    $key = getenv('GEMINI_API_KEY');
    $path = '/home/mtpc/private/gemini-config.php';
    if (!$key && is_file($path)) {
        require $path;
        $key = isset($GEMINI_API_KEY) ? $GEMINI_API_KEY : '';
    }
    if (!$key) throw new Exception('Máy chủ chưa cấu hình GEMINI_API_KEY cho Orb Moodle.');

    $system = 'Bạn là Nhi, trợ lý học tập đang trò chuyện trực tiếp trong Moodle với một học sinh. '
        . 'Chỉ dùng công cụ moodle_student_action để tra cứu các khóa học mà chính học sinh đã ghi danh, nội dung bài học, bài tập, bài kiểm tra, điểm, tiến độ, thông báo, diễn đàn và lịch của chính em. '
        . 'Tuyệt đối không tạo, sửa, xóa, ghi danh, chấm điểm, gửi tin, xem danh sách người dùng hoặc xem dữ liệu của học sinh khác. Nếu được yêu cầu điều khiển Moodle, hãy nói rõ Orb học sinh chỉ có quyền đọc.';
    $payload = array(
        'systemInstruction' => array('parts' => array(array('text' =>
            $system
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
        $failed = json_decode((string)$raw, true);
        $detail = is_array($failed) && !empty($failed['error']['message']) ? trim((string)$failed['error']['message']) : '';
        if ($detail === '') $detail = $error !== '' ? $error : 'Không có nội dung phản hồi.';
        throw new Exception('Gemini HTTP ' . $status . ': ' . $detail);
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['candidates'][0]['content']['parts'])) {
        throw new Exception('Gemini trả về dữ liệu không hợp lệ.');
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
    $role = 'student';
    $operator = array('user_id' => (string)$USER->id, 'user_name' => fullname($USER), 'role' => $role);
    if (isset($SESSION->mtpc_moodle_orb_role) && $SESSION->mtpc_moodle_orb_role !== $role) {
        unset($SESSION->mtpc_moodle_orb_history, $SESSION->mtpc_moodle_orb_pending);
    }
    $SESSION->mtpc_moodle_orb_role = $role;
    $normalized = mtpc_orb_agent_normalize($text);
    $sessionCourses = mtpc_moodle_orb_enrolled_courses();
    unset($SESSION->mtpc_moodle_orb_pending);

    if (in_array($normalized, array('chao', 'xin chao', 'hello', 'hi'), true)) {
        mtpc_moodle_orb_response(200, array('ok' => true, 'reply' => 'Chào em! Nhi có thể giúp xem khóa học, bài tập, điểm, tiến độ, thông báo và lịch học của chính em trên Moodle.'));
    }
    $courseQuestion = strpos($normalized, 'khoa hoc') !== false && (
        strpos($normalized, 'cua toi') !== false || strpos($normalized, 'cua minh') !== false
        || strpos($normalized, 'da ghi danh') !== false || strpos($normalized, 'dang hoc') !== false
        || strpos($normalized, 'co may') !== false || strpos($normalized, 'bao nhieu') !== false
        || strpos($normalized, 'liet ke') !== false
    );
    if ($courseQuestion) {
        mtpc_moodle_orb_response(200, array('ok' => true, 'reply' => mtpc_moodle_orb_courses_reply($sessionCourses)));
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
                if ($name !== 'moodle_student_action') throw new Exception('Orb học sinh chỉ có quyền tra cứu dữ liệu học tập của chính mình.');
                $action = isset($args['action']) ? (string)$args['action'] : 'status';
                if ($action === 'courses') {
                    $result = array('courses' => $sessionCourses);
                } else {
                    $needsCourse = array('course_contents','assignments','assignment','quizzes','grades','quiz_attempts','quiz_grades','course_completion','activity_completion','forums','announcements','calendar_events');
                    $verifiedCourse = in_array($action, $needsCourse, true) ? mtpc_moodle_orb_course_from_session($args, $sessionCourses) : null;
                    $result = mtpc_orb_agent_moodle_student_tool($args, $operator, $verifiedCourse);
                }
            } catch (Exception $error) {
                $result = array('ok' => false, 'error' => $error->getMessage());
            }
            $encoded = json_encode(array('result' => $result), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (strlen($encoded) > 14000) $encoded = substr($encoded, 0, 14000) . '...';
            $responses[] = array('functionResponse' => array('name' => $name, 'response' => array('content' => $encoded)));
        }
        $contents[] = array('role' => 'user', 'parts' => $responses);
    }
    mtpc_moodle_orb_save_history($contents);
    mtpc_moodle_orb_response(200, array('ok' => true, 'reply' => 'Kết quả hơi dài. Anh/chị thu hẹp yêu cầu giúp mình nhé.'));
} catch (Throwable $error) {
    mtpc_moodle_orb_response(500, array('ok' => false, 'error' => $error->getMessage()));
}
