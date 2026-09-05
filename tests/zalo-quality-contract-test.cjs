const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const index = fs.readFileSync(path.join(root, 'admin', 'index.html'), 'utf8');
const oa = fs.readFileSync(path.join(root, 'admin', 'api', 'zalo-oa.php'), 'utf8');
const admin = fs.readFileSync(path.join(root, 'admin', 'api', 'zalo-admin.php'), 'utf8');

assert.ok(!index.includes("group_id:groupId,count:100"), 'AI group-member tool still requests more than Zalo allows');
assert.ok(oa.includes('min(50, (int)$_GET[\'count\'])'), 'Zalo group-member API must cap page size at 50');
assert.ok(!admin.includes("'count' => 100"), 'Zalo admin commands still request more than 50 group members');
assert.ok(oa.includes("$type === 'sticker'"), 'Sticker messages are not normalized');
assert.ok(oa.includes("$messageType === 'sticker'"), 'Sticker webhook events do not trigger a reply');
assert.ok(oa.includes('chỉ 1 đến 3 câu'), 'Zalo response prompt is not concise');
assert.ok(oa.includes('maxOutputTokens\' => 220'), 'Zalo response token budget is too high');
assert.ok(index.includes('rel="icon"'), 'Admin favicon is missing');
assert.ok(admin.includes("'email_briefing'"), 'Zalo admin email briefing intent is missing');
assert.ok(admin.includes('đọc mail hôm nay'), 'Zalo admin must support the common read-mail command path');
assert.ok(admin.includes("'/home/mtpc/private/email-config.php'"), 'Zalo admin email gateway config path is missing');
assert.ok(admin.includes("'email.read'"), 'Zalo admin email permission is missing');

console.log('Zalo quality contract OK: group limit, concise replies, sticker handling, favicon, email command path.');
