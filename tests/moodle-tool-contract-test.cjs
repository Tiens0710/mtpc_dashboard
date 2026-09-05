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

const apiActions = [
  'courses', 'categories', 'users', 'enrolled-users', 'course-contents',
  'assignments', 'forums', 'assignment-submissions', 'assignment-grades',
  'quizzes', 'quiz-attempts', 'quiz-grades', 'grade-items',
  'course-completion', 'activity-completion',
  'groups', 'calendar-events', 'post-lecture', 'post-lecture-file',
  'post-announcement', 'create-assignment', 'create-quiz', 'manage-activity',
  'save-grade', 'bulk-save-grades', 'create-group', 'add-group-member',
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
assert.ok(index.includes('assignment_name'), 'Moodle schema should accept assignment names');
assert.ok(index.includes('user_query'), 'Moodle schema should accept natural user queries');
assert.ok(index.includes('ai-file-chat.js?v=20260905-3'), 'AI file adapter cache version is stale');
assert.ok(version.includes('$plugin->version = 2026090503;'), 'Plugin version was not bumped');
for (const bridge of ['create_assignment', 'create_quiz', 'manage_activity']) {
  assert.ok(services.includes(`'local_mtpcbridge_${bridge}'`), `Plugin service declaration is missing ${bridge}`);
  assert.ok(external.includes(`function ${bridge}(`), `Plugin implementation is missing ${bridge}`);
  assert.ok(install.includes(`'local_mtpcbridge_${bridge}'`), `Fresh installation does not grant ${bridge}`);
}

console.log(`Moodle tool contract OK: ${apiActions.length} actions, ${new Set(requiredFunctions).size} Web Service functions.`);
