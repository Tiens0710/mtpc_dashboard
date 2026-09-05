<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_mtpcbridge_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2026090501) {
        // This project uses a manually-created external service named/shortnamed
        // "dashboard". Keep its bridge functions in sync after deployment.
        $services = $DB->get_records('external_services');
        $functions = array(
            'local_mtpcbridge_create_lecture',
            'local_mtpcbridge_create_file_lecture',
            'local_mtpcbridge_create_announcement',
        );
        foreach ($services as $service) {
            $name = core_text::strtolower(trim((string)$service->name));
            $shortname = core_text::strtolower(trim((string)$service->shortname));
            if ($name !== 'dashboard' && $shortname !== 'dashboard') {
                continue;
            }
            foreach ($functions as $functionname) {
                if (!$DB->record_exists('external_services_functions', array(
                    'externalserviceid' => $service->id,
                    'functionname' => $functionname,
                ))) {
                    $record = new stdClass();
                    $record->externalserviceid = $service->id;
                    $record->functionname = $functionname;
                    $DB->insert_record('external_services_functions', $record);
                }
            }
        }
        upgrade_plugin_savepoint(true, 2026090501, 'local', 'mtpcbridge');
    }

    if ($oldversion < 2026090502) {
        // The AI dashboard can only call functions explicitly granted to its
        // external service. Keep every Moodle tool exposed by admin/index.html
        // in sync so a deployment cannot leave individual actions unusable.
        $services = $DB->get_records('external_services');
        $functions = array(
            'core_course_get_courses',
            'core_course_get_categories',
            'core_user_get_users',
            'core_enrol_get_enrolled_users',
            'core_course_get_contents',
            'mod_assign_get_assignments',
            'mod_forum_get_forums_by_courses',
            'mod_assign_get_submissions',
            'mod_assign_get_grades',
            'core_group_get_course_groups',
            'core_calendar_get_calendar_events',
            'local_mtpcbridge_create_lecture',
            'local_mtpcbridge_create_file_lecture',
            'local_mtpcbridge_create_announcement',
            'mod_assign_save_grade',
            'core_group_create_groups',
            'core_group_add_group_members',
            'core_calendar_create_calendar_events',
            'core_message_send_instant_messages',
            'core_course_create_courses',
            'core_course_update_courses',
            'core_course_delete_courses',
            'enrol_manual_enrol_users',
            'enrol_manual_unenrol_users',
            'core_user_create_users',
            'core_user_update_users',
            'core_user_delete_users',
        );
        foreach ($services as $service) {
            $name = core_text::strtolower(trim((string)$service->name));
            $shortname = core_text::strtolower(trim((string)$service->shortname));
            if ($name !== 'dashboard' && $shortname !== 'dashboard') {
                continue;
            }
            foreach ($functions as $functionname) {
                if (!$DB->record_exists('external_functions', array('name' => $functionname))) {
                    continue;
                }
                if (!$DB->record_exists('external_services_functions', array(
                    'externalserviceid' => $service->id,
                    'functionname' => $functionname,
                ))) {
                    $record = new stdClass();
                    $record->externalserviceid = $service->id;
                    $record->functionname = $functionname;
                    $DB->insert_record('external_services_functions', $record);
                }
            }
        }
        upgrade_plugin_savepoint(true, 2026090502, 'local', 'mtpcbridge');
    }

    if ($oldversion < 2026090503) {
        $services = $DB->get_records('external_services');
        $functions = array(
            'local_mtpcbridge_create_assignment', 'local_mtpcbridge_create_quiz', 'local_mtpcbridge_manage_activity',
            'mod_quiz_get_quizzes_by_courses', 'mod_quiz_get_user_attempts', 'mod_quiz_get_user_best_grade',
            'gradereport_user_get_grade_items',
            'core_completion_get_course_completion_status', 'core_completion_get_activities_completion_status',
            'core_group_delete_groups', 'core_group_delete_group_members', 'core_calendar_delete_calendar_events',
        );
        foreach ($services as $service) {
            $name = core_text::strtolower(trim((string)$service->name));
            $shortname = core_text::strtolower(trim((string)$service->shortname));
            if ($name !== 'dashboard' && $shortname !== 'dashboard') continue;
            foreach ($functions as $functionname) {
                if (strpos($functionname, 'local_mtpcbridge_') !== 0 && !$DB->record_exists('external_functions', array('name' => $functionname))) continue;
                if (!$DB->record_exists('external_services_functions', array('externalserviceid'=>$service->id, 'functionname'=>$functionname))) {
                    $record = new stdClass();
                    $record->externalserviceid = $service->id;
                    $record->functionname = $functionname;
                    $DB->insert_record('external_services_functions', $record);
                }
            }
        }
        upgrade_plugin_savepoint(true, 2026090503, 'local', 'mtpcbridge');
    }

    return true;
}
