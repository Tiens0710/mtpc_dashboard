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

    return true;
}
