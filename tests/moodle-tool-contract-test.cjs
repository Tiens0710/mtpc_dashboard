const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const index = fs.readFileSync(path.join(root, 'admin', 'index.html'), 'utf8');
const api = fs.readFileSync(path.join(root, 'admin', 'api', 'moodle.php'), 'utf8');
const upgrade = fs.readFileSync(path.join(root, 'moodle-plugin', 'local', 'mtpcbridge', 'db', 'upgrade.php'), 'utf8');
const version = fs.readFileSync(path.join(root, 'moodle-plugin', 'local', 'mtpcbridge', 'version.php'), 'utf8');

const apiActions = [
  'courses', 'categories', 'users', 'enrolled-users', 'course-contents',
  'assignments', 'forums', 'assignment-submissions', 'assignment-grades',
  'groups', 'calendar-events', 'post-lecture', 'post-lecture-file',
  'post-announcement', 'save-grade', 'create-group', 'add-group-member',
  'create-calendar-event', 'send-message', 'create-course', 'enrol-user',
  'unenrol-user',
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
assert.ok(index.includes('ai-file-chat.js?v=20260905-3'), 'AI file adapter cache version is stale');
assert.ok(version.includes('$plugin->version = 2026090502;'), 'Plugin version was not bumped');

console.log(`Moodle tool contract OK: ${apiActions.length} actions, ${new Set(requiredFunctions).size} Web Service functions.`);
