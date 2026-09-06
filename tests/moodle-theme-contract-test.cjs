const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const theme = path.join(root, 'moodle-theme', 'mtpc');
const read = (file) => fs.readFileSync(path.join(theme, file), 'utf8');

for (const file of ['config.php', 'lib.php', 'version.php', 'scss/mtpc.scss', 'lang/en/theme_mtpc.php', 'lang/vi/theme_mtpc.php', 'classes/privacy/provider.php']) {
  assert.ok(fs.existsSync(path.join(theme, file)), `Missing Moodle theme file: ${file}`);
}
assert.ok(read('config.php').includes("$THEME->parents = array('boost')"), 'MTPC theme must inherit Boost');
assert.ok(read('scss/mtpc.scss').includes(':focus-visible'), 'Theme must preserve visible keyboard focus');
assert.ok(read('scss/mtpc.scss').includes('prefers-reduced-motion'), 'Theme must respect reduced-motion preferences');
assert.ok(!read('scss/mtpc.scss').includes('transition: all'), 'Theme must not use transition: all');
assert.ok(read('scss/mtpc.scss').includes('minmax(min(100%, 17.5rem), 1fr)'), 'Course cards must use a responsive grid');
assert.ok(read('classes/privacy/provider.php').includes('null_provider'), 'Theme privacy provider is missing');
assert.ok(fs.readFileSync(path.join(root, '.cpanel.yml'), 'utf8').includes('moodle-theme/mtpc'), 'cPanel does not deploy the Moodle theme');

console.log('Moodle theme contract OK: Boost inheritance, responsive cards, focus and reduced motion.');
