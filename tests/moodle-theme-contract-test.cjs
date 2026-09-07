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
assert.ok(read('scss/mtpc.scss').includes('grid-template-columns: repeat(auto-fill, minmax(18rem, 22rem))'), 'Course cards must use a responsive grid');
assert.ok(read('scss/mtpc.scss').includes('max-width: 110rem !important'), 'Dashboard content must not be trapped in the default narrow container');
assert.ok(read('scss/mtpc.scss').includes('#page-mod-forum-view'), 'Announcements page must have a dedicated visual treatment');
assert.ok(read('scss/mtpc.scss').includes('.forumheaderlist'), 'Announcements discussion list must be styled');
assert.ok(read('scss/mtpc.scss').includes('.forumsearch'), 'Announcements search must be styled');
assert.ok(read('scss/mtpc.scss').includes('.courseindex .courseindex-item.current'), 'Course index needs a clear current-item state');
assert.ok(read('scss/mtpc.scss').includes('.path-mod-forum'), 'Forum styles must cover Moodle forum body classes');
assert.ok(read('scss/mtpc.scss').includes('background: var(--mtpc-green-900)'), 'Navbar must use a stable MTPC green surface');
assert.ok(!read('scss/mtpc.scss').includes('linear-gradient'), 'Theme must avoid decorative gradients that make Moodle feel synthetic');
assert.ok(read('classes/privacy/provider.php').includes('null_provider'), 'Theme privacy provider is missing');
assert.match(read('version.php'), /\$plugin->version\s*=\s*2026090703;/, 'Theme version must be bumped for the Gemini Live Orb revision');
assert.ok(read('scss/mtpc.scss').includes('.navbar.fixed-top.bg-white'), 'Navbar override must cover Boost white navbar state');
assert.ok(read('scss/mtpc.scss').includes('.drawer-toggles .drawer-toggler .btn'), 'Drawer toggle needs an explicit light-surface style');
assert.ok(read('scss/mtpc.scss').includes('grid-template-columns: repeat(auto-fill, minmax(18rem, 22rem))'), 'Dashboard cards must not stretch into a large empty panel');
assert.ok(read('scss/mtpc.scss').includes('.card-grid[data-region="card-deck"] .course-card'), 'Theme must style the Moodle course-card markup');
assert.ok(read('scss/mtpc.scss').includes('height: clamp(10rem, 24vw, 12rem)'), 'Current Moodle course artwork needs a larger responsive media area');
assert.ok(read('scss/mtpc.scss').includes('min-height: 4.75rem'), 'Current Moodle course card body must stay compact');
assert.ok(read('scss/mtpc.scss').includes('background-size: cover'), 'Course artwork must fill the media area cleanly');
assert.ok(read('scss/mtpc.scss').includes('minmax(min(100%, 18rem), 22rem)'), 'Course grid must not overflow narrow tablet widths');
assert.ok(read('scss/mtpc.scss').includes('height: 10rem'), 'Course artwork needs a smaller mobile height');
assert.ok(read('scss/mtpc.scss').includes('min-width: 0'), 'Mobile course controls must be allowed to shrink');
assert.ok(read('scss/mtpc.scss').includes('#page.drawers > .main-inner > .drawer-toggles'), 'Mobile drawer toggle selector must match Boost markup');
assert.ok(read('scss/mtpc.scss').includes('position: fixed !important'), 'Mobile drawer toggle must stay anchored to the viewport');
assert.ok(read('scss/mtpc.scss').includes('top: calc(3.75rem + .75rem)'), 'Mobile drawer toggle must sit below the navbar');
assert.ok(read('scss/mtpc.scss').includes('.mtpc-orb'), 'Theme is missing Moodle Orb styles');
assert.ok(read('scss/mtpc.scss').includes('.ai-orb-field'), 'Theme is missing the Orb energy field');
assert.ok(read('scss/mtpc.scss').includes('[data-voice-state="listening"]'), 'Theme is missing the Orb listening state');
assert.ok(read('scss/mtpc.scss').includes('.mtpc-orb.is-voice-open'), 'Theme is missing the expanded Orb voice workspace');
assert.ok(read('scss/mtpc.scss').includes('.mtpc-orb-voice-form'), 'Expanded Orb must include its own text composer');
assert.ok(read('scss/mtpc.scss').includes('.mtpc-orb-voice-transcript'), 'Expanded Orb must keep conversation content in a bounded scrolling region');
assert.ok(read('javascript/mtpc-orb.js').includes("responseModalities: ['AUDIO']"), 'Expanded Orb must request native Gemini Live audio');
assert.ok(read('javascript/mtpc-orb.js').includes('createBuffer(1, floats.length, 24000)'), 'Expanded Orb must play Gemini Live PCM at 24 kHz');
assert.ok(!read('javascript/mtpc-orb.js').includes('speechSynthesis'), 'Moodle Orb must not use browser speech synthesis');
assert.ok(!read('javascript/mtpc-orb.js').includes('SpeechRecognition'), 'Moodle Orb must not use browser speech recognition');
assert.ok(read('scss/mtpc.scss').includes('@media (max-height: 650px)'), 'Expanded Orb must adapt to short and landscape viewports');
assert.ok(!read('javascript/mtpc-orb.js').includes('data-orb-prompt'), 'Moodle Orb chat must not include redundant suggestion chips');
assert.ok(!read('scss/mtpc.scss').includes('.mtpc-orb-voice-hints'), 'Moodle Orb must not reserve layout space for removed suggestions');
assert.ok(fs.readFileSync(path.join(root, '.cpanel.yml'), 'utf8').includes('moodle-theme/mtpc'), 'cPanel does not deploy the Moodle theme');

console.log('Moodle theme contract OK: Boost inheritance, responsive cards, focus and reduced motion.');
