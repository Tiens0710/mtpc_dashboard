<?php
/**
 * MoodleFullClient.php
 *
 * Mở rộng MoodleClient để hỗ trợ ĐẦY ĐỦ các nhóm chức năng Moodle Web Services.
 * Tương thích PHP 5.6 trở lên — không dùng typed properties, scalar hints, ??, fn().
 * Vẫn pure PHP + cURL, không cần Composer, chạy được trên cPanel.
 *
 * Kế thừa toàn bộ logic nền từ MoodleClient (call, flattenParams, saveGrade, extractOnlinetext...)
 * và bổ sung wrapper cho ~50 hàm wsfunction thường dùng nhất.
 *
 * Cách dùng:
 *   $moodle = new MoodleFullClient($config['moodle_url'], $config['moodle_token']);
 *   $courses = $moodle->getCourses();
 *   $users = $moodle->getUsers(array('email' => 'sv@example.com'));
 *   $moodle->enrolUsers(array(array('roleid'=>5,'userid'=>123,'courseid'=>10)));
 *
 * Docs gốc: https://moodledev.io/docs/apis/subsystems/external
 * Danh sách hàm khả dụng của site bạn: $moodle->getAvailableFunctions() hoặc core_webservice_get_site_info
 */

require_once __DIR__ . '/MoodleClient.php';

class MoodleFullClient extends MoodleClient
{
    // ================================================================
    //  SITE / DISCOVERY
    // ================================================================

    /** Lấy thông tin site + danh sách hàm web service được cấp cho token */
    public function getSiteInfo()
    {
        return $this->call('core_webservice_get_site_info');
    }

    /** Lấy danh sách tên hàm khả dụng (để biết site bật những gì) */
    public function getAvailableFunctions()
    {
        $info = $this->getSiteInfo();
        $funcs = isset($info['functions']) ? $info['functions'] : array();
        $out = array();
        foreach ($funcs as $f) {
            $out[] = isset($f['name']) ? $f['name'] : '';
        }
        return $out;
    }

    /** Kiểm tra một hàm có được token này cấp quyền không */
    public function isFunctionAvailable($wsfunction)
    {
        return in_array($wsfunction, $this->getAvailableFunctions(), true);
    }

    // ================================================================
    //  COURSE
    // ================================================================

    /** Lấy tất cả khoá học (hoặc lọc theo ids nếu truyền vào) */
    public function getCourses($courseIds = array())
    {
        if (empty($courseIds)) {
            $data = $this->call('core_course_get_courses');
            // Moodle 4.x trả về mảng trực tiếp; một số version bọc trong key 'courses'
            if (isset($data['courses'])) return $data['courses'];
            return $data;
        }
        // Lấy theo ids bằng get_courses_by_field từng id rồi gộp
        $result = array();
        foreach ($courseIds as $id) {
            $data = $this->call('core_course_get_courses_by_field', array(
                'field' => 'id',
                'value' => (string)$id,
            ));
            if (!empty($data['courses'])) {
                $result = array_merge($result, $data['courses']);
            }
        }
        return $result;
    }

    /** Lấy khoá học theo field (id, shortname, idnumber, category) */
    public function getCoursesByField($field, $value)
    {
        $data = $this->call('core_course_get_courses_by_field', array(
            'field' => $field,
            'value' => $value,
        ));
        return isset($data['courses']) ? $data['courses'] : array();
    }

    /** Tìm khoá học theo shortname */
    public function getCourseByShortname($shortname)
    {
        $courses = $this->getCoursesByField('shortname', $shortname);
        return isset($courses[0]) ? $courses[0] : null;
    }

    /** Lấy nội dung (sections, modules) của một khoá học */
    public function getCourseContents($courseId)
    {
        return $this->call('core_course_get_contents', array(
            'courseid' => $courseId,
        ));
    }

    /**
     * Tạo khoá học mới.
     * @param array $courses mỗi phần tử: ['fullname','shortname','categoryid', 'summary'=>..., 'visible'=>1, ...]
     * @return array danh sách course vừa tạo (có id)
     */
    public function createCourses($courses)
    {
        $data = $this->call('core_course_create_courses', array(
            'courses' => array_values($courses),
        ));
        return $data;
    }

    /** Cập nhật khoá học (cần id trong mỗi phần tử) */
    public function updateCourses($courses)
    {
        return $this->call('core_course_update_courses', array(
            'courses' => array_values($courses),
        ));
    }

    /** Xoá khoá học */
    public function deleteCourses($courseIds)
    {
        return $this->call('core_course_delete_courses', array(
            'courseids' => array_values($courseIds),
        ));
    }

    /** Lấy danh sách khoá học mà user đã enrol */
    public function getEnrolledCourses($userId)
    {
        return $this->call('core_enrol_get_users_courses', array(
            'userid' => $userId,
        ));
    }

    /** Lấy danh sách user đã enrol trong khoá học */
    public function getEnrolledUsers($courseId, $options = array())
    {
        $params = array('courseid' => $courseId);
        if (!empty($options)) {
            $params['options'] = $options;
        }
        return $this->call('core_enrol_get_enrolled_users', $params);
    }

    /** Lấy danh mục (categories) */
    public function getCategories($criteria = array())
    {
        // criteria: [['key'=>'name','value'=>'...'], ...]
        $params = array();
        if (!empty($criteria)) {
            $params['criteria'] = array_values($criteria);
        }
        $data = $this->call('core_course_get_categories', $params);
        return $data;
    }

    /** Tạo danh mục */
    public function createCategories($categories)
    {
        // mỗi category: ['name','parent'=>0,'idnumber'=>'','description'=>'',...]
        return $this->call('core_course_create_categories', array(
            'categories' => array_values($categories),
        ));
    }

    // ================================================================
    //  USER
    // ================================================================

    /**
     * Tìm user theo criteria (Moodle cho phép search phức tạp).
     * Ví dụ: [['key'=>'email','value'=>'a@b.com']], [['key'=>'username','value'=>'sv001']]
     */
    public function getUsers($criteria = array())
    {
        if (empty($criteria)) {
            // trả về rỗng nếu không có criteria (Moodle yêu cầu ít nhất 1)
            return array();
        }
        // criteria dạng assoc ['email'=>'a@b.com'] -> chuyển sang [['key'=>'email','value'=>'a@b.com']]
        $normalized = array();
        foreach ($criteria as $k => $v) {
            if (is_array($v) && isset($v['key'])) {
                $normalized[] = $v;
            } else {
                $normalized[] = array('key' => $k, 'value' => (string)$v);
            }
        }
        $data = $this->call('core_user_get_users', array(
            'criteria' => $normalized,
        ));
        return isset($data['users']) ? $data['users'] : array();
    }

    /** Lấy user theo field (id, username, email, idnumber) */
    public function getUserByField($field, $value)
    {
        return $this->call('core_user_get_users_by_field', array(
            'field' => $field,
            'values' => array((string)$value),
        ));
    }

    /** Lấy nhiều user theo danh sách ids (tái dùng hàm cha nhưng alias) */
    public function getUsersByFieldIds($userIds)
    {
        return $this->getUsersByIds($userIds);
    }

    /**
     * Tạo user mới.
     * @param array $users mỗi user: ['username','password','firstname','lastname','email','auth'=>'manual', 'idnumber'=>..., 'lang'=>'vi', ...]
     */
    public function createUsers($users)
    {
        return $this->call('core_user_create_users', array(
            'users' => array_values($users),
        ));
    }

    /** Cập nhật user (cần id) */
    public function updateUsers($users)
    {
        return $this->call('core_user_update_users', array(
            'users' => array_values($users),
        ));
    }

    /** Xoá user */
    public function deleteUsers($userIds)
    {
        return $this->call('core_user_delete_users', array(
            'userids' => array_values($userIds),
        ));
    }

    /** Lấy profile đầy đủ của user trong context course */
    public function getCourseUserProfiles($userIds, $courseId)
    {
        $list = array();
        foreach ($userIds as $id) {
            $list[] = array('userid' => $id, 'courseid' => $courseId);
        }
        return $this->call('core_user_get_course_user_profiles', array(
            'userlist' => $list,
        ));
    }

    // ================================================================
    //  ENROL / ROLE / GROUP / COHORT
    // ================================================================

    /**
     * Ghi danh user vào khoá học (manual enrol).
     * @param array $enrolments mỗi phần tử: ['roleid'=>5,'userid'=>123,'courseid'=>10,'timestart'=>0,'timeend'=>0,'suspend'=>0]
     * roleid 5 = student, 3 = teacher, 4 = non-editing teacher (tuỳ site)
     */
    public function enrolUsers($enrolments)
    {
        return $this->call('enrol_manual_enrol_users', array(
            'enrolments' => array_values($enrolments),
        ));
    }

    /** Huỷ ghi danh */
    public function unenrolUsers($enrolments)
    {
        // enrolments: [['userid'=>123,'courseid'=>10,'roleid'=>5], ...]
        return $this->call('enrol_manual_unenrol_users', array(
            'enrolments' => array_values($enrolments),
        ));
    }

    /** Gán role cho user trong context (course/category/system) */
    public function assignRoles($assignments)
    {
        // assignments: [['roleid'=>5,'userid'=>123,'contextid'=>..., 'contextlevel'=>'course', 'instanceid'=>10], ...]
        return $this->call('core_role_assign_roles', array(
            'assignments' => array_values($assignments),
        ));
    }

    /** Gỡ role */
    public function unassignRoles($unassignments)
    {
        return $this->call('core_role_unassign_roles', array(
            'unassignments' => array_values($unassignments),
        ));
    }

    // --- Group ---
    public function getCourseGroups($courseId)
    {
        return $this->call('core_group_get_course_groups', array(
            'courseid' => $courseId,
        ));
    }

    public function createGroups($groups)
    {
        // groups: [['courseid'=>10,'name'=>'Nhóm 1','description'=>'...','enrolmentkey'=>''], ...]
        return $this->call('core_group_create_groups', array(
            'groups' => array_values($groups),
        ));
    }

    public function deleteGroups($groupIds)
    {
        return $this->call('core_group_delete_groups', array(
            'groupids' => array_values($groupIds),
        ));
    }

    public function addGroupMembers($members)
    {
        // members: [['groupid'=>1,'userid'=>123], ...]
        return $this->call('core_group_add_group_members', array(
            'members' => array_values($members),
        ));
    }

    public function deleteGroupMembers($members)
    {
        return $this->call('core_group_delete_group_members', array(
            'members' => array_values($members),
        ));
    }

    // --- Cohort ---
    public function getCohorts($cohortIds = array())
    {
        return $this->call('core_cohort_get_cohorts', array(
            'cohortids' => array_values($cohortIds),
        ));
    }

    public function createCohorts($cohorts)
    {
        // cohorts: [['categorytype'=>['type'=>'system','value'=>''], 'name'=>'K17', 'idnumber'=>'K17', 'description'=>'', 'visible'=>1], ...]
        return $this->call('core_cohort_create_cohorts', array(
            'cohorts' => array_values($cohorts),
        ));
    }

    public function addCohortMembers($members)
    {
        // members: [['cohorttype'=>['type'=>'id','value'=>1], 'usertype'=>['type'=>'id','value'=>123]], ...]
        return $this->call('core_cohort_add_cohort_members', array(
            'members' => array_values($members),
        ));
    }

    // ================================================================
    //  ASSIGNMENT (mở rộng từ MoodleClient)
    // ================================================================

    /** Lấy danh sách assignment trong các khoá học */
    public function getAssignments($courseIds, $capabilities = array(), $includenotenrolledcourses = false)
    {
        $params = array(
            'courseids' => array_values($courseIds),
        );
        if (!empty($capabilities)) {
            $params['capabilities'] = array_values($capabilities);
        }
        if ($includenotenrolledcourses) {
            $params['includenotenrolledcourses'] = 1;
        }
        $data = $this->call('mod_assign_get_assignments', $params);
        return isset($data['courses']) ? $data['courses'] : array();
    }

    /** Lấy grades của assignment */
    public function getAssignmentGrades($assignmentIds)
    {
        $data = $this->call('mod_assign_get_grades', array(
            'assignmentids' => array_values($assignmentIds),
        ));
        return isset($data['assignments']) ? $data['assignments'] : array();
    }

    /** Lấy user mapping cho assignment (participant) */
    public function listAssignmentParticipants($assignmentId, $includeEnrolments = true)
    {
        return $this->call('mod_assign_list_participants', array(
            'assignid' => $assignmentId,
            'groupid' => 0,
            'filter' => '',
            'skip' => 0,
            'limit' => 0,
            'onlyids' => 0,
            'includeenrolments' => $includeEnrolments ? 1 : 0,
        ));
    }

    // saveGrade đã có ở MoodleClient, kế thừa luôn
    // getSubmissions đã có ở MoodleClient

    // ================================================================
    //  QUIZ
    // ================================================================

    public function getQuizzesByCourses($courseIds)
    {
        $data = $this->call('mod_quiz_get_quizzes_by_courses', array(
            'courseids' => array_values($courseIds),
        ));
        return isset($data['quizzes']) ? $data['quizzes'] : array();
    }

    public function getQuizAttempts($quizId, $status = 'all', $includePreviews = false)
    {
        return $this->call('mod_quiz_get_user_attempts', array(
            'quizid' => $quizId,
            'userid' => 0, // 0 = all users nếu có quyền
            'status' => $status, // all, finished, unfinished
            'includepreviews' => $includePreviews ? 1 : 0,
        ));
    }

    public function getUserQuizAttempts($quizId, $userId, $status = 'all')
    {
        return $this->call('mod_quiz_get_user_attempts', array(
            'quizid' => $quizId,
            'userid' => $userId,
            'status' => $status,
        ));
    }

    public function getQuizAttemptData($attemptId, $page = 0)
    {
        return $this->call('mod_quiz_get_attempt_data', array(
            'attemptid' => $attemptId,
            'page' => $page,
        ));
    }

    public function getQuizGrades($quizIds, $userIds = array())
    {
        $grades = array();
        foreach ($quizIds as $qid) {
            $targets = $userIds ? $userIds : array(0);
            foreach ($targets as $userId) {
                try {
                    $g = $this->call('mod_quiz_get_user_best_grade', array('quizid' => $qid, 'userid' => (int)$userId));
                    $grades[] = array('quizid' => (int)$qid, 'userid' => (int)$userId, 'grade' => $g);
                } catch (RuntimeException $e) {
                    $grades[] = array('quizid' => (int)$qid, 'userid' => (int)$userId, 'error' => $e->getMessage());
                } catch (Exception $e) {
                    $grades[] = array('quizid' => (int)$qid, 'userid' => (int)$userId, 'error' => $e->getMessage());
                }
            }
        }
        return $grades;
    }

    public function getCourseCompletion($courseId, $userId)
    {
        return $this->call('core_completion_get_course_completion_status', array(
            'courseid' => (int)$courseId,
            'userid' => (int)$userId,
        ));
    }

    public function getActivityCompletion($courseId, $userId)
    {
        return $this->call('core_completion_get_activities_completion_status', array(
            'courseid' => (int)$courseId,
            'userid' => (int)$userId,
        ));
    }

    // ================================================================
    //  FORUM
    // ================================================================

    public function getForumsByCourses($courseIds)
    {
        $data = $this->call('mod_forum_get_forums_by_courses', array(
            'courseids' => array_values($courseIds),
        ));
        return $data;
    }

    public function getForumDiscussions($forumId, $sortBy = -1, $sortDirection = -1, $page = -1, $perPage = 0)
    {
        $params = array('forumid' => $forumId);
        if ($sortBy !== -1) $params['sortby'] = $sortBy;
        if ($sortDirection !== -1) $params['sortdirection'] = $sortDirection;
        if ($page !== -1) $params['page'] = $page;
        if ($perPage !== 0) $params['perpage'] = $perPage;
        return $this->call('mod_forum_get_forum_discussions', $params);
    }

    public function getDiscussionPosts($discussionId, $sortBy = 'created', $sortDirection = 'asc')
    {
        return $this->call('mod_forum_get_discussion_posts', array(
            'discussionid' => $discussionId,
            'sortby' => $sortBy,
            'sortdirection' => $sortDirection,
        ));
    }

    public function addForumDiscussion($forumId, $subject, $message, $groupId = -1, $options = array())
    {
        $params = array_merge(array(
            'forumid' => $forumId,
            'subject' => $subject,
            'message' => $message,
        ), $options);
        if ($groupId !== -1) $params['groupid'] = $groupId;
        return $this->call('mod_forum_add_discussion', $params);
    }

    /**
     * Đăng thông báo qua local_mtpcbridge khi Moodle đã cài plugin bridge.
     * Hàm này dùng quyền moodle/course:manageactivities của service account,
     * tránh lệ thuộc vào capability startdiscussion của API forum mặc định.
     */
    public function createAnnouncement($courseId, $forumId, $subject, $message)
    {
        return $this->call('local_mtpcbridge_create_announcement', array(
            'courseid' => (int)$courseId,
            'forumid' => (int)$forumId,
            'subject' => (string)$subject,
            'message' => (string)$message,
        ));
    }

    /**
     * Tạo bài giảng Page hoặc URL thông qua plugin local_mtpcbridge trên Moodle.
     * Plugin dùng add_moduleinfo() phía Moodle để tạo đúng course module.
     */
    public function createLecture($courseId, $sectionNum, $type, $name, $content = '', $contentFormat = 1, $url = '')
    {
        return $this->call('local_mtpcbridge_create_lecture', array(
            'courseid' => (int)$courseId,
            'sectionnum' => (int)$sectionNum,
            'type' => (string)$type,
            'name' => (string)$name,
            'content' => (string)$content,
            'contentformat' => (int)$contentFormat,
            'url' => (string)$url,
        ));
    }

    /**
     * Tạo bài giảng dạng Page và đính kèm file qua plugin local_mtpcbridge.
     */
    public function createFileLecture($courseId, $sectionNum, $name, $filename, $mimetype, $fileContent, $description = '')
    {
        return $this->call('local_mtpcbridge_create_file_lecture', array(
            'courseid' => (int)$courseId,
            'sectionnum' => (int)$sectionNum,
            'name' => (string)$name,
            'filename' => (string)$filename,
            'mimetype' => (string)$mimetype,
            'filecontent' => base64_encode($fileContent),
            'description' => (string)$description,
        ));
    }

    public function createAssignment($courseId, $sectionNum, $name, $intro = '', $dueDate = 0, $allowFrom = 0, $cutoffDate = 0, $grade = 10, $maxFiles = 1, $maxBytes = 0)
    {
        return $this->call('local_mtpcbridge_create_assignment', array(
            'courseid' => (int)$courseId, 'sectionnum' => (int)$sectionNum,
            'name' => (string)$name, 'intro' => (string)$intro,
            'duedate' => (int)$dueDate, 'allowsubmissionsfromdate' => (int)$allowFrom,
            'cutoffdate' => (int)$cutoffDate, 'grade' => (float)$grade,
            'maxfiles' => (int)$maxFiles, 'maxbytes' => (int)$maxBytes,
        ));
    }

    public function createQuiz($courseId, $sectionNum, $name, $intro = '', $timeOpen = 0, $timeClose = 0, $timeLimit = 0, $attempts = 0, $grade = 10)
    {
        return $this->call('local_mtpcbridge_create_quiz', array(
            'courseid' => (int)$courseId, 'sectionnum' => (int)$sectionNum,
            'name' => (string)$name, 'intro' => (string)$intro,
            'timeopen' => (int)$timeOpen, 'timeclose' => (int)$timeClose,
            'timelimit' => (int)$timeLimit, 'attempts' => (int)$attempts,
            'grade' => (float)$grade,
        ));
    }

    public function manageActivity($courseModuleId, $action, $name = '', $sectionNum = -1)
    {
        return $this->call('local_mtpcbridge_manage_activity', array(
            'coursemoduleid' => (int)$courseModuleId, 'action' => (string)$action,
            'name' => (string)$name, 'sectionnum' => (int)$sectionNum,
        ));
    }

    public function addDiscussionPost($discussionId, $subject, $message, $options = array())
    {
        $params = array_merge(array(
            'discussionid' => $discussionId,
            'subject' => $subject,
            'message' => $message,
        ), $options);
        return $this->call('mod_forum_add_discussion_post', $params);
    }

    // ================================================================
    //  GRADEBOOK
    // ================================================================

    public function getGradeItems($courseId)
    {
        // Dùng gradereport_user_get_grade_items (cần userid) hoặc core_grades
        // Ở đây dùng core_course_get_contents + grade_items nếu có
        try {
            return $this->call('gradereport_user_get_grade_items', array(
                'courseid' => $courseId,
            ));
        } catch (RuntimeException $e) {
            // Fallback: lấy grades cho tất cả enrolled users
            return array('error' => $e->getMessage(), 'hint' => 'Cần quyền gradereport/user:view hoặc thử core_grades_get_grades');
        } catch (Exception $e) {
            return array('error' => $e->getMessage(), 'hint' => 'Cần quyền gradereport/user:view hoặc thử core_grades_get_grades');
        }
    }

    public function getUserGrades($courseId, $userId)
    {
        return $this->call('gradereport_user_get_grade_items', array(
            'courseid' => $courseId,
            'userid' => $userId,
        ));
    }

    public function updateGrades($source, $courseId, $itemType, $itemModule, $itemInstance, $itemNumber, $grades, $itemName = null)
    {
        // grades: [['userid'=>123,'rawgrade'=>85,'feedback'=>'...'], ...]
        $params = array(
            'source' => $source,
            'courseid' => $courseId,
            'component' => $itemModule,
            'activityid' => $itemInstance,
            'itemnumber' => $itemNumber,
            'grades' => array_values($grades),
        );
        if ($itemName !== null) $params['itemname'] = $itemName;
        // Moodle có nhiều hàm update grades, ở đây dùng core_grades_update_grades
        return $this->call('core_grades_update_grades', $params);
    }

    // ================================================================
    //  CALENDAR
    // ================================================================

    public function getCalendarEvents($courseIds = array(), $includeSiteEvents = true, $timeSortFrom = 0)
    {
        $events = array();
        if (!empty($courseIds)) {
            $events['courseids'] = array_values($courseIds);
        }
        if ($includeSiteEvents) {
            $events['siteevents'] = 1;
        }
        $params = array();
        if (!empty($events)) {
            $params['events'] = $events;
        }
        if ($timeSortFrom !== 0) {
            $params['options'] = array('timesortfrom' => $timeSortFrom);
        }
        return $this->call('core_calendar_get_calendar_events', $params);
    }

    public function createCalendarEvents($events)
    {
        // events: [['name'=>'Hạn nộp bài','description'=>'...','eventtype'=>'course','courseid'=>10,'timestart'=>..., 'timeduration'=>0, 'visible'=>1], ...]
        return $this->call('core_calendar_create_calendar_events', array(
            'events' => array_values($events),
        ));
    }

    public function deleteCalendarEvents($eventIds)
    {
        $list = array();
        foreach ($eventIds as $id) {
            $list[] = array('eventid' => $id);
        }
        return $this->call('core_calendar_delete_calendar_events', array(
            'events' => $list,
        ));
    }

    // ================================================================
    //  MESSAGE
    // ================================================================

    public function sendMessage($toUserId, $text, $textFormat = 'MOODLE')
    {
        return $this->call('core_message_send_instant_messages', array(
            'messages' => array(
                array(
                    'touserid' => $toUserId,
                    'text' => $text,
                    'textformat' => $textFormat === 'HTML' ? 1 : 0,
                )
            )
        ));
    }

    public function sendMessages($messages)
    {
        // messages: [['touserid'=>123,'text'=>'...','textformat'=>0], ...]
        return $this->call('core_message_send_instant_messages', array(
            'messages' => array_values($messages),
        ));
    }

    public function getMessages($userId, $otherUserId, $limitFrom = 0, $limitNum = 20, $type = 'both', $newestFirst = true)
    {
        return $this->call('core_message_get_messages', array(
            'useridto' => $userId,
            'useridfrom' => $otherUserId,
            'limitfrom' => $limitFrom,
            'limitnum' => $limitNum,
            'type' => $type,
            'newestfirst' => $newestFirst ? 1 : 0,
        ));
    }

    // ================================================================
    //  FILES / RESOURCE
    // ================================================================

    /** Lấy danh sách file trong một filearea (cần contextid, component, filearea, itemid) */
    public function getFiles($contextId, $component, $fileArea, $itemId, $filePath = '/', $fileName = '')
    {
        $params = array(
            'contextid' => $contextId,
            'component' => $component,
            'filearea' => $fileArea,
            'itemid' => $itemId,
            'filepath' => $filePath,
            'filename' => $fileName,
        );
        return $this->call('core_files_get_files', $params);
    }

    /**
     * Upload file lên draft area (dùng cho mod_assign, mod_resource...).
     * Moodle yêu cầu upload qua core_files_upload với base64.
     * @param int $contextId thường là context user (lấy từ getSiteInfo['userprivateaccesskey'] không, mà lấy contextid của user)
     * @param string $fileName tên file
     * @param string $filePath nội dung file (binary) hoặc đường dẫn local
     * @param int $itemId draft item id (0 để tạo mới)
     */
    public function uploadFile($contextId, $component, $fileArea, $itemId, $fileName, $fileContent, $filePath = '/')
    {
        $base64 = base64_encode($fileContent);
        return $this->call('core_files_upload', array(
            'contextid' => $contextId,
            'component' => $component,
            'filearea' => $fileArea,
            'itemid' => $itemId,
            'filepath' => $filePath,
            'filename' => $fileName,
            'filecontent' => $base64,
            'contextlevel' => 'user', // hoặc course/block...
            'instanceid' => $contextId,
        ));
    }

    /** Helper: tải file từ pluginfile.php URL (kế thừa từ MoodleClient) */
    // downloadSubmissionFile đã có ở parent

    // ================================================================
    //  COHORT / BADGE / COMPETENCY (mở rộng)
    // ================================================================

    public function getBadges($courseId = 0)
    {
        if ($courseId === 0) {
            return $this->call('core_badges_get_user_badges', array());
        }
        return $this->call('core_badges_get_course_badges', array('courseid' => $courseId));
    }

    // ================================================================
    //  SEARCH / PAGINATION HELPERS
    // ================================================================

    /**
     * Lấy tất cả user theo phân trang (Moodle giới hạn 1000/user/search).
     * Tự động loop cho đến hết.
     */
    public function getAllUsers($perPage = 1000)
    {
        $all = array();
        $page = 0;
        while (true) {
            $data = $this->call('core_user_get_users', array(
                'criteria' => array(), // rỗng sẽ lấy theo sort nhưng cần criteria; fallback dùng search
            ));
            // Nếu API trên không hỗ trợ rỗng, dùng alternative: tìm theo wildcard
            // Thử với criteria key=username value=% (tuỳ version)
            if (empty($data['users'])) {
                break;
            }
            $all = array_merge($all, $data['users']);
            if (count($data['users']) < $perPage) break;
            $page++;
        }
        return $all;
    }

    /**
     * Helper gọi bất kỳ hàm nào với retry khi gặp rate limit / timeout
     */
    public function callWithRetry($wsfunction, $params = array(), $retries = 3, $delayMs = 1000)
    {
        $lastEx = null;
        for ($i = 0; $i < $retries; $i++) {
            try {
                return $this->call($wsfunction, $params);
            } catch (RuntimeException $e) {
                $lastEx = $e;
                $msg = strtolower($e->getMessage());
                $isRetryable = strpos($msg, 'timeout') !== false
                    || strpos($msg, 'curl') !== false
                    || strpos($msg, 'too many') !== false
                    || strpos($msg, 'rate') !== false;
                if (!$isRetryable || $i === $retries - 1) throw $e;
                usleep($delayMs * 1000);
            } catch (Exception $e) {
                $lastEx = $e;
                if ($i === $retries - 1) throw $e;
                usleep($delayMs * 1000);
            }
        }
        throw $lastEx;
    }
}
