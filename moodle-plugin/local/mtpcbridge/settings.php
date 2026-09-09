<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_mtpcbridge', get_string('pluginname', 'local_mtpcbridge'));
    $settings->add(new admin_setting_configcheckbox(
        'local_mtpcbridge/zalonotifyenabled',
        get_string('zalonotifyenabled', 'local_mtpcbridge'),
        get_string('zalonotifyenabled_desc', 'local_mtpcbridge'),
        1
    ));
    $ADMIN->add('localplugins', $settings);
}
