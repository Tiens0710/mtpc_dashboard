<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->libdir . '/resourcelib.php');

/**
 * Minimal Moodle-side bridge used by the MTPC Admin dashboard.
 * It creates standard Page or URL resources, so teachers do not need to
 * repeat the Moodle activity form for every lecture.
 */
class local_mtpcbridge_external extends external_api {
    public static function create_lecture_parameters() {
        return new external_function_parameters(array(
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_REQUIRED),
            'sectionnum' => new external_value(PARAM_INT, 'Course section number', VALUE_DEFAULT, 0),
            'type' => new external_value(PARAM_ALPHANUMEXT, 'page or url', VALUE_DEFAULT, 'page'),
            'name' => new external_value(PARAM_TEXT, 'Lecture title', VALUE_REQUIRED),
            'content' => new external_value(PARAM_RAW, 'HTML lecture content', VALUE_DEFAULT, ''),
            'contentformat' => new external_value(PARAM_INT, 'Content format', VALUE_DEFAULT, FORMAT_HTML),
            'url' => new external_value(PARAM_URL, 'External lecture/video URL', VALUE_DEFAULT, ''),
        ));
    }

    public static function create_lecture($courseid, $sectionnum, $type, $name, $content, $contentformat, $url) {
        global $CFG;

        $params = self::validate_parameters(self::create_lecture_parameters(), array(
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'type' => $type,
            'name' => $name,
            'content' => $content,
            'contentformat' => $contentformat,
            'url' => $url,
        ));

        $course = get_course($params['courseid']);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        $type = strtolower(trim($params['type']));
        if (!in_array($type, array('page', 'url'), true)) {
            throw new invalid_parameter_exception('Lecture type must be page or url.');
        }
        $name = trim($params['name']);
        if ($name === '') {
            throw new invalid_parameter_exception('Lecture title is required.');
        }
        if (strlen($params['content']) > 1000000) {
            throw new invalid_parameter_exception('Lecture content is too large.');
        }
        if (!in_array((int)$params['contentformat'], array(FORMAT_HTML, FORMAT_MOODLE, FORMAT_PLAIN, FORMAT_MARKDOWN), true)) {
            throw new invalid_parameter_exception('Unsupported content format.');
        }

        list($module, $coursecontext, $sectioninfo, $cm, $data) = prepare_new_moduleinfo_data(
            $course, $type, (int)$params['sectionnum']
        );
        $data->name = clean_param($name, PARAM_TEXT);
        $data->intro = '';
        $data->introformat = FORMAT_HTML;
        $data->display = RESOURCELIB_DISPLAY_EMBED;
        $data->popupwidth = 620;
        $data->popupheight = 450;
        $data->printintro = 0;
        $data->printlastmodified = 1;
        $data->visible = 1;

        if ($type === 'page') {
            $data->content = clean_param($params['content'], PARAM_CLEANHTML);
            $data->contentformat = (int)$params['contentformat'];
            $data->revision = 1;
        } else {
            $safeurl = clean_param(trim($params['url']), PARAM_URL);
            if ($safeurl === '' || !preg_match('/^https?:\/\//i', $safeurl)) {
                throw new invalid_parameter_exception('A valid http(s) lecture URL is required.');
            }
            $data->externalurl = $safeurl;
            $data->display = RESOURCELIB_DISPLAY_NEW;
        }

        $created = add_moduleinfo($data, $course, null);
        return array(
            'coursemoduleid' => (int)$created->coursemodule,
            'instanceid' => (int)$created->instance,
            'courseid' => (int)$course->id,
            'sectionnum' => (int)$params['sectionnum'],
            'type' => $type,
            'name' => $created->name,
            'url' => $type === 'url' ? $data->externalurl : '',
        );
    }

    public static function create_lecture_returns() {
        return new external_single_structure(array(
            'coursemoduleid' => new external_value(PARAM_INT),
            'instanceid' => new external_value(PARAM_INT),
            'courseid' => new external_value(PARAM_INT),
            'sectionnum' => new external_value(PARAM_INT),
            'type' => new external_value(PARAM_ALPHANUMEXT),
            'name' => new external_value(PARAM_TEXT),
            'url' => new external_value(PARAM_URL),
        ));
    }

    /**
     * Create a Page activity and attach one uploaded lecture file to it.
     * The dashboard sends base64 because Moodle Web Services receives JSON.
     */
    public static function create_file_lecture_parameters() {
        return new external_function_parameters(array(
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_REQUIRED),
            'sectionnum' => new external_value(PARAM_INT, 'Course section number', VALUE_DEFAULT, 0),
            'name' => new external_value(PARAM_TEXT, 'Lecture title', VALUE_REQUIRED),
            'filename' => new external_value(PARAM_FILE, 'Uploaded filename', VALUE_REQUIRED),
            'mimetype' => new external_value(PARAM_TEXT, 'Uploaded file MIME type', VALUE_DEFAULT, 'application/octet-stream'),
            'filecontent' => new external_value(PARAM_RAW, 'Base64 encoded file content', VALUE_REQUIRED),
            'description' => new external_value(PARAM_RAW, 'Optional description', VALUE_DEFAULT, ''),
        ));
    }

    public static function create_file_lecture($courseid, $sectionnum, $name, $filename, $mimetype, $filecontent, $description) {
        global $DB, $USER;

        $params = self::validate_parameters(self::create_file_lecture_parameters(), array(
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'name' => $name,
            'filename' => $filename,
            'mimetype' => $mimetype,
            'filecontent' => $filecontent,
            'description' => $description,
        ));

        $course = get_course($params['courseid']);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        $name = trim($params['name']);
        $filename = clean_param(trim($params['filename']), PARAM_FILE);
        $description = clean_param($params['description'], PARAM_CLEANHTML);
        if ($name === '' || $filename === '') {
            throw new invalid_parameter_exception('Lecture title and filename are required.');
        }
        if (strlen($params['filecontent']) > 28 * 1024 * 1024) {
            throw new invalid_parameter_exception('Uploaded file is too large.');
        }
        $filecontent = base64_decode($params['filecontent'], true);
        if ($filecontent === false || $filecontent === '') {
            throw new invalid_parameter_exception('Uploaded file content is not valid base64.');
        }
        if (strlen($filecontent) > 20 * 1024 * 1024) {
            throw new invalid_parameter_exception('Uploaded file must be 20 MB or smaller.');
        }

        list($module, $coursecontext, $sectioninfo, $cm, $data) = prepare_new_moduleinfo_data(
            $course, 'page', (int)$params['sectionnum']
        );
        $data->name = clean_param($name, PARAM_TEXT);
        $data->intro = '';
        $data->introformat = FORMAT_HTML;
        $data->display = RESOURCELIB_DISPLAY_EMBED;
        $data->printintro = 0;
        $data->printlastmodified = 1;
        $data->visible = 1;
        $data->content = '<p>' . ($description !== '' ? $description . '</p><p>' : '') . 'Tài liệu bài giảng: <a href="@@PLUGINFILE@@/' . rawurlencode($filename) . '">' . s($filename) . '</a></p>';
        $data->contentformat = FORMAT_HTML;
        $data->revision = 1;

        $created = add_moduleinfo($data, $course, null);
        $modulecontext = context_module::instance($created->coursemodule);
        $fs = get_file_storage();
        $stored = $fs->create_file_from_string(array(
            'contextid' => $modulecontext->id,
            'component' => 'mod_page',
            'filearea' => 'content',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => $USER->id,
            'author' => fullname($USER),
            'license' => 'allrightsreserved',
        ), $filecontent);

        $page = $DB->get_record('page', array('id' => $created->instance), '*', MUST_EXIST);
        $page->content = '<p>' . ($description !== '' ? $description . '</p><p>' : '') . 'Tài liệu bài giảng: <a href="@@PLUGINFILE@@/' . rawurlencode($stored->get_filename()) . '">' . s($stored->get_filename()) . '</a></p>';
        $page->revision = 1;
        $DB->update_record('page', $page);

        return array(
            'coursemoduleid' => (int)$created->coursemodule,
            'instanceid' => (int)$created->instance,
            'courseid' => (int)$course->id,
            'sectionnum' => (int)$params['sectionnum'],
            'type' => 'file',
            'name' => $created->name,
            'filename' => $stored->get_filename(),
            'mimetype' => $stored->get_mimetype(),
            'filesize' => (int)$stored->get_filesize(),
        );
    }

    public static function create_file_lecture_returns() {
        return new external_single_structure(array(
            'coursemoduleid' => new external_value(PARAM_INT),
            'instanceid' => new external_value(PARAM_INT),
            'courseid' => new external_value(PARAM_INT),
            'sectionnum' => new external_value(PARAM_INT),
            'type' => new external_value(PARAM_ALPHANUMEXT),
            'name' => new external_value(PARAM_TEXT),
            'filename' => new external_value(PARAM_FILE),
            'mimetype' => new external_value(PARAM_TEXT),
            'filesize' => new external_value(PARAM_INT),
        ));
    }

    /**
     * Create a discussion in the course News/Announcements forum.
     *
     * Moodle's built-in mod_forum_add_discussion web service is declared
     * with mod/forum:startdiscussion even for News forums. This bridge uses
     * the same forum library as Moodle's own web UI and authorises the
     * service account with the course-level manageactivities capability.
     */
    public static function create_announcement_parameters() {
        return new external_function_parameters(array(
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_REQUIRED),
            'forumid' => new external_value(PARAM_INT, 'Announcements forum instance ID', VALUE_REQUIRED),
            'subject' => new external_value(PARAM_TEXT, 'Announcement subject', VALUE_REQUIRED),
            'message' => new external_value(PARAM_RAW, 'Announcement HTML message', VALUE_REQUIRED),
        ));
    }

    public static function create_announcement($courseid, $forumid, $subject, $message) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $params = self::validate_parameters(self::create_announcement_parameters(), array(
            'courseid' => $courseid,
            'forumid' => $forumid,
            'subject' => $subject,
            'message' => $message,
        ));

        $course = get_course($params['courseid']);
        $coursecontext = context_course::instance($course->id);
        self::validate_context($coursecontext);
        require_capability('moodle/course:manageactivities', $coursecontext);

        $forum = $DB->get_record('forum', array('id' => $params['forumid']), '*', MUST_EXIST);
        if ((int)$forum->course !== (int)$course->id) {
            throw new invalid_parameter_exception('The forum does not belong to the selected course.');
        }
        if ((string)$forum->type !== 'news') {
            throw new invalid_parameter_exception('The selected forum is not the course News/Announcements forum.');
        }

        $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $subject = trim(clean_param($params['subject'], PARAM_TEXT));
        $message = clean_param($params['message'], PARAM_CLEANHTML);
        if ($subject === '' || $message === '') {
            throw new invalid_parameter_exception('Announcement subject and message are required.');
        }
        if (function_exists('mb_strlen') ? mb_strlen($message, 'UTF-8') > 12000 : strlen($message) > 48000) {
            throw new invalid_parameter_exception('Announcement message is too long.');
        }

        $discussion = new stdClass();
        $discussion->course = $course->id;
        $discussion->forum = $forum->id;
        $discussion->message = $message;
        $discussion->messageformat = FORMAT_HTML;
        $discussion->messagetrust = trusttext_trusted($context);
        $discussion->itemid = 0;
        $discussion->groupid = -1;
        $discussion->mailnow = 0;
        $discussion->subject = $subject;
        $discussion->name = $subject;
        $discussion->timestart = 0;
        $discussion->timeend = 0;
        $discussion->timelocked = 0;
        $discussion->attachments = null;
        $discussion->pinned = FORUM_DISCUSSION_UNPINNED;

        $discussionid = forum_add_discussion($discussion, null);
        if (!$discussionid) {
            throw new moodle_exception('couldnotadd', 'forum');
        }

        $discussion->id = $discussionid;
        $event = \mod_forum\event\discussion_created::create(array(
            'context' => $context,
            'objectid' => $discussion->id,
            'other' => array('forumid' => $forum->id),
        ));
        $event->add_record_snapshot('forum_discussions', $discussion);
        $event->trigger();

        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) && ($forum->completiondiscussions || $forum->completionposts)) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }

        $settings = new stdClass();
        $settings->discussionsubscribe = true;
        forum_post_subscription($settings, $forum, $discussion);

        return array(
            'discussionid' => (int)$discussionid,
            'courseid' => (int)$course->id,
            'forumid' => (int)$forum->id,
            'url' => $CFG->wwwroot . '/mod/forum/view.php?f=' . (int)$forum->id,
        );
    }

    public static function create_announcement_returns() {
        return new external_single_structure(array(
            'discussionid' => new external_value(PARAM_INT),
            'courseid' => new external_value(PARAM_INT),
            'forumid' => new external_value(PARAM_INT),
            'url' => new external_value(PARAM_URL),
        ));
    }
}
