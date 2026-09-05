<?php
function mtpc_zalo_group_read($path) {
    if ($path === 'one') return array(array('group_id'=>'g-1','name'=>'MTPC'));
    if ($path === 'many') return array(array('group_id'=>'g-1','name'=>'MTPC'),array('group_id'=>'g-2','name'=>'Giáo viên'));
    return array();
}

require __DIR__ . '/../admin/api/orb-agent.php';

function orb_assert($condition, $message) {
    if (!$condition) { fwrite(STDERR, "FAIL: " . $message . "\n"); exit(1); }
}

orb_assert(mtpc_orb_agent_group_identifier('one', '') === 'MTPC', 'single group was not selected');
orb_assert(mtpc_orb_agent_group_identifier('many', '') === '', 'ambiguous groups must not be guessed');
orb_assert(mtpc_orb_agent_group_identifier('many', 'MTPC') === 'MTPC', 'explicit group changed');
orb_assert(mtpc_orb_agent_plain_text('Hiện có **MTPC** và _2 thành viên_.') === 'Hiện có MTPC và 2 thành viên.', 'Markdown was not removed');
orb_assert(strpos(file_get_contents(__DIR__ . '/../admin/api/orb-agent.php'), "'assignment_submissions','assignment_grades'") !== false, 'Zalo Agent Core is missing Moodle assignment reads');
orb_assert(strpos(file_get_contents(__DIR__ . '/../admin/api/orb-agent.php'), 'mtpc_orb_agent_user') !== false, 'Zalo Agent Core is missing natural Moodle user lookup');
orb_assert(strpos(file_get_contents(__DIR__ . '/../admin/api/orb-agent.php'), 'mtpc_orb_agent_assignment') !== false, 'Zalo Agent Core is missing natural assignment lookup');
orb_assert(strpos(file_get_contents(__DIR__ . '/../admin/api/orb-agent.php'), 'mtpc_orb_agent_group') !== false, 'Zalo Agent Core is missing natural Moodle group lookup');

echo "PASS: Zalo follow-up group selection and plain-text replies\n";
