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
        'training' => array('students.read', 'students.write', 'academic.read', 'academic.write', 'attendance.read', 'attendance.write', 'finance.read', 'audit.read', 'groups.read', 'groups.write', 'email.read', 'zalo.send', 'zalo.broadcast'),
        'teacher' => array('students.read', 'academic.read', 'attendance.read', 'attendance.write', 'groups.read')
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
    $prompt = 'Bạn là bộ phân tích lệnh cho trợ lý quản trị Trường Trung cấp Miền Tây. Người gửi đã được xác thực qua Zalo User ID và có vai trò ' . $role . '. Hãy hiểu câu tiếng Việt và chỉ trả về một JSON hợp lệ, không markdown, theo schema: {"intent":"...","student_identifier":"","query":"","new_status":"","new_class_name":"","group_identifier":"","group_message":"","group_name":"","group_description":"","recipient_user_id":"","recipient_name":"","private_message":"","broadcast_query":"","broadcast_class_name":"","broadcast_status":"","broadcast_message":"","email_date_mode":"today","email_query":"","email_sender":"","email_subject":"","email_unread_only":false,"email_uid":""}. Intent hợp lệ: students_summary (tổng quan số lượng sinh viên), student_search (tìm nhiều sinh viên theo tên/mã/lớp), student_profile (xem một hồ sơ theo mã hoặc ID), attendance_alerts (cảnh báo điểm danh), finance_summary (tổng quan học phí/công nợ), email_briefing (liệt kê và tóm tắt email theo ngày), email_search (tìm email theo nội dung/người gửi/tiêu đề), email_read (đọc chi tiết email khi có UID rõ ràng), student_status_update (đổi trạng thái sinh viên), student_class_update (đổi lớp sinh viên), zalo_private_send (gửi ngay một tin nhắn riêng qua OA đến Zalo User ID), zalo_student_broadcast (soạn thông báo riêng đến từng học viên theo mã/tên/lớp/trạng thái), groups_list (liệt kê nhóm GMF đang quản lý), group_info (xem thông tin nhóm), group_members (xem thành viên nhóm), group_conversation (xem tin nhắn nhóm), group_send (gửi tin vào nhóm), group_update (đổi tên hoặc mô tả nhóm), unknown. Với email_briefing/email_search, email_date_mode chỉ được là today, yesterday, recent hoặc date; email_query/email_sender/email_subject lấy đúng điều kiện người dùng nêu; email_unread_only chỉ bật khi người dùng nói chưa đọc; email_uid chỉ lấy số UID nếu người dùng nêu rõ. Không tự bịa UID. Các thao tác gửi, trả lời, xóa hoặc thay đổi email chưa được hỗ trợ qua Zalo. Với recipient_user_id lấy đúng Zalo User ID số do người dùng cung cấp, với recipient_name lấy tên ghi chú nếu có, với private_message lấy nguyên nội dung cần gửi. Với broadcast_query/broadcast_class_name/broadcast_status lấy đúng điều kiện lọc; với broadcast_message lấy nguyên nội dung thông báo. Với group_identifier lấy group_id hoặc tên nhóm đã được nêu; với group_message lấy nguyên nội dung cần gửi; với group_name/group_description chỉ lấy giá trị mới. Với student_status_update chỉ dùng trạng thái Đang học, Bảo lưu, Đã tốt nghiệp hoặc Thôi học. Với student_class_update lấy tên lớp mới. Không tự bịa mã sinh viên, group_id hoặc recipient_user_id; nếu thiếu tham số để thực hiện thì vẫn chọn intent phù hợp và để chuỗi rỗng. Không thực hiện thao tác, không trả lời giải thích. Câu người dùng: ' . mtpc_zalo_admin_text($question, 2000);
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
    $intent['group_identifier'] = isset($intent['group_identifier']) ? mtpc_zalo_admin_text($intent['group_identifier'], 180) : '';
    $intent['group_message'] = isset($intent['group_message']) ? mtpc_zalo_admin_text($intent['group_message'], 2000) : '';
    $intent['group_name'] = isset($intent['group_name']) ? mtpc_zalo_admin_text($intent['group_name'], 100) : '';
    $intent['group_description'] = isset($intent['group_description']) ? mtpc_zalo_admin_text($intent['group_description'], 500) : '';
    $intent['recipient_user_id'] = isset($intent['recipient_user_id']) ? mtpc_zalo_admin_text($intent['recipient_user_id'], 160) : '';
    $intent['recipient_name'] = isset($intent['recipient_name']) ? mtpc_zalo_admin_text($intent['recipient_name'], 180) : '';
    $intent['private_message'] = isset($intent['private_message']) ? mtpc_zalo_admin_text($intent['private_message'], 2000) : '';
    $intent['broadcast_query'] = isset($intent['broadcast_query']) ? mtpc_zalo_admin_text($intent['broadcast_query'], 160) : '';
    $intent['broadcast_class_name'] = isset($intent['broadcast_class_name']) ? mtpc_zalo_admin_text($intent['broadcast_class_name'], 100) : '';
    $intent['broadcast_status'] = isset($intent['broadcast_status']) ? mtpc_zalo_admin_status_label(mtpc_zalo_admin_text($intent['broadcast_status'], 50)) : '';
    $intent['broadcast_message'] = isset($intent['broadcast_message']) ? mtpc_zalo_admin_text($intent['broadcast_message'], 2000) : '';
    $intent['email_date_mode'] = isset($intent['email_date_mode']) && in_array($intent['email_date_mode'], array('today', 'yesterday', 'recent', 'date'), true) ? $intent['email_date_mode'] : 'today';
    $intent['email_query'] = isset($intent['email_query']) ? mtpc_zalo_admin_text($intent['email_query'], 180) : '';
    $intent['email_sender'] = isset($intent['email_sender']) ? mtpc_zalo_admin_text($intent['email_sender'], 180) : '';
    $intent['email_subject'] = isset($intent['email_subject']) ? mtpc_zalo_admin_text($intent['email_subject'], 180) : '';
    $intent['email_unread_only'] = !empty($intent['email_unread_only']);
    $intent['email_uid'] = isset($intent['email_uid']) ? mtpc_zalo_admin_text($intent['email_uid'], 30) : '';
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

/* Read-only email access for authenticated Zalo operators. This intentionally
 * returns a short digest instead of forwarding an entire mailbox message. */
function mtpc_zalo_admin_email_decode_header($value) {
    $parts = function_exists('imap_mime_header_decode') ? @imap_mime_header_decode((string)$value) : false;
    if (!is_array($parts)) return trim((string)$value);
    $result = '';
    foreach ($parts as $part) {
        $charset = isset($part->charset) ? strtoupper((string)$part->charset) : 'DEFAULT';
        $text = isset($part->text) ? (string)$part->text : '';
        if ($charset !== 'DEFAULT' && $charset !== 'UTF-8' && function_exists('iconv')) {
            $converted = @iconv($charset, 'UTF-8//IGNORE', $text);
            if ($converted !== false) $text = $converted;
        }
        $result .= $text;
    }
    return trim($result);
}

function mtpc_zalo_admin_email_clean_preview($value, $limit) {
    $value = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', (string)$value);
    $value = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $value);
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8');
    $value = trim(preg_replace('/\s+/', ' ', $value));
    return mtpc_zalo_admin_text($value, $limit);
}

function mtpc_zalo_admin_email_part_list($structure, $prefix, &$plain, &$html) {
    if (isset($structure->parts) && is_array($structure->parts)) {
        foreach ($structure->parts as $index => $part) {
            $partPrefix = $prefix === '' ? (string)($index + 1) : $prefix . '.' . ($index + 1);
            mtpc_zalo_admin_email_part_list($part, $partPrefix, $plain, $html);
        }
        return;
    }
    if (!isset($structure->type) || (int)$structure->type !== 0) return;
    $subtype = isset($structure->subtype) ? strtoupper((string)$structure->subtype) : 'PLAIN';
    $charset = 'UTF-8';
    $parameters = array();
    if (isset($structure->parameters) && is_array($structure->parameters)) $parameters[] = $structure->parameters;
    if (isset($structure->dparameters) && is_array($structure->dparameters)) $parameters[] = $structure->dparameters;
    foreach ($parameters as $rows) foreach ($rows as $parameter) {
        if (isset($parameter->attribute, $parameter->value) && strtoupper((string)$parameter->attribute) === 'CHARSET') $charset = (string)$parameter->value;
    }
    $part = array('part' => $prefix, 'encoding' => isset($structure->encoding) ? (int)$structure->encoding : 0, 'charset' => $charset);
    if ($subtype === 'PLAIN') $plain[] = $part;
    if ($subtype === 'HTML') $html[] = $part;
}

function mtpc_zalo_admin_email_body($imap, $uid) {
    $structure = @imap_fetchstructure($imap, $uid, FT_UID);
    if (!$structure) return '';
    $plain = array(); $html = array();
    mtpc_zalo_admin_email_part_list($structure, '', $plain, $html);
    $parts = count($plain) ? $plain : $html;
    foreach ($parts as $part) {
        $raw = $part['part'] === '' ? @imap_body($imap, $uid, FT_UID | FT_PEEK) : @imap_fetchbody($imap, $uid, $part['part'], FT_UID | FT_PEEK);
        if ($raw === false || $raw === '') continue;
        if ((int)$part['encoding'] === 3) $raw = base64_decode($raw);
        elseif ((int)$part['encoding'] === 4) $raw = quoted_printable_decode($raw);
        if ($part['charset'] && strtoupper($part['charset']) !== 'UTF-8' && strtoupper($part['charset']) !== 'US-ASCII' && function_exists('iconv')) {
            $converted = @iconv($part['charset'], 'UTF-8//IGNORE', $raw);
            if ($converted !== false) $raw = $converted;
        }
        return mtpc_zalo_admin_email_clean_preview($raw, 420);
    }
    return '';
}

function mtpc_zalo_admin_email_range($mode) {
    $zone = new DateTimeZone('Asia/Ho_Chi_Minh');
    $today = new DateTime('today', $zone);
    if ($mode === 'yesterday') { $from = clone $today; $from->modify('-1 day'); $to = clone $today; return array($from, $to, 'hôm qua'); }
    if ($mode === 'recent') { $from = clone $today; $from->modify('-6 days'); $to = clone $today; $to->modify('+1 day'); return array($from, $to, '7 ngày gần đây'); }
    $to = clone $today; $to->modify('+1 day');
    return array($today, $to, 'hôm nay');
}

function mtpc_zalo_admin_email_digest($operator, $intent) {
    if (!mtpc_zalo_admin_permission(isset($operator['role']) ? $operator['role'] : '', 'email.read')) return 'Vai trò Zalo hiện tại không được xem hộp thư.';
    if (!function_exists('imap_open')) return 'PHP IMAP chưa được bật nên em chưa thể đọc hộp thư.';
    $configPath = '/home/mtpc/private/email-config.php';
    if (!is_file($configPath)) return 'Chưa cấu hình hộp thư tại /home/mtpc/private/email-config.php.';
    require $configPath;
    $host = isset($MTPC_EMAIL_IMAP_HOST) ? trim((string)$MTPC_EMAIL_IMAP_HOST) : '';
    $port = isset($MTPC_EMAIL_IMAP_PORT) ? (int)$MTPC_EMAIL_IMAP_PORT : 993;
    $encryption = isset($MTPC_EMAIL_IMAP_ENCRYPTION) ? strtolower(trim((string)$MTPC_EMAIL_IMAP_ENCRYPTION)) : 'ssl';
    $folder = isset($MTPC_EMAIL_FOLDER) ? trim((string)$MTPC_EMAIL_FOLDER) : 'INBOX';
    $user = isset($MTPC_EMAIL_USERNAME) ? trim((string)$MTPC_EMAIL_USERNAME) : '';
    $password = isset($MTPC_EMAIL_PASSWORD) ? (string)$MTPC_EMAIL_PASSWORD : '';
    if (stripos($user, '@gmail.com') !== false) $password = preg_replace('/\s+/', '', $password);
    if ($host === '' || $user === '' || $password === '') return 'Cấu hình IMAP còn thiếu nên em chưa thể đọc hộp thư.';
    if (!preg_match('/^[a-z0-9.-]+$/i', $host) || $port < 1 || $port > 65535 || !in_array($encryption, array('ssl', 'tls', 'none'), true)) return 'Cấu hình IMAP không hợp lệ.';
    $validateCert = isset($MTPC_EMAIL_VALIDATE_CERT) ? (bool)$MTPC_EMAIL_VALIDATE_CERT : true;
    $flags = '/imap' . ($encryption === 'ssl' ? '/ssl' : '') . ($encryption === 'tls' ? '/tls' : '') . ($validateCert ? '' : '/novalidate-cert');
    $imap = @imap_open('{' . $host . ':' . $port . $flags . '}' . $folder, $user, $password, OP_READONLY, 1);
    if (!$imap) return 'Không kết nối được hộp thư. Hãy kiểm tra IMAP Gmail và mật khẩu ứng dụng.';
    $requestedUid = trim(isset($intent['email_uid']) ? (string)$intent['email_uid'] : '');
    if ($requestedUid !== '') {
        if (!preg_match('/^\d+$/', $requestedUid)) { imap_close($imap); return 'UID email không hợp lệ.'; }
        $overview = @imap_fetch_overview($imap, $requestedUid, FT_UID);
        if (!is_array($overview) || !isset($overview[0])) { imap_close($imap); return 'Không tìm thấy email có UID ' . $requestedUid . '.'; }
        $row = $overview[0];
        $from = mtpc_zalo_admin_email_decode_header(isset($row->from) ? $row->from : '');
        $title = mtpc_zalo_admin_email_decode_header(isset($row->subject) && trim((string)$row->subject) !== '' ? $row->subject : '(Không có tiêu đề)');
        $body = mtpc_zalo_admin_email_body($imap, $requestedUid);
        imap_close($imap);
        return mtpc_zalo_admin_text('📧 ' . $title . "\nTừ: " . $from . "\n\n" . ($body !== '' ? $body : '(Email không có nội dung văn bản đọc được.)'), 1900);
    }
    $mode = isset($intent['email_date_mode']) ? (string)$intent['email_date_mode'] : 'today';
    $range = mtpc_zalo_admin_email_range(in_array($mode, array('today', 'yesterday', 'recent'), true) ? $mode : 'today');
    $criteria = 'SINCE "' . $range[0]->format('d-M-Y') . '" BEFORE "' . $range[1]->format('d-M-Y') . '"' . (!empty($intent['email_unread_only']) ? ' UNSEEN' : '');
    $uids = @imap_search($imap, $criteria, SE_UID, 'UTF-8');
    if (!is_array($uids)) $uids = array();
    rsort($uids, SORT_NUMERIC);
    $query = trim(isset($intent['email_query']) ? (string)$intent['email_query'] : '');
    $sender = trim(isset($intent['email_sender']) ? (string)$intent['email_sender'] : '');
    $subject = trim(isset($intent['email_subject']) ? (string)$intent['email_subject'] : '');
    $rows = array();
    foreach ($uids as $uid) {
        $overview = @imap_fetch_overview($imap, (string)$uid, FT_UID);
        if (!is_array($overview) || !isset($overview[0])) continue;
        $row = $overview[0];
        $from = mtpc_zalo_admin_email_decode_header(isset($row->from) ? $row->from : '');
        $title = mtpc_zalo_admin_email_decode_header(isset($row->subject) && trim((string)$row->subject) !== '' ? $row->subject : '(Không có tiêu đề)');
        if ($sender !== '' && stripos($from, $sender) === false) continue;
        if ($subject !== '' && stripos($title, $subject) === false) continue;
        $preview = mtpc_zalo_admin_email_body($imap, (string)$uid);
        if ($query !== '' && stripos($from . ' ' . $title . ' ' . $preview, $query) === false) continue;
        $timestamp = isset($row->udate) ? (int)$row->udate : (isset($row->date) ? (int)strtotime($row->date) : 0);
        $rows[] = array('uid' => (string)$uid, 'from' => $from, 'subject' => $title, 'preview' => $preview, 'unread' => empty($row->seen), 'timestamp' => $timestamp);
        if (count($rows) >= 8) break;
    }
    imap_close($imap);
    if (!$rows) return '📭 Không có email ' . $range[2] . ($query !== '' ? ' phù hợp với “' . $query . '”' : '') . '.';
    $lines = array('📬 Email ' . $range[2] . ': ' . count($rows) . ' thư');
    foreach ($rows as $index => $row) {
        $time = $row['timestamp'] ? date('H:i', $row['timestamp']) : '--:--';
        $status = $row['unread'] ? ' · chưa đọc' : '';
        $line = ($index + 1) . '. [' . $time . '] ' . mtpc_zalo_admin_text($row['from'], 90) . $status . "\n   " . mtpc_zalo_admin_text($row['subject'], 150) . ' · UID ' . $row['uid'];
        if ($row['preview'] !== '') $line .= "\n   " . $row['preview'];
        $lines[] = $line;
    }
    return mtpc_zalo_admin_text(implode("\n", $lines), 1900);
}

function mtpc_zalo_admin_find_group($groupsPath, $identifier) {
    $identifier = trim((string)$identifier);
    if ($identifier === '') return null;
    $normalized = mtpc_zalo_admin_normalize($identifier);
    foreach (mtpc_zalo_group_read($groupsPath) as $row) {
        if (!is_array($row)) continue;
        $groupId = isset($row['group_id']) ? trim((string)$row['group_id']) : '';
        $name = isset($row['name']) ? trim((string)$row['name']) : (isset($row['group_name']) ? trim((string)$row['group_name']) : '');
        if ($groupId === $identifier || ($normalized !== '' && $normalized === mtpc_zalo_admin_normalize($name))) return $row;
    }
    if (preg_match('/^[A-Za-z0-9._:-]{4,180}$/', $identifier)) return array('group_id' => $identifier, 'name' => $identifier);
    return null;
}

function mtpc_zalo_admin_group_list($groupsPath, $operator) {
    if (!mtpc_zalo_admin_permission($operator['role'], 'groups.read')) return 'Vai trò Zalo hiện tại không được xem nhóm GMF.';
    $rows = mtpc_zalo_group_read($groupsPath);
    if (!$rows) return 'Chưa có nhóm GMF nào trong danh sách quản lý trên Admin.';
    $lines = array();
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $name = isset($row['name']) && $row['name'] !== '' ? $row['name'] : (isset($row['group_name']) && $row['group_name'] !== '' ? $row['group_name'] : 'Nhóm GMF');
        $status = isset($row['status']) ? $row['status'] : 'chưa đồng bộ';
        $members = isset($row['total_member']) ? ', ' . (int)$row['total_member'] . ' thành viên' : '';
        $lines[] = '• ' . $name . ' · ID ' . (isset($row['group_id']) ? $row['group_id'] : '—') . ' · ' . $status . $members;
    }
    return 'Danh sách nhóm GMF đang quản lý:\n' . implode("\n", $lines);
}

function mtpc_zalo_admin_group_info($config, $groupsPath, $operator, $identifier) {
    if (!mtpc_zalo_admin_permission($operator['role'], 'groups.read')) return 'Vai trò Zalo hiện tại không được xem thông tin nhóm.';
    $group = mtpc_zalo_admin_find_group($groupsPath, $identifier);
    if (!$group) return 'Không tìm thấy nhóm GMF. Hãy dùng group_id hoặc tên nhóm đã kết nối trên Admin.';
    $id = (string)$group['group_id'];
    try {
        $response = mtpc_zalo_group_api($config, 'GET', 'getgroup', null, array('group_id' => $id));
        $info = mtpc_zalo_group_info($response);
        if (empty($info['group_id'])) $info['group_id'] = $id;
        mtpc_zalo_group_save($groupsPath, $info, array());
        $name = isset($info['name']) && $info['name'] !== '' ? $info['name'] : (isset($group['name']) ? $group['name'] : $id);
        return 'Nhóm ' . $name . ': ID ' . $id . ', trạng thái ' . (isset($info['status']) ? $info['status'] : 'chưa rõ') . ', ' . (isset($info['total_member']) ? (int)$info['total_member'] : 'chưa rõ') . ' thành viên.' . (isset($info['group_link']) && $info['group_link'] !== '' ? ' Link: ' . $info['group_link'] : '');
    } catch (Exception $error) {
        return 'Không thể đọc thông tin nhóm: ' . $error->getMessage();
    }
}

function mtpc_zalo_admin_group_members($config, $groupsPath, $operator, $identifier) {
    if (!mtpc_zalo_admin_permission($operator['role'], 'groups.read')) return 'Vai trò Zalo hiện tại không được xem thành viên nhóm.';
    $group = mtpc_zalo_admin_find_group($groupsPath, $identifier);
    if (!$group) return 'Không tìm thấy nhóm GMF.';
    try {
        $response = mtpc_zalo_group_api($config, 'GET', 'listmember', null, array('group_id' => $group['group_id'], 'offset' => 0, 'count' => 50));
        $data = isset($response['data']) && is_array($response['data']) ? $response['data'] : array();
        $members = isset($data['members']) && is_array($data['members']) ? $data['members'] : array();
        if (!$members) return 'Nhóm chưa có dữ liệu thành viên.';
        $lines = array();
        foreach ($members as $member) if (is_array($member)) $lines[] = '• ' . (isset($member['name']) ? $member['name'] : 'Chưa có tên') . ' · ' . (isset($member['user_id']) ? $member['user_id'] : (isset($member['oa_id']) ? 'OA' : '—'));
        return 'Thành viên nhóm ' . (isset($group['name']) ? $group['name'] : $group['group_id']) . ':\n' . implode("\n", $lines);
    } catch (Exception $error) {
        return 'Không thể đọc thành viên nhóm: ' . $error->getMessage();
    }
}

function mtpc_zalo_admin_group_conversation($config, $groupsPath, $operator, $identifier) {
    if (!mtpc_zalo_admin_permission($operator['role'], 'groups.read')) return 'Vai trò Zalo hiện tại không được xem tin nhắn nhóm.';
    $group = mtpc_zalo_admin_find_group($groupsPath, $identifier);
    if (!$group) return 'Không tìm thấy nhóm GMF.';
    try {
        $response = mtpc_zalo_group_api($config, 'GET', 'conversation', null, array('group_id' => $group['group_id'], 'offset' => 0, 'count' => 20));
        $rows = isset($response['data']) && is_array($response['data']) ? $response['data'] : array();
        if (isset($rows['messages']) && is_array($rows['messages'])) $rows = $rows['messages'];
        if (!$rows) return 'Nhóm chưa có tin nhắn gần đây.';
        $lines = array();
        foreach ($rows as $message) if (is_array($message)) {
            $sender = isset($message['from_display_name']) ? $message['from_display_name'] : 'Người dùng';
            $content = isset($message['message']) && $message['message'] !== '' ? $message['message'] : '[' . (isset($message['type']) ? $message['type'] : 'tin nhắn') . ']';
            $lines[] = '• ' . $sender . ': ' . $content;
        }
        return '20 tin nhắn gần đây của nhóm ' . (isset($group['name']) ? $group['name'] : $group['group_id']) . ':\n' . implode("\n", array_reverse($lines));
    } catch (Exception $error) {
        return 'Không thể đọc tin nhắn nhóm: ' . $error->getMessage();
    }
}

function mtpc_zalo_admin_send_private($config, $messagesPath, $operator, $intent) {
    if (!mtpc_zalo_admin_permission($operator['role'], 'zalo.send')) return 'Vai trò Zalo hiện tại không được gửi tin nhắn riêng qua OA.';
    $userId = trim(isset($intent['recipient_user_id']) ? (string)$intent['recipient_user_id'] : '');
    $message = trim(isset($intent['private_message']) ? (string)$intent['private_message'] : '');
    if ($userId === '' || !preg_match('/^[0-9]{6,160}$/', $userId)) return 'Cần đúng Zalo User ID dạng số của người nhận. Có thể lấy ID trong mục Tin nhắn Zalo OA trên Admin.';
    if ($message === '') return 'Cần có nội dung tin nhắn muốn gửi.';
    try {
        mtpc_zalo_send($config, $userId, $message);
        $name = trim(isset($intent['recipient_name']) ? (string)$intent['recipient_name'] : '');
        mtpc_zalo_append($messagesPath, array('id' => mtpc_zalo_id(), 'direction' => 'outbound', 'event_name' => 'zalo_operator_send_private', 'user_id' => $userId, 'user_name' => $name, 'text' => $message, 'received_at' => gmdate('c'), 'read' => true));
        return 'Đã gửi tin nhắn riêng qua Zalo OA đến ' . ($name !== '' ? $name . ' (' . $userId . ')' : $userId) . '.';
    } catch (Exception $error) {
        return 'Không gửi được tin nhắn riêng: ' . $error->getMessage();
    }
}
function mtpc_zalo_admin_broadcast_pending_summary($pdo, $operator, $intent) {
    if (!mtpc_zalo_admin_permission($operator['role'], 'zalo.broadcast')) return 'Vai trò Zalo hiện tại không được gửi thông báo hàng loạt.';
    $query = trim(isset($intent['broadcast_query']) ? (string)$intent['broadcast_query'] : '');
    $className = trim(isset($intent['broadcast_class_name']) ? (string)$intent['broadcast_class_name'] : '');
    $status = trim(isset($intent['broadcast_status']) ? (string)$intent['broadcast_status'] : '');
    $message = trim(isset($intent['broadcast_message']) ? (string)$intent['broadcast_message'] : '');
    if ($query === '' && $className === '' && $status === '') return 'Anh cần nêu nhóm nhận, ví dụ “thông báo lớp CNTT-K26” hoặc mã/tên học viên.';
    if ($message === '') return 'Anh cần nêu nội dung thông báo muốn gửi.';
    $where = array('1=1'); $params = array();
    if ($query !== '') { $where[] = '(student_code LIKE :q OR full_name LIKE :q OR class_name LIKE :q)'; $params[':q'] = '%' . $query . '%'; }
    if ($className !== '') { $where[] = 'class_name=:class_name'; $params[':class_name'] = $className; }
    if ($status !== '') { $where[] = 'status=:status'; $params[':status'] = $status; }
    try { $statement = $pdo->prepare('SELECT id,student_code,full_name,class_name,zalo_user_id FROM students WHERE ' . implode(' AND ', $where) . ' ORDER BY full_name ASC,id ASC LIMIT 100'); $statement->execute($params); $rows = $statement->fetchAll(); }
    catch (PDOException $error) { return 'Database chưa có cột zalo_user_id. Hãy chạy migration thêm liên kết Zalo cho học viên.'; }
    if (!$rows) return 'Không tìm thấy học viên phù hợp.';
    $intent['broadcast_student_ids'] = array(); $linked = 0; $names = array();
    foreach ($rows as $row) { $intent['broadcast_student_ids'][] = (int)$row['id']; if (trim((string)$row['zalo_user_id']) !== '') $linked++; if (count($names) < 5) $names[] = $row['student_code'] . ' · ' . $row['full_name']; }
    $intent['summary'] = 'Gửi thông báo riêng cho ' . $linked . '/' . count($rows) . ' học viên đã liên kết Zalo' . ($className !== '' ? ' lớp ' . $className : '') . ': “' . $message . '”.';
    $intent['broadcast_linked'] = $linked; $intent['broadcast_total'] = count($rows); $intent['broadcast_sample'] = implode('; ', $names);
    return $intent;
}
function mtpc_zalo_admin_execute_broadcast($pdo, $operator, $intent, $config, $messagesPath) {
    if (!mtpc_zalo_admin_permission($operator['role'], 'zalo.broadcast')) return 'Vai trò Zalo hiện tại không được gửi thông báo hàng loạt.';
    $ids = isset($intent['broadcast_student_ids']) && is_array($intent['broadcast_student_ids']) ? $intent['broadcast_student_ids'] : array();
    $ids = array_values(array_filter(array_map('intval', $ids), function ($id) { return $id > 0; }));
    if (!$ids) return 'Danh sách học viên cần gửi đã rỗng.';
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $statement = $pdo->prepare('SELECT id,student_code,full_name,class_name,program_name,cohort,status,zalo_user_id FROM students WHERE id IN (' . $marks . ')');
    $statement->execute($ids); $rows = $statement->fetchAll(); $sent = 0; $skipped = 0; $failed = 0;
    $template = trim((string)$intent['broadcast_message']);
    foreach ($rows as $row) {
        $userId = trim((string)$row['zalo_user_id']);
        if ($userId === '' || !preg_match('/^[0-9]{6,160}$/', $userId)) { $skipped++; continue; }
        $text = strtr($template, array('{{id}}'=>(string)$row['id'],'{{student_id}}'=>(string)$row['id'],'{{student_code}}'=>(string)$row['student_code'],'{{full_name}}'=>(string)$row['full_name'],'{{class_name}}'=>(string)$row['class_name'],'{{program_name}}'=>(string)$row['program_name'],'{{cohort}}'=>(string)$row['cohort'],'{{status}}'=>(string)$row['status']));
        try { mtpc_zalo_send($config, $userId, $text); $sent++; mtpc_zalo_append($messagesPath, array('id'=>mtpc_zalo_id(),'direction'=>'outbound','event_name'=>'zalo_operator_student_notification','user_id'=>$userId,'user_name'=>$row['full_name'],'student_id'=>(int)$row['id'],'student_code'=>$row['student_code'],'text'=>$text,'received_at'=>gmdate('c'),'read'=>true)); } catch (Exception $error) { $failed++; }
        usleep(120000);
    }
    return 'Đã gửi ' . $sent . '/' . count($rows) . ' thông báo riêng qua Zalo OA' . ($skipped ? '; bỏ qua ' . $skipped . ' học viên chưa gắn Zalo User ID' : '') . ($failed ? '; thất bại ' . $failed . ' tin' : '') . '.';
}

function mtpc_zalo_admin_group_pending_summary($groupsPath, $operator, $intent) {
    if (!mtpc_zalo_admin_permission($operator['role'], 'groups.write')) return 'Vai trò Zalo hiện tại chỉ được xem nhóm, không được thay đổi hoặc gửi tin.';
    $group = mtpc_zalo_admin_find_group($groupsPath, isset($intent['group_identifier']) ? $intent['group_identifier'] : '');
    if (!$group) return 'Không tìm thấy nhóm GMF. Hãy dùng đúng group_id hoặc tên nhóm đã kết nối trên Admin.';
    $intent['group_id'] = (string)$group['group_id'];
    $intent['group_label'] = isset($group['name']) && $group['name'] !== '' ? $group['name'] : $intent['group_id'];
    if ($intent['intent'] === 'group_send') {
        if (!isset($intent['group_message']) || trim($intent['group_message']) === '') return 'Anh cần nêu nội dung tin nhắn muốn gửi vào nhóm.';
        $intent['summary'] = 'Gửi vào nhóm “' . $intent['group_label'] . '”: “' . $intent['group_message'] . '”.';
    } elseif ($intent['intent'] === 'group_update') {
        if ((!isset($intent['group_name']) || trim($intent['group_name']) === '') && (!isset($intent['group_description']) || trim($intent['group_description']) === '')) return 'Anh cần nêu tên mới hoặc mô tả mới của nhóm.';
        $changes = array();
        if (!empty($intent['group_name'])) $changes[] = 'tên thành “' . $intent['group_name'] . '”';
        if (!empty($intent['group_description'])) $changes[] = 'mô tả thành “' . $intent['group_description'] . '”';
        $intent['summary'] = 'Cập nhật nhóm “' . $intent['group_label'] . '”: ' . implode(', ', $changes) . '.';
    }
    return $intent;
}

function mtpc_zalo_admin_execute_group_pending($config, $groupsPath, $pdo, $operator, $intent) {
    $groupId = isset($intent['group_id']) ? (string)$intent['group_id'] : '';
    if ($intent['intent'] === 'group_send') {
        $response = mtpc_zalo_group_api($config, 'POST', 'message', array('recipient' => array('group_id' => $groupId), 'message' => array('text' => $intent['group_message'])), array());
        mtpc_zalo_admin_audit($pdo, $operator, 'zalo.group_send', 'zalo_group', $groupId, null, array('text' => $intent['group_message']));
        return 'Đã gửi tin vào nhóm “' . (isset($intent['group_label']) ? $intent['group_label'] : $groupId) . '”.';
    }
    if ($intent['intent'] === 'group_update') {
        $payload = array('group_id' => $groupId); $local = array('group_id' => $groupId);
        if (!empty($intent['group_name'])) { $payload['group_name'] = $intent['group_name']; $local['name'] = $intent['group_name']; }
        if (!empty($intent['group_description'])) { $payload['group_description'] = $intent['group_description']; $local['group_description'] = $intent['group_description']; }
        mtpc_zalo_group_api($config, 'POST', 'updateinfo', $payload, array());
        mtpc_zalo_group_save($groupsPath, $local, array());
        mtpc_zalo_admin_audit($pdo, $operator, 'zalo.group_update', 'zalo_group', $groupId, null, $local);
        return 'Đã cập nhật nhóm “' . (isset($intent['group_label']) ? $intent['group_label'] : $groupId) . '”.';
    }
    throw new Exception('Thao tác nhóm Zalo chưa được hỗ trợ.');
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

function mtpc_zalo_admin_execute_pending($pdo, $operator, $intent, $config, $groupsPath, $messagesPath) {
    if ($intent['intent'] === 'group_send' || $intent['intent'] === 'group_update') return mtpc_zalo_admin_execute_group_pending($config, $groupsPath, $pdo, $operator, $intent);
    if ($intent['intent'] === 'zalo_student_broadcast') return mtpc_zalo_admin_execute_broadcast($pdo, $operator, $intent, $config, $messagesPath);
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

function mtpc_zalo_admin_handle_message($operator, $text, $pendingPath, $config, $groupsPath, $messagesPath) {
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
                $reply = mtpc_zalo_admin_execute_pending($pdo, $operator, $item['intent'], $config, $groupsPath, $messagesPath);
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
        /* Common read-mail commands are deterministic. This prevents a
         * harmless phrase such as “đọc mail hôm nay” from being mistaken for
         * an unsupported Moodle command when Gemini is uncertain. */
        $hasEmailWord = strpos($normalized, 'mail') !== false || strpos($normalized, 'email') !== false;
        $isEmailRead = preg_match('/\b(doc|xem|kiem tra|co|hom nay|hom qua|moi|chua doc|unread)\b/', $normalized) && !preg_match('/\b(gui|send|tra loi|xoa|archive|luu tru)\b/', $normalized);
        if ($hasEmailWord && $isEmailRead) {
            $intent = array('intent' => (strpos($normalized, 'tim ') !== false || strpos($normalized, 'search ') !== false) ? 'email_search' : 'email_briefing', 'email_date_mode' => strpos($normalized, 'hom qua') !== false ? 'yesterday' : (strpos($normalized, '7 ngay') !== false ? 'recent' : 'today'), 'email_query' => '', 'email_sender' => '', 'email_subject' => '', 'email_unread_only' => strpos($normalized, 'chua doc') !== false || strpos($normalized, 'unread') !== false, 'email_uid' => '');
        } else {
            $intent = mtpc_zalo_admin_generate_intent($text, $operator);
        }
        $name = $intent['intent'];
        if ($name === 'email_briefing' || $name === 'email_search') {
            return array('reply' => mtpc_zalo_admin_email_digest($operator, $intent), 'event_name' => 'zalo_email_command');
        }
        if ($name === 'email_read') {
            if (empty($intent['email_uid']) || !preg_match('/^\d+$/', $intent['email_uid'])) return array('reply' => 'Anh cần gửi UID của email cần đọc. Trước hết nhắn “đọc mail hôm nay” để em liệt kê email và UID.', 'event_name' => 'zalo_email_command');
            return array('reply' => mtpc_zalo_admin_email_digest($operator, $intent), 'event_name' => 'zalo_email_command');
        }
        $pdo = mtpc_zalo_admin_db();
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
        if ($name === 'zalo_private_send') return array('reply' => mtpc_zalo_admin_send_private($config, $messagesPath, $operator, $intent), 'event_name' => 'zalo_private_send');
        if ($name === 'zalo_student_broadcast') {
            $prepared = mtpc_zalo_admin_broadcast_pending_summary($pdo, $operator, $intent);
            if (!is_array($prepared)) return array('reply' => $prepared, 'event_name' => 'zalo_student_broadcast');
            $pending = mtpc_zalo_admin_pending_read($pendingPath);
            $pending[$userId] = array('intent' => $prepared, 'expires_at' => time() + 600);
            mtpc_zalo_admin_write_json($pendingPath, $pending);
            return array('reply' => 'Em đã chuẩn bị: ' . $prepared['summary'] . "\nMẫu người nhận: " . $prepared['broadcast_sample'] . "\nNhắn “XÁC NHẬN” để gửi ngay hoặc “HỦY” để bỏ qua. Thời hạn 10 phút.", 'event_name' => 'zalo_student_broadcast_pending');
        }
        if ($name === 'groups_list') return array('reply' => mtpc_zalo_admin_group_list($groupsPath, $operator), 'event_name' => 'zalo_group_command');
        if ($name === 'group_info') return array('reply' => mtpc_zalo_admin_group_info($config, $groupsPath, $operator, $intent['group_identifier']), 'event_name' => 'zalo_group_command');
        if ($name === 'group_members') return array('reply' => mtpc_zalo_admin_group_members($config, $groupsPath, $operator, $intent['group_identifier']), 'event_name' => 'zalo_group_command');
        if ($name === 'group_conversation') return array('reply' => mtpc_zalo_admin_group_conversation($config, $groupsPath, $operator, $intent['group_identifier']), 'event_name' => 'zalo_group_command');
        if ($name === 'group_send' || $name === 'group_update') {
            $prepared = mtpc_zalo_admin_group_pending_summary($groupsPath, $operator, $intent);
            if (!is_array($prepared)) return array('reply' => $prepared, 'event_name' => 'zalo_group_command');
            $pending = mtpc_zalo_admin_pending_read($pendingPath);
            $pending[$userId] = array('intent' => $prepared, 'expires_at' => time() + 600);
            mtpc_zalo_admin_write_json($pendingPath, $pending);
            return array('reply' => 'Em đã soạn thao tác nhóm: ' . $prepared['summary'] . "\nNhắn “XÁC NHẬN” để thực hiện hoặc “HỦY” để bỏ qua. Thời hạn xác nhận là 10 phút.", 'event_name' => 'zalo_group_command_pending');
        }
        if ($name === 'student_status_update' || $name === 'student_class_update') {
            $prepared = mtpc_zalo_admin_pending_summary($pdo, $operator, $intent);
            if (!is_array($prepared)) return array('reply' => $prepared, 'event_name' => 'zalo_admin_command');
            $pending = mtpc_zalo_admin_pending_read($pendingPath);
            $pending[$userId] = array('intent' => $prepared, 'expires_at' => time() + 600);
            mtpc_zalo_admin_write_json($pendingPath, $pending);
            return array('reply' => 'Em đã soạn thao tác: ' . $prepared['summary'] . "\nNhắn “XÁC NHẬN” để thực hiện hoặc “HỦY” để bỏ qua. Thời hạn xác nhận là 10 phút.", 'event_name' => 'zalo_admin_command_pending');
        }
        return array('reply' => 'Em chưa nhận diện được lệnh quản trị. Anh có thể yêu cầu xem dữ liệu sinh viên, điểm danh, học phí hoặc nhóm Zalo GMF; gửi tin và đổi thông tin nhóm sẽ cần xác nhận.', 'event_name' => 'zalo_admin_command_unknown');
    } catch (Exception $error) {
        return array('reply' => 'Không thể xử lý lệnh quản trị: ' . $error->getMessage(), 'event_name' => 'zalo_admin_command_error');
    }
}
