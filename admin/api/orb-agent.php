<?php
/*
 * Shared server-side agent used by authenticated chat channels (Zalo first).
 * It mirrors the remote-capable tools of the browser Orb and deliberately
 * keeps write operations behind the existing XAC NHAN / HUY workflow.
 * PHP 5.6 compatible.
 */

function mtpc_orb_agent_api_key() {
    $key = getenv('GEMINI_API_KEY');
    $path = '/home/mtpc/private/gemini-config.php';
    if (!$key && is_file($path)) {
        require $path;
        $key = isset($GEMINI_API_KEY) ? $GEMINI_API_KEY : '';
    }
    return trim((string)$key);
}

function mtpc_orb_agent_tool($name, $description, $properties, $required) {
    $parameters = array('type' => 'OBJECT', 'properties' => $properties);
    if ($required) $parameters['required'] = $required;
    return array('name' => $name, 'description' => $description, 'parameters' => $parameters);
}

function mtpc_orb_agent_tools() {
    return array(
        mtpc_orb_agent_tool('student_action', 'Đọc hoặc cập nhật dữ liệu học viên. Dùng tên/mã/lớp tự nhiên; không bắt người dùng nhớ ID.', array(
            'action' => array('type' => 'STRING', 'enum' => array('summary','search','profile','attendance_alerts','finance_summary','update_status','update_class')),
            'query' => array('type' => 'STRING'),
            'student_identifier' => array('type' => 'STRING'),
            'new_status' => array('type' => 'STRING'),
            'new_class_name' => array('type' => 'STRING')
        ), array('action')),
        mtpc_orb_agent_tool('email_action', 'Đọc, tìm và tóm tắt hộp thư của trường. UID chỉ cần khi đọc một thư cụ thể.', array(
            'action' => array('type' => 'STRING', 'enum' => array('briefing','search','read')),
            'date_mode' => array('type' => 'STRING', 'enum' => array('today','yesterday','recent','date')),
            'query' => array('type' => 'STRING'), 'sender' => array('type' => 'STRING'),
            'subject' => array('type' => 'STRING'), 'unread_only' => array('type' => 'BOOLEAN'),
            'uid' => array('type' => 'STRING')
        ), array('action')),
        mtpc_orb_agent_tool('zalo_action', 'Đọc và quản lý tin nhắn hoặc nhóm Zalo GMF. Tự tra nhóm theo tên.', array(
            'action' => array('type' => 'STRING', 'enum' => array('list_groups','group_info','group_members','group_conversation','send_group_message','update_group','send_private','broadcast_students')),
            'group_identifier' => array('type' => 'STRING'), 'group_name' => array('type' => 'STRING'),
            'group_description' => array('type' => 'STRING'), 'message' => array('type' => 'STRING'),
            'recipient_user_id' => array('type' => 'STRING'), 'recipient_name' => array('type' => 'STRING'),
            'query' => array('type' => 'STRING'), 'class_name' => array('type' => 'STRING'),
            'status' => array('type' => 'STRING')
        ), array('action')),
        mtpc_orb_agent_tool('moodle_action', 'Đọc và vận hành Moodle bằng tên khóa học hoặc tên người dùng. Không yêu cầu ID nếu có thể tự tra tên.', array(
            'action' => array('type' => 'STRING', 'enum' => array('status','courses','course_contents','course_members','search_users','assignments','assignment_submissions','assignment_grades','quizzes','quiz_attempts','quiz_grades','grade_items','course_completion','activity_completion','forums','groups','calendar_events','create_course','update_course','delete_course','create_user','update_user','delete_user','enrol_user','bulk_enrol','unenrol_user','post_announcement','post_lecture','create_assignment','create_quiz','manage_activity','save_grade','bulk_save_grades','create_group','add_group_member','remove_group_member','delete_group','create_calendar_event','delete_calendar_event','send_message')),
            'course_name' => array('type' => 'STRING'), 'course_id' => array('type' => 'INTEGER'),
            'query' => array('type' => 'STRING'), 'user_query' => array('type' => 'STRING'), 'user_id' => array('type' => 'INTEGER'),
            'assignment_name' => array('type' => 'STRING'), 'assignment_id' => array('type' => 'INTEGER'),
            'quiz_name' => array('type' => 'STRING'), 'quiz_id' => array('type' => 'INTEGER'),
            'activity_name' => array('type' => 'STRING'), 'course_module_id' => array('type' => 'INTEGER'),
            'grade' => array('type' => 'NUMBER'), 'feedback' => array('type' => 'STRING'),
            'grades' => array('type' => 'ARRAY', 'items' => array('type' => 'OBJECT', 'properties' => array('user_id'=>array('type'=>'INTEGER'),'user_query'=>array('type'=>'STRING'),'grade'=>array('type'=>'NUMBER'),'feedback'=>array('type'=>'STRING')))),
            'role_id' => array('type' => 'INTEGER'), 'subject' => array('type' => 'STRING'),
            'message' => array('type' => 'STRING'), 'lecture_title' => array('type' => 'STRING'),
            'lecture_content' => array('type' => 'STRING'), 'lecture_url' => array('type' => 'STRING'),
            'lecture_type' => array('type' => 'STRING', 'enum' => array('page','url')),
            'section_num' => array('type' => 'INTEGER'), 'group_name' => array('type' => 'STRING'),
            'group_id' => array('type' => 'INTEGER'), 'event_name' => array('type' => 'STRING'), 'event_id' => array('type' => 'INTEGER'),
            'description' => array('type' => 'STRING'), 'timestart' => array('type' => 'INTEGER'),
            'timeduration' => array('type' => 'INTEGER'), 'fullname' => array('type' => 'STRING'),
            'shortname' => array('type' => 'STRING'), 'category_id' => array('type' => 'INTEGER'),
            'visible' => array('type' => 'BOOLEAN'), 'username' => array('type' => 'STRING'),
            'email' => array('type' => 'STRING'), 'password' => array('type' => 'STRING'),
            'firstname' => array('type' => 'STRING'), 'lastname' => array('type' => 'STRING'),
            'suspended' => array('type' => 'BOOLEAN'), 'user_names' => array('type' => 'ARRAY', 'items' => array('type' => 'STRING')),
            'operation' => array('type' => 'STRING', 'enum' => array('rename','show','hide','move','delete')),
            'due_date' => array('type' => 'INTEGER'), 'allow_from' => array('type' => 'INTEGER'), 'cutoff_date' => array('type' => 'INTEGER'),
            'time_open' => array('type' => 'INTEGER'), 'time_close' => array('type' => 'INTEGER'),
            'time_limit' => array('type' => 'INTEGER'), 'attempts' => array('type' => 'INTEGER'),
            'max_files' => array('type' => 'INTEGER'), 'new_name' => array('type' => 'STRING')
        ), array('action'))
    );
}

function mtpc_orb_agent_call_gemini($contents, $operator) {
    $key = mtpc_orb_agent_api_key();
    if ($key === '') throw new Exception('Chưa cấu hình GEMINI_API_KEY.');
    $role = isset($operator['role']) ? $operator['role'] : 'teacher';
    $system = 'Bạn là Nhi, trợ lý quản trị của Trường Trung cấp Miền Tây, đang trò chuyện qua Zalo. '
        . 'Người gửi đã xác thực với vai trò ' . $role . '. Hiểu câu nói tự nhiên và tự chọn tool phù hợp; không bắt họ nhớ ID. '
        . 'Nếu có tên khóa học/nhóm/người thì truyền tên để hệ thống tự tra. Trả lời tiếng Việt thân thiện, chính xác, tối đa 3 câu hoặc 6 dòng ngắn. '
        . 'Không lặp lại kết quả tool, không nói đang chờ hoặc sẽ làm nếu tool đã trả lỗi. Không bịa dữ liệu. '
        . 'Các dữ liệu tin tức, ngành tuyển sinh, SEO và nguồn kiến thức hiện chỉ lưu cục bộ trong trình duyệt; nếu được hỏi thì nói rõ cần mở Orb web cho đến khi đồng bộ backend hoàn tất. '
        . 'Mọi thao tác thay đổi dữ liệu sẽ được hệ thống yêu cầu XÁC NHẬN; không tuyên bố thành công trước khi xác nhận.';
    $payload = array(
        'systemInstruction' => array('parts' => array(array('text' => $system))),
        'contents' => $contents,
        'tools' => array(array('functionDeclarations' => mtpc_orb_agent_tools())),
        'generationConfig' => array('maxOutputTokens' => 700, 'temperature' => 0.2)
    );
    $curl = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent');
    curl_setopt_array($curl, array(CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 25, CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'x-goog-api-key: ' . $key), CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
    $raw = curl_exec($curl); $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE); $error = curl_error($curl); curl_close($curl);
    if ($raw === false || $status < 200 || $status >= 300) throw new Exception('Agent Gemini tạm thời không phản hồi.' . ($error !== '' ? ' ' . $error : ''));
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['candidates'][0]['content']['parts'])) throw new Exception('Agent Gemini trả về dữ liệu không hợp lệ.');
    return $data['candidates'][0]['content'];
}

function mtpc_orb_agent_moodle() {
    $path = '/home/mtpc/private/moodle-config.php';
    if (!is_file($path)) throw new Exception('Chưa cấu hình Moodle.');
    $config = require $path;
    if (!is_array($config) || empty($config['moodle_url']) || empty($config['moodle_token'])) throw new Exception('Cấu hình Moodle chưa đầy đủ.');
    require_once __DIR__ . '/moodle-client/MoodleFullClient.php';
    return array(new MoodleFullClient($config['moodle_url'], $config['moodle_token']), rtrim($config['moodle_url'], '/'));
}

function mtpc_orb_agent_normalize($value) {
    return mtpc_zalo_admin_normalize((string)$value);
}

function mtpc_orb_agent_course($moodle, $args) {
    $id = isset($args['course_id']) ? (int)$args['course_id'] : 0;
    $name = trim(isset($args['course_name']) ? (string)$args['course_name'] : '');
    if ($id > 0) return array('id' => $id, 'fullname' => $name);
    if ($name === '') throw new Exception('Cần tên khóa học.');
    $needle = mtpc_orb_agent_normalize($name); $matches = array();
    foreach ($moodle->getCourses() as $course) {
        $full = isset($course['fullname']) ? (string)$course['fullname'] : '';
        $short = isset($course['shortname']) ? (string)$course['shortname'] : '';
        $hay = mtpc_orb_agent_normalize($full . ' ' . $short);
        if ($needle !== '' && (strpos($hay, $needle) !== false || strpos($needle, mtpc_orb_agent_normalize($full)) !== false)) $matches[] = $course;
    }
    if (count($matches) === 1) return $matches[0];
    if (!$matches) throw new Exception('Không tìm thấy khóa học “' . $name . '”.');
    $labels = array(); foreach (array_slice($matches, 0, 5) as $row) $labels[] = isset($row['fullname']) ? $row['fullname'] : ('ID ' . $row['id']);
    throw new Exception('Có nhiều khóa học phù hợp: ' . implode('; ', $labels) . '. Hãy nói tên đầy đủ hơn.');
}

function mtpc_orb_agent_user($moodle, $args) {
    $id = isset($args['user_id']) ? (int)$args['user_id'] : 0;
    if ($id > 0) return array('id' => $id);
    $query = trim(isset($args['user_query']) ? (string)$args['user_query'] : (isset($args['query']) ? (string)$args['query'] : ''));
    if ($query === '') throw new Exception('Cần tên, username hoặc email tài khoản Moodle.');
    $users = (array)$moodle->getUsers(array('search' => $query));
    $needle = mtpc_orb_agent_normalize($query); $exact = array();
    foreach ($users as $user) {
        foreach (array('fullname','username','email','idnumber') as $field) {
            if (isset($user[$field]) && mtpc_orb_agent_normalize($user[$field]) === $needle) { $exact[] = $user; break; }
        }
    }
    $matches = $exact ? $exact : $users;
    if (count($matches) === 1) return $matches[0];
    if (!$matches) throw new Exception('Không tìm thấy tài khoản Moodle “' . $query . '”.');
    $labels = array(); foreach (array_slice($matches, 0, 5) as $row) $labels[] = (isset($row['fullname']) ? $row['fullname'] : $row['username']) . (empty($row['email']) ? '' : ' (' . $row['email'] . ')');
    throw new Exception('Có nhiều tài khoản phù hợp: ' . implode('; ', $labels) . '. Hãy nói rõ tên hoặc email.');
}

function mtpc_orb_agent_assignment($moodle, $courseId, $args) {
    $id = isset($args['assignment_id']) ? (int)$args['assignment_id'] : 0;
    if ($id > 0) return array('id' => $id);
    $name = trim(isset($args['assignment_name']) ? (string)$args['assignment_name'] : '');
    if ($name === '') throw new Exception('Cần tên bài tập.');
    $needle = mtpc_orb_agent_normalize($name); $rows = array();
    foreach ((array)$moodle->getAssignments(array($courseId)) as $course) {
        foreach ((array)(isset($course['assignments']) ? $course['assignments'] : array()) as $assignment) $rows[] = $assignment;
    }
    $exact = array(); $partial = array();
    foreach ($rows as $row) {
        $value = mtpc_orb_agent_normalize(isset($row['name']) ? $row['name'] : '');
        if ($value === $needle) $exact[] = $row;
        elseif ($needle !== '' && strpos($value, $needle) !== false) $partial[] = $row;
    }
    $matches = $exact ? $exact : $partial;
    if (count($matches) === 1) return $matches[0];
    if (!$matches) throw new Exception('Không tìm thấy bài tập “' . $name . '” trong khóa học.');
    $labels = array(); foreach (array_slice($matches, 0, 5) as $row) $labels[] = isset($row['name']) ? $row['name'] : ('ID ' . $row['id']);
    throw new Exception('Có nhiều bài tập phù hợp: ' . implode('; ', $labels) . '. Hãy nói tên đầy đủ hơn.');
}

function mtpc_orb_agent_group($moodle, $courseId, $args) {
    $id = isset($args['group_id']) ? (int)$args['group_id'] : 0;
    if ($id > 0) return array('id' => $id);
    $name = trim(isset($args['group_name']) ? (string)$args['group_name'] : '');
    if ($name === '') throw new Exception('Cần tên nhóm Moodle.');
    $needle = mtpc_orb_agent_normalize($name); $matches = array();
    foreach ((array)$moodle->getCourseGroups($courseId) as $group) {
        $value = mtpc_orb_agent_normalize(isset($group['name']) ? $group['name'] : '');
        if ($value === $needle || ($needle !== '' && strpos($value, $needle) !== false)) $matches[] = $group;
    }
    if (count($matches) === 1) return $matches[0];
    if (!$matches) throw new Exception('Không tìm thấy nhóm Moodle “' . $name . '”.');
    $labels = array(); foreach (array_slice($matches, 0, 5) as $row) $labels[] = isset($row['name']) ? $row['name'] : ('ID ' . $row['id']);
    throw new Exception('Có nhiều nhóm Moodle phù hợp: ' . implode('; ', $labels) . '. Hãy nói tên đầy đủ hơn.');
}

function mtpc_orb_agent_quiz($moodle, $courseId, $args) {
    $id = isset($args['quiz_id']) ? (int)$args['quiz_id'] : 0;
    if ($id > 0) return array('id' => $id);
    $name = trim(isset($args['quiz_name']) ? (string)$args['quiz_name'] : '');
    if ($name === '') throw new Exception('Cần tên bài kiểm tra.');
    $needle = mtpc_orb_agent_normalize($name); $matches = array();
    foreach ((array)$moodle->getQuizzesByCourses(array($courseId)) as $quiz) {
        $value = mtpc_orb_agent_normalize(isset($quiz['name']) ? $quiz['name'] : '');
        if ($value === $needle || ($needle !== '' && strpos($value, $needle) !== false)) $matches[] = $quiz;
    }
    if (count($matches) === 1) return $matches[0];
    if (!$matches) throw new Exception('Không tìm thấy bài kiểm tra “' . $name . '” trong khóa học.');
    $labels = array(); foreach (array_slice($matches, 0, 5) as $row) $labels[] = isset($row['name']) ? $row['name'] : ('ID ' . $row['id']);
    throw new Exception('Có nhiều bài kiểm tra phù hợp: ' . implode('; ', $labels) . '. Hãy nói tên đầy đủ hơn.');
}

function mtpc_orb_agent_activity($moodle, $courseId, $args) {
    $id = isset($args['course_module_id']) ? (int)$args['course_module_id'] : 0;
    if ($id > 0) return array('id' => $id);
    $name = trim(isset($args['activity_name']) ? (string)$args['activity_name'] : '');
    if ($name === '') throw new Exception('Cần tên hoạt động hoặc tài nguyên Moodle.');
    $needle = mtpc_orb_agent_normalize($name); $matches = array();
    foreach ((array)$moodle->getCourseContents($courseId) as $section) {
        foreach ((array)(isset($section['modules']) ? $section['modules'] : array()) as $module) {
            $value = mtpc_orb_agent_normalize(isset($module['name']) ? $module['name'] : '');
            if ($value === $needle || ($needle !== '' && strpos($value, $needle) !== false)) $matches[] = $module;
        }
    }
    if (count($matches) === 1) return $matches[0];
    if (!$matches) throw new Exception('Không tìm thấy hoạt động “' . $name . '” trong khóa học.');
    $labels = array(); foreach (array_slice($matches, 0, 5) as $row) $labels[] = isset($row['name']) ? $row['name'] : ('ID ' . $row['id']);
    throw new Exception('Có nhiều hoạt động phù hợp: ' . implode('; ', $labels) . '. Hãy nói tên đầy đủ hơn.');
}

function mtpc_orb_agent_event($moodle, $courseId, $args) {
    $id = isset($args['event_id']) ? (int)$args['event_id'] : 0;
    if ($id > 0) return array('id' => $id);
    $name = trim(isset($args['event_name']) ? (string)$args['event_name'] : '');
    if ($name === '') throw new Exception('Cần tên sự kiện Moodle.');
    $response = $moodle->getCalendarEvents(array($courseId), false);
    $events = isset($response['events']) && is_array($response['events']) ? $response['events'] : array();
    $needle = mtpc_orb_agent_normalize($name); $matches = array();
    foreach ($events as $event) {
        $value = mtpc_orb_agent_normalize(isset($event['name']) ? $event['name'] : '');
        if ($value === $needle || ($needle !== '' && strpos($value, $needle) !== false)) $matches[] = $event;
    }
    if (count($matches) === 1) return $matches[0];
    if (!$matches) throw new Exception('Không tìm thấy sự kiện “' . $name . '” trong khóa học.');
    throw new Exception('Có nhiều sự kiện trùng tên “' . $name . '”. Hãy nói tên cụ thể hơn.');
}

function mtpc_orb_agent_forums($moodle, $courseId) {
    try {
        $forums = $moodle->getForumsByCourses(array($courseId));
        if (isset($forums['forums']) && is_array($forums['forums'])) return $forums['forums'];
        return is_array($forums) ? $forums : array();
    } catch (Exception $error) {
        $forums = array();
        foreach ((array)$moodle->getCourseContents($courseId) as $section) {
            foreach ((array)(isset($section['modules']) ? $section['modules'] : array()) as $module) {
                if (strtolower((string)(isset($module['modname']) ? $module['modname'] : '')) !== 'forum' || empty($module['instance'])) continue;
                $forums[] = array('id'=>(int)$module['instance'],'name'=>isset($module['name'])?$module['name']:'Forum','type'=>strpos(mtpc_orb_agent_normalize(isset($module['name'])?$module['name']:''),'announcement')!==false?'news':'');
            }
        }
        return $forums;
    }
}

function mtpc_orb_agent_pending($tool, $args, $summary) {
    return array('pending' => true, 'intent' => array('intent' => 'orb_tool', 'tool' => $tool, 'args' => $args, 'summary' => $summary));
}

function mtpc_orb_agent_plain_text($value) {
    $value = (string)$value;
    $value = preg_replace('/```[a-z0-9_-]*\s*/i', '', $value);
    $value = str_replace('```', '', $value);
    $value = preg_replace('/\*\*([^*]+)\*\*/u', '$1', $value);
    $value = preg_replace('/__([^_]+)__/u', '$1', $value);
    $value = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/u', '$1', $value);
    $value = preg_replace('/(?<!_)_([^_\n]+)_(?!_)/u', '$1', $value);
    $value = preg_replace('/^#{1,6}\s+/m', '', $value);
    return trim($value);
}

function mtpc_orb_agent_group_identifier($groupsPath, $identifier) {
    $identifier = trim((string)$identifier);
    if ($identifier !== '') return $identifier;
    $groups = array();
    foreach ((array)mtpc_zalo_group_read($groupsPath) as $row) {
        if (is_array($row) && !empty($row['group_id'])) $groups[] = $row;
    }
    if (count($groups) !== 1) return '';
    if (!empty($groups[0]['name'])) return (string)$groups[0]['name'];
    if (!empty($groups[0]['group_name'])) return (string)$groups[0]['group_name'];
    return (string)$groups[0]['group_id'];
}

function mtpc_orb_agent_zalo_recipient($messagesPath, $pdo, $nameOrId) {
    $value = trim((string)$nameOrId);
    if (preg_match('/^[0-9]{6,160}$/', $value)) return array('user_id'=>$value,'user_name'=>'');
    $needle = mtpc_orb_agent_normalize($value); $found = array();
    if ($needle === '') throw new Exception('Cần tên người nhận.');
    if (function_exists('mtpc_zalo_read_messages')) foreach (mtpc_zalo_read_messages($messagesPath) as $row) {
        $uid = trim(isset($row['user_id']) ? (string)$row['user_id'] : '');
        $name = trim(isset($row['user_name']) ? (string)$row['user_name'] : '');
        if ($uid === '' || $name === '' || strpos(mtpc_orb_agent_normalize($name),$needle) === false) continue;
        $found[$uid] = array('user_id'=>$uid,'user_name'=>$name);
    }
    try {
        $statement = $pdo->prepare('SELECT full_name,zalo_user_id FROM students WHERE zalo_user_id IS NOT NULL AND zalo_user_id<>"" AND full_name LIKE :q LIMIT 10');
        $statement->execute(array(':q'=>'%'.$value.'%'));
        foreach ($statement->fetchAll() as $row) $found[(string)$row['zalo_user_id']] = array('user_id'=>(string)$row['zalo_user_id'],'user_name'=>$row['full_name']);
    } catch (Exception $ignored) {}
    $rows = array_values($found);
    if (count($rows) === 1) return $rows[0];
    if (!$rows) throw new Exception('Không tìm thấy người nhận Zalo tên “'.$value.'”.');
    $labels=array(); foreach (array_slice($rows,0,5) as $row) $labels[]=$row['user_name'];
    throw new Exception('Có nhiều người nhận phù hợp: '.implode(', ',$labels).'. Hãy nói tên đầy đủ hơn.');
}

function mtpc_orb_agent_execute_tool($name, $args, $operator, $config, $groupsPath, $messagesPath, $confirmed) {
    if (!is_array($args)) $args = array();
    $pdo = null;
    if ($name === 'student_action') {
        $action = isset($args['action']) ? $args['action'] : '';
        $permission = $action === 'attendance_alerts' ? 'attendance.read' : ($action === 'finance_summary' ? 'finance.read' : (in_array($action, array('update_status','update_class'), true) ? 'students.write' : 'students.read'));
        if (!mtpc_zalo_admin_permission(isset($operator['role']) ? $operator['role'] : '', $permission)) throw new Exception('Vai trò Zalo hiện tại không có quyền thực hiện thao tác này.');
        $pdo = mtpc_zalo_admin_db();
        if ($action === 'summary') return mtpc_zalo_admin_read_summary($pdo);
        if ($action === 'search') return mtpc_zalo_admin_read_search($pdo, $operator, isset($args['query']) ? $args['query'] : '');
        if ($action === 'profile') return mtpc_zalo_admin_read_profile($pdo, $operator, isset($args['student_identifier']) ? $args['student_identifier'] : '');
        if ($action === 'attendance_alerts') return mtpc_zalo_admin_read_attendance_alerts($pdo);
        if ($action === 'finance_summary') return mtpc_zalo_admin_read_finance_summary($pdo);
        $legacy = array('intent' => $action === 'update_status' ? 'student_status_update' : 'student_class_update', 'student_identifier' => isset($args['student_identifier']) ? $args['student_identifier'] : '', 'new_status' => isset($args['new_status']) ? $args['new_status'] : '', 'new_class_name' => isset($args['new_class_name']) ? $args['new_class_name'] : '');
        if (!$confirmed) {
            $prepared = mtpc_zalo_admin_pending_summary($pdo, $operator, $legacy);
            if (!is_array($prepared)) return $prepared;
            return mtpc_orb_agent_pending($name, $args, $prepared['summary']);
        }
        $prepared = mtpc_zalo_admin_pending_summary($pdo, $operator, $legacy);
        if (!is_array($prepared)) throw new Exception($prepared);
        return mtpc_zalo_admin_execute_pending($pdo, $operator, $prepared, $config, $groupsPath, $messagesPath);
    }
    if ($name === 'email_action') {
        if (!mtpc_zalo_admin_permission(isset($operator['role']) ? $operator['role'] : '', 'email.read')) throw new Exception('Vai trò Zalo hiện tại không được xem hộp thư.');
        $action = isset($args['action']) ? $args['action'] : 'briefing';
        $intent = array('intent' => $action === 'read' ? 'email_read' : ($action === 'search' ? 'email_search' : 'email_briefing'), 'email_date_mode' => isset($args['date_mode']) ? $args['date_mode'] : 'today', 'email_query' => isset($args['query']) ? $args['query'] : '', 'email_sender' => isset($args['sender']) ? $args['sender'] : '', 'email_subject' => isset($args['subject']) ? $args['subject'] : '', 'email_unread_only' => !empty($args['unread_only']), 'email_uid' => isset($args['uid']) ? $args['uid'] : '');
        return mtpc_zalo_admin_email_digest($operator, $intent);
    }
    if ($name === 'zalo_action') {
        $action = isset($args['action']) ? $args['action'] : '';
        $identifier = isset($args['group_identifier']) ? $args['group_identifier'] : '';
        if (in_array($action, array('group_info','group_members','group_conversation','send_group_message','update_group'), true)) {
            $identifier = mtpc_orb_agent_group_identifier($groupsPath, $identifier);
            if ($identifier !== '') $args['group_identifier'] = $identifier;
        }
        if ($action === 'list_groups') return mtpc_zalo_admin_group_list($groupsPath, $operator);
        if ($action === 'group_info') return mtpc_zalo_admin_group_info($config, $groupsPath, $operator, $identifier);
        if ($action === 'group_members') return mtpc_zalo_admin_group_members($config, $groupsPath, $operator, $identifier);
        if ($action === 'group_conversation') return mtpc_zalo_admin_group_conversation($config, $groupsPath, $operator, $identifier);
        $legacy = array('intent' => '', 'group_identifier' => $identifier, 'group_message' => isset($args['message']) ? $args['message'] : '', 'group_name' => isset($args['group_name']) ? $args['group_name'] : '', 'group_description' => isset($args['group_description']) ? $args['group_description'] : '', 'recipient_user_id' => isset($args['recipient_user_id']) ? $args['recipient_user_id'] : '', 'recipient_name' => isset($args['recipient_name']) ? $args['recipient_name'] : '', 'private_message' => isset($args['message']) ? $args['message'] : '', 'broadcast_query' => isset($args['query']) ? $args['query'] : '', 'broadcast_class_name' => isset($args['class_name']) ? $args['class_name'] : '', 'broadcast_status' => isset($args['status']) ? $args['status'] : '', 'broadcast_message' => isset($args['message']) ? $args['message'] : '');
        if ($action === 'send_private') $legacy['intent'] = 'zalo_private_send';
        elseif ($action === 'broadcast_students') $legacy['intent'] = 'zalo_student_broadcast';
        elseif ($action === 'send_group_message') $legacy['intent'] = 'group_send';
        else $legacy['intent'] = 'group_update';
        if ($legacy['intent'] === 'zalo_private_send' && empty($legacy['recipient_user_id']) && !empty($legacy['recipient_name'])) {
            $recipient = mtpc_orb_agent_zalo_recipient($messagesPath, mtpc_zalo_admin_db(), $legacy['recipient_name']);
            $legacy['recipient_user_id'] = $recipient['user_id']; $legacy['recipient_name'] = $recipient['user_name'];
            $args['recipient_user_id'] = $recipient['user_id']; $args['recipient_name'] = $recipient['user_name'];
        }
        if (!$confirmed) {
            if ($legacy['intent'] === 'zalo_student_broadcast') $prepared = mtpc_zalo_admin_broadcast_pending_summary(mtpc_zalo_admin_db(), $operator, $legacy);
            elseif ($legacy['intent'] === 'zalo_private_send') {
                if (empty($legacy['recipient_user_id']) || empty($legacy['private_message'])) return 'Cần người nhận và nội dung tin nhắn.';
                $prepared = $legacy; $prepared['summary'] = 'Gửi tin Zalo cho ' . ($legacy['recipient_name'] !== '' ? $legacy['recipient_name'] : $legacy['recipient_user_id']) . ': “' . mtpc_zalo_admin_text($legacy['private_message'], 180) . '”.';
            } else $prepared = mtpc_zalo_admin_group_pending_summary($groupsPath, $operator, $legacy);
            if (!is_array($prepared)) return $prepared;
            return mtpc_orb_agent_pending($name, $args, $prepared['summary']);
        }
        if ($legacy['intent'] === 'zalo_private_send') return mtpc_zalo_admin_send_private($config, $messagesPath, $operator, $legacy);
        if ($legacy['intent'] === 'zalo_student_broadcast') { $prepared = mtpc_zalo_admin_broadcast_pending_summary(mtpc_zalo_admin_db(), $operator, $legacy); return mtpc_zalo_admin_execute_broadcast(mtpc_zalo_admin_db(), $operator, $prepared, $config, $messagesPath); }
        $prepared = mtpc_zalo_admin_group_pending_summary($groupsPath, $operator, $legacy);
        if (!is_array($prepared)) throw new Exception($prepared);
        return mtpc_zalo_admin_execute_group_pending($config, $groupsPath, mtpc_zalo_admin_db(), $operator, $prepared);
    }
    if ($name === 'moodle_action') return mtpc_orb_agent_moodle_tool($args, $operator, $confirmed);
    throw new Exception('Tool không được hỗ trợ.');
}

function mtpc_orb_agent_moodle_tool($args, $operator, $confirmed) {
    if (!mtpc_zalo_admin_permission(isset($operator['role']) ? $operator['role'] : '', 'moodle.read')) throw new Exception('Vai trò Zalo hiện tại không được dùng Moodle.');
    list($moodle, $moodleUrl) = mtpc_orb_agent_moodle();
    $action = isset($args['action']) ? (string)$args['action'] : 'status';
    $write = in_array($action, array('create_course','update_course','delete_course','create_user','update_user','delete_user','enrol_user','bulk_enrol','unenrol_user','post_announcement','post_lecture','create_assignment','create_quiz','manage_activity','save_grade','bulk_save_grades','create_group','add_group_member','remove_group_member','delete_group','create_calendar_event','delete_calendar_event','send_message'), true);
    if ($write && !mtpc_zalo_admin_permission(isset($operator['role']) ? $operator['role'] : '', 'moodle.write')) throw new Exception('Vai trò Zalo hiện tại chỉ được xem Moodle.');
    if ($write && $action === 'create_course' && trim(isset($args['fullname']) ? $args['fullname'] : (isset($args['course_name']) ? $args['course_name'] : '')) === '') throw new Exception('Cần tên khóa học.');
    $needsCourse = in_array($action, array('course_contents','course_members','assignments','assignment_submissions','assignment_grades','quizzes','quiz_attempts','quiz_grades','grade_items','course_completion','activity_completion','forums','groups','calendar_events','update_course','delete_course','enrol_user','bulk_enrol','unenrol_user','post_announcement','post_lecture','create_assignment','create_quiz','manage_activity','save_grade','bulk_save_grades','create_group','add_group_member','remove_group_member','delete_group','create_calendar_event','delete_calendar_event'), true);
    if ($needsCourse) {
        $targetCourse = mtpc_orb_agent_course($moodle, $args);
        $args['course_id'] = (int)$targetCourse['id'];
        $args['course_name'] = isset($targetCourse['fullname']) ? $targetCourse['fullname'] : (isset($args['course_name']) ? $args['course_name'] : '');
    }
    if (in_array($action,array('update_user','delete_user','enrol_user','unenrol_user','save_grade','add_group_member','remove_group_member','send_message','course_completion','activity_completion'),true) && empty($args['user_id'])) {
        $targetUser = mtpc_orb_agent_user($moodle, $args);
        $args['user_id'] = (int)$targetUser['id'];
    }
    if (in_array($action,array('quiz_attempts','quiz_grades','grade_items'),true) && empty($args['user_id']) && (!empty($args['user_query']) || !empty($args['query']))) {
        $targetUser = mtpc_orb_agent_user($moodle, $args);
        $args['user_id'] = (int)$targetUser['id'];
    }
    if (in_array($action,array('assignment_submissions','assignment_grades','save_grade','bulk_save_grades'),true) && empty($args['assignment_id'])) {
        $targetAssignment = mtpc_orb_agent_assignment($moodle, (int)$args['course_id'], $args);
        $args['assignment_id'] = (int)$targetAssignment['id'];
    }
    if (in_array($action,array('add_group_member','remove_group_member','delete_group'),true) && empty($args['group_id'])) {
        $targetGroup = mtpc_orb_agent_group($moodle, (int)$args['course_id'], $args);
        $args['group_id'] = (int)$targetGroup['id'];
    }
    if (in_array($action,array('quiz_attempts','quiz_grades'),true) && empty($args['quiz_id'])) {
        $targetQuiz = mtpc_orb_agent_quiz($moodle, (int)$args['course_id'], $args);
        $args['quiz_id'] = (int)$targetQuiz['id'];
    }
    if ($action === 'manage_activity' && empty($args['course_module_id'])) {
        $targetActivity = mtpc_orb_agent_activity($moodle, (int)$args['course_id'], $args);
        $args['course_module_id'] = (int)$targetActivity['id'];
    }
    if ($action === 'delete_calendar_event' && empty($args['event_id'])) {
        $targetEvent = mtpc_orb_agent_event($moodle, (int)$args['course_id'], $args);
        $args['event_id'] = (int)$targetEvent['id'];
    }
    if ($action === 'bulk_enrol') {
        $resolved = array();
        foreach ((array)(isset($args['user_names']) ? $args['user_names'] : array()) as $name) {
            $user = mtpc_orb_agent_user($moodle, array('user_query' => $name));
            $resolved[] = (int)$user['id'];
        }
        if (!$resolved) throw new Exception('Cần danh sách tên người dùng để ghi danh.');
        $args['user_ids'] = $resolved;
    }
    if ($action === 'bulk_save_grades') {
        $rows = array();
        foreach ((array)(isset($args['grades']) ? $args['grades'] : array()) as $row) {
            if (empty($row['user_id'])) {
                $user = mtpc_orb_agent_user($moodle, array('user_query' => isset($row['user_query']) ? $row['user_query'] : ''));
                $row['user_id'] = (int)$user['id'];
            }
            if (!isset($row['grade'])) throw new Exception('Mỗi học viên cần có điểm.');
            $rows[] = $row;
        }
        if (!$rows) throw new Exception('Cần danh sách điểm cần lưu.');
        $args['grades'] = $rows;
    }
    if ($write && $action === 'post_announcement' && (empty($args['subject']) || empty($args['message']))) throw new Exception('Cần tiêu đề và nội dung thông báo.');
    if ($write && $action === 'post_lecture' && empty($args['lecture_title'])) throw new Exception('Cần tiêu đề bài giảng.');
    if ($write && $action === 'create_assignment' && empty($args['assignment_name'])) throw new Exception('Cần tên bài tập.');
    if ($write && $action === 'create_quiz' && empty($args['quiz_name'])) throw new Exception('Cần tên bài kiểm tra.');
    if ($write && $action === 'manage_activity' && empty($args['operation'])) throw new Exception('Cần thao tác đổi tên, hiện, ẩn, chuyển mục hoặc xóa.');
    if ($write && $action === 'create_group' && empty($args['group_name'])) throw new Exception('Cần tên nhóm Moodle.');
    if ($write && $action === 'save_grade' && !isset($args['grade'])) throw new Exception('Cần điểm cần chấm.');
    if ($write && $action === 'create_calendar_event' && (empty($args['event_name']) || empty($args['timestart']))) throw new Exception('Cần tên sự kiện và thời gian bắt đầu.');
    if ($write && !$confirmed) {
        $label = str_replace('_', ' ', $action);
        $course = isset($args['course_name']) ? $args['course_name'] : (isset($args['fullname']) ? $args['fullname'] : '');
        return mtpc_orb_agent_pending('moodle_action', $args, 'Thực hiện “' . $label . '”' . ($course !== '' ? ' trên khóa “' . $course . '”' : '') . '.');
    }
    if ($action === 'status') { $site = $moodle->getSiteInfo(); return array('site' => isset($site['sitename']) ? $site['sitename'] : '', 'functions' => isset($site['functions']) ? count($site['functions']) : 0); }
    if ($action === 'courses') { $rows = array(); foreach ($moodle->getCourses() as $c) $rows[] = array('id'=>(int)$c['id'],'fullname'=>$c['fullname'],'shortname'=>$c['shortname']); return array('courses' => $rows); }
    if ($action === 'search_users') return array('users' => $moodle->getUsers(array('search' => isset($args['query']) ? $args['query'] : '')));
    if ($action === 'create_course') {
        $full = trim(isset($args['fullname']) ? $args['fullname'] : (isset($args['course_name']) ? $args['course_name'] : ''));
        if ($full === '') throw new Exception('Cần tên khóa học.');
        $short = trim(isset($args['shortname']) ? $args['shortname'] : '');
        if ($short === '') $short = strtoupper(substr(md5($full . microtime(true)), 0, 8));
        $categoryId = isset($args['category_id']) ? (int)$args['category_id'] : 0;
        if ($categoryId <= 0) { $categories = $moodle->getCategories(); if (!$categories) throw new Exception('Moodle chưa có danh mục khóa học phù hợp.'); $categoryId = (int)$categories[0]['id']; }
        return array('message'=>'Đã tạo khóa học.','courses'=>$moodle->createCourses(array(array('fullname'=>$full,'shortname'=>$short,'categoryid'=>$categoryId,'summary'=>isset($args['description'])?$args['description']:'','visible'=>isset($args['visible'])?($args['visible']?1:0):1))));
    }
    if ($action === 'create_user') {
        foreach (array('username','password','firstname','lastname','email') as $field) if (empty($args[$field])) throw new Exception('Tạo tài khoản cần username, mật khẩu, họ, tên và email.');
        return array('message'=>'Đã tạo tài khoản Moodle.','users'=>$moodle->createUsers(array(array('username'=>$args['username'],'password'=>$args['password'],'firstname'=>$args['firstname'],'lastname'=>$args['lastname'],'email'=>$args['email'],'auth'=>'manual'))));
    }
    if ($action === 'update_user') {
        $row = array('id'=>(int)$args['user_id']); foreach (array('username','firstname','lastname','email') as $field) if (isset($args[$field]) && $args[$field] !== '') $row[$field]=$args[$field]; if (isset($args['suspended'])) $row['suspended']=$args['suspended']?1:0;
        return array('message'=>'Đã cập nhật tài khoản Moodle.','result'=>$moodle->updateUsers(array($row)));
    }
    if ($action === 'delete_user') { $moodle->deleteUsers(array((int)$args['user_id'])); return array('message'=>'Đã xóa tài khoản Moodle.'); }
    if ($action === 'send_message') return array('message'=>'Đã gửi tin Moodle.','result'=>$moodle->sendMessages(array(array('touserid'=>(int)$args['user_id'],'text'=>isset($args['message'])?$args['message']:'','textformat'=>0))));
    $course = mtpc_orb_agent_course($moodle, $args); $courseId = (int)$course['id'];
    if ($action === 'course_contents') return array('course'=>$course,'sections'=>$moodle->getCourseContents($courseId));
    if ($action === 'course_members') return array('course'=>$course,'users'=>$moodle->getEnrolledUsers($courseId));
    if ($action === 'assignments') return array('course'=>$course,'assignments'=>$moodle->getAssignments(array($courseId)));
    if ($action === 'assignment_submissions') return array('course'=>$course,'assignment_id'=>(int)$args['assignment_id'],'submissions'=>$moodle->getSubmissions((int)$args['assignment_id']));
    if ($action === 'assignment_grades') return array('course'=>$course,'assignment_id'=>(int)$args['assignment_id'],'grades'=>$moodle->getAssignmentGrades(array((int)$args['assignment_id'])));
    if ($action === 'quizzes') return array('course'=>$course,'quizzes'=>$moodle->getQuizzesByCourses(array($courseId)));
    if ($action === 'quiz_attempts') {
        $rows = array();
        if (!empty($args['user_id'])) $rows[] = array('userid'=>(int)$args['user_id'], 'result'=>$moodle->getUserQuizAttempts((int)$args['quiz_id'],(int)$args['user_id']));
        else foreach (array_slice((array)$moodle->getEnrolledUsers($courseId), 0, 200) as $user) $rows[] = array('userid'=>(int)$user['id'], 'fullname'=>isset($user['fullname'])?$user['fullname']:'', 'result'=>$moodle->getUserQuizAttempts((int)$args['quiz_id'],(int)$user['id']));
        return array('course'=>$course,'quiz_id'=>(int)$args['quiz_id'],'attempts'=>$rows);
    }
    if ($action === 'quiz_grades') {
        $userIds=!empty($args['user_id'])?array((int)$args['user_id']):array(); if(!$userIds)foreach(array_slice((array)$moodle->getEnrolledUsers($courseId),0,200) as $user)$userIds[]=(int)$user['id'];
        return array('course'=>$course,'quiz_id'=>(int)$args['quiz_id'],'grades'=>$moodle->getQuizGrades(array((int)$args['quiz_id']),$userIds));
    }
    if ($action === 'grade_items') return array('course'=>$course,'grades'=>!empty($args['user_id'])?$moodle->getUserGrades($courseId,(int)$args['user_id']):$moodle->getGradeItems($courseId));
    if ($action === 'course_completion') return array('course'=>$course,'completion'=>$moodle->getCourseCompletion($courseId,(int)$args['user_id']));
    if ($action === 'activity_completion') return array('course'=>$course,'completion'=>$moodle->getActivityCompletion($courseId,(int)$args['user_id']));
    if ($action === 'forums') return array('course'=>$course,'forums'=>mtpc_orb_agent_forums($moodle,$courseId));
    if ($action === 'groups') return array('course'=>$course,'groups'=>$moodle->getCourseGroups($courseId));
    if ($action === 'calendar_events') return array('course'=>$course,'events'=>$moodle->getCalendarEvents(array($courseId), false));
    if ($action === 'update_course') { $row=array('id'=>$courseId); foreach(array('fullname','shortname') as $field) if(isset($args[$field])&&$args[$field]!=='')$row[$field]=$args[$field]; if(isset($args['visible']))$row['visible']=$args['visible']?1:0; return array('message'=>'Đã cập nhật khóa học.','result'=>$moodle->updateCourses(array($row))); }
    if ($action === 'delete_course') { $moodle->deleteCourses(array($courseId)); return array('message'=>'Đã xóa khóa học.'); }
    if ($action === 'enrol_user' || $action === 'unenrol_user') {
        $uid = isset($args['user_id']) ? (int)$args['user_id'] : 0; if ($uid <= 0) throw new Exception('Cần tài khoản Moodle cần ghi danh.');
        $row = array('roleid'=>isset($args['role_id'])?(int)$args['role_id']:5,'userid'=>$uid,'courseid'=>$courseId);
        if ($action === 'enrol_user') $moodle->enrolUsers(array($row)); else $moodle->unenrolUsers(array($row));
        return $action === 'enrol_user' ? 'Đã ghi danh tài khoản.' : 'Đã hủy ghi danh tài khoản.';
    }
    if ($action === 'bulk_enrol') { $rows=array(); foreach((array)$args['user_ids'] as $uid)$rows[]=array('roleid'=>isset($args['role_id'])?(int)$args['role_id']:5,'userid'=>(int)$uid,'courseid'=>$courseId); return array('message'=>'Đã ghi danh '.count($rows).' tài khoản.','result'=>$moodle->enrolUsers($rows)); }
    if ($action === 'post_announcement') {
        $subject = trim(isset($args['subject']) ? $args['subject'] : ''); $message = trim(isset($args['message']) ? $args['message'] : '');
        if ($subject === '' || $message === '') throw new Exception('Cần tiêu đề và nội dung thông báo.');
        $forums = mtpc_orb_agent_forums($moodle,$courseId); $forum = null;
        foreach ((array)$forums as $f) { $n = mtpc_orb_agent_normalize(isset($f['name'])?$f['name']:''); if ((isset($f['type'])&&$f['type']==='news') || strpos($n,'announcement')!==false || strpos($n,'thong bao')!==false) { $forum=$f; break; } }
        if (!$forum || empty($forum['id'])) throw new Exception('Không tìm thấy diễn đàn Thông báo trong khóa học.');
        return array('message'=>'Đã đăng thông báo.','result'=>$moodle->createAnnouncement($courseId,(int)$forum['id'],$subject,$message));
    }
    if ($action === 'post_lecture') {
        $title = trim(isset($args['lecture_title'])?$args['lecture_title']:''); if ($title==='') throw new Exception('Cần tiêu đề bài giảng.');
        return array('message'=>'Đã đăng bài giảng.','result'=>$moodle->createLecture($courseId,isset($args['section_num'])?(int)$args['section_num']:0,isset($args['lecture_type'])?$args['lecture_type']:'page',$title,isset($args['lecture_content'])?$args['lecture_content']:'',1,isset($args['lecture_url'])?$args['lecture_url']:''));
    }
    if ($action === 'create_assignment') return array('message'=>'Đã tạo bài tập.','result'=>$moodle->createAssignment($courseId,isset($args['section_num'])?(int)$args['section_num']:0,$args['assignment_name'],isset($args['description'])?$args['description']:'',isset($args['due_date'])?(int)$args['due_date']:0,isset($args['allow_from'])?(int)$args['allow_from']:0,isset($args['cutoff_date'])?(int)$args['cutoff_date']:0,isset($args['grade'])?(float)$args['grade']:10,isset($args['max_files'])?(int)$args['max_files']:1,0));
    if ($action === 'create_quiz') return array('message'=>'Đã tạo bài kiểm tra trống. Hãy thêm câu hỏi trong Moodle.','result'=>$moodle->createQuiz($courseId,isset($args['section_num'])?(int)$args['section_num']:0,$args['quiz_name'],isset($args['description'])?$args['description']:'',isset($args['time_open'])?(int)$args['time_open']:0,isset($args['time_close'])?(int)$args['time_close']:0,isset($args['time_limit'])?(int)$args['time_limit']:0,isset($args['attempts'])?(int)$args['attempts']:0,isset($args['grade'])?(float)$args['grade']:10));
    if ($action === 'manage_activity') return array('message'=>'Đã cập nhật hoạt động Moodle.','result'=>$moodle->manageActivity((int)$args['course_module_id'],$args['operation'],isset($args['new_name'])?$args['new_name']:'',isset($args['section_num'])?(int)$args['section_num']:-1));
    if ($action === 'save_grade') return array('message'=>'Đã lưu điểm.','result'=>$moodle->saveGrade((int)$args['assignment_id'],(int)$args['user_id'],(float)$args['grade'],isset($args['feedback'])?$args['feedback']:'',''));
    if ($action === 'bulk_save_grades') { $saved=array(); foreach((array)$args['grades'] as $row)$saved[]=$moodle->saveGrade((int)$args['assignment_id'],(int)$row['user_id'],(float)$row['grade'],isset($row['feedback'])?$row['feedback']:'',''); return array('message'=>'Đã lưu điểm cho '.count($saved).' học viên.','result'=>$saved); }
    if ($action === 'create_group') return array('message'=>'Đã tạo nhóm Moodle.','result'=>$moodle->createGroups(array(array('courseid'=>$courseId,'name'=>isset($args['group_name'])?$args['group_name']:'','description'=>isset($args['description'])?$args['description']:''))));
    if ($action === 'add_group_member') return array('message'=>'Đã thêm thành viên.','result'=>$moodle->addGroupMembers(array(array('groupid'=>(int)$args['group_id'],'userid'=>(int)$args['user_id']))));
    if ($action === 'remove_group_member') return array('message'=>'Đã xóa thành viên khỏi nhóm.','result'=>$moodle->deleteGroupMembers(array(array('groupid'=>(int)$args['group_id'],'userid'=>(int)$args['user_id']))));
    if ($action === 'delete_group') { $moodle->deleteGroups(array((int)$args['group_id'])); return array('message'=>'Đã xóa nhóm Moodle.'); }
    if ($action === 'create_calendar_event') return array('message'=>'Đã tạo lịch Moodle.','result'=>$moodle->createCalendarEvents(array(array('name'=>$args['event_name'],'description'=>isset($args['description'])?$args['description']:'','eventtype'=>'course','courseid'=>$courseId,'timestart'=>(int)$args['timestart'],'timeduration'=>isset($args['timeduration'])?(int)$args['timeduration']:0,'visible'=>1))));
    if ($action === 'delete_calendar_event') { $moodle->deleteCalendarEvents(array((int)$args['event_id'])); return array('message'=>'Đã xóa sự kiện Moodle.'); }
    throw new Exception('Thao tác Moodle chưa được hỗ trợ trên Zalo.');
}

function mtpc_orb_agent_execute_pending($operator, $intent, $config, $groupsPath, $messagesPath) {
    return mtpc_orb_agent_execute_tool(isset($intent['tool']) ? $intent['tool'] : '', isset($intent['args']) ? $intent['args'] : array(), $operator, $config, $groupsPath, $messagesPath, true);
}

function mtpc_orb_agent_history($messagesPath, $userId, $currentText) {
    $history = array();
    if (function_exists('mtpc_zalo_read_messages')) {
        foreach (mtpc_zalo_read_messages($messagesPath) as $row) {
            if ((string)(isset($row['user_id']) ? $row['user_id'] : '') !== (string)$userId) continue;
            $direction = isset($row['direction']) ? $row['direction'] : '';
            $text = trim(isset($row['text']) ? (string)$row['text'] : '');
            if ($text === '' || !in_array($direction, array('inbound','outbound'), true)) continue;
            $history[] = array('role'=>$direction === 'inbound' ? 'user' : 'model','parts'=>array(array('text'=>mtpc_zalo_admin_text($text,1800))));
            if (count($history) >= 10) break;
        }
        $history = array_reverse($history);
    }
    $last = count($history) ? $history[count($history)-1] : null;
    $lastText = $last && isset($last['parts'][0]['text']) ? trim($last['parts'][0]['text']) : '';
    if (!$last || $last['role'] !== 'user' || $lastText !== trim((string)$currentText)) {
        $history[] = array('role'=>'user','parts'=>array(array('text'=>mtpc_zalo_admin_text($currentText,4000))));
    }
    return $history;
}

function mtpc_orb_agent_handle_message($operator, $text, $pendingPath, $config, $groupsPath, $messagesPath) {
    if (trim((string)$text) === '') $text = 'Tôi vừa gửi một sticker. Hãy phản hồi ngắn gọn, tự nhiên.';
    $contents = mtpc_orb_agent_history($messagesPath, isset($operator['user_id']) ? $operator['user_id'] : '', $text);
    for ($round=0; $round<4; $round++) {
        $content = mtpc_orb_agent_call_gemini($contents, $operator); $contents[] = $content;
        $calls = array(); $reply = '';
        foreach ($content['parts'] as $part) { if (isset($part['functionCall'])) $calls[] = $part['functionCall']; if (isset($part['text'])) $reply .= $part['text']; }
        if (!$calls) return array('reply'=>trim($reply)!==''?mtpc_orb_agent_plain_text($reply):'Em chưa có đủ thông tin để trả lời chính xác.','event_name'=>'zalo_orb_agent');
        $responses = array();
        foreach ($calls as $call) {
            $name = isset($call['name']) ? $call['name'] : ''; $args = isset($call['args'])&&is_array($call['args'])?$call['args']:array();
            try { $result = mtpc_orb_agent_execute_tool($name,$args,$operator,$config,$groupsPath,$messagesPath,false); }
            catch (Exception $error) { $result = array('ok'=>false,'error'=>$error->getMessage()); }
            if (is_array($result) && !empty($result['pending'])) {
                $pending = mtpc_zalo_admin_pending_read($pendingPath); $uid = (string)$operator['user_id'];
                $pending[$uid] = array('intent'=>$result['intent'],'expires_at'=>time()+600); mtpc_zalo_admin_write_json($pendingPath,$pending);
                return array('reply'=>'Em đã chuẩn bị: '.$result['intent']['summary']."\nNhắn “XÁC NHẬN” để thực hiện hoặc “HỦY” để bỏ qua. Thời hạn 10 phút.",'event_name'=>'zalo_orb_agent_pending');
            }
            $encoded = json_encode(array('result'=>$result),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); if (strlen($encoded)>14000) $encoded=substr($encoded,0,14000).'...';
            $responses[] = array('functionResponse'=>array('name'=>$name,'response'=>array('content'=>$encoded)));
        }
        $contents[] = array('role'=>'user','parts'=>$responses);
    }
    return array('reply'=>'Em đã tra cứu nhưng kết quả quá dài. Anh thu hẹp yêu cầu giúp em nhé.','event_name'=>'zalo_orb_agent_limit');
}
