<?php
/**
 * MTPC Moodle bridge.
 *
 * Secrets stay in /home/mtpc/private/moodle-config.php. This endpoint only
 * exposes the Moodle data/actions needed by the protected Admin dashboard.
 * PHP 5.6 compatible.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET,POST,OPTIONS');

function mtpc_moodle_response($status, $payload)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require __DIR__ . '/_student_bootstrap.php';

$action = isset($_GET['action']) ? (string)$_GET['action'] : 'status';
$writePermissions = array(
    'create-course' => 'moodle.write', 'update-course' => 'moodle.write', 'delete-course' => 'moodle.write',
    'enrol-user' => 'moodle.write', 'unenrol-user' => 'moodle.write', 'create-user' => 'moodle.write',
    'update-user' => 'moodle.write', 'delete-user' => 'moodle.write',
    'post-announcement' => 'moodle.content.write', 'post-lecture' => 'moodle.content.write', 'post-lecture-file' => 'moodle.content.write', 'save-grade' => 'moodle.grade.write',
    'create-group' => 'moodle.group.write', 'add-group-member' => 'moodle.group.write',
    'create-calendar-event' => 'moodle.calendar.write', 'send-message' => 'moodle.message.write'
);
mtpc_require_permission(isset($writePermissions[$action]) ? $writePermissions[$action] : 'moodle.read');

$configPath = '/home/mtpc/private/moodle-config.php';
if (!is_file($configPath) || !is_readable($configPath)) {
    mtpc_moodle_response(503, array('ok' => false, 'error' => 'Chưa cấu hình Moodle. Tạo /home/mtpc/private/moodle-config.php.'));
}

$config = require $configPath;
if (!is_array($config)) {
    mtpc_moodle_response(503, array('ok' => false, 'error' => 'moodle-config.php phải return một mảng cấu hình.'));
}

$moodleUrl = isset($config['moodle_url']) ? trim((string)$config['moodle_url']) : '';
$moodleToken = isset($config['moodle_token']) ? trim((string)$config['moodle_token']) : '';
if ($moodleUrl === '' || $moodleToken === '' || strpos($moodleUrl, 'example.com') !== false || strpos($moodleToken, 'your_') === 0) {
    mtpc_moodle_response(503, array('ok' => false, 'error' => 'Moodle URL hoặc Web Service token chưa được cấu hình đúng.'));
}

require_once __DIR__ . '/moodle-client/MoodleFullClient.php';

function mtpc_moodle_text($value, $limit)
{
    $value = trim((string)$value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $limit, 'UTF-8') : substr($value, 0, $limit);
}

function mtpc_moodle_body()
{
    $body = json_decode(file_get_contents('php://input'), true);
    return is_array($body) ? $body : array();
}

function mtpc_moodle_course($course)
{
    return array(
        'id' => isset($course['id']) ? (int)$course['id'] : 0,
        'fullname' => isset($course['fullname']) ? (string)$course['fullname'] : '',
        'shortname' => isset($course['shortname']) ? (string)$course['shortname'] : '',
        'idnumber' => isset($course['idnumber']) ? (string)$course['idnumber'] : '',
        'categoryid' => isset($course['categoryid']) ? (int)$course['categoryid'] : 0,
        'categoryname' => isset($course['categoryname']) ? (string)$course['categoryname'] : '',
        'summary' => isset($course['summary']) ? trim(strip_tags((string)$course['summary'])) : '',
        'visible' => !isset($course['visible']) || (int)$course['visible'] === 1,
        'startdate' => isset($course['startdate']) ? (int)$course['startdate'] : 0,
        'enddate' => isset($course['enddate']) ? (int)$course['enddate'] : 0,
    );
}

function mtpc_moodle_user($user)
{
    return array(
        'id' => isset($user['id']) ? (int)$user['id'] : 0,
        'username' => isset($user['username']) ? (string)$user['username'] : '',
        'fullname' => isset($user['fullname']) ? (string)$user['fullname'] : trim((isset($user['firstname']) ? $user['firstname'] : '') . ' ' . (isset($user['lastname']) ? $user['lastname'] : '')),
        'firstname' => isset($user['firstname']) ? (string)$user['firstname'] : '',
        'lastname' => isset($user['lastname']) ? (string)$user['lastname'] : '',
        'email' => isset($user['email']) ? (string)$user['email'] : '',
        'idnumber' => isset($user['idnumber']) ? (string)$user['idnumber'] : '',
        'suspended' => !empty($user['suspended']),
    );
}

function mtpc_moodle_forums($data)
{
    if (isset($data['forums']) && is_array($data['forums'])) return $data['forums'];
    return is_array($data) ? $data : array();
}

function mtpc_moodle_find_announcement_forum($moodle, $courseId)
{
    $forums = mtpc_moodle_forums($moodle->getForumsByCourses(array($courseId)));
    foreach ($forums as $forum) {
        $type = isset($forum['type']) ? strtolower((string)$forum['type']) : '';
        $rawName = isset($forum['name']) ? (string)$forum['name'] : '';
        $name = function_exists('mb_strtolower') ? mb_strtolower($rawName, 'UTF-8') : strtolower($rawName);
        if ($type === 'news' || strpos($name, 'announcement') !== false || strpos($name, 'thông báo') !== false || strpos($name, 'thong bao') !== false) {
            return $forum;
        }
    }
    return null;
}

try {
    $moodle = new MoodleFullClient($moodleUrl, $moodleToken);

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'status') {
        $site = $moodle->getSiteInfo();
        $functions = array();
        if (!empty($site['functions']) && is_array($site['functions'])) {
            foreach ($site['functions'] as $function) {
                if (isset($function['name'])) {
                    $functions[] = (string)$function['name'];
                }
            }
        }
        $required = array(
            'core_course_get_courses', 'core_course_get_contents', 'core_user_get_users',
            'core_enrol_get_enrolled_users', 'core_course_create_courses', 'enrol_manual_enrol_users',
            'mod_forum_get_forums_by_courses', 'mod_forum_add_discussion',
            'mod_assign_get_submissions', 'mod_assign_get_grades', 'mod_assign_save_grade',
            'core_group_get_course_groups', 'core_group_create_groups', 'core_group_add_group_members',
            'core_calendar_get_calendar_events', 'core_calendar_create_calendar_events',
            'core_message_send_instant_messages', 'local_mtpcbridge_create_lecture', 'local_mtpcbridge_create_file_lecture'
        );
        $available = array();
        foreach ($required as $name) {
            $available[$name] = in_array($name, $functions, true);
        }
        mtpc_moodle_response(200, array('ok' => true, 'connected' => true, 'site' => array(
            'name' => isset($site['sitename']) ? (string)$site['sitename'] : '',
            'url' => isset($site['siteurl']) ? (string)$site['siteurl'] : $moodleUrl,
            'username' => isset($site['username']) ? (string)$site['username'] : '',
            'user_id' => isset($site['userid']) ? (int)$site['userid'] : 0,
        ), 'function_count' => count($functions), 'required_functions' => $available));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'courses') {
        $courses = $moodle->getCourses();
        $rows = array();
        foreach ($courses as $course) {
            if (is_array($course)) {
                $rows[] = mtpc_moodle_course($course);
            }
        }
        mtpc_moodle_response(200, array('ok' => true, 'courses' => $rows, 'total' => count($rows)));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'categories') {
        $categories = $moodle->getCategories();
        mtpc_moodle_response(200, array('ok' => true, 'categories' => is_array($categories) ? $categories : array()));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'users') {
        $query = mtpc_moodle_text(isset($_GET['q']) ? $_GET['q'] : '', 120);
        $field = isset($_GET['field']) ? (string)$_GET['field'] : 'search';
        $allowedFields = array('search', 'username', 'email', 'idnumber', 'firstname', 'lastname');
        if ($query === '') {
            mtpc_moodle_response(422, array('ok' => false, 'error' => 'Nhập từ khoá để tìm tài khoản Moodle.'));
        }
        if (!in_array($field, $allowedFields, true)) {
            $field = 'search';
        }
        $users = $moodle->getUsers(array($field => $query));
        $rows = array();
        foreach ($users as $user) {
            if (is_array($user)) {
                $rows[] = mtpc_moodle_user($user);
            }
        }
        mtpc_moodle_response(200, array('ok' => true, 'users' => $rows, 'total' => count($rows)));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'enrolled-users') {
        $courseId = isset($_GET['courseid']) ? (int)$_GET['courseid'] : 0;
        if ($courseId <= 0) {
            mtpc_moodle_response(422, array('ok' => false, 'error' => 'Course ID không hợp lệ.'));
        }
        $users = $moodle->getEnrolledUsers($courseId);
        $rows = array();
        foreach ($users as $user) {
            if (is_array($user)) {
                $rows[] = mtpc_moodle_user($user);
            }
        }
        mtpc_moodle_response(200, array('ok' => true, 'courseid' => $courseId, 'users' => $rows, 'total' => count($rows)));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'course-contents') {
        $courseId = isset($_GET['courseid']) ? (int)$_GET['courseid'] : 0;
        if ($courseId <= 0) {
            mtpc_moodle_response(422, array('ok' => false, 'error' => 'Course ID không hợp lệ.'));
        }
        $contents = $moodle->getCourseContents($courseId);
        mtpc_moodle_response(200, array('ok' => true, 'courseid' => $courseId, 'sections' => is_array($contents) ? $contents : array()));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'assignments') {
        $courseId = isset($_GET['courseid']) ? (int)$_GET['courseid'] : 0;
        if ($courseId <= 0) {
            mtpc_moodle_response(422, array('ok' => false, 'error' => 'Course ID không hợp lệ.'));
        }
        $courses = $moodle->getAssignments(array($courseId));
        $assignments = isset($courses[0]['assignments']) && is_array($courses[0]['assignments']) ? $courses[0]['assignments'] : array();
        mtpc_moodle_response(200, array('ok' => true, 'courseid' => $courseId, 'assignments' => $assignments));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'forums') {
        $courseId = isset($_GET['courseid']) ? (int)$_GET['courseid'] : 0;
        if ($courseId <= 0) mtpc_moodle_response(422, array('ok' => false, 'error' => 'Course ID không hợp lệ.'));
        $forums = mtpc_moodle_forums($moodle->getForumsByCourses(array($courseId)));
        mtpc_moodle_response(200, array('ok' => true, 'courseid' => $courseId, 'forums' => $forums, 'total' => count($forums)));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'assignment-submissions') {
        $assignmentId = isset($_GET['assignmentid']) ? (int)$_GET['assignmentid'] : 0;
        if ($assignmentId <= 0) mtpc_moodle_response(422, array('ok' => false, 'error' => 'Assignment ID không hợp lệ.'));
        $submissions = $moodle->getSubmissions($assignmentId);
        mtpc_moodle_response(200, array('ok' => true, 'assignmentid' => $assignmentId, 'submissions' => is_array($submissions) ? $submissions : array(), 'total' => is_array($submissions) ? count($submissions) : 0));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'assignment-grades') {
        $assignmentId = isset($_GET['assignmentid']) ? (int)$_GET['assignmentid'] : 0;
        if ($assignmentId <= 0) mtpc_moodle_response(422, array('ok' => false, 'error' => 'Assignment ID không hợp lệ.'));
        $grades = $moodle->getAssignmentGrades(array($assignmentId));
        mtpc_moodle_response(200, array('ok' => true, 'assignmentid' => $assignmentId, 'grades' => is_array($grades) ? $grades : array()));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'groups') {
        $courseId = isset($_GET['courseid']) ? (int)$_GET['courseid'] : 0;
        if ($courseId <= 0) mtpc_moodle_response(422, array('ok' => false, 'error' => 'Course ID không hợp lệ.'));
        $groups = $moodle->getCourseGroups($courseId);
        mtpc_moodle_response(200, array('ok' => true, 'courseid' => $courseId, 'groups' => is_array($groups) ? $groups : array(), 'total' => is_array($groups) ? count($groups) : 0));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'calendar-events') {
        $courseId = isset($_GET['courseid']) ? (int)$_GET['courseid'] : 0;
        $events = $courseId > 0 ? $moodle->getCalendarEvents(array($courseId), false) : $moodle->getCalendarEvents(array(), true);
        mtpc_moodle_response(200, array('ok' => true, 'events' => is_array($events) ? $events : array()));
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        mtpc_moodle_response(405, array('ok' => false, 'error' => 'Thao tác Moodle không được hỗ trợ.'));
    }

    $body = mtpc_moodle_body();
    if ($action === 'post-lecture') {
        $courseId = isset($body['courseid']) ? (int)$body['courseid'] : 0;
        $sectionNum = isset($body['sectionnum']) ? max(0, (int)$body['sectionnum']) : 0;
        $type = isset($body['type']) ? strtolower(trim((string)$body['type'])) : 'page';
        $name = mtpc_moodle_text(isset($body['name']) ? $body['name'] : '', 254);
        $content = mtpc_moodle_text(isset($body['content']) ? $body['content'] : '', 1000000);
        $url = mtpc_moodle_text(isset($body['url']) ? $body['url'] : '', 2000);
        $contentFormat = isset($body['contentformat']) ? (int)$body['contentformat'] : 1;
        if ($courseId <= 0 || $name === '') mtpc_moodle_response(422, array('ok' => false, 'error' => 'Cần Course ID và tiêu đề bài giảng.'));
        if (!in_array($type, array('page', 'url'), true)) mtpc_moodle_response(422, array('ok' => false, 'error' => 'Loại bài giảng chỉ hỗ trợ page hoặc url.'));
        if ($type === 'page' && $content === '') mtpc_moodle_response(422, array('ok' => false, 'error' => 'Bài giảng dạng page cần có nội dung.'));
        if ($type === 'url' && $url === '') mtpc_moodle_response(422, array('ok' => false, 'error' => 'Bài giảng dạng URL cần có liên kết.'));
        $result = $moodle->createLecture($courseId, $sectionNum, $type, $name, $content, $contentFormat, $url);
        mtpc_audit('moodle.lecture.create', 'moodle_course', $courseId, null, array('type' => $type, 'name' => $name, 'sectionnum' => $sectionNum));
        mtpc_moodle_response(201, array('ok' => true, 'message' => 'Đã đăng bài giảng vào nội dung khoá học Moodle.', 'courseid' => $courseId, 'lecture' => $result));
    }

    if ($action === 'post-lecture-file') {
        $courseId = isset($body['courseid']) ? (int)$body['courseid'] : 0;
        $sectionNum = isset($body['sectionnum']) ? max(0, (int)$body['sectionnum']) : 0;
        $name = mtpc_moodle_text(isset($body['name']) ? $body['name'] : '', 254);
        $filename = mtpc_moodle_text(isset($body['filename']) ? $body['filename'] : '', 180);
        $mimetype = mtpc_moodle_text(isset($body['mimetype']) ? $body['mimetype'] : 'application/octet-stream', 120);
        $encoded = isset($body['filecontent']) ? trim((string)$body['filecontent']) : '';
        if ($courseId <= 0 || $name === '' || $filename === '' || $encoded === '') {
            mtpc_moodle_response(422, array('ok' => false, 'error' => 'Cần Course ID, tên bài giảng và file.'));
        }
        if (preg_match('/[\\\/]/', $filename) || !preg_match('/\.[a-z0-9]{1,8}$/i', $filename)) {
            mtpc_moodle_response(422, array('ok' => false, 'error' => 'Tên file bài giảng không hợp lệ.'));
        }
        $allowed = array(
            'pdf' => 'application/pdf', 'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain', 'md' => 'text/markdown', 'html' => 'text/html',
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        );
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!isset($allowed[$extension])) {
            mtpc_moodle_response(422, array('ok' => false, 'error' => 'Chỉ hỗ trợ PDF, Word, PowerPoint, TXT/Markdown/HTML và ảnh JPG/PNG.'));
        }
        $decoded = base64_decode($encoded, true);
        if ($decoded === false || $decoded === '') {
            mtpc_moodle_response(422, array('ok' => false, 'error' => 'Nội dung file không hợp lệ.'));
        }
        if (strlen($decoded) > 20 * 1024 * 1024) {
            mtpc_moodle_response(413, array('ok' => false, 'error' => 'File bài giảng tối đa 20 MB.'));
        }
        if (!isset($allowed[$extension])) {
            mtpc_moodle_response(422, array('ok' => false, 'error' => 'Định dạng file chưa được hỗ trợ.'));
        }
        $mime = isset($allowed[$extension]) ? $allowed[$extension] : $mimetype;
        $result = $moodle->createFileLecture($courseId, $sectionNum, $name, $filename, $mime, $decoded);
        mtpc_audit('moodle.lecture.file.create', 'moodle_course', $courseId, null, array('name' => $name, 'filename' => $filename, 'sectionnum' => $sectionNum, 'bytes' => strlen($decoded)));
        mtpc_moodle_response(201, array('ok' => true, 'message' => 'Đã đăng file bài giảng vào Moodle.', 'courseid' => $courseId, 'lecture' => $result));
    }

    if ($action === 'post-announcement') {
        $courseId = isset($body['courseid']) ? (int)$body['courseid'] : 0;
        $forumId = isset($body['forumid']) ? (int)$body['forumid'] : 0;
        $subject = mtpc_moodle_text(isset($body['subject']) ? $body['subject'] : '', 254);
        $message = mtpc_moodle_text(isset($body['message']) ? $body['message'] : '', 12000);
        if ($courseId <= 0 || $subject === '' || $message === '') mtpc_moodle_response(422, array('ok' => false, 'error' => 'Cần Course ID, tiêu đề và nội dung bài đăng.'));
        $forum = $forumId > 0 ? array('id' => $forumId, 'name' => 'Forum #' . $forumId) : mtpc_moodle_find_announcement_forum($moodle, $courseId);
        if (!$forum || empty($forum['id'])) mtpc_moodle_response(422, array('ok' => false, 'error' => 'Không tìm thấy diễn đàn thông báo của khoá học. Hãy truyền Forum ID hoặc tạo forum Announcements trong Moodle.'));
        $result = $moodle->addForumDiscussion((int)$forum['id'], $subject, $message);
        mtpc_audit('moodle.announcement.create', 'moodle_course', $courseId, null, array('forumid' => (int)$forum['id'], 'subject' => $subject));
        mtpc_moodle_response(201, array('ok' => true, 'message' => 'Đã đăng thông báo lên Moodle.', 'courseid' => $courseId, 'forum' => $forum, 'result' => $result));
    }

    if ($action === 'save-grade') {
        $assignmentId = isset($body['assignmentid']) ? (int)$body['assignmentid'] : 0;
        $userId = isset($body['userid']) ? (int)$body['userid'] : 0;
        $grade = isset($body['grade']) ? (float)$body['grade'] : -1;
        $feedback = mtpc_moodle_text(isset($body['feedback']) ? $body['feedback'] : '', 4000);
        $workflow = mtpc_moodle_text(isset($body['workflowstate']) ? $body['workflowstate'] : '', 40);
        if ($assignmentId <= 0 || $userId <= 0 || $grade < 0) mtpc_moodle_response(422, array('ok' => false, 'error' => 'Cần Assignment ID, User ID và điểm hợp lệ.'));
        $result = $moodle->saveGrade($assignmentId, $userId, $grade, $feedback, $workflow);
        mtpc_audit('moodle.assignment.grade', 'moodle_assignment', $assignmentId, null, array('userid' => $userId, 'grade' => $grade));
        mtpc_moodle_response(200, array('ok' => true, 'message' => 'Đã lưu điểm và nhận xét.', 'result' => $result));
    }

    if ($action === 'create-group') {
        $courseId = isset($body['courseid']) ? (int)$body['courseid'] : 0;
        $name = mtpc_moodle_text(isset($body['name']) ? $body['name'] : '', 254);
        if ($courseId <= 0 || $name === '') mtpc_moodle_response(422, array('ok' => false, 'error' => 'Cần Course ID và tên nhóm.'));
        $group = array('courseid' => $courseId, 'name' => $name, 'description' => mtpc_moodle_text(isset($body['description']) ? $body['description'] : '', 4000));
        if (isset($body['enrolmentkey'])) $group['enrolmentkey'] = mtpc_moodle_text($body['enrolmentkey'], 254);
        $result = $moodle->createGroups(array($group));
        mtpc_audit('moodle.group.create', 'moodle_course', $courseId, null, array('name' => $name));
        mtpc_moodle_response(201, array('ok' => true, 'message' => 'Đã tạo nhóm Moodle.', 'result' => $result));
    }

    if ($action === 'add-group-member') {
        $groupId = isset($body['groupid']) ? (int)$body['groupid'] : 0;
        $userId = isset($body['userid']) ? (int)$body['userid'] : 0;
        if ($groupId <= 0 || $userId <= 0) mtpc_moodle_response(422, array('ok' => false, 'error' => 'Cần Group ID và User ID hợp lệ.'));
        $result = $moodle->addGroupMembers(array(array('groupid' => $groupId, 'userid' => $userId)));
        mtpc_audit('moodle.group.member.add', 'moodle_group', $groupId, null, array('userid' => $userId));
        mtpc_moodle_response(200, array('ok' => true, 'message' => 'Đã thêm tài khoản vào nhóm Moodle.', 'result' => $result));
    }

    if ($action === 'create-calendar-event') {
        $courseId = isset($body['courseid']) ? (int)$body['courseid'] : 0;
        $name = mtpc_moodle_text(isset($body['name']) ? $body['name'] : '', 254);
        $timeStart = isset($body['timestart']) ? (int)$body['timestart'] : 0;
        if ($courseId <= 0 || $name === '' || $timeStart <= 0) mtpc_moodle_response(422, array('ok' => false, 'error' => 'Cần Course ID, tên sự kiện và thời gian bắt đầu dạng Unix timestamp.'));
        $event = array('name' => $name, 'description' => mtpc_moodle_text(isset($body['description']) ? $body['description'] : '', 4000), 'eventtype' => 'course', 'courseid' => $courseId, 'timestart' => $timeStart, 'timeduration' => isset($body['timeduration']) ? max(0, (int)$body['timeduration']) : 0, 'visible' => 1);
        $result = $moodle->createCalendarEvents(array($event));
        mtpc_audit('moodle.calendar.create', 'moodle_course', $courseId, null, $event);
        mtpc_moodle_response(201, array('ok' => true, 'message' => 'Đã tạo lịch trên Moodle.', 'result' => $result));
    }

    if ($action === 'send-message') {
        $userIds = isset($body['userids']) && is_array($body['userids']) ? $body['userids'] : (isset($body['userid']) ? array($body['userid']) : array());
        $text = mtpc_moodle_text(isset($body['text']) ? $body['text'] : '', 4000);
        $messages = array();
        foreach ($userIds as $userId) if ((int)$userId > 0) $messages[] = array('touserid' => (int)$userId, 'text' => $text, 'textformat' => 0);
        if (!$messages || $text === '') mtpc_moodle_response(422, array('ok' => false, 'error' => 'Cần ít nhất một User ID và nội dung tin nhắn.'));
        $result = $moodle->sendMessages($messages);
        mtpc_audit('moodle.message.send', 'moodle_user', implode(',', $userIds), null, array('count' => count($messages)));
        mtpc_moodle_response(200, array('ok' => true, 'message' => 'Đã gửi thông báo Moodle.', 'count' => count($messages), 'result' => $result));
    }

    if ($action === 'create-course') {
        $fullname = mtpc_moodle_text(isset($body['fullname']) ? $body['fullname'] : '', 254);
        $shortname = mtpc_moodle_text(isset($body['shortname']) ? $body['shortname'] : '', 100);
        $categoryId = isset($body['categoryid']) ? (int)$body['categoryid'] : 1;
        if ($fullname === '' || $shortname === '' || $categoryId <= 0) {
            mtpc_moodle_response(422, array('ok' => false, 'error' => 'Cần tên khoá học, shortname và category ID hợp lệ.'));
        }
        $created = $moodle->createCourses(array(array('fullname' => $fullname, 'shortname' => $shortname, 'categoryid' => $categoryId, 'summary' => mtpc_moodle_text(isset($body['summary']) ? $body['summary'] : '', 4000), 'visible' => isset($body['visible']) ? (int)(bool)$body['visible'] : 1)));
        mtpc_audit('moodle.course.create', 'moodle_course', $shortname, null, array('fullname' => $fullname, 'shortname' => $shortname, 'categoryid' => $categoryId));
        mtpc_moodle_response(201, array('ok' => true, 'message' => 'Đã tạo khoá học trên Moodle.', 'courses' => $created));
    }

    if ($action === 'update-course') {
        $id = isset($body['id']) ? (int)$body['id'] : 0;
        $fullname = mtpc_moodle_text(isset($body['fullname']) ? $body['fullname'] : '', 254);
        if ($id <= 0 || $fullname === '') {
            mtpc_moodle_response(422, array('ok' => false, 'error' => 'Cần Course ID và tên khoá học.'));
        }
        $course = array('id' => $id, 'fullname' => $fullname);
        foreach (array('shortname', 'idnumber', 'summary', 'startdate', 'enddate', 'visible', 'categoryid') as $key) {
            if (array_key_exists($key, $body)) {
                $course[$key] = $key === 'summary' ? mtpc_moodle_text($body[$key], 4000) : $body[$key];
            }
        }
        $result = $moodle->updateCourses(array($course));
        mtpc_audit('moodle.course.update', 'moodle_course', $id, null, $course);
        mtpc_moodle_response(200, array('ok' => true, 'message' => 'Đã cập nhật khoá học trên Moodle.', 'result' => $result));
    }

    if ($action === 'delete-course') {
        $id = isset($body['id']) ? (int)$body['id'] : 0;
        if ($id <= 0 || !isset($body['confirm']) || $body['confirm'] !== 'DELETE') {
            mtpc_moodle_response(422, array('ok' => false, 'error' => 'Xoá khoá học cần Course ID và confirm=DELETE.'));
        }
        $result = $moodle->deleteCourses(array($id));
        mtpc_audit('moodle.course.delete', 'moodle_course', $id, null, array('deleted' => true));
        mtpc_moodle_response(200, array('ok' => true, 'message' => 'Đã xoá khoá học trên Moodle.', 'result' => $result));
    }

    if ($action === 'enrol-user' || $action === 'unenrol-user') {
        $userId = isset($body['userid']) ? (int)$body['userid'] : 0;
        $courseId = isset($body['courseid']) ? (int)$body['courseid'] : 0;
        $roleId = isset($body['roleid']) ? (int)$body['roleid'] : 5;
        if ($userId <= 0 || $courseId <= 0 || $roleId <= 0) {
            mtpc_moodle_response(422, array('ok' => false, 'error' => 'Cần User ID, Course ID và Role ID hợp lệ.'));
        }
        $enrolment = array('userid' => $userId, 'courseid' => $courseId, 'roleid' => $roleId);
        $result = $action === 'enrol-user' ? $moodle->enrolUsers(array($enrolment)) : $moodle->unenrolUsers(array($enrolment));
        mtpc_audit('moodle.' . $action, 'moodle_enrolment', $userId . ':' . $courseId, null, $enrolment);
        mtpc_moodle_response(200, array('ok' => true, 'message' => $action === 'enrol-user' ? 'Đã ghi danh tài khoản vào khoá học.' : 'Đã huỷ ghi danh tài khoản.', 'result' => $result));
    }

    if ($action === 'create-user') {
        $username = mtpc_moodle_text(isset($body['username']) ? $body['username'] : '', 100);
        $password = (string)(isset($body['password']) ? $body['password'] : '');
        $firstname = mtpc_moodle_text(isset($body['firstname']) ? $body['firstname'] : '', 100);
        $lastname = mtpc_moodle_text(isset($body['lastname']) ? $body['lastname'] : '', 100);
        $email = mtpc_moodle_text(isset($body['email']) ? $body['email'] : '', 254);
        if ($username === '' || strlen($password) < 8 || $firstname === '' || $lastname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            mtpc_moodle_response(422, array('ok' => false, 'error' => 'Cần username, mật khẩu từ 8 ký tự, họ, tên và email hợp lệ.'));
        }
        $user = array('username' => $username, 'password' => $password, 'firstname' => $firstname, 'lastname' => $lastname, 'email' => $email, 'auth' => 'manual');
        $created = $moodle->createUsers(array($user));
        mtpc_audit('moodle.user.create', 'moodle_user', $username, null, array('username' => $username, 'firstname' => $firstname, 'lastname' => $lastname, 'email' => $email));
        mtpc_moodle_response(201, array('ok' => true, 'message' => 'Đã tạo tài khoản Moodle.', 'users' => $created));
    }

    if ($action === 'update-user') {
        $id = isset($body['id']) ? (int)$body['id'] : 0;
        if ($id <= 0) {
            mtpc_moodle_response(422, array('ok' => false, 'error' => 'User ID không hợp lệ.'));
        }
        $user = array('id' => $id);
        foreach (array('username', 'firstname', 'lastname', 'email', 'idnumber', 'city', 'country', 'description') as $key) {
            if (array_key_exists($key, $body)) {
                $user[$key] = mtpc_moodle_text($body[$key], 1000);
            }
        }
        if (isset($body['password']) && strlen((string)$body['password']) >= 8) {
            $user['password'] = (string)$body['password'];
        }
        $result = $moodle->updateUsers(array($user));
        mtpc_audit('moodle.user.update', 'moodle_user', $id, null, array_diff_key($user, array('password' => true)));
        mtpc_moodle_response(200, array('ok' => true, 'message' => 'Đã cập nhật tài khoản Moodle.', 'result' => $result));
    }

    if ($action === 'delete-user') {
        $id = isset($body['id']) ? (int)$body['id'] : 0;
        if ($id <= 0 || !isset($body['confirm']) || $body['confirm'] !== 'DELETE') {
            mtpc_moodle_response(422, array('ok' => false, 'error' => 'Xoá tài khoản cần User ID và confirm=DELETE.'));
        }
        $result = $moodle->deleteUsers(array($id));
        mtpc_audit('moodle.user.delete', 'moodle_user', $id, null, array('deleted' => true));
        mtpc_moodle_response(200, array('ok' => true, 'message' => 'Đã xoá tài khoản Moodle.', 'result' => $result));
    }

    mtpc_moodle_response(400, array('ok' => false, 'error' => 'Action Moodle không hợp lệ.'));
} catch (Exception $e) {
    mtpc_moodle_response(502, array('ok' => false, 'error' => 'Moodle không xử lý được yêu cầu.', 'detail' => mtpc_moodle_text($e->getMessage(), 500)));
}
