const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const index = fs.readFileSync(path.join(root, 'admin', 'index.html'), 'utf8');
const api = fs.readFileSync(path.join(root, 'admin', 'api', 'moodle.php'), 'utf8');
const upgrade = fs.readFileSync(path.join(root, 'moodle-plugin', 'local', 'mtpcbridge', 'db', 'upgrade.php'), 'utf8');
const version = fs.readFileSync(path.join(root, 'moodle-plugin', 'local', 'mtpcbridge', 'version.php'), 'utf8');
const services = fs.readFileSync(path.join(root, 'moodle-plugin', 'local', 'mtpcbridge', 'db', 'services.php'), 'utf8');
const external = fs.readFileSync(path.join(root, 'moodle-plugin', 'local', 'mtpcbridge', 'externallib.php'), 'utf8');
const install = fs.readFileSync(path.join(root, 'moodle-plugin', 'local', 'mtpcbridge', 'db', 'install.php'), 'utf8');
const themeConfig = fs.readFileSync(path.join(root, 'moodle-theme', 'mtpc', 'config.php'), 'utf8');
const orbJs = fs.readFileSync(path.join(root, 'moodle-theme', 'mtpc', 'javascript', 'mtpc-orb.js'), 'utf8');
const orbEndpoint = fs.readFileSync(path.join(root, 'moodle-plugin', 'local', 'mtpcbridge', 'moodle-orb.php'), 'utf8');
const orbLiveToken = fs.readFileSync(path.join(root, 'moodle-plugin', 'local', 'mtpcbridge', 'moodle-live-token.php'), 'utf8');
const orbAgent = fs.readFileSync(path.join(root, 'admin', 'api', 'orb-agent.php'), 'utf8');
const events = fs.readFileSync(path.join(root, 'moodle-plugin', 'local', 'mtpcbridge', 'db', 'events.php'), 'utf8');
const observer = fs.readFileSync(path.join(root, 'moodle-plugin', 'local', 'mtpcbridge', 'classes', 'observer.php'), 'utf8');
const zaloTask = fs.readFileSync(path.join(root, 'moodle-plugin', 'local', 'mtpcbridge', 'classes', 'task', 'send_zalo_notification.php'), 'utf8');
const zaloClient = fs.readFileSync(path.join(root, 'moodle-plugin', 'local', 'mtpcbridge', 'classes', 'local', 'zalo_client.php'), 'utf8');
const installXml = fs.readFileSync(path.join(root, 'moodle-plugin', 'local', 'mtpcbridge', 'db', 'install.xml'), 'utf8');
const privacyProvider = fs.readFileSync(path.join(root, 'moodle-plugin', 'local', 'mtpcbridge', 'classes', 'privacy', 'provider.php'), 'utf8');
const cleanupTask = fs.readFileSync(path.join(root, 'moodle-plugin', 'local', 'mtpcbridge', 'classes', 'task', 'cleanup_zalo_queue.php'), 'utf8');
const reminderTask = fs.readFileSync(path.join(root, 'moodle-plugin', 'local', 'mtpcbridge', 'classes', 'task', 'send_online_class_reminder.php'), 'utf8');

const apiActions = [
  'courses', 'categories', 'users', 'enrolled-users', 'course-contents',
  'assignments', 'forums', 'announcements', 'assignment-submissions', 'assignment-grades',
  'quizzes', 'quiz-attempts', 'quiz-grades', 'grade-items',
  'course-completion', 'activity-completion',
  'groups', 'calendar-events', 'post-lecture', 'post-lecture-file',
  'post-announcement', 'delete-announcements', 'create-assignment', 'create-quiz', 'create-quiz-from-questions', 'manage-activity',
  'save-grade', 'bulk-save-grades', 'ai-grade-assignment', 'create-group', 'add-group-member',
  'remove-group-member', 'delete-group', 'create-calendar-event',
  'delete-calendar-event', 'send-message', 'create-course', 'update-course',
  'delete-course', 'create-user', 'update-user', 'delete-user',
  'enrol-user', 'bulk-enrol', 'unenrol-user',
];

for (const action of apiActions) {
  assert.match(api, new RegExp(`'${action.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}'\\s*=>\\s*array\\(`), `Missing permission contract for ${action}`);
}

const requirementBlock = api.match(/\$functionRequirements\s*=\s*array\(([\s\S]*?)\n\s*\);/);
assert.ok(requirementBlock, 'Moodle function requirement map is missing');
const requiredFunctions = [...requirementBlock[1].matchAll(/'(?:core|mod|enrol|local)_[a-z0-9_]+(?:_[a-z0-9_]+)*'/g)]
  .map((match) => match[0].slice(1, -1));
for (const functionName of new Set(requiredFunctions)) {
  assert.ok(upgrade.includes(`'${functionName}'`), `Plugin upgrade does not grant ${functionName}`);
}

assert.ok(index.includes('mtpcMoodleUniqueShortname'), 'Course shortname auto-generation is missing');
assert.ok(index.includes('var refreshedCourseData=await moodleRequest(\'courses\')'), 'Course lookup must refresh a stale browser cache after a miss');
assert.ok(index.includes("['moodle_create_course','moodle_update_course','moodle_delete_course'].indexOf(tool)!==-1)mtpcMoodleCourses=[]"), 'Course mutations must invalidate the browser course cache');
assert.ok(index.includes('timestart_text'), 'Natural-language calendar time support is missing');
assert.ok(index.includes('missing_functions'), 'Moodle status does not expose missing functions');
assert.ok(api.includes("'tool_status' => $toolStatus"), 'Moodle status should expose readiness per tool');
assert.ok(api.includes("'ready_tools' => $readyTools"), 'Moodle status should count ready tools');
assert.ok(index.includes('mtpcResolveMoodleUser'), 'Natural Moodle user lookup is missing');
assert.ok(index.includes('mtpcResolveMoodleAssignment'), 'Natural assignment lookup is missing');
assert.ok(index.includes('mtpcResolveMoodleGroup'), 'Natural Moodle group lookup is missing');
assert.ok(index.includes('mtpcResolveMoodleQuiz'), 'Natural Moodle quiz lookup is missing');
assert.ok(index.includes('mtpcResolveMoodleActivity'), 'Natural Moodle activity lookup is missing');
assert.ok(index.includes('mtpcResolveMoodleEvent'), 'Natural Moodle event lookup is missing');
assert.ok(index.includes("'ai_grade_assignment'"), 'Admin Orb must expose AI grading drafts');
assert.ok(index.includes("'create_online_class'"), 'Admin Orb must expose the Google Meet workflow');
assert.ok(index.includes('parameters.properties.meet_url'), 'Online classes must accept a teacher-provided Meet link');
assert.ok(api.includes("$action === 'ai-grade-assignment'"), 'Moodle bridge must generate AI grading drafts');
assert.ok(api.includes("'requires_teacher_review'=>true"), 'AI grades must require teacher review before saving');
assert.ok(api.includes("'saved'=>false"), 'AI grading drafts must not silently write official grades');
assert.ok(index.includes('assignment_name'), 'Moodle schema should accept assignment names');
assert.ok(index.includes('user_query'), 'Moodle schema should accept natural user queries');
assert.ok(index.includes('ai-file-chat.js?v=20260911-2'), 'AI file adapter cache version is stale');
assert.ok(version.includes('$plugin->version = 2026091101;'), 'Plugin version was not bumped');
assert.ok(upgrade.includes('upgrade_plugin_savepoint(true, 2026090901'), 'AI grading service upgrade is missing');
assert.ok(upgrade.includes('upgrade_plugin_savepoint(true, 2026090902'), 'Zalo notification queue upgrade is missing');
assert.ok(events.includes('\\\\core\\\\event\\\\notification_sent'), 'Moodle course notifications are not observed');
assert.ok(observer.includes("queue_adhoc_task($task, true)"), 'Zalo delivery must be queued with duplicate protection');
assert.ok(observer.includes("is_enrolled($context, $userid, '', true)"), 'Only actively enrolled students may receive course notifications');
assert.ok(zaloTask.includes("'skipped'"), 'Unlinked or unenrolled students must be skipped safely');
assert.ok(zaloClient.includes("'moodle_course_notification'"), 'Mirrored Moodle notifications must be logged');
assert.ok(zaloClient.includes('mtpc-zalo-oa/token-state.json'), 'Moodle must share the rotated school Zalo OA token');
assert.ok(installXml.includes('notificationid_uix'), 'Zalo notification queue needs persistent duplicate protection');
assert.ok(privacyProvider.includes('delete_data_for_user'), 'Zalo delivery records must support Moodle privacy deletion');
assert.ok(cleanupTask.includes('90 * DAYSECS'), 'Old Zalo delivery records must be cleaned automatically');
assert.ok(upgrade.includes('upgrade_plugin_savepoint(true, 2026090903'), 'Online class reminder service upgrade is missing');
assert.ok(upgrade.includes('upgrade_plugin_savepoint(true, 2026090904'), 'Activity component check upgrade is missing');
assert.ok(external.includes("get_config('mod_quiz', 'version')"), 'Quiz availability must use the mod_quiz component name');
assert.ok(external.includes("get_config('mod_assign', 'version')"), 'Assignment availability must use the mod_assign component name');
assert.ok(!external.includes("get_config('quiz', 'version')"), 'Legacy quiz component check would reject an installed Quiz module');
assert.ok(external.includes('schedule_online_class_reminder'), 'Moodle bridge must queue online class reminders');
assert.ok(reminderTask.includes('timestart - 900') || external.includes("['timestart'] - 900"), 'Online class reminder must run 15 minutes before class');
assert.ok(reminderTask.includes('message_send($message)'), 'Online class reminder must create a Moodle notification for Zalo mirroring');
assert.ok(upgrade.includes('upgrade_plugin_savepoint(true, 2026090601'), 'Moodle announcement upgrade is missing');
assert.ok(themeConfig.includes("$THEME->javascripts_footer = array('mtpc-orb');"), 'Moodle theme does not load the Orb widget');
assert.ok(orbJs.includes('moodle-orb.php'), 'Moodle Orb widget is missing its server endpoint');
assert.ok(orbJs.includes('aria-label="Bấm Orb để nói với Nhi"'), 'Moodle Orb button is missing an accessible label');
assert.ok(orbJs.includes('ai-orb-stage'), 'Moodle Orb widget must use the Orb-first visual control');
assert.ok(orbJs.includes('moodle-live-token.php'), 'Moodle Orb widget must obtain an authenticated Gemini Live token');
assert.ok(orbJs.includes('BidiGenerateContentConstrained'), 'Moodle Orb widget must use the same Gemini Live WebSocket transport as admin');
assert.ok(orbJs.includes("mimeType: 'audio/pcm;rate=16000'"), 'Moodle Orb must stream microphone PCM at 16 kHz');
assert.ok(orbJs.includes("voiceName: token.voice || 'Zephyr'"), 'Moodle Orb must use the admin Orb voice');
assert.ok(!orbJs.includes('SpeechRecognition'), 'Moodle Orb must not use browser speech recognition');
assert.ok(!orbJs.includes('speechSynthesis'), 'Moodle Orb must not use browser speech synthesis');
assert.ok(orbJs.includes('mtpc-orb-voice-form'), 'Expanded Orb must support text without opening the chat panel');
assert.ok(orbJs.includes("mode: 'tool'"), 'Gemini Live tool calls must be routed through the authenticated Moodle bridge');
for (const action of ['today_summary', 'due_work', 'open_course', 'open_activity', 'progress_summary', 'grades_summary']) {
  assert.ok(orbJs.includes(`'${action}'`), `Moodle Orb must declare the student-safe ${action} action`);
  assert.ok(orbEndpoint.includes(`'${action}'`), `Moodle Orb endpoint must implement the student-safe ${action} action`);
}
assert.ok(orbJs.includes('target.origin === base.origin'), 'Moodle Orb navigation must remain on the authenticated Moodle origin');
assert.ok(orbJs.includes('target.pathname.indexOf(base.pathname) === 0'), 'Moodle Orb navigation must remain inside the configured Moodle path');
assert.ok(orbJs.includes('window.location.assign(target.href)'), 'Moodle Orb must navigate after a verified open tool result');
assert.ok(orbEndpoint.includes("$action === 'open_course'"), 'Moodle Orb endpoint must support verified course navigation');
assert.ok(orbEndpoint.includes("$action === 'open_activity'"), 'Moodle Orb endpoint must support verified activity navigation');
assert.ok(orbEndpoint.includes('get_fast_modinfo'), 'Moodle Orb learning tools must enforce Moodle activity visibility');
assert.ok(orbEndpoint.includes("gg.userid = :userid"), 'Moodle Orb grades must be restricted to the current user');
assert.ok(orbEndpoint.includes('gi.hidden = 0'), 'Moodle Orb must not expose hidden grade items');
assert.ok(orbEndpoint.includes('Phạm vi duy nhất là dữ liệu và các trang trong Moodle đang mở'), 'Moodle text fallback must remain strictly within Moodle');
assert.ok(orbLiveToken.includes('require_login()'), 'Moodle Live token endpoint must require an authenticated user');
assert.ok(orbLiveToken.includes('require_sesskey()'), 'Moodle Live token endpoint must verify the Moodle session key');
assert.ok(orbLiveToken.includes('v1beta/auth_tokens'), 'Moodle Live token endpoint must issue constrained Gemini tokens');
assert.ok(orbEndpoint.includes("$role = 'student'"), 'Moodle Orb endpoint must always use the student-safe role');
assert.ok(orbEndpoint.includes('moodle_student_action'), 'Moodle Orb endpoint is missing the student-safe tool');
assert.ok(orbEndpoint.includes('enrol_get_all_users_courses'), 'Moodle Orb must resolve enrolled courses from the authenticated Moodle session');
assert.ok(orbEndpoint.includes('mtpc_moodle_orb_course_from_session'), 'Moodle Orb must validate requested courses against the current Moodle session');
assert.ok(orbEndpoint.includes('mtpc_moodle_orb_all_announcements'), 'Moodle Orb must ground cross-course announcement questions');
assert.ok(orbEndpoint.includes("'mode' => 'ANY'"), 'Text fallback must require a Moodle tool call before answering');
assert.ok(!orbEndpoint.includes("mtpc_moodle_orb_tool($student)"), 'Moodle Orb endpoint must not expose the admin Moodle tool');
assert.ok(orbAgent.includes('function mtpc_orb_agent_moodle_student_tool'), 'Shared Orb agent is missing the student-safe Moodle tool');
assert.ok(orbAgent.includes('$verifiedCourse = null'), 'Student Moodle tool must accept a server-verified enrolled course');
assert.ok(!orbEndpoint.includes("'moodle_action'"), 'Moodle Orb endpoint must not expose the admin Moodle tool');
for (const bridge of ['create_assignment', 'create_quiz', 'manage_activity', 'list_announcements', 'delete_announcements']) {
  assert.ok(services.includes(`'local_mtpcbridge_${bridge}'`), `Plugin service declaration is missing ${bridge}`);
  assert.ok(external.includes(`function ${bridge}(`), `Plugin implementation is missing ${bridge}`);
  assert.ok(install.includes(`'local_mtpcbridge_${bridge}'`), `Fresh installation does not grant ${bridge}`);
}
assert.ok(services.includes("'local_mtpcbridge_create_quiz_from_questions'"), 'Plugin service declaration is missing create_quiz_from_questions');
assert.ok(external.includes('create_quiz_from_questions'), 'Plugin implementation is missing create_quiz_from_questions');
assert.ok(install.includes("'local_mtpcbridge_create_quiz_from_questions'"), 'Fresh installation does not grant create_quiz_from_questions');
assert.ok(upgrade.includes('upgrade_plugin_savepoint(true, 2026091101'), 'Quiz question import upgrade is missing');

console.log(`Moodle tool contract OK: ${apiActions.length} actions, ${new Set(requiredFunctions).size} Web Service functions.`);
