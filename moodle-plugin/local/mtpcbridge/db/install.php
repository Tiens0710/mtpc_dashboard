<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_mtpcbridge_install() {
    global $DB;
    $functions = array(
        'local_mtpcbridge_create_lecture', 'local_mtpcbridge_create_file_lecture', 'local_mtpcbridge_create_announcement',
        'local_mtpcbridge_create_assignment', 'local_mtpcbridge_create_quiz', 'local_mtpcbridge_manage_activity',
        'core_course_get_courses', 'core_course_get_categories', 'core_course_get_contents',
        'core_course_create_courses', 'core_course_update_courses', 'core_course_delete_courses',
        'core_user_get_users', 'core_user_create_users', 'core_user_update_users', 'core_user_delete_users',
        'core_enrol_get_enrolled_users', 'enrol_manual_enrol_users', 'enrol_manual_unenrol_users',
        'mod_assign_get_assignments', 'mod_assign_get_submissions', 'mod_assign_get_grades', 'mod_assign_save_grade',
        'mod_quiz_get_quizzes_by_courses', 'mod_quiz_get_user_attempts', 'mod_quiz_get_user_best_grade',
        'mod_forum_get_forums_by_courses', 'core_group_get_course_groups', 'core_group_create_groups',
        'core_group_add_group_members', 'core_group_delete_groups', 'core_group_delete_group_members',
        'core_calendar_get_calendar_events', 'core_calendar_create_calendar_events', 'core_calendar_delete_calendar_events',
        'core_message_send_instant_messages', 'gradereport_user_get_grade_items',
        'core_completion_get_course_completion_status', 'core_completion_get_activities_completion_status',
    );
    foreach ($DB->get_records('external_services') as $service) {
        $name = core_text::strtolower(trim((string)$service->name));
        $shortname = core_text::strtolower(trim((string)$service->shortname));
        if ($name !== 'dashboard' && $shortname !== 'dashboard') continue;
        foreach ($functions as $functionname) {
            if (strpos($functionname, 'local_mtpcbridge_') !== 0 && !$DB->record_exists('external_functions', array('name'=>$functionname))) continue;
            if ($DB->record_exists('external_services_functions', array('externalserviceid'=>$service->id, 'functionname'=>$functionname))) continue;
            $record = new stdClass();
            $record->externalserviceid = $service->id;
            $record->functionname = $functionname;
            $DB->insert_record('external_services_functions', $record);
        }
    }
}
