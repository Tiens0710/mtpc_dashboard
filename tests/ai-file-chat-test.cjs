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
    if (url === 'api/ai-file.php') {
      assert.equal(options.headers['X-MTPC-File-Request'], '1');
      assert.equal(await options.body.get('file').text(), 'original document');
      calls.push('process');
      return { ok: true, async json() { return {ok:true, summary:'Tóm tắt <script>literal</script>', file:{name:'result.txt',mime:'text/plain',base64:btoa('new document')}}; } };
    }
    if (url === 'api/moodle-quiz-file.php') {
      assert.equal(options.headers['X-MTPC-Quiz-File-Request'], '1'); calls.push('quiz-draft');
      return {ok:true, async json(){return{ok:true,message:'Đã tạo bản nháp.',draft:{title:'Kiểm tra Python',intro:'Đọc kỹ câu hỏi',summary:'2 câu hợp lệ',questions:[{type:'multichoice',name:'Câu 1',questiontext:'2 + 2 bằng bao nhiêu?',defaultmark:1,answers:[{text:'4',fraction:1},{text:'3',fraction:0}]},{type:'truefalse',name:'Câu 2',questiontext:'Python là ngôn ngữ lập trình.',defaultmark:1,answers:[{text:'true',fraction:1},{text:'false',fraction:0}]}]}}}};
    }
    if (url === 'api/moodle.php?action=create-quiz-from-questions') {
      const body=JSON.parse(options.body);assert.equal(body.courseid,7);assert.equal(body.questions.length,2);calls.push('quiz-create');
      return {ok:true,async json(){return{ok:true,message:'Đã tạo bài kiểm tra Moodle và nhập 2 câu hỏi.'}}};
    }
    throw new Error('Unexpected URL '+url);
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
  context.window.MTPC_ADMIN_FILE_CHAT.onSelectFile(new File(['original document'], 'questions.txt'));
  await context.window.MTPC_ADMIN_FILE_CHAT.runTool('draft_quiz_from_file',{instruction:'Tạo bài kiểm tra',course_id:7,quiz_name:'Kiểm tra Python'});
  assert.equal(calls.at(-1),'quiz-draft');assert.equal(cards.length,2);await cards[1].children[5].onclick();assert.equal(calls.at(-1),'quiz-create');assert.equal(selectedFile,null);
  await sendText('đăng file này lên Moodle');
  assert.equal(calls.at(-1), 'original-send');
  assert.equal(context.window.MTPC_ADMIN_FILE_CHAT.handlesTool('zalo_group_action'), false);
  context.window.MTPC_ADMIN_FILE_CHAT.onSelectFile(new File(['original document'], 'source.txt'));
  context.fetch = async () => ({ok:false, json:async()=>({ok:false,error:'Hạn mức'})});
  await sendText('Tóm tắt file');
  assert(messages.includes('Hạn mức')); assert.equal(cards.length, 2);
  cards[0].children[3].onclick(); assert.equal(selectedFile.name, 'result.txt');
  console.log('PASS: file processing, safe result card, reviewed quiz draft, confirmed Moodle creation, error handling');
})().catch(error => { console.error(error); process.exitCode = 1; });
