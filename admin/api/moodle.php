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
$writeActions = array('create-course', 'update-course', 'delete-course', 'enrol-user', 'unenrol-user', 'create-user', 'update-user', 'delete-user');
mtpc_require_permission(in_array($action, $writeActions, true) ? 'moodle.write' : 'moodle.read');

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
        $required = array('core_course_get_courses', 'core_course_get_contents', 'core_user_get_users', 'core_enrol_get_enrolled_users', 'core_course_create_courses', 'enrol_manual_enrol_users');
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

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        mtpc_moodle_response(405, array('ok' => false, 'error' => 'Thao tác Moodle không được hỗ trợ.'));
    }

    $body = mtpc_moodle_body();
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
