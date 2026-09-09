<?php
defined('MOODLE_INTERNAL') || die();

$functions = array(
    'local_mtpcbridge_create_lecture' => array(
        'classname' => 'local_mtpcbridge_external',
        'methodname' => 'create_lecture',
        'description' => 'Create a Page or URL lecture resource in a course.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities',
    ),
    'local_mtpcbridge_create_file_lecture' => array(
        'classname' => 'local_mtpcbridge_external',
        'methodname' => 'create_file_lecture',
        'description' => 'Create a Page lecture with an attached file in a course.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities',
    ),
    'local_mtpcbridge_create_announcement' => array(
        'classname' => 'local_mtpcbridge_external',
        'methodname' => 'create_announcement',
        'description' => 'Create an announcement in a course announcements forum.',
        'type' => 'write',
        'capabilities' => 'mod/forum:addnews',
    ),
    'local_mtpcbridge_list_announcements' => array(
        'classname' => 'local_mtpcbridge_external', 'methodname' => 'list_announcements',
        'description' => 'List and search course announcements.', 'type' => 'read',
        'capabilities' => 'mod/forum:viewdiscussion',
        'services' => array('dashboard'),
    ),
    'local_mtpcbridge_delete_announcements' => array(
        'classname' => 'local_mtpcbridge_external', 'methodname' => 'delete_announcements',
        'description' => 'Delete selected course announcement discussions.', 'type' => 'write',
        'capabilities' => 'mod/forum:deleteanypost',
        'services' => array('dashboard'),
    ),
    'local_mtpcbridge_create_assignment' => array(
        'classname' => 'local_mtpcbridge_external', 'methodname' => 'create_assignment',
        'description' => 'Create a standard Assignment activity in a course.', 'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities',
    ),
    'local_mtpcbridge_create_quiz' => array(
        'classname' => 'local_mtpcbridge_external', 'methodname' => 'create_quiz',
        'description' => 'Create a standard empty Quiz activity in a course.', 'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities',
    ),
    'local_mtpcbridge_manage_activity' => array(
        'classname' => 'local_mtpcbridge_external', 'methodname' => 'manage_activity',
        'description' => 'Rename, show, hide, move, or delete a course activity.', 'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities',
    ),
    'local_mtpcbridge_schedule_online_class_reminder' => array(
        'classname' => 'local_mtpcbridge_external',
        'methodname' => 'schedule_online_class_reminder',
        'description' => 'Queue a Moodle and Zalo reminder 15 minutes before an online class.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities',
    ),
);
