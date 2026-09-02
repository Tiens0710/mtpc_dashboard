<?php
/**
 * MoodleClient.php
 *
 * Client thuần PHP (chỉ dùng cURL có sẵn) gọi Moodle Web Services REST API.
 * Tương thích PHP 5.6 trở lên — không dùng typed properties, scalar type hints, ??, Throwable.
 * Không cần Composer / thư viện ngoài — chạy được trên hầu hết hosting cPanel.
 *
 * Docs tham khảo: https://moodledev.io/docs/apis/subsystems/external
 */
class MoodleClient
{
    protected $endpoint;
    protected $token;

    public function __construct($baseUrl, $token)
    {
        if (empty($baseUrl) || empty($token)) {
            throw new InvalidArgumentException('MoodleClient cần baseUrl và token');
        }
        $this->endpoint = rtrim($baseUrl, '/') . '/webservice/rest/server.php';
        $this->token = $token;
    }

    /**
     * Gọi một wsfunction bất kỳ của Moodle.
     *
     * @param string $wsfunction Tên hàm, vd: "mod_assign_get_submissions"
     * @param array  $params     Tham số dạng mảng (hỗ trợ mảng lồng nhau theo chuẩn Moodle)
     * @return array             Response đã decode từ JSON
     * @throws RuntimeException  Khi HTTP lỗi hoặc Moodle trả về exception
     */
    public function call($wsfunction, $params = array())
    {
        $postFields = array_merge(
            array(
                'wstoken' => $this->token,
                'wsfunction' => $wsfunction,
                'moodlewsrestformat' => 'json',
            ),
            $this->flattenParams($params)
        );

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postFields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("Lỗi cURL khi gọi Moodle: {$curlError}");
        }
        if ($httpCode !== 200) {
            throw new RuntimeException("Moodle HTTP error {$httpCode}: {$response}");
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Không parse được JSON từ Moodle: ' . json_last_error_msg());
        }

        // Moodle trả lỗi kèm field "exception" thay vì đổi HTTP status code
        if (is_array($data) && isset($data['exception'])) {
            $msg = "Moodle API error [" . (isset($data['errorcode']) ? $data['errorcode'] : '') . "]: " . (isset($data['message']) ? $data['message'] : '');
            if (!empty($data['debuginfo'])) {
                $msg .= ' — ' . $data['debuginfo'];
            }
            throw new RuntimeException($msg);
        }

        return $data;
    }

    /**
     * Moodle yêu cầu tham số dạng mảng/nested phải truyền theo format
     * key[0][field]=value. Hàm này tự chuyển mảng PHP lồng nhau sang format đó.
     */
    protected function flattenParams($params, $prefix = '')
    {
        $result = array();
        foreach ($params as $key => $value) {
            $fullKey = $prefix === '' ? $key : "{$prefix}[{$key}]";
            if ($value === null) {
                continue;
            }
            if (is_array($value)) {
                // Fix: dùng array_merge thay vì += (union) để tránh mất key khi trùng
                $result = array_merge($result, $this->flattenParams($value, $fullKey));
            } else {
                $result[$fullKey] = $value;
            }
        }
        return $result;
    }

    // ---- Các hàm tiện ích cho ca dùng "chấm bài tự động" ----

    /** Lấy danh sách bài nộp (submissions) của một assignment */
    public function getSubmissions($assignmentId)
    {
        $data = $this->call('mod_assign_get_submissions', array(
            'assignmentids' => array($assignmentId),
        ));
        if (isset($data['assignments'][0]['submissions'])) {
            return $data['assignments'][0]['submissions'];
        }
        return array();
    }

    /** Lấy thông tin user theo id (để log/báo cáo) */
    public function getUsersByIds($userIds)
    {
        if (empty($userIds)) {
            return array();
        }
        return $this->call('core_user_get_users_by_field', array(
            'field' => 'id',
            'values' => $userIds,
        ));
    }

    /**
     * Ghi điểm + nhận xét cho một submission.
     * $grade: số điểm theo thang điểm của assignment (thường 0-100)
     * $feedback: chuỗi HTML nhận xét
     * $workflowState: trạng thái workflow. Mặc định '' (không dùng workflow).
     *   Chỉ truyền 'graded' khi assignment có bật "Marking workflow" (Site admin > Assignment settings).
     *   Nếu site không bật workflow mà truyền 'graded' sẽ bị lỗi "invalid parameter".
     *
     * Tự động fallback:
     *  - Nếu Moodle báo lỗi do workflowstate, sẽ thử lại với workflowstate = ''.
     *  - Nếu Moodle báo lỗi do plugindata (site tắt plugin Comments), sẽ thử lại không kèm plugindata hoặc chỉ gửi grade.
     */
    public function saveGrade($assignmentId, $userId, $grade, $feedback, $workflowState = '')
    {
        $attempts = array();

        // Thử 1: workflowState theo yêu cầu + có plugindata comments
        $attempts[] = array(
            'assignmentid' => $assignmentId,
            'userid' => $userId,
            'grade' => $grade,
            'attemptnumber' => -1,
            'addattempt' => 0,
            'workflowstate' => $workflowState,
            'applytoall' => 0,
            'plugindata' => array(
                'assignfeedbackcomments_editor' => array(
                    'text' => $feedback,
                    'format' => 1, // FORMAT_HTML
                ),
            ),
        );

        // Thử 2: nếu workflowState khác rỗng thì thử lại với '' (cho site không bật marking workflow)
        if ($workflowState !== '') {
            $attempts[] = array(
                'assignmentid' => $assignmentId,
                'userid' => $userId,
                'grade' => $grade,
                'attemptnumber' => -1,
                'addattempt' => 0,
                'workflowstate' => '',
                'applytoall' => 0,
                'plugindata' => array(
                    'assignfeedbackcomments_editor' => array(
                        'text' => $feedback,
                        'format' => 1,
                    ),
                ),
            );
        }

        // Thử 3: không kèm plugindata (cho site tắt plugin feedback Comments)
        $attempts[] = array(
            'assignmentid' => $assignmentId,
            'userid' => $userId,
            'grade' => $grade,
            'attemptnumber' => -1,
            'addattempt' => 0,
            'workflowstate' => '',
            'applytoall' => 0,
        );

        // Thử 4: tối giản nhất, chỉ grade (phòng trường hợp Moodle version cũ)
        $attempts[] = array(
            'assignmentid' => $assignmentId,
            'userid' => $userId,
            'grade' => $grade,
        );

        $lastException = null;
        foreach ($attempts as $idx => $params) {
            try {
                return $this->call('mod_assign_save_grade', $params);
            } catch (RuntimeException $e) {
                $lastException = $e;
                $msg = strtolower($e->getMessage());
                // Chỉ retry khi lỗi liên quan đến invalid parameter / workflowstate / plugindata
                $isRetryable = strpos($msg, 'invalid parameter') !== false
                    || strpos($msg, 'workflowstate') !== false
                    || strpos($msg, 'plugindata') !== false
                    || strpos($msg, 'assignfeedbackcomments') !== false
                    || strpos($msg, 'errorcode') !== false;
                // Nếu không phải lỗi retryable thì ném luôn
                if (!$isRetryable && $idx === 0) {
                    throw $e;
                }
                // Nếu đã thử hết các fallback mà vẫn lỗi thì ném lỗi cuối
                if ($idx === count($attempts) - 1) {
                    throw $e;
                }
                // Ngược lại thử attempt tiếp theo
                continue;
            } catch (Exception $e) {
                // PHP 5.6: catch Exception cho các lỗi khác (không có Throwable)
                $lastException = $e;
                if ($idx === count($attempts) - 1) {
                    throw $e;
                }
                continue;
            }
        }

        if ($lastException !== null) {
            throw $lastException;
        }
        throw new RuntimeException('Không thể ghi điểm vào Moodle sau nhiều lần thử');
    }

    /**
     * Trích xuất nội dung text từ submission (hỗ trợ cả onlinetext).
     * Dùng strip_tags nhưng giữ lại dòng mới và xử lý đúng tiếng Việt UTF-8.
     * Trả về chuỗi rỗng nếu không có onlinetext (có thể là nộp file).
     */
    public function extractOnlinetext($submission)
    {
        $plugins = isset($submission['plugins']) ? $submission['plugins'] : array();
        foreach ($plugins as $plugin) {
            $type = isset($plugin['type']) ? $plugin['type'] : '';
            if ($type === 'onlinetext') {
                $rawText = '';
                if (isset($plugin['editorfields'][0]['text'])) {
                    $rawText = $plugin['editorfields'][0]['text'];
                }
                // Giữ xuống dòng: thay <br>, </p>, </div> bằng \n trước khi strip_tags
                $withBreaks = preg_replace('/<br\s*\/?>/i', "\n", $rawText);
                $withBreaks = preg_replace('/<\/(p|div|h[1-6]|li|tr)>/i', "\n", $withBreaks);
                $text = trim(strip_tags($withBreaks));
                // Decode HTML entities để giữ tiếng Việt có dấu ( &amp; &lt; v.v.)
                $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                // Chuẩn hoá khoảng trắng nhưng giữ dòng
                $text = preg_replace('/[ \t]+/u', ' ', $text);
                $text = preg_replace('/\n{3,}/', "\n\n", $text);
                return trim($text);
            }
        }
        return '';
    }

    /**
     * Lấy danh sách file nộp (nếu assignment cho phép nộp file).
     * Trả về mảng các file info có 'fileurl' (cần append ?token=... để tải).
     */
    public function extractSubmissionFiles($submission)
    {
        $files = array();
        $plugins = isset($submission['plugins']) ? $submission['plugins'] : array();
        foreach ($plugins as $plugin) {
            $type = isset($plugin['type']) ? $plugin['type'] : '';
            if ($type === 'file') {
                $fileList = array();
                if (isset($plugin['fileareas'][0]['files'])) {
                    $fileList = $plugin['fileareas'][0]['files'];
                }
                foreach ($fileList as $f) {
                    if (!empty($f['fileurl'])) {
                        $files[] = $f;
                    }
                }
            }
        }
        return $files;
    }

    /**
     * Tải nội dung file từ Moodle qua fileurl + token.
     * Hỗ trợ file text thuần (txt, csv). Với PDF/DOCX sẽ trả về thông báo gợi ý.
     * Trả về null nếu không tải được.
     */
    public function downloadSubmissionFile($fileUrl, $maxBytes = 500000)
    {
        $url = $fileUrl . (strpos($fileUrl, '?') !== false ? '&' : '?') . 'token=' . urlencode($this->token);
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ));
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($data === false || $httpCode !== 200) {
            return null;
        }
        if (strlen($data) > $maxBytes) {
            $data = substr($data, 0, $maxBytes);
        }
        // Thử decode nếu là text
        if (function_exists('mb_check_encoding')) {
            if (mb_check_encoding($data, 'UTF-8')) {
                return $data;
            }
        } else {
            // fallback nếu không có mbstring
            if (preg_match('//u', $data)) {
                return $data;
            }
        }
        // Nếu không phải UTF-8 text, có thể là binary (PDF/DOCX) -> không parse được bằng pure PHP
        return null;
    }
}
