<?php
namespace local_mtpcbridge;

defined('MOODLE_INTERNAL') || die();

class observer {
    public static function notification_sent(\core\event\notification_sent $event) {
        global $DB;

        $enabled = get_config('local_mtpcbridge', 'zalonotifyenabled');
        if ($enabled !== false && empty($enabled)) return;

        $courseid = !empty($event->other['courseid']) ? (int)$event->other['courseid'] : 0;
        $userid = (int)$event->relateduserid;
        $notificationid = (int)$event->objectid;
        if ($courseid <= SITEID || $userid <= 0 || $notificationid <= 0) return;

        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context || !is_enrolled($context, $userid, '', true)) return;

        $studentrole = false;
        foreach (get_user_roles($context, $userid, true) as $role) {
            if ((isset($role->archetype) && $role->archetype === 'student') || in_array($role->shortname, array('student', 'hocvien', 'learner'), true)) {
                $studentrole = true;
                break;
            }
        }
        if (!$studentrole) return;

        if ($DB->record_exists('local_mtpcbridge_zalo', array('notificationid' => $notificationid))) return;
        $record = (object)array(
            'notificationid' => $notificationid,
            'userid' => $userid,
            'courseid' => $courseid,
            'status' => 'queued',
            'attempts' => 0,
            'lasterror' => null,
            'timecreated' => time(),
            'timemodified' => time(),
        );
        try {
            $record->id = $DB->insert_record('local_mtpcbridge_zalo', $record);
        } catch (\dml_write_exception $error) {
            // A concurrent observer already queued this notification.
            return;
        }

        $task = new \local_mtpcbridge\task\send_zalo_notification();
        $task->set_custom_data((object)array('queueid' => (int)$record->id));
        $task->set_userid($userid);
        \core\task\manager::queue_adhoc_task($task, true);
    }
}
