<?php
defined('MOODLE_INTERNAL') || die();

$THEME->name = 'mtpc';
$THEME->sheets = array();
$THEME->editor_sheets = array();
$THEME->parents = array('boost');
$THEME->enable_dock = false;
$THEME->yuicssmodules = array();
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->requiredblocks = '';
$THEME->addblockposition = BLOCK_ADDBLOCK_POSITION_FLATNAV;
$THEME->haseditswitch = true;
$THEME->usescourseindex = true;
$THEME->scss = function($theme) {
    return theme_mtpc_get_main_scss_content($theme);
};
$THEME->prescsscallback = 'theme_mtpc_get_pre_scss';
$THEME->activityheaderconfig = array('notitle' => true);
$THEME->javascripts_footer = array('mtpc-orb');
