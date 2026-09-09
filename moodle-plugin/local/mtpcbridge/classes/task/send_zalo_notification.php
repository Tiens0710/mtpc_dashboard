<?php
namespace local_mtpcbridge\task;

defined('MOODLE_INTERNAL') || die();

class send_zalo_notification extends \core\task\adhoc_task {
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $queueid = isset($data->queueid) ? (int)$data->queueid : 0;
        $queue = $DB->get_record('local_mtpcbridge_zalo', array('id' => $queueid));
        if (!$queue || $queue->status === 'sent' || $queue->status === 'skipped') return;

        $queue->status = 'processing';
        $queue->attempts = (int)$queue->attempts + 1;
        $queue->timemodified = time();
        $DB->update_record('local_mtpcbridge_zalo', $queue);

        try {
            $notification = $DB->get_record('notifications', array('id' => $queue->notificationid), '*', MUST_EXIST);
            $user = $DB->get_record('user', array('id' => $queue->userid), '*', MUST_EXIST);
            $course = get_course($queue->courseid);
            $context = \context_course::instance($course->id, IGNORE_MISSING);
            if (!$context || $user->deleted || $user->suspended || !is_enrolled($context, $user, '', true)) {
                $this->finish($queue, 'skipped', 'Học viên không còn hoạt động hoặc không còn ghi danh.');
                return;
            }

            $student = \local_mtpcbridge\local\zalo_client::find_student($user);
            if (!$student || empty($student['zalo_user_id'])) {
                $this->finish($queue, 'skipped', 'Học viên chưa liên kết Zalo OA.');
                return;
            }

            $message = \local_mtpcbridge\local\zalo_client::notification_text($course, $notification);
            \local_mtpcbridge\local\zalo_client::send((string)$student['zalo_user_id'], $message);
            \local_mtpcbridge\local\zalo_client::log_sent($student, $message, $queue);
            $this->finish($queue, 'sent', '');
            mtrace('MTPC Zalo: sent Moodle notification ' . $queue->notificationid . ' to student ' . $queue->userid . '.');
        } catch (\Throwable $error) {
            $this->finish($queue, 'failed', $error->getMessage());
            throw $error;
        }
    }

    private function finish($queue, $status, $error) {
        global $DB;
        $queue->status = $status;
        $queue->lasterror = $error === '' ? null : \core_text::substr($error, 0, 2000);
        $queue->timemodified = time();
        $DB->update_record('local_mtpcbridge_zalo', $queue);
    }
}
