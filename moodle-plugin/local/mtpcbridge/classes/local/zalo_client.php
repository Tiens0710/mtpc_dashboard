<?php
namespace local_mtpcbridge\local;

defined('MOODLE_INTERNAL') || die();

class zalo_client {
    private static $config = null;
    private static $tokenstate = '/home/mtpc/private/mtpc-zalo-oa/token-state.json';

    public static function find_student($user) {
        $path = '/home/mtpc/private/db-config.php';
        if (!is_file($path)) throw new \RuntimeException('Missing private database configuration.');
        $values = (function($file) {
            require $file;
            return array(
                'host' => isset($MTPC_DB_HOST) ? $MTPC_DB_HOST : '',
                'name' => isset($MTPC_DB_NAME) ? $MTPC_DB_NAME : '',
                'user' => isset($MTPC_DB_USER) ? $MTPC_DB_USER : '',
                'pass' => isset($MTPC_DB_PASS) ? $MTPC_DB_PASS : '',
            );
        })($path);
        foreach ($values as $value) if ($value === '') throw new \RuntimeException('Incomplete private database configuration.');
        $pdo = new \PDO('mysql:host=' . $values['host'] . ';dbname=' . $values['name'] . ';charset=utf8mb4', $values['user'], $values['pass'], array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC));

        $student = false;
        if (trim((string)$user->idnumber) !== '') {
            $query = $pdo->prepare('SELECT id,student_code,full_name,zalo_user_id FROM students WHERE student_code=:code LIMIT 1');
            $query->execute(array(':code' => trim((string)$user->idnumber)));
            $student = $query->fetch();
        }
        if (!$student && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            $query = $pdo->prepare('SELECT id,student_code,full_name,zalo_user_id FROM students WHERE LOWER(email)=:email LIMIT 1');
            $query->execute(array(':email' => strtolower(trim((string)$user->email))));
            $student = $query->fetch();
        }
        return $student ?: null;
    }

    public static function notification_text($course, $notification) {
        $subject = trim(strip_tags((string)$notification->subject));
        $body = trim(strip_tags((string)(!empty($notification->smallmessage) ? $notification->smallmessage : $notification->fullmessage)));
        $body = html_entity_decode(preg_replace('/\s+/u', ' ', $body), ENT_QUOTES, 'UTF-8');
        if ($subject !== '' && stripos($body, $subject) === 0) $body = trim(substr($body, strlen($subject)));
        $parts = array('📚 ' . format_string($course->fullname));
        if ($subject !== '') $parts[] = $subject;
        if ($body !== '') $parts[] = $body;
        if (!empty($notification->contexturl)) $parts[] = 'Xem trên Moodle: ' . clean_param($notification->contexturl, PARAM_URL);
        $text = implode("\n", $parts);
        return \core_text::strlen($text) > 1900 ? \core_text::substr($text, 0, 1897) . '…' : $text;
    }

    public static function send($userid, $message) {
        $config = self::configuration();
        $attempt = self::send_once($config, $userid, $message);
        if (self::token_failed($attempt)) {
            $config = self::refresh_token($config);
            $attempt = self::send_once($config, $userid, $message);
        }
        $response = $attempt['response'];
        if ($attempt['raw'] === false || $attempt['status'] < 200 || $attempt['status'] >= 300) throw new \RuntimeException('Zalo OA HTTP ' . $attempt['status'] . ': ' . $attempt['error']);
        if (!is_array($response)) throw new \RuntimeException('Zalo OA returned invalid JSON.');
        $code = isset($response['error']) ? (int)$response['error'] : (isset($response['error_code']) ? (int)$response['error_code'] : 0);
        if ($code !== 0) throw new \RuntimeException('Zalo OA error ' . $code . ': ' . (isset($response['message']) ? $response['message'] : 'Unknown error'));
    }

    public static function log_sent($student, $message, $queue) {
        $dir = '/home/mtpc/private/mtpc-zalo-oa';
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) return;
        $row = array(
            'id' => 'zalo-' . gmdate('YmdHis') . '-' . substr(sha1(uniqid('', true)), 0, 10),
            'direction' => 'outbound', 'event_name' => 'moodle_course_notification',
            'user_id' => (string)$student['zalo_user_id'], 'user_name' => (string)$student['full_name'],
            'student_id' => (int)$student['id'], 'student_code' => (string)$student['student_code'],
            'moodle_notification_id' => (int)$queue->notificationid, 'moodle_course_id' => (int)$queue->courseid,
            'text' => $message, 'received_at' => gmdate('c'), 'read' => true,
        );
        @file_put_contents($dir . '/messages.jsonl', json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
    }

    private static function configuration() {
        if (self::$config !== null) return self::$config;
        $path = '/home/mtpc/private/zalo-oa-config.php';
        if (!is_file($path)) throw new \RuntimeException('Missing Zalo OA configuration.');
        $config = (function($file) {
            require $file;
            return array(
                'access_token' => isset($MTPC_ZALO_OA_ACCESS_TOKEN) ? trim((string)$MTPC_ZALO_OA_ACCESS_TOKEN) : '',
                'refresh_token' => isset($MTPC_ZALO_OA_REFRESH_TOKEN) ? trim((string)$MTPC_ZALO_OA_REFRESH_TOKEN) : '',
                'app_id' => isset($MTPC_ZALO_OA_APP_ID) ? trim((string)$MTPC_ZALO_OA_APP_ID) : '',
                'secret_key' => isset($MTPC_ZALO_OA_SECRET_KEY) ? trim((string)$MTPC_ZALO_OA_SECRET_KEY) : '',
                'send_url' => isset($MTPC_ZALO_OA_SEND_URL) && trim((string)$MTPC_ZALO_OA_SEND_URL) !== '' ? trim((string)$MTPC_ZALO_OA_SEND_URL) : 'https://openapi.zalo.me/v3.0/oa/message/cs',
                'token_url' => isset($MTPC_ZALO_OA_TOKEN_URL) && trim((string)$MTPC_ZALO_OA_TOKEN_URL) !== '' ? trim((string)$MTPC_ZALO_OA_TOKEN_URL) : 'https://oauth.zaloapp.com/v4/oa/access_token',
            );
        })($path);
        $state = is_file(self::$tokenstate) ? json_decode((string)file_get_contents(self::$tokenstate), true) : array();
        if (is_array($state)) {
            if (!empty($state['access_token'])) $config['access_token'] = trim((string)$state['access_token']);
            if (!empty($state['refresh_token'])) $config['refresh_token'] = trim((string)$state['refresh_token']);
            if (!empty($state['expires_at'])) $config['expires_at'] = (int)$state['expires_at'];
        }
        if (empty($config['access_token']) || (!empty($config['expires_at']) && $config['expires_at'] <= time())) $config = self::refresh_token($config);
        self::$config = $config;
        return $config;
    }

    private static function send_once($config, $userid, $message) {
        $curl = curl_init($config['send_url']);
        curl_setopt_array($curl, array(CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>array('Content-Type: application/json','access_token: '.$config['access_token']),CURLOPT_POSTFIELDS=>json_encode(array('recipient'=>array('user_id'=>$userid),'message'=>array('text'=>$message)),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)));
        $raw = curl_exec($curl); $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE); $error = curl_error($curl); curl_close($curl);
        return array('raw'=>$raw,'status'=>$status,'error'=>$error,'response'=>json_decode((string)$raw,true));
    }

    private static function token_failed($attempt) {
        if ((int)$attempt['status'] === 401) return true;
        $response = is_array($attempt['response']) ? $attempt['response'] : array();
        $message = strtolower((string)(isset($response['message']) ? $response['message'] : ''));
        return strpos($message, 'access token') !== false || strpos($message, 'expired') !== false || strpos($message, 'invalid token') !== false;
    }

    private static function refresh_token($config) {
        foreach (array('refresh_token','app_id','secret_key') as $key) if (empty($config[$key])) throw new \RuntimeException('Zalo token expired and ' . $key . ' is missing.');
        $lock = fopen('/home/mtpc/private/mtpc-zalo-oa-token-refresh.lock', 'c');
        if ($lock) flock($lock, LOCK_EX);
        try {
            $state = is_file(self::$tokenstate) ? json_decode((string)file_get_contents(self::$tokenstate), true) : array();
            if (is_array($state) && !empty($state['access_token']) && !empty($state['expires_at']) && (int)$state['expires_at'] > time() + 60) {
                $config['access_token'] = $state['access_token']; $config['refresh_token'] = !empty($state['refresh_token']) ? $state['refresh_token'] : $config['refresh_token']; $config['expires_at'] = (int)$state['expires_at'];
                return $config;
            }
            $curl = curl_init($config['token_url']);
            curl_setopt_array($curl,array(CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>array('Content-Type: application/x-www-form-urlencoded','secret_key: '.$config['secret_key']),CURLOPT_POSTFIELDS=>http_build_query(array('grant_type'=>'refresh_token','refresh_token'=>$config['refresh_token'],'app_id'=>$config['app_id']),'','&')));
            $raw=curl_exec($curl); $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE); $error=curl_error($curl); curl_close($curl); $response=json_decode((string)$raw,true);
            if ($raw===false || $status<200 || $status>=300 || empty($response['access_token'])) throw new \RuntimeException('Cannot refresh Zalo token: '.($error!==''?$error:'HTTP '.$status));
            $state=array('access_token'=>trim((string)$response['access_token']),'refresh_token'=>!empty($response['refresh_token'])?trim((string)$response['refresh_token']):$config['refresh_token'],'expires_at'=>time()+max(60,(int)(isset($response['expires_in'])?$response['expires_in']:3600))-60,'updated_at'=>gmdate('c'));
            if (@file_put_contents(self::$tokenstate,json_encode($state,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n",LOCK_EX)===false) throw new \RuntimeException('Cannot save rotated Zalo token.');
            return array_merge($config,$state);
        } finally {
            if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
        }
    }
}
