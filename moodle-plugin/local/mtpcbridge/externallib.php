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
}
