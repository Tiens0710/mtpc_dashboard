const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict');
class Element {
  constructor() { this.value = ''; this.children = []; }
  append(...nodes) { this.children.push(...nodes); }
  appendChild(node) { this.children.push(node); }
  querySelector() { return null; }
}
const ids = new Map(), messages = [], calls = [];
const context = {
  document: { head: new Element(), getElementById(id) { if (!ids.has(id)) ids.set(id, new Element()); return ids.get(id); }, createElement() { return new Element(); } },
  window: { addEventListener() {} }, URL, File, FormData, AbortController, Uint8Array, atob, setTimeout, clearTimeout,
  adminPendingMoodleFile: null, adminPendingMoodleCommand: '', adminLiveSocket: null,
  adminSendOrbText() { calls.push('original-send'); }, adminRunTool(name) { calls.push(name); return {ok:true}; },
  adminClearMoodleAttachment() { context.adminPendingMoodleFile = null; },
  adminSetMoodleAttachment() {}, adminMoodleAttachmentContextText() {},
  adminMoodleFileExtension(file) { return file.name.split('.').pop(); },
  adminRenderMoodleAttachment() {}, adminSendMoodleAttachmentContext() {}, adminShowMoodleFilePrompt() {}, adminOrbStatus() {},
  adminTranscript(role, text) { messages.push(text); }, flash(text) { messages.push(text); },
  ADMIN_TOOL_DECLARATIONS: [{functionDeclarations:[]}],
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
  input.value = 'Tóm tắt tài liệu thành TXT'; await context.adminSendOrbText();
  assert.equal(calls.length, 0); assert(messages.some(x => x.includes('chọn file')));
  context.adminSetMoodleAttachment(new File(['original document'], 'source.txt'));
  input.value = 'xử lý'; await context.adminSendOrbText();
  assert.deepEqual(calls, ['process']);
  const cards = context.document.getElementById('adminOrbTranscript').children;
  assert.equal(cards.length, 1); assert(cards[0].children[1].textContent.includes('<script>literal'));
  assert.equal(cards[0].children[2].download, 'result.txt');
  input.value = 'đăng lên Moodle'; await context.adminSendOrbText();
  assert.equal(calls.at(-1), 'original-send');
  await context.adminRunTool('zalo_group_action', {}); assert.equal(calls.at(-1), 'zalo_group_action');
  context.fetch = async () => ({ok:false, json:async()=>({ok:false,error:'Hạn mức'})});
  input.value = 'Tóm tắt file'; await context.adminSendOrbText();
  assert(messages.includes('Hạn mức')); assert.equal(cards.length, 1);
  cards[0].children[3].onclick(); assert.equal(context.adminPendingMoodleFile.name, 'result.txt');
  console.log('PASS: picker, processing bytes, safe result card, download, reuse, error, existing tool delegation');
})().catch(error => { console.error(error); process.exitCode = 1; });
