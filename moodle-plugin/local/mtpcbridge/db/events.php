<?php
defined('MOODLE_INTERNAL') || die();

$observers = array(
    array(
        'eventname' => '\\core\\event\\notification_sent',
        'callback' => '\\local_mtpcbridge\\observer::notification_sent',
        'priority' => 9999,
    ),
);
