<?php
namespace local_mtpcbridge\task;

defined('MOODLE_INTERNAL') || die();

class send_online_class_reminder extends \core\task\adhoc_task {
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '/message/lib.php');
        $data = $this->get_custom_data();
        $course = get_course((int)$data->courseid);
        $context = \context_course::instance($course->id);
        $students = get_enrolled_users($context, 'moodle/course:view', 0, 'u.*', null, 0, 0, true);
        $sent = 0;

        foreach ($students as $student) {
            $isstudent = false;
            foreach (get_user_roles($context, $student->id, true) as $role) {
                if ((isset($role->archetype) && $role->archetype === 'student') || in_array($role->shortname, array('student', 'hocvien', 'learner'), true)) {
                    $isstudent = true;
                    break;
                }
            }
            if (!$isstudent || $student->deleted || $student->suspended) continue;

            $text = 'Buổi học “' . format_string($data->name) . '” sẽ bắt đầu sau 15 phút.' . "\nTham gia: " . $data->meeturl;
            $message = new \core\message\message();
            $message->component = 'local_mtpcbridge';
            $message->name = 'onlineclassreminder';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $student;
            $message->subject = 'Sắp đến giờ học: ' . format_string($data->name);
            $message->fullmessage = $text;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = '<p>' . nl2br(s($text)) . '</p>';
            $message->smallmessage = $text;
            $message->notification = 1;
            $message->courseid = $course->id;
            $message->contexturl = $data->meeturl;
            $message->contexturlname = get_string('joinonlineclass', 'local_mtpcbridge');
            if (message_send($message)) $sent++;
        }
        mtrace('MTPC online class reminder: sent ' . $sent . ' Moodle notifications for course ' . $course->id . '.');
    }
}
