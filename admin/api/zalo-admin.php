<?php
/*
 * Privileged Zalo OA commands. This file is included by zalo-oa.php only.
 * PHP 5.6 compatible. No file in this library is inside the public web root.
 */

function mtpc_zalo_admin_read_json($path, $fallback) {
    if (!is_file($path)) return $fallback;
    $raw = @file_get_contents($path);
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : $fallback;
}

function mtpc_zalo_admin_write_json($path, $data) {
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) return false;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    return @file_put_contents($path, $json . "\n", LOCK_EX) !== false;
}

function mtpc_zalo_admin_operator_id($value) {
    return trim((string)$value);
}

function mtpc_zalo_admin_operator_list($path) {
    $rows = mtpc_zalo_admin_read_json($path, array());
    $result = array();
    foreach ($rows as $row) {
        if (!is_array($row) || mtpc_zalo_admin_operator_id(isset($row['user_id']) ? $row['user_id'] : '') === '') continue;
        $row['user_id'] = mtpc_zalo_admin_operator_id($row['user_id']);
        $row['user_name'] = isset($row['user_name']) ? trim((string)$row['user_name']) : '';
        $row['role'] = isset($row['role']) && in_array($row['role'], array('admin', 'training', 'teacher'), true) ? $row['role'] : 'teacher';
        $row['status'] = isset($row['status']) && $row['status'] === 'active' ? 'active' : 'disabled';
        $result[] = $row;
    }
    return $result;
}

function mtpc_zalo_admin_find_operator($path, $userId) {
    $userId = mtpc_zalo_admin_operator_id($userId);
    if ($userId === '') return null;
    foreach (mtpc_zalo_admin_operator_list($path) as $row) {
        if ((string)$row['user_id'] === $userId && isset($row['status']) && $row['status'] === 'active') return $row;
    }
    return null;
}

function mtpc_zalo_admin_update_operator_name($path, $userId, $userName) {
    $userId = mtpc_zalo_admin_operator_id($userId);
    $userName = trim((string)$userName);
    if ($userId === '' || $userName === '') return false;
    $rows = mtpc_zalo_admin_operator_list($path);
    foreach ($rows as $index => $row) {
        if ((string)$row['user_id'] !== $userId) continue;
        if ((string)$row['user_name'] === $userName) return true;
        $rows[$index]['user_name'] = $userName;
        $rows[$index]['updated_at'] = gmdate('c');
        return mtpc_zalo_admin_write_json($path, $rows);
    }
    return false;
}

function mtpc_zalo_admin_normalize_phone($value) {
    $digits = preg_replace('/[^0-9]+/', '', (string)$value);
    if (substr($digits, 0, 2) === '84') $digits = '0' . substr($digits, 2);
    return $digits;
}

function mtpc_zalo_admin_link_code($text) {
    $normalized = mtpc_zalo_admin_normalize($text);
    if (preg_match('/^(?:ket noi|lien ket)(?: ma)? ([0-9]{6})$/', $normalized, $matches)) return $matches[1];
    return '';
}

function mtpc_zalo_admin_create_link_request($path, $body) {
    $phone = mtpc_zalo_admin_normalize_phone(isset($body['phone']) ? $body['phone'] : '');
    if (!preg_match('/^0[0-9]{9,10}$/', $phone)) throw new Exception('Số điện thoại không hợp lệ. Hãy nhập số Việt Nam, ví dụ 0375711766.');
    $userName = mtpc_zalo_admin_text(isset($body['user_name']) ? $body['user_name'] : '', 180);
    $role = isset($body['role']) && in_array($body['role'], array('admin', 'training', 'teacher'), true) ? $body['role'] : 'teacher';
    $status = isset($body['status']) && $body['status'] === 'disabled' ? 'disabled' : 'active';
    $requests = mtpc_zalo_admin_read_json($path, array());
    $now = time(); $active = array();
    foreach ($requests as $request) if (is_array($request) && isset($request['expires_unix']) && (int)$request['expires_unix'] > $now) $active[] = $request;
    $used = array(); foreach ($active as $request) if (isset($request['code'])) $used[(string)$request['code']] = true;
    do { $code = (string)mt_rand(100000, 999999); } while (isset($used[$code]));
    $record = array('phone' => $phone, 'user_name' => $userName, 'role' => $role, 'status' => $status, 'code' => $code, 'created_at' => gmdate('c'), 'expires_at' => gmdate('c', $now + 1800), 'expires_unix' => $now + 1800);
    $active[] = $record;
    if (!mtpc_zalo_admin_write_json($path, $active)) throw new Exception('Không thể lưu yêu cầu liên kết Zalo.');
    return $record;
}

function mtpc_zalo_admin_consume_link_request($requestsPath, $operatorsPath, $userId, $eventUserName, $text) {
    $code = mtpc_zalo_admin_link_code($text);
    if ($code === '' || trim((string)$userId) === '') return null;
    $requests = mtpc_zalo_admin_read_json($requestsPath, array());
    $now = time(); $matched = null; $remaining = array();
    foreach ($requests as $request) {
        if (!is_array($request)) continue;
        $isMatch = $matched === null && isset($request['code']) && (string)$request['code'] === $code && isset($request['expires_unix']) && (int)$request['expires_unix'] >= $now;
        if ($isMatch) $matched = $request; else if (isset($request['expires_unix']) && (int)$request['expires_unix'] >= $now) $remaining[] = $request;
    }
    if ($matched === null) return null;
    $operators = mtpc_zalo_admin_operator_list($operatorsPath);
    $operator = array('user_id' => trim((string)$userId), 'phone' => $matched['phone'], 'user_name' => trim((string)$eventUserName) !== '' ? trim((string)$eventUserName) : $matched['user_name'], 'role' => $matched['role'], 'status' => $matched['status'], 'linked_at' => gmdate('c'), 'updated_at' => gmdate('c'));
    $saved = false;
    foreach ($operators as $index => $existing) {
        if ((string)$existing['user_id'] === (string)$userId || (!empty($existing['phone']) && (string)$existing['phone'] === (string)$matched['phone'])) {
            if (empty($operator['user_name']) && !empty($existing['user_name'])) $operator['user_name'] = $existing['user_name'];
            if (!isset($existing['created_at'])) $operator['created_at'] = gmdate('c'); else $operator['created_at'] = $existing['created_at'];
            $operators[$index] = array_merge($existing, $operator);
            $saved = true;
            break;
        }
    }
    if (!$saved) {
        $operator['created_at'] = gmdate('c');
        $operators[] = $operator;
    }
    if (!mtpc_zalo_admin_write_json($operatorsPath, $operators)) throw new Exception('Không thể lưu Zalo User ID sau khi liên kết.');
    mtpc_zalo_admin_write_json($requestsPath, $remaining);
    $reply = $operator['status'] === 'active' ? 'Đã liên kết thành công số điện thoại ' . $operator['phone'] . ' với tài khoản Zalo này. Từ bây giờ bạn có thể gửi lệnh quản trị trực tiếp cho OA.' : 'Đã ghi nhận tài khoản Zalo của bạn, nhưng quyền đang ở trạng thái tạm khóa. Vui lòng liên hệ quản trị viên.';
    return array('operator' => $operator, 'reply' => $reply);
}

function mtpc_zalo_admin_permission($role, $permission) {
    $map = array(
        'admin' => array('*'),
        'training' => array('students.read', 'students.write', 'academic.read', 'academic.write', 'attendance.read', 'attendance.write', 'finance.read', 'audit.read'),
        'teacher' => array('students.read', 'academic.read', 'attendance.read', 'attendance.write')
    );
    $allowed = isset($map[$role]) ? $map[$role] : array();
    return in_array('*', $allowed, true) || in_array($permission, $allowed, true);
}

function mtpc_zalo_admin_db() {
    $configPath = '/home/mtpc/private/db-config.php';
    if (!is_file($configPath)) throw new Exception('Chưa tìm thấy cấu hình database.');
    require $configPath;
    if (!isset($MTPC_DB_HOST, $MTPC_DB_NAME, $MTPC_DB_USER, $MTPC_DB_PASS)) throw new Exception('Cấu hình database chưa đủ.');
    try {
        return new PDO('mysql:host=' . $MTPC_DB_HOST . ';dbname=' . $MTPC_DB_NAME . ';charset=utf8mb4', $MTPC_DB_USER, $MTPC_DB_PASS, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC));
    } catch (Exception $error) {
        throw new Exception('Không thể kết nối database.');
    }
}

function mtpc_zalo_admin_text($value, $length) {
    $value = trim((string)$value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length);
}

function mtpc_zalo_admin_normalize($text) {
    $text = function_exists('mb_strtolower') ? mb_strtolower((string)$text, 'UTF-8') : strtolower((string)$text);
    $text = strtr($text, array('à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a','â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a','è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e','ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i','ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o','ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o','ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u','ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y','đ'=>'d'));
    return trim(preg_replace('/[^a-z0-9]+/i', ' ', $text));
}

function mtpc_zalo_admin_mask($value, $visible) {
    $value = (string)$value;
    if ($value === '') return '';
    $length = strlen($value);
    return $length <= $visible ? str_repeat('*', $length) : str_repeat('*', $length - $visible) . substr($value, -$visible);
}

function mtpc_zalo_admin_find_student($pdo, $identifier) {
    $identifier = trim((string)$identifier);
    if ($identifier === '') return null;
    if (ctype_digit($identifier)) {
        $statement = $pdo->prepare('SELECT * FROM students WHERE id=:id LIMIT 1');
        $statement->execute(array(':id' => (int)$identifier));
    } else {
        $statement = $pdo->prepare('SELECT * FROM students WHERE student_code=:code LIMIT 1');
        $statement->execute(array(':code' => $identifier));
    }
    $row = $statement->fetch();
    return $row ? $row : null;
}

function mtpc_zalo_admin_student_line($row, $role) {
    $line = (string)$row['student_code'] . ' · ' . (string)$row['full_name'] . ' · ' . (string)($row['class_name'] ? $row['class_name'] : 'chưa có lớp') . ' · ' . (string)$row['status'];
    if ($role === 'admin' || $role === 'training') {
        if (!empty($row['phone'])) $line .= ' · ' . $row['phone'];
    }
    return $line;
}

function mtpc_zalo_admin_audit($pdo, $operator, $action, $entityType, $entityId, $before, $after) {
    try {
        $statement = $pdo->prepare('INSERT INTO system_audit_logs(actor_username,actor_role,action,entity_type,entity_id,before_data,after_data,ip_address) VALUES(:u,:r,:a,:t,:i,:b,:n,:ip)');
        $actor = 'zalo:' . mtpc_zalo_admin_text(isset($operator['user_id']) ? $operator['user_id'] : 'unknown', 90);
        $statement->execute(array(
            ':u' => $actor,
            ':r' => isset($operator['role']) ? $operator['role'] : 'teacher',
            ':a' => $action,
            ':t' => $entityType,
            ':i' => (string)$entityId,
            ':b' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':n' => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'zalo-webhook'
        ));
    } catch (Exception $ignore) {
        // An audit failure must not make a successful, already committed update look failed.
    }
}

function mtpc_zalo_admin_pending_read($path) {
    $data = mtpc_zalo_admin_read_json($path, array());
    return is_array($data) ? $data : array();
}

function mtpc_zalo_admin_pending_clear($path, $userId) {
    $pending = mtpc_zalo_admin_pending_read($path);
    unset($pending[(string)$userId]);
    mtpc_zalo_admin_write_json($path, $pending);
}

function mtpc_zalo_admin_status_label($status) {
    return $status === 'Đang học' || $status === 'Bảo lưu' || $status === 'Đã tốt nghiệp' || $status === 'Thôi học' ? $status : '';
}

function mtpc_zalo_admin_generate_intent($question, $operator) {
    $apiKey = getenv('GEMINI_API_KEY');
    $privateConfig = '/home/mtpc/private/gemini-config.php';
    if (!$apiKey && is_file($privateConfig)) {
        require $privateConfig;
        $apiKey = isset($GEMINI_API_KEY) ? $GEMINI_API_KEY : '';
    }
    if (!$apiKey) throw new Exception('Chưa cấu hình GEMINI_API_KEY cho lệnh quản trị Zalo.');
    $role = isset($operator['role']) ? $operator['role'] : 'teacher';
    $prompt = 'Bạn là bộ phân tích lệnh cho trợ lý quản trị Trường Trung cấp Miền Tây. Người gửi đã được xác thực qua Zalo User ID và có vai trò ' . $role . '. Hãy hiểu câu tiếng Việt và chỉ trả về một JSON hợp lệ, không markdown, theo schema: {"intent":"...","student_identifier":"","query":"","new_status":"","new_class_name":""}. Intent hợp lệ: students_summary (tổng quan số lượng sinh viên), student_search (tìm nhiều sinh viên theo tên/mã/lớp), student_profile (xem một hồ sơ theo mã hoặc ID), attendance_alerts (cảnh báo điểm danh), finance_summary (tổng quan học phí/công nợ), student_status_update (đổi trạng thái sinh viên), student_class_update (đổi lớp sinh viên), unknown. Với student_status_update chỉ dùng trạng thái Đang học, Bảo lưu, Đã tốt nghiệp hoặc Thôi học. Với student_class_update lấy tên lớp mới. Không tự bịa mã sinh viên; nếu thiếu tham số để thực hiện thì vẫn chọn intent phù hợp và để chuỗi rỗng. Không thực hiện thao tác, không trả lời giải thích. Câu người dùng: ' . mtpc_zalo_admin_text($question, 2000);
    $payload = json_encode(array(
        'systemInstruction' => array('parts' => array(array('text' => $prompt))),
        'contents' => array(array('role' => 'user', 'parts' => array(array('text' => mtpc_zalo_admin_text($question, 2000))))),
        'generationConfig' => array('maxOutputTokens' => 300, 'responseMimeType' => 'application/json')
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $curl = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent');
    curl_setopt_array($curl, array(CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'x-goog-api-key: ' . $apiKey), CURLOPT_POSTFIELDS => $payload));
    $raw = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if ($raw === false || $status < 200 || $status >= 300) throw new Exception('Gemini không phân tích được lệnh quản trị.' . ($error !== '' ? ' ' . $error : ''));
    $response = json_decode($raw, true);
    $text = '';
    if (is_array($response) && isset($response['candidates'][0]['content']['parts'])) foreach ($response['candidates'][0]['content']['parts'] as $part) if (isset($part['text'])) $text .= $part['text'];
    $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text));
    $intent = json_decode($text, true);
    if (!is_array($intent)) throw new Exception('Gemini trả về lệnh không hợp lệ.');
    $intent['intent'] = isset($intent['intent']) ? (string)$intent['intent'] : 'unknown';
    $intent['student_identifier'] = isset($intent['student_identifier']) ? mtpc_zalo_admin_text($intent['student_identifier'], 100) : '';
    $intent['query'] = isset($intent['query']) ? mtpc_zalo_admin_text($intent['query'], 160) : '';
    $intent['new_status'] = isset($intent['new_status']) ? mtpc_zalo_admin_status_label(mtpc_zalo_admin_text($intent['new_status'], 50)) : '';
    $intent['new_class_name'] = isset($intent['new_class_name']) ? mtpc_zalo_admin_text($intent['new_class_name'], 100) : '';
    return $intent;
}

function mtpc_zalo_admin_read_summary($pdo) {
    $total = (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
    $rows = $pdo->query('SELECT status,COUNT(*) AS total FROM students GROUP BY status ORDER BY status')->fetchAll();
    $parts = array();
    foreach ($rows as $row) $parts[] = $row['status'] . ': ' . (int)$row['total'];
    return 'Hiện có ' . $total . ' sinh viên. ' . (empty($parts) ? 'Chưa có phân loại trạng thái.' : implode('; ', $parts) . '.');
}

function mtpc_zalo_admin_read_search($pdo, $operator, $query) {
    $query = trim((string)$query);
    if ($query === '') return 'Anh cần cho em tên, mã sinh viên hoặc lớp cần tìm.';
    $statement = $pdo->prepare('SELECT id,student_code,full_name,program_name,class_name,status,phone FROM students WHERE student_code LIKE :q OR full_name LIKE :q OR class_name LIKE :q ORDER BY full_name ASC LIMIT 10');
    $statement->execute(array(':q' => '%' . $query . '%'));
    $rows = $statement->fetchAll();
    if (!$rows) return 'Không tìm thấy sinh viên phù hợp với “' . $query . '”.';
    $lines = array();
    foreach ($rows as $row) $lines[] = '• ' . mtpc_zalo_admin_student_line($row, isset($operator['role']) ? $operator['role'] : 'teacher');
    return 'Em tìm thấy ' . count($rows) . ' sinh viên:\n' . implode("\n", $lines);
}

function mtpc_zalo_admin_read_profile($pdo, $operator, $identifier) {
    $row = mtpc_zalo_admin_find_student($pdo, $identifier);
    if (!$row) return 'Không tìm thấy sinh viên có mã hoặc ID “' . $identifier . '”.';
    $role = isset($operator['role']) ? $operator['role'] : 'teacher';
    $reply = 'Hồ sơ ' . $row['student_code'] . ': ' . $row['full_name'] . '. Ngành ' . ($row['program_name'] ? $row['program_name'] : 'chưa có') . ', lớp ' . ($row['class_name'] ? $row['class_name'] : 'chưa có') . ', trạng thái ' . $row['status'] . '.';
    if ($row['cohort']) $reply .= ' Khóa ' . $row['cohort'] . '.';
    if (($role === 'admin' || $role === 'training') && $row['phone']) $reply .= ' Số điện thoại: ' . $row['phone'] . '.';
    return $reply;
}

function mtpc_zalo_admin_read_attendance_alerts($pdo) {
    $sql = "SELECT s.student_code,s.full_name,s.class_name,SUM(CASE WHEN ar.status='absent' THEN 1 ELSE 0 END) AS absent_count,SUM(CASE WHEN ar.status IN('absent','late') THEN 1 ELSE 0 END) AS warning_score FROM attendance_records ar INNER JOIN attendance_sessions ats ON ats.id=ar.session_id INNER JOIN students s ON s.id=ar.student_id WHERE ats.session_date>=DATE_SUB(CURDATE(),INTERVAL 30 DAY) GROUP BY s.id,s.student_code,s.full_name,s.class_name HAVING warning_score>=3 ORDER BY warning_score DESC,absent_count DESC LIMIT 10";
    $rows = $pdo->query($sql)->fetchAll();
    if (!$rows) return '30 ngày gần đây chưa có sinh viên vượt ngưỡng cảnh báo điểm danh.';
    $lines = array();
    foreach ($rows as $row) $lines[] = '• ' . $row['student_code'] . ' · ' . $row['full_name'] . ' · vắng ' . (int)$row['absent_count'] . ', điểm cảnh báo ' . (int)$row['warning_score'];
    return 'Danh sách cảnh báo điểm danh:\n' . implode("\n", $lines);
}

function mtpc_zalo_admin_read_finance_summary($pdo) {
    $due = (float)$pdo->query('SELECT COALESCE(SUM(amount_due),0) FROM student_fees')->fetchColumn();
    $paid = (float)$pdo->query('SELECT COALESCE(SUM(amount),0) FROM fee_payments')->fetchColumn();
    $debt = max(0, $due - $paid);
    return 'Tổng phải thu: ' . number_format($due, 0, ',', '.') . ' đồng. Đã thu: ' . number_format($paid, 0, ',', '.') . ' đồng. Còn nợ khoảng: ' . number_format($debt, 0, ',', '.') . ' đồng.';
}

function mtpc_zalo_admin_pending_summary($pdo, $operator, $intent) {
    if (!isset($intent['student_identifier']) || $intent['student_identifier'] === '') return 'Anh gửi giúp em mã sinh viên hoặc ID hồ sơ cần thay đổi nhé.';
    $row = mtpc_zalo_admin_find_student($pdo, isset($intent['student_identifier']) ? $intent['student_identifier'] : '');
    if (!$row) return 'Không tìm thấy sinh viên để thay đổi.';
    $intent['student_id'] = (int)$row['id'];
    $intent['student_code'] = $row['student_code'];
    $intent['student_name'] = $row['full_name'];
    if ($intent['intent'] === 'student_status_update') {
        if (!mtpc_zalo_admin_permission(isset($operator['role']) ? $operator['role'] : '', 'students.write')) return 'Vai trò Zalo hiện tại chỉ được xem, không được đổi trạng thái sinh viên.';
        if ($intent['new_status'] === '') return 'Anh cần nêu trạng thái mới: Đang học, Bảo lưu, Đã tốt nghiệp hoặc Thôi học.';
        if ($intent['new_status'] === $row['status']) return 'Sinh viên ' . $row['student_code'] . ' đã ở trạng thái ' . $row['status'] . '.';
        $intent['summary'] = 'Đổi trạng thái ' . $row['student_code'] . ' · ' . $row['full_name'] . ' từ “' . $row['status'] . '” thành “' . $intent['new_status'] . '”.';
        return $intent;
    }
    if ($intent['intent'] === 'student_class_update') {
        if (!mtpc_zalo_admin_permission(isset($operator['role']) ? $operator['role'] : '', 'students.write')) return 'Vai trò Zalo hiện tại chỉ được xem, không được chuyển lớp.';
        if ($intent['new_class_name'] === '') return 'Anh cần nêu tên lớp mới.';
        if ($intent['new_class_name'] === $row['class_name']) return 'Sinh viên ' . $row['student_code'] . ' đã ở lớp ' . $row['class_name'] . '.';
        $intent['summary'] = 'Chuyển lớp ' . $row['student_code'] . ' · ' . $row['full_name'] . ' từ “' . ($row['class_name'] ? $row['class_name'] : 'chưa có lớp') . '” sang “' . $intent['new_class_name'] . '”.';
        return $intent;
    }
    return 'unknown';
}

function mtpc_zalo_admin_execute_pending($pdo, $operator, $intent) {
    $row = mtpc_zalo_admin_find_student($pdo, isset($intent['student_id']) ? $intent['student_id'] : '');
    if (!$row) throw new Exception('Không tìm thấy hồ sơ sinh viên nữa, thao tác đã dừng.');
    $actor = 'zalo:' . mtpc_zalo_admin_text(isset($operator['user_id']) ? $operator['user_id'] : 'unknown', 90);
    $pdo->beginTransaction();
    try {
        if ($intent['intent'] === 'student_status_update') {
            $oldStatus = (string)$row['status'];
            $statement = $pdo->prepare('UPDATE students SET status=:status WHERE id=:id');
            $statement->execute(array(':status' => $intent['new_status'], ':id' => (int)$row['id']));
            $history = $pdo->prepare('INSERT INTO student_history(student_id,action,field_name,old_value,new_value,reason,actor_username,actor_role) VALUES(:id,"update","status",:old,:new,:reason,:actor,:role)');
            $history->execute(array(':id' => (int)$row['id'], ':old' => $row['status'], ':new' => $intent['new_status'], ':reason' => 'Điều khiển trực tiếp qua Zalo OA', ':actor' => $actor, ':role' => $operator['role']));
            $row['status'] = $intent['new_status'];
            mtpc_zalo_admin_audit($pdo, $operator, 'student.status_update.zalo', 'student', $row['id'], array('status' => $oldStatus), array('status' => $row['status']));
            $pdo->commit();
            return 'Đã cập nhật thành công: ' . $row['student_code'] . ' · ' . $row['full_name'] . ' hiện ở trạng thái ' . $row['status'] . '.';
        }
        if ($intent['intent'] === 'student_class_update') {
            $oldClass = (string)$row['class_name'];
            $statement = $pdo->prepare('UPDATE students SET class_name=:class_name WHERE id=:id');
            $statement->execute(array(':class_name' => $intent['new_class_name'], ':id' => (int)$row['id']));
            $history = $pdo->prepare('INSERT INTO student_history(student_id,action,field_name,old_value,new_value,reason,actor_username,actor_role) VALUES(:id,"update","class_name",:old,:new,:reason,:actor,:role)');
            $history->execute(array(':id' => (int)$row['id'], ':old' => $oldClass, ':new' => $intent['new_class_name'], ':reason' => 'Điều khiển trực tiếp qua Zalo OA', ':actor' => $actor, ':role' => $operator['role']));
            mtpc_zalo_admin_audit($pdo, $operator, 'student.class_update.zalo', 'student', $row['id'], array('class_name' => $oldClass), array('class_name' => $intent['new_class_name']));
            $pdo->commit();
            return 'Đã cập nhật thành công: ' . $row['student_code'] . ' · ' . $row['full_name'] . ' chuyển sang lớp ' . $intent['new_class_name'] . '.';
        }
    } catch (Exception $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw new Exception('Thao tác Zalo chưa được hỗ trợ.');
}

function mtpc_zalo_admin_handle_message($operator, $text, $pendingPath) {
    $userId = isset($operator['user_id']) ? (string)$operator['user_id'] : '';
    $pending = mtpc_zalo_admin_pending_read($pendingPath);
    $normalized = mtpc_zalo_admin_normalize($text);
    if (isset($pending[$userId]) && is_array($pending[$userId])) {
        $item = $pending[$userId];
        if (!isset($item['expires_at']) || (int)$item['expires_at'] < time()) {
            unset($pending[$userId]);
            mtpc_zalo_admin_write_json($pendingPath, $pending);
        } elseif (in_array($normalized, array('xac nhan', 'xac nhan thuc hien', 'thuc hien', 'dong y', 'ok'), true)) {
            try {
                $pdo = mtpc_zalo_admin_db();
                $reply = mtpc_zalo_admin_execute_pending($pdo, $operator, $item['intent']);
                unset($pending[$userId]);
                mtpc_zalo_admin_write_json($pendingPath, $pending);
                return array('reply' => $reply, 'event_name' => 'zalo_admin_command_executed');
            } catch (Exception $error) {
                return array('reply' => 'Không thể thực hiện lệnh: ' . $error->getMessage(), 'event_name' => 'zalo_admin_command_error');
            }
        } elseif (in_array($normalized, array('huy', 'bo qua', 'cancel'), true)) {
            unset($pending[$userId]);
            mtpc_zalo_admin_write_json($pendingPath, $pending);
            return array('reply' => 'Đã hủy thao tác đang chờ.', 'event_name' => 'zalo_admin_command_cancelled');
        } else {
            return array('reply' => 'Em đang chờ xác nhận thao tác: ' . $item['intent']['summary'] . "\nNhắn “XÁC NHẬN” để thực hiện hoặc “HỦY” để bỏ qua.", 'event_name' => 'zalo_admin_command_waiting_confirmation');
        }
    }
    try {
        $intent = mtpc_zalo_admin_generate_intent($text, $operator);
        $pdo = mtpc_zalo_admin_db();
        $name = $intent['intent'];
        if ($name === 'students_summary') {
            if (!mtpc_zalo_admin_permission($operator['role'], 'students.read')) return array('reply' => 'Vai trò Zalo hiện tại không được xem dữ liệu sinh viên.', 'event_name' => 'zalo_admin_permission_denied');
            return array('reply' => mtpc_zalo_admin_read_summary($pdo), 'event_name' => 'zalo_admin_command');
        }
        if ($name === 'student_search') {
            if (!mtpc_zalo_admin_permission($operator['role'], 'students.read')) return array('reply' => 'Vai trò Zalo hiện tại không được xem dữ liệu sinh viên.', 'event_name' => 'zalo_admin_permission_denied');
            return array('reply' => mtpc_zalo_admin_read_search($pdo, $operator, $intent['query']), 'event_name' => 'zalo_admin_command');
        }
        if ($name === 'student_profile') {
            if (!mtpc_zalo_admin_permission($operator['role'], 'students.read')) return array('reply' => 'Vai trò Zalo hiện tại không được xem hồ sơ sinh viên.', 'event_name' => 'zalo_admin_permission_denied');
            if ($intent['student_identifier'] === '') return array('reply' => 'Anh gửi giúp em mã sinh viên hoặc ID hồ sơ cần xem nhé.', 'event_name' => 'zalo_admin_command');
            return array('reply' => mtpc_zalo_admin_read_profile($pdo, $operator, $intent['student_identifier']), 'event_name' => 'zalo_admin_command');
        }
        if ($name === 'attendance_alerts') {
            if (!mtpc_zalo_admin_permission($operator['role'], 'attendance.read')) return array('reply' => 'Vai trò Zalo hiện tại không được xem điểm danh.', 'event_name' => 'zalo_admin_permission_denied');
            return array('reply' => mtpc_zalo_admin_read_attendance_alerts($pdo), 'event_name' => 'zalo_admin_command');
        }
        if ($name === 'finance_summary') {
            if (!mtpc_zalo_admin_permission($operator['role'], 'finance.read')) return array('reply' => 'Vai trò Zalo hiện tại không được xem học phí và công nợ.', 'event_name' => 'zalo_admin_permission_denied');
            return array('reply' => mtpc_zalo_admin_read_finance_summary($pdo), 'event_name' => 'zalo_admin_command');
        }
        if ($name === 'student_status_update' || $name === 'student_class_update') {
            $prepared = mtpc_zalo_admin_pending_summary($pdo, $operator, $intent);
            if (!is_array($prepared)) return array('reply' => $prepared, 'event_name' => 'zalo_admin_command');
            $pending = mtpc_zalo_admin_pending_read($pendingPath);
            $pending[$userId] = array('intent' => $prepared, 'expires_at' => time() + 600);
            mtpc_zalo_admin_write_json($pendingPath, $pending);
            return array('reply' => 'Em đã soạn thao tác: ' . $prepared['summary'] . "\nNhắn “XÁC NHẬN” để thực hiện hoặc “HỦY” để bỏ qua. Thời hạn xác nhận là 10 phút.", 'event_name' => 'zalo_admin_command_pending');
        }
        return array('reply' => 'Em chưa nhận diện được lệnh quản trị. Anh có thể yêu cầu: xem tổng số sinh viên, xem hồ sơ MTPC001, tìm sinh viên, xem cảnh báo điểm danh, xem tổng quan học phí, đổi trạng thái hoặc chuyển lớp.', 'event_name' => 'zalo_admin_command_unknown');
    } catch (Exception $error) {
        return array('reply' => 'Không thể xử lý lệnh quản trị: ' . $error->getMessage(), 'event_name' => 'zalo_admin_command_error');
    }
}
