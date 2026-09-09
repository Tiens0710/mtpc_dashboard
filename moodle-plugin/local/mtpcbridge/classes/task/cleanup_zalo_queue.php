<?php
namespace local_mtpcbridge\task;

defined('MOODLE_INTERNAL') || die();

class cleanup_zalo_queue extends \core\task\scheduled_task {
    public function get_name() {
        return get_string('task:cleanupzaloqueue', 'local_mtpcbridge');
    }

    public function execute() {
        global $DB;
        $cutoff = time() - (90 * DAYSECS);
        $DB->delete_records_select('local_mtpcbridge_zalo', 'timemodified < :cutoff AND status IN (:sent,:skipped)', array('cutoff'=>$cutoff, 'sent'=>'sent', 'skipped'=>'skipped'));
    }
}
