const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict');
class Element {
  constructor() { this.value = ''; this.children = []; }
  append(...nodes) { this.children.push(...nodes); }
  appendChild(node) { this.children.push(node); }
  querySelector() { return null; }
}
const ids = new Map(), messages = [], calls = [];
let selectedFile = null, pendingMoodle = '';
const host = {
  getFile() { return selectedFile; }, setFile(file) { selectedFile = file; },
  getPendingMoodle() { return pendingMoodle; }, setPendingMoodle(value) { pendingMoodle = value; },
  extension(file) { return file.name.split('.').pop(); }, renderAttachment() {}, showPrompt() {}, status() {}, sendContext() {},
  transcript(role, text) { messages.push(text); }, liveSocket() { return null; }, toolDeclarations: [], flash(text) { messages.push(text); }
};
const context = {
  document: { head: new Element(), getElementById(id) { if (!ids.has(id)) ids.set(id, new Element()); return ids.get(id); }, createElement() { return new Element(); } },
  window: { MTPC_ADMIN_FILE_HOST: host, addEventListener() {} }, URL, File, FormData, AbortController, Uint8Array, atob, setTimeout, clearTimeout,
  WebSocket: { OPEN: 1 },
  async fetch(url, options) {
    assert.equal(url, 'api/ai-file.php');
    assert.equal(options.headers['X-MTPC-File-Request'], '1');
    assert.equal(await options.body.get('file').text(), 'original document');
    calls.push('process');
    return { ok: true, async json() { return {ok:true, summary:'Tóm tắt <script>literal</script>', file:{name:'result.txt',mime:'text/plain',base64:btoa('new document')}}; } };
  }
};
vm.createContext(context); vm.runInContext(fs.readFileSync('admin/ai-file-chat.js','utf8'), context);
(async () => {
  const input = context.document.getElementById('adminOrbInput');
  async function sendText(text) { input.value = text; if (!await context.window.MTPC_ADMIN_FILE_CHAT.handleText(text)) calls.push('original-send'); }
  await sendText('Bạn hãy đọc qua file này nhé và xuất TXT');
  assert.equal(calls.length, 0); assert(messages.some(x => x.includes('chọn file')));
  context.window.MTPC_ADMIN_FILE_CHAT.onSelectFile(new File(['original document'], 'source.txt'));
  await sendText('xử lý');
  assert.deepEqual(calls, ['process']);
  const cards = context.document.getElementById('adminOrbTranscript').children;
  assert.equal(cards.length, 1); assert(cards[0].children[1].textContent.includes('<script>literal'));
  assert.equal(cards[0].children[2].download, 'result.txt');
  await sendText('đăng file này lên Moodle');
  assert.equal(calls.at(-1), 'original-send');
  assert.equal(context.window.MTPC_ADMIN_FILE_CHAT.handlesTool('zalo_group_action'), false);
  context.fetch = async () => ({ok:false, json:async()=>({ok:false,error:'Hạn mức'})});
  await sendText('Tóm tắt file');
  assert(messages.includes('Hạn mức')); assert.equal(cards.length, 1);
  cards[0].children[3].onclick(); assert.equal(selectedFile.name, 'result.txt');
  console.log('PASS: picker, processing bytes, safe result card, download, reuse, error, existing tool delegation');
})().catch(error => { console.error(error); process.exitCode = 1; });
