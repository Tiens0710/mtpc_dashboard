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
);
