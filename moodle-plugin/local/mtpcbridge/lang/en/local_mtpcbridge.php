<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'MTPC teaching bridge';
$string['zalonotifyenabled'] = 'Send course notifications through Zalo OA';
$string['zalonotifyenabled_desc'] = 'Mirrors Moodle notifications related to an enrolled student\'s course through the school Zalo OA. Students must be linked by Moodle ID number or email.';
$string['task:cleanupzaloqueue'] = 'Clean old Zalo notification delivery records';
$string['messageprovider:onlineclassreminder'] = 'Online class reminders';
$string['joinonlineclass'] = 'Join online class';
$string['privacy:path'] = 'Zalo notification deliveries';
$string['privacy:metadata:queue'] = 'Stores delivery state to prevent duplicate Zalo notifications.';
$string['privacy:metadata:queue:notificationid'] = 'The Moodle notification ID.';
$string['privacy:metadata:queue:userid'] = 'The recipient Moodle user ID.';
$string['privacy:metadata:queue:courseid'] = 'The related Moodle course ID.';
$string['privacy:metadata:queue:status'] = 'The Zalo delivery status.';
$string['privacy:metadata:queue:attempts'] = 'The number of delivery attempts.';
$string['privacy:metadata:queue:lasterror'] = 'The most recent delivery error.';
$string['privacy:metadata:queue:timecreated'] = 'The time the delivery was queued.';
$string['privacy:metadata:queue:timemodified'] = 'The time the delivery state last changed.';
