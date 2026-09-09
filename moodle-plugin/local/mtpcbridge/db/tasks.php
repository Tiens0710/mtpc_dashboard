<?php
defined('MOODLE_INTERNAL') || die();

$tasks = array(
    array(
        'classname' => '\\local_mtpcbridge\\task\\cleanup_zalo_queue',
        'blocking' => 0,
        'minute' => 'R',
        'hour' => 'R',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ),
);
