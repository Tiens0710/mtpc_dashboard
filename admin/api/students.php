<?php
/* MTPC student management API. PHP 5.6 compatible. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

function mtpc_students_response($status, $payload) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$configPath = '/home/mtpc/private/db-config.php';
if (!is_file($configPath)) {
    mtpc_students_response(500, array('ok' => false, 'error' => 'Chưa tìm thấy file cấu hình database.'));
}

require $configPath;

$requiredConfig = array('$MTPC_DB_HOST', '$MTPC_DB_NAME', '$MTPC_DB_USER', '$MTPC_DB_PASS');
if (!isset($MTPC_DB_HOST, $MTPC_DB_NAME, $MTPC_DB_USER, $MTPC_DB_PASS)) {
    mtpc_students_response(500, array('ok' => false, 'error' => 'File cấu hình database chưa đủ thông tin.'));
}

try {
    $pdo = new PDO(
        'mysql:host=' . $MTPC_DB_HOST . ';dbname=' . $MTPC_DB_NAME . ';charset=utf8mb4',
        $MTPC_DB_USER,
        $MTPC_DB_PASS,
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
    );
} catch (Exception $error) {
    mtpc_students_response(500, array('ok' => false, 'error' => 'Không thể kết nối database.'));
}

function mtpc_students_json_body() {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    return is_array($body) ? $body : array();
}

function mtpc_students_clean($value, $maxLength) {
    $value = trim((string)$value);
    if (function_exists('mb_substr')) return mb_substr($value, 0, $maxLength, 'UTF-8');
    return substr($value, 0, $maxLength);
}

function mtpc_students_row($row) {
    return array(
        'id' => (int)$row['id'],
        'student_code' => $row['student_code'],
        'full_name' => $row['full_name'],
        'date_of_birth' => $row['date_of_birth'],
        'phone' => $row['phone'],
        'email' => $row['email'],
        'program_name' => $row['program_name'],
        'class_name' => $row['class_name'],
        'status' => $row['status'],
        'note' => $row['note'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    );
}

function mtpc_students_find_identifier($pdo, $identifier) {
    $identifier = trim((string)$identifier);
    if ($identifier === '') return null;
    if (ctype_digit($identifier)) {
        $statement = $pdo->prepare('SELECT * FROM students WHERE id = :id LIMIT 1');
        $statement->execute(array(':id' => (int)$identifier));
    } else {
        $statement = $pdo->prepare('SELECT * FROM students WHERE student_code = :student_code LIMIT 1');
        $statement->execute(array(':student_code' => $identifier));
    }
    $row = $statement->fetch();
    return $row ? mtpc_students_row($row) : null;
}

$action = isset($_GET['action']) ? (string)$_GET['action'] : 'list';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'summary') {
        $total = (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
        $statement = $pdo->query('SELECT status, COUNT(*) AS count FROM students GROUP BY status ORDER BY status');
        $byStatus = array();
        foreach ($statement as $row) $byStatus[$row['status']] = (int)$row['count'];
        mtpc_students_response(200, array('ok' => true, 'summary' => array('total' => $total, 'by_status' => $byStatus)));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get') {
        $identifier = isset($_GET['id']) ? $_GET['id'] : (isset($_GET['student_code']) ? $_GET['student_code'] : '');
        $student = mtpc_students_find_identifier($pdo, $identifier);
        if (!$student) mtpc_students_response(404, array('ok' => false, 'error' => 'Không tìm thấy sinh viên.'));
        mtpc_students_response(200, array('ok' => true, 'student' => $student));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
        $query = isset($_GET['q']) ? mtpc_students_clean($_GET['q'], 120) : '';
        $status = isset($_GET['status']) ? mtpc_students_clean($_GET['status'], 50) : '';
        $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 50;
        $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
        $where = array('1=1');
        $params = array();
        if ($query !== '') {
            $where[] = '(student_code LIKE :query OR full_name LIKE :query OR phone LIKE :query OR email LIKE :query OR program_name LIKE :query OR class_name LIKE :query)';
            $params[':query'] = '%' . $query . '%';
        }
        if ($status !== '') {
            $where[] = 'status = :status';
            $params[':status'] = $status;
        }
        $whereSql = implode(' AND ', $where);
        $countStatement = $pdo->prepare('SELECT COUNT(*) FROM students WHERE ' . $whereSql);
        $countStatement->execute($params);
        $total = (int)$countStatement->fetchColumn();
        $sql = 'SELECT * FROM students WHERE ' . $whereSql . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $students = array();
        foreach ($statement as $row) $students[] = mtpc_students_row($row);
        mtpc_students_response(200, array('ok' => true, 'students' => $students, 'total' => $total, 'limit' => $limit, 'offset' => $offset));
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        mtpc_students_response(405, array('ok' => false, 'error' => 'Phương thức không được hỗ trợ.'));
    }

    $body = mtpc_students_json_body();
    $fields = array(
        'student_code' => mtpc_students_clean(isset($body['student_code']) ? $body['student_code'] : '', 30),
        'full_name' => mtpc_students_clean(isset($body['full_name']) ? $body['full_name'] : '', 150),
        'date_of_birth' => mtpc_students_clean(isset($body['date_of_birth']) ? $body['date_of_birth'] : '', 10),
        'phone' => mtpc_students_clean(isset($body['phone']) ? $body['phone'] : '', 30),
        'email' => mtpc_students_clean(isset($body['email']) ? $body['email'] : '', 150),
        'program_name' => mtpc_students_clean(isset($body['program_name']) ? $body['program_name'] : '', 150),
        'class_name' => mtpc_students_clean(isset($body['class_name']) ? $body['class_name'] : '', 100),
        'status' => mtpc_students_clean(isset($body['status']) ? $body['status'] : 'Đang học', 50),
        'note' => mtpc_students_clean(isset($body['note']) ? $body['note'] : '', 2000)
    );

    if ($action === 'create') {
        if ($fields['student_code'] === '' || $fields['full_name'] === '') {
            mtpc_students_response(422, array('ok' => false, 'error' => 'Mã sinh viên và họ tên là bắt buộc.'));
        }
        $statement = $pdo->prepare('INSERT INTO students (student_code, full_name, date_of_birth, phone, email, program_name, class_name, status, note) VALUES (:student_code, :full_name, :date_of_birth, :phone, :email, :program_name, :class_name, :status, :note)');
        $statement->execute(array(
            ':student_code' => $fields['student_code'], ':full_name' => $fields['full_name'],
            ':date_of_birth' => $fields['date_of_birth'] !== '' ? $fields['date_of_birth'] : null,
            ':phone' => $fields['phone'], ':email' => $fields['email'], ':program_name' => $fields['program_name'],
            ':class_name' => $fields['class_name'], ':status' => $fields['status'], ':note' => $fields['note']
        ));
        $student = mtpc_students_find_identifier($pdo, $pdo->lastInsertId());
        mtpc_students_response(201, array('ok' => true, 'student' => $student, 'message' => 'Đã thêm sinh viên.'));
    }

    if ($action === 'update') {
        $identifier = isset($body['id']) ? $body['id'] : (isset($body['student_code']) ? $body['student_code'] : '');
        $current = mtpc_students_find_identifier($pdo, $identifier);
        if (!$current) mtpc_students_response(404, array('ok' => false, 'error' => 'Không tìm thấy sinh viên để cập nhật.'));
        if ($fields['student_code'] === '' || $fields['full_name'] === '') {
            mtpc_students_response(422, array('ok' => false, 'error' => 'Mã sinh viên và họ tên là bắt buộc.'));
        }
        $statement = $pdo->prepare('UPDATE students SET student_code = :student_code, full_name = :full_name, date_of_birth = :date_of_birth, phone = :phone, email = :email, program_name = :program_name, class_name = :class_name, status = :status, note = :note WHERE id = :id');
        $statement->execute(array(
            ':student_code' => $fields['student_code'], ':full_name' => $fields['full_name'],
            ':date_of_birth' => $fields['date_of_birth'] !== '' ? $fields['date_of_birth'] : null,
            ':phone' => $fields['phone'], ':email' => $fields['email'], ':program_name' => $fields['program_name'],
            ':class_name' => $fields['class_name'], ':status' => $fields['status'], ':note' => $fields['note'], ':id' => (int)$current['id']
        ));
        $student = mtpc_students_find_identifier($pdo, $current['id']);
        mtpc_students_response(200, array('ok' => true, 'student' => $student, 'message' => 'Đã cập nhật hồ sơ sinh viên.'));
    }

    mtpc_students_response(400, array('ok' => false, 'error' => 'Thao tác không hợp lệ.'));
} catch (PDOException $error) {
    if ((int)$error->errorInfo[1] === 1062) {
        mtpc_students_response(409, array('ok' => false, 'error' => 'Mã sinh viên đã tồn tại.'));
    }
    mtpc_students_response(500, array('ok' => false, 'error' => 'Database không thể xử lý yêu cầu.'));
} catch (Exception $error) {
    mtpc_students_response(500, array('ok' => false, 'error' => 'Không thể xử lý yêu cầu.'));
}
