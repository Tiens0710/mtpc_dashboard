<?php
defined('MOODLE_INTERNAL') || die();

function theme_mtpc_get_pre_scss($theme) {
    return implode("\n", array(
        '$primary: #087a49;',
        '$success: #15985d;',
        '$link-color: #087a49;',
        '$border-radius: .75rem;',
        '$border-radius-lg: 1.25rem;',
        '$font-family-sans-serif: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;',
    ));
}

function theme_mtpc_get_main_scss_content($theme) {
    global $CFG;
    $scss = file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
    $custom = __DIR__ . '/scss/mtpc.scss';
    if (is_readable($custom)) $scss .= "\n" . file_get_contents($custom);
    return $scss;
}

