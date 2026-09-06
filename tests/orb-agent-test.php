<?php
function mtpc_zalo_group_read($path) {
    if ($path === 'one') return array(array('group_id'=>'g-1','name'=>'MTPC'));
    if ($path === 'many') return array(array('group_id'=>'g-1','name'=>'MTPC'),array('group_id'=>'g-2','name'=>'Giáo viên'));
    return array();
}
function mtpc_zalo_read_messages($path) {
    return array(
        array('id'=>'newer','user_id'=>'u-1','direction'=>'inbound','text'=>'tin đến sau'),
        array('id'=>'current','user_id'=>'u-1','direction'=>'inbound','text'=>'tin hiện tại'),
        array('id'=>'old-reply','user_id'=>'u-1','direction'=>'outbound','text'=>'trả lời cũ'),
        array('id'=>'old','user_id'=>'u-1','direction'=>'inbound','text'=>'tin cũ')
    );
}
function mtpc_zalo_admin_text($value, $limit) {
    return function_exists('mb_substr') ? mb_substr((string)$value, 0, $limit, 'UTF-8') : substr((string)$value, 0, $limit);
}
function mtpc_zalo_admin_normalize($value) {
    $value = strtolower(trim((string)$value));
    $map = array('à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a','â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a','đ'=>'d','è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e','ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i','ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o','ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o','ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u','ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y');
    return preg_replace('/\s+/', ' ', strtr($value, $map));
}

require __DIR__ . '/../admin/api/orb-agent.php';

function orb_assert($condition, $message) {
    if (!$condition) { fwrite(STDERR, "FAIL: " . $message . "\n"); exit(1); }
}

orb_assert(mtpc_orb_agent_group_identifier('one', '') === 'MTPC', 'single group was not selected');
orb_assert(mtpc_orb_agent_group_identifier('many', '') === '', 'ambiguous groups must not be guessed');
orb_assert(mtpc_orb_agent_group_identifier('many', 'MTPC') === 'MTPC', 'explicit group changed');
orb_assert(mtpc_orb_agent_plain_text('Hiện có **MTPC** và _2 thành viên_.') === 'Hiện có MTPC và 2 thành viên.', 'Markdown was not removed');
$history = mtpc_orb_agent_history('fixture', 'u-1', 'tin hiện tại', 'current');
orb_assert(count($history) === 3, 'Conversation history crossed the current Zalo message boundary');
orb_assert($history[0]['parts'][0]['text'] === 'tin cũ' && $history[2]['parts'][0]['text'] === 'tin hiện tại', 'Conversation history order is incorrect');
$greeting = mtpc_orb_agent_handle_message(array('user_id'=>'u-1'), 'xin chào', '', array(), '', 'fixture');
orb_assert($greeting['event_name'] === 'zalo_orb_agent_fast', 'Simple Zalo greetings must not wait for Gemini');
orb_assert(json_encode(mtpc_orb_agent_tools()) !== false, 'Agent tool schema is not valid JSON');
orb_assert(strpos(file_get_contents(__DIR__ . '/../admin/api/orb-agent.php'), "'assignment_submissions','assignment_grades'") !== false, 'Zalo Agent Core is missing Moodle assignment reads');
orb_assert(strpos(file_get_contents(__DIR__ . '/../admin/api/orb-agent.php'), 'mtpc_orb_agent_user') !== false, 'Zalo Agent Core is missing natural Moodle user lookup');
orb_assert(strpos(file_get_contents(__DIR__ . '/../admin/api/orb-agent.php'), 'mtpc_orb_agent_assignment') !== false, 'Zalo Agent Core is missing natural assignment lookup');
orb_assert(strpos(file_get_contents(__DIR__ . '/../admin/api/orb-agent.php'), 'mtpc_orb_agent_group') !== false, 'Zalo Agent Core is missing natural Moodle group lookup');
orb_assert(strpos(file_get_contents(__DIR__ . '/../admin/api/orb-agent.php'), 'mtpc_orb_agent_quiz') !== false, 'Zalo Agent Core is missing natural quiz lookup');
orb_assert(strpos(file_get_contents(__DIR__ . '/../admin/api/orb-agent.php'), 'mtpc_orb_agent_activity') !== false, 'Zalo Agent Core is missing natural activity lookup');
orb_assert(strpos(file_get_contents(__DIR__ . '/../admin/api/orb-agent.php'), "'create_assignment','create_quiz','manage_activity'") !== false, 'Zalo Agent Core is missing Moodle content writes');
orb_assert(strpos(file_get_contents(__DIR__ . '/../admin/api/orb-agent.php'), "'bulk_enrol'") !== false, 'Zalo Agent Core is missing bulk enrolment');
$zaloApi = file_get_contents(__DIR__ . '/../admin/api/zalo-oa.php');
$zaloEnv = file_get_contents(__DIR__ . '/../admin/api/zalo-env.php');
orb_assert(strpos($zaloApi, 'mtpc_zalo_refresh_access_token') !== false, 'Zalo automatic token refresh is missing');
orb_assert(strpos($zaloApi, 'token-state.json') !== false, 'Rotated Zalo tokens are not persisted outside the web root');
orb_assert(strpos($zaloEnv, 'MTPC_ZALO_OA_REFRESH_TOKEN') !== false, 'Zalo refresh token environment support is missing');
orb_assert(strpos($zaloApi, 'mtpc_zalo_processing_lock') !== false, 'Per-user Zalo processing lock is missing');
orb_assert(strpos($zaloApi, "['_message_row_id']") !== false, 'Current Zalo message boundary is not forwarded to the agent');

echo "PASS: Zalo follow-up group selection and plain-text replies\n";
