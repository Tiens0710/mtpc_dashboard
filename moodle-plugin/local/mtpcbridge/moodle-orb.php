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
require_once($CFG->libdir . '/completionlib.php');

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
    $tool = mtpc_orb_agent_student_moodle_tool();
    $actions = array('today_summary','due_work','open_activity','progress_summary','grades_summary');
    foreach ($actions as $action) $tool['parameters']['properties']['action']['enum'][] = $action;
    $tool['parameters']['properties']['days'] = array('type' => 'INTEGER', 'description' => 'Số ngày sắp tới cần xem, từ 1 đến 30.');
    $tool['parameters']['properties']['activity_type'] = array(
        'type' => 'STRING',
        'enum' => array('assign', 'quiz', 'page', 'url', 'forum', 'resource'),
        'description' => 'Loại hoạt động Moodle khi cần mở trực tiếp.',
    );
    return $tool;
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

function mtpc_moodle_orb_due_work($courses, $days) {
    global $DB, $USER;
    $days = max(1, min(30, (int)$days));
    $now = time();
    $todaystart = usergetmidnight($now);
    $tomorrow = $todaystart + DAYSECS;
    $paststart = $todaystart - (30 * DAYSECS);
    $windowend = $now + ($days * DAYSECS);
    $items = array();
    foreach ((array)$courses as $course) {
        $courseid = (int)$course['id'];
        $modinfo = get_fast_modinfo($courseid, (int)$USER->id);
        foreach ((array)$modinfo->get_instances_of('assign') as $cm) {
            if (!$cm->uservisible || empty($cm->instance)) continue;
            $assignment = $DB->get_record('assign', array('id' => (int)$cm->instance), 'id,name,duedate,cutoffdate', IGNORE_MISSING);
            if (!$assignment) continue;
            $due = !empty($assignment->duedate) ? (int)$assignment->duedate : (int)$assignment->cutoffdate;
            if ($due <= 0 || $due < $paststart || $due > $windowend) continue;
            $submissions = $DB->get_records('assign_submission', array('assignment' => (int)$assignment->id, 'userid' => (int)$USER->id), 'attemptnumber DESC', 'id,status,attemptnumber,timemodified', 0, 1);
            $submission = $submissions ? reset($submissions) : null;
            $completed = $submission && isset($submission->status) && $submission->status === 'submitted';
            $items[] = array(
                'type' => 'assignment', 'name' => format_string($assignment->name), 'course' => $course,
                'course_module_id' => (int)$cm->id, 'due_at' => $due, 'due_text' => userdate($due),
                'status' => $completed ? 'submitted' : ($due < $now ? 'overdue' : ($due < $tomorrow && $due >= $todaystart ? 'today' : 'upcoming')),
                'url' => $cm->url ? $cm->url->out(false) : '',
            );
        }
        foreach ((array)$modinfo->get_instances_of('quiz') as $cm) {
            if (!$cm->uservisible || empty($cm->instance)) continue;
            $quiz = $DB->get_record('quiz', array('id' => (int)$cm->instance), 'id,name,timeclose', IGNORE_MISSING);
            if (!$quiz || empty($quiz->timeclose) || (int)$quiz->timeclose < $paststart || (int)$quiz->timeclose > $windowend) continue;
            $finished = $DB->record_exists('quiz_attempts', array('quiz' => (int)$quiz->id, 'userid' => (int)$USER->id, 'state' => 'finished', 'preview' => 0));
            $due = (int)$quiz->timeclose;
            $items[] = array(
                'type' => 'quiz', 'name' => format_string($quiz->name), 'course' => $course,
                'course_module_id' => (int)$cm->id, 'due_at' => $due, 'due_text' => userdate($due),
                'status' => $finished ? 'finished' : ($due < $now ? 'overdue' : ($due < $tomorrow && $due >= $todaystart ? 'today' : 'upcoming')),
                'url' => $cm->url ? $cm->url->out(false) : '',
            );
        }
    }
    usort($items, function($a, $b) { return (int)$a['due_at'] - (int)$b['due_at']; });
    $result = array('today' => array(), 'upcoming' => array(), 'overdue' => array(), 'completed' => array());
    foreach ($items as $item) {
        if ($item['status'] === 'today') $result['today'][] = $item;
        else if ($item['status'] === 'overdue') $result['overdue'][] = $item;
        else if ($item['status'] === 'submitted' || $item['status'] === 'finished') $result['completed'][] = $item;
        else $result['upcoming'][] = $item;
    }
    $result['days'] = $days;
    $result['counts'] = array('today' => count($result['today']), 'upcoming' => count($result['upcoming']), 'overdue' => count($result['overdue']), 'completed' => count($result['completed']));
    foreach (array('today', 'upcoming', 'overdue', 'completed') as $bucket) {
        $result[$bucket] = array_slice($result[$bucket], 0, 20);
    }
    return $result;
}

function mtpc_moodle_orb_find_activity($course, $args) {
    global $USER;
    $modinfo = get_fast_modinfo((int)$course['id'], (int)$USER->id);
    $cmid = isset($args['course_module_id']) ? (int)$args['course_module_id'] : 0;
    $name = trim(isset($args['activity_name']) ? (string)$args['activity_name'] : '');
    $type = mtpc_orb_agent_normalize(isset($args['activity_type']) ? (string)$args['activity_type'] : '');
    $aliases = array('assignment' => 'assign', 'bai tap' => 'assign', 'bai kiem tra' => 'quiz', 'kiem tra' => 'quiz', 'tai nguyen' => 'resource', 'duong dan' => 'url', 'dien dan' => 'forum');
    if (isset($aliases[$type])) $type = $aliases[$type];
    $needle = mtpc_orb_agent_normalize($name);
    $exact = array(); $partial = array();
    foreach ((array)$modinfo->get_cms() as $cm) {
        if (!$cm->uservisible || !$cm->url || (!empty($cm->deletioninprogress))) continue;
        if ($cmid > 0 && (int)$cm->id !== $cmid) continue;
        if ($type !== '' && mtpc_orb_agent_normalize($cm->modname) !== $type) continue;
        $label = mtpc_orb_agent_normalize($cm->name);
        if ($cmid > 0 || ($needle !== '' && $label === $needle)) $exact[] = $cm;
        else if ($needle !== '' && strpos($label, $needle) !== false) $partial[] = $cm;
    }
    $matches = $exact ? $exact : $partial;
    if (count($matches) === 1) return $matches[0];
    if (!$matches) throw new Exception('Không tìm thấy hoạt động học tập phù hợp trong khóa học này.');
    $labels = array(); foreach (array_slice($matches, 0, 5) as $cm) $labels[] = format_string($cm->name);
    throw new Exception('Có nhiều hoạt động phù hợp: ' . implode(', ', $labels) . '. Hãy nói rõ tên hơn.');
}

function mtpc_moodle_orb_progress_summary($course) {
    global $USER;
    $courseobject = get_course((int)$course['id']);
    $completion = new completion_info($courseobject);
    $modinfo = get_fast_modinfo((int)$course['id'], (int)$USER->id);
    $total = 0; $completed = 0; $next = null;
    foreach ((array)$modinfo->get_cms() as $cm) {
        if (!$cm->uservisible || !empty($cm->deletioninprogress) || !$cm->url) continue;
        if ($completion->is_enabled($cm) == COMPLETION_TRACKING_NONE) continue;
        $total++;
        $data = $completion->get_data($cm, false, (int)$USER->id);
        if (!empty($data->completionstate)) $completed++;
        else if ($next === null) $next = array('name' => format_string($cm->name), 'type' => $cm->modname, 'course_module_id' => (int)$cm->id, 'url' => $cm->url->out(false));
    }
    return array('course' => $course, 'tracked' => $total, 'completed' => $completed, 'remaining' => max(0, $total - $completed), 'percent' => $total > 0 ? (int)round(($completed * 100) / $total) : null, 'next_activity' => $next);
}

function mtpc_moodle_orb_grades_summary($course) {
    global $DB, $USER;
    $context = context_course::instance((int)$course['id']);
    require_capability('moodle/grade:view', $context, (int)$USER->id);
    $sql = 'SELECT gi.id, gi.itemname, gi.itemtype, gi.itemmodule, gi.grademin, gi.grademax, gg.finalgrade, gg.feedback '
        . 'FROM {grade_items} gi LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid '
        . 'WHERE gi.courseid = :courseid AND gi.hidden = 0 AND (gg.hidden IS NULL OR gg.hidden = 0) ORDER BY gi.sortorder ASC';
    $rows = $DB->get_records_sql($sql, array('userid' => (int)$USER->id, 'courseid' => (int)$course['id']));
    $items = array(); $coursegrade = null; $graded = 0;
    foreach ($rows as $row) {
        if ($row->itemtype === 'course' && $row->finalgrade !== null) $coursegrade = array('grade' => (float)$row->finalgrade, 'maximum' => (float)$row->grademax);
        if ($row->itemtype !== 'mod' || trim((string)$row->itemname) === '') continue;
        if ($row->finalgrade !== null) $graded++;
        $items[] = array('name' => format_string($row->itemname), 'module' => (string)$row->itemmodule, 'grade' => $row->finalgrade === null ? null : (float)$row->finalgrade, 'minimum' => (float)$row->grademin, 'maximum' => (float)$row->grademax, 'feedback' => trim(strip_tags((string)$row->feedback)));
    }
    return array('course' => $course, 'course_grade' => $coursegrade, 'graded_count' => $graded, 'ungraded_count' => max(0, count($items) - $graded), 'items' => array_slice($items, 0, 30));
}

function mtpc_moodle_orb_execute_student_tool($args, $operator, $sessioncourses) {
    global $CFG;
    $action = isset($args['action']) ? (string)$args['action'] : 'status';
    if ($action === 'courses') return array('courses' => $sessioncourses);
    if ($action === 'today_summary' || $action === 'due_work') {
        $selected = $sessioncourses;
        if (!empty($args['course_id']) || trim(isset($args['course_name']) ? (string)$args['course_name'] : '') !== '') $selected = array(mtpc_moodle_orb_course_from_session($args, $sessioncourses));
        $due = mtpc_moodle_orb_due_work($selected, isset($args['days']) ? (int)$args['days'] : ($action === 'today_summary' ? 7 : 14));
        if ($action === 'today_summary') $due['enrolled_course_count'] = count($sessioncourses);
        return $due;
    }
    $needscourse = array('open_course','open_activity','progress_summary','grades_summary','course_contents','assignments','assignment','quizzes','grades','quiz_attempts','quiz_grades','course_completion','activity_completion','forums','announcements','calendar_events');
    $verifiedcourse = in_array($action, $needscourse, true) ? mtpc_moodle_orb_course_from_session($args, $sessioncourses) : null;
    if ($action === 'open_course') {
        return array(
            'ok' => true,
            'open_course' => true,
            'course' => $verifiedcourse,
            'redirect_url' => rtrim($CFG->wwwroot, '/') . '/course/view.php?id=' . (int)$verifiedcourse['id'],
        );
    }
    if ($action === 'open_activity') {
        $activity = mtpc_moodle_orb_find_activity($verifiedcourse, $args);
        return array('ok' => true, 'navigate' => true, 'course' => $verifiedcourse, 'activity' => array('name' => format_string($activity->name), 'type' => $activity->modname, 'course_module_id' => (int)$activity->id), 'redirect_url' => $activity->url->out(false));
    }
    if ($action === 'progress_summary') return mtpc_moodle_orb_progress_summary($verifiedcourse);
    if ($action === 'grades_summary') return mtpc_moodle_orb_grades_summary($verifiedcourse);
    return mtpc_orb_agent_moodle_student_tool($args, $operator, $verifiedcourse);
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
        . 'Dùng today_summary khi học sinh hỏi hôm nay hoặc sắp tới cần làm gì; due_work cho bài sắp đến hạn hoặc quá hạn; progress_summary cho tiến độ; grades_summary cho tổng kết điểm; open_activity khi học sinh yêu cầu mở một bài học, bài tập hoặc bài kiểm tra cụ thể. Chỉ nói đã mở hoặc đã chuyển trang khi kết quả công cụ trả về redirect_url. '
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
    global $USER, $SESSION;
    $role = 'student';
    $operator = array('user_id' => (string)$USER->id, 'user_name' => fullname($USER), 'role' => $role);
    if (isset($SESSION->mtpc_moodle_orb_role) && $SESSION->mtpc_moodle_orb_role !== $role) {
        unset($SESSION->mtpc_moodle_orb_history, $SESSION->mtpc_moodle_orb_pending);
    }
    $SESSION->mtpc_moodle_orb_role = $role;
    $sessionCourses = mtpc_moodle_orb_enrolled_courses();
    unset($SESSION->mtpc_moodle_orb_pending);

    $mode = isset($body['mode']) ? (string)$body['mode'] : 'chat';
    if ($mode === 'tool') {
        $name = isset($body['name']) ? (string)$body['name'] : '';
        if ($name !== 'moodle_student_action') mtpc_moodle_orb_response(403, array('ok' => false, 'error' => 'Orb học sinh chỉ được tra cứu dữ liệu học tập của chính mình.'));
        $args = isset($body['args']) && is_array($body['args']) ? $body['args'] : array();
        try {
            $result = mtpc_moodle_orb_execute_student_tool($args, $operator, $sessionCourses);
            mtpc_moodle_orb_response(200, array('ok' => true, 'result' => $result));
        } catch (Throwable $toolerror) {
            mtpc_moodle_orb_response(422, array('ok' => false, 'error' => $toolerror->getMessage()));
        }
    }

    $text = trim(isset($body['text']) ? (string)$body['text'] : '');
    if ($text === '') mtpc_moodle_orb_response(422, array('ok' => false, 'error' => 'Bạn chưa nhập yêu cầu.'));
    $normalized = mtpc_orb_agent_normalize($text);

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
                $result = mtpc_moodle_orb_execute_student_tool($args, $operator, $sessionCourses);
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
