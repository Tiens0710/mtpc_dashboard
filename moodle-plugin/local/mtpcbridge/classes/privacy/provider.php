<?php
namespace local_mtpcbridge\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_mtpcbridge_zalo', array(
            'notificationid' => 'privacy:metadata:queue:notificationid',
            'userid' => 'privacy:metadata:queue:userid',
            'courseid' => 'privacy:metadata:queue:courseid',
            'status' => 'privacy:metadata:queue:status',
            'attempts' => 'privacy:metadata:queue:attempts',
            'lasterror' => 'privacy:metadata:queue:lasterror',
            'timecreated' => 'privacy:metadata:queue:timecreated',
            'timemodified' => 'privacy:metadata:queue:timemodified',
        ), 'privacy:metadata:queue');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = 'SELECT ctx.id FROM {context} ctx JOIN {local_mtpcbridge_zalo} q ON q.courseid=ctx.instanceid WHERE ctx.contextlevel=:contextlevel AND q.userid=:userid';
        $contextlist->add_from_sql($sql, array('contextlevel' => CONTEXT_COURSE, 'userid' => $userid));
        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) continue;
            $records = $DB->get_records('local_mtpcbridge_zalo', array('userid' => $contextlist->get_user()->id, 'courseid' => $context->instanceid));
            $rows = array();
            foreach ($records as $record) {
                $rows[] = (object)array(
                    'notificationid' => $record->notificationid,
                    'status' => $record->status,
                    'attempts' => $record->attempts,
                    'lasterror' => $record->lasterror,
                    'timecreated' => transform::datetime($record->timecreated),
                    'timemodified' => transform::datetime($record->timemodified),
                );
            }
            if ($rows) writer::with_context($context)->export_data(array(get_string('privacy:path', 'local_mtpcbridge')), (object)array('deliveries' => $rows));
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel === CONTEXT_COURSE) $DB->delete_records('local_mtpcbridge_zalo', array('courseid' => $context->instanceid));
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $courseids = array();
        foreach ($contextlist->get_contexts() as $context) if ($context->contextlevel === CONTEXT_COURSE) $courseids[] = (int)$context->instanceid;
        if (!$courseids) return;
        list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $params['userid'] = $contextlist->get_user()->id;
        $DB->delete_records_select('local_mtpcbridge_zalo', 'userid=:userid AND courseid ' . $insql, $params);
    }
}
