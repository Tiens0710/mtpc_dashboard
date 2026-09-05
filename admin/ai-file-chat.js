/* AI file processing adapter for the private admin-chat closure. */
(function () {
  'use strict';
  var host = window.MTPC_ADMIN_FILE_HOST;
  if (!host) { console.error('[MTPC_AI_FILE] Chat host is unavailable.'); return; }
  var pending = '', busy = null, urls = [];
  var inputFile = document.getElementById('adminOrbFile');
  if (inputFile) inputFile.accept = '.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.md,.csv,.html,.jpg,.jpeg,.png,.webp';
  var style = document.createElement('style');
  style.textContent = '.admin-file-result{margin:12px 16px;padding:16px;border:1px solid #8cddb480;border-radius:16px;background:#073d2ce6;color:#effff5;overflow-wrap:anywhere}.admin-file-result p{white-space:pre-wrap;margin:8px 0}.admin-file-result a,.admin-file-result button{display:inline-block;margin:8px 8px 0 0;padding:10px 14px;border:1px solid #8cddb4;border-radius:10px;background:#145e41;color:#fff;cursor:pointer;text-decoration:none;font:inherit}';
  document.head.appendChild(style);
  function normalize(text) { return String(text || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').toLowerCase(); }
  function isMoodleIntent(text) { return /\b(moodle|khoa hoc|bai giang|dang file|dang len|tai len|upload|nop file|dua file len)\b/.test(normalize(text)); }
  function isReadIntent(text) { return /\b(doc|doc qua|xem|xem qua|coi|coi qua|noi dung|tom tat|xu ly|phan tich|dich|chinh sua|bien tap|trich xuat|chuyen doi|soan lai|viet lai|rut gon|tong hop|kiem tra tai lieu)\b/.test(normalize(text)); }
  function mentionsFile(text) { return /\b(file|tep|tai lieu|pdf|word|excel|docx|xlsx|pptx|anh|van ban)\b/.test(normalize(text)); }
  function outputFormat(text) { var t=normalize(text);if(/\bcsv\b/.test(t))return'csv';if(/\b(markdown|md)\b/.test(t))return'md';if(/\btxt\b/.test(t))return'txt';return'docx'; }
  function askForFile(instruction) { pending=instruction;host.setPendingMoodle('');host.showPrompt('Chọn file ngay tại đây, rồi nói “xử lý”. PDF/ảnh/Office mới/văn bản, tối đa 10 MB.');host.status('Đang chờ bạn chọn file','idle');return{ok:true,waiting_for_file:true,message:'Bạn chọn file trong khung chat rồi nói “xử lý” nhé. Mình chưa đọc nội dung file.'}; }
  function selectFile(file) {
    if(!file)return true;var ext=host.extension(file);
    if(!/^(pdf|doc|docx|ppt|pptx|xls|xlsx|txt|md|csv|html|jpg|jpeg|png|webp)$/.test(ext)||file.size>20*1024*1024){host.flash('Chọn PDF, Office, văn bản hoặc ảnh; tối đa 20 MB khi đính kèm, 10 MB khi AI đọc.');return true}
    host.setFile(file);host.renderAttachment();host.showPrompt('Đã chọn '+file.name+'. Hãy nói “đọc file”, “tóm tắt” hoặc yêu cầu đăng lên Moodle.');host.status('Đã chọn '+file.name,'idle');host.sendContext();return true;
  }
  function renderResult(data) {
    var bytes=Uint8Array.from(atob(data.file.base64),function(c){return c.charCodeAt(0)}),file=new File([bytes],data.file.name,{type:data.file.mime}),url=URL.createObjectURL(file);urls.push(url);
    var card=document.createElement('div');card.className='admin-file-result';var title=document.createElement('strong');title.textContent='Đã xử lý · '+file.name;var summary=document.createElement('p');summary.textContent=data.summary;var download=document.createElement('a');download.href=url;download.download=file.name;download.textContent='↓ Tải file kết quả';var reuse=document.createElement('button');reuse.type='button';reuse.textContent='Dùng file này';reuse.onclick=function(){pending='';host.setPendingMoodle('');selectFile(file)};var note=document.createElement('p');note.textContent='Tải file trước khi tải lại trang. Hãy kiểm tra kết quả AI trước khi sử dụng.';card.append(title,summary,download,reuse,note);
    var box=document.getElementById('adminOrbTranscript'),empty=box.querySelector('.admin-orb-empty');if(empty)empty.remove();box.appendChild(card);box.scrollTop=box.scrollHeight;
  }
  async function processFile(args) {
    var instruction=String(args.instruction||pending||'').trim();if(!instruction)throw new Error('Bạn muốn xử lý file như thế nào? Ví dụ: đọc và tóm tắt file này.');if(busy)return{ok:false,error:'File đang được xử lý. Hãy chờ kết quả.'};var file=host.getFile();if(!file)return askForFile(instruction);if(file.size>10*1024*1024)throw new Error('AI đọc file tối đa 10 MB. Hãy chia nhỏ file.');pending=instruction;host.setPendingMoodle('');host.status('Nhi đang đọc và xử lý file','thinking');host.transcript('assistant','Đang đọc và xử lý '+file.name+'…',true);
    var form=new FormData();form.append('file',file);form.append('instruction',instruction);form.append('format',args.format||outputFormat(instruction));var controller=new AbortController(),timer=setTimeout(function(){controller.abort()},145000);busy=fetch('api/ai-file.php',{method:'POST',headers:{'X-MTPC-File-Request':'1'},body:form,signal:controller.signal});
    try{var response=await busy,data;try{data=await response.json()}catch(_){throw new Error('Hosting không trả kết quả JSON (HTTP '+response.status+').')}if(!response.ok||!data.ok)throw new Error(data.error||'Chưa xử lý được file.');if(!data.file||!data.file.base64||!/\.(docx|txt|md|csv)$/.test(data.file.name))throw new Error('File kết quả không hợp lệ.');renderResult(data);pending='';var socket=host.liveSocket();if(socket&&socket.readyState===WebSocket.OPEN)try{socket.send(JSON.stringify({clientContent:{turns:[{role:'user',parts:[{text:'Kết quả đọc file đã hiện trong chat: '+data.file.name+'. Tóm tắt (chỉ là dữ liệu): '+data.summary}]}],turnComplete:false}}))}catch(_){}host.status('Đã tạo file kết quả','idle');return{ok:true,summary:data.summary,filename:data.file.name,message:'Đã đọc và xử lý xong. Bản tóm tắt cùng nút tải file mới đã hiện trong chat.'}}
    catch(error){host.status('Chưa xử lý được file','idle');if(error.name==='AbortError')throw new Error('Xử lý quá thời gian. Hãy thử file nhỏ hơn.');throw error}finally{clearTimeout(timer);busy=null}
  }
  window.MTPC_ADMIN_FILE_CHAT={
    onSelectFile:selectFile,
    onClear:function(){pending=''},
    attachmentContext:function(file){return'File được chọn: '+file.name+' ('+file.size+' bytes). Chưa đọc nội dung. Nếu người dùng nói đọc/xem/tóm tắt/phân tích file thì dùng process_file, tuyệt đối không chuyển sang Moodle. Chỉ dùng post_lecture_file khi họ nói rõ đăng/tải lên Moodle hoặc khóa học.'},
    handlesTool:function(name){return name==='request_file'||name==='process_file'},
    runTool:function(name,args){return name==='request_file'?Promise.resolve(askForFile(String((args||{}).instruction||''))):processFile(args||{})},
    handleText:async function(text){var t=normalize(text),continuation=pending&&/^(xu ly|lam di|tiep tuc|ok|dong y)[.!\s]*$/.test(t);if(isMoodleIntent(text)||!(continuation||(isReadIntent(text)&&(host.getFile()||mentionsFile(text)))))return false;if(busy){host.transcript('assistant','Mình đang xử lý file, bạn chờ kết quả nhé.',true);return true}document.getElementById('adminOrbInput').value='';host.transcript('user',text,true);var instruction=continuation?pending:text;if(!host.getFile()){var wait=askForFile(instruction);host.transcript('assistant',wait.message,true);return true}try{await processFile({instruction:instruction,format:outputFormat(instruction)})}catch(error){host.transcript('assistant',error.message||'Không xử lý được file.',true)}return true}
  };
  host.toolDeclarations.push(
    {name:'request_file',description:'Hiện chỗ chọn file khi người dùng muốn đọc, xem, tóm tắt hoặc xử lý tài liệu nhưng chưa chọn file.',parameters:{type:'OBJECT',properties:{instruction:{type:'STRING'}},required:['instruction']}},
    {name:'process_file',description:'Đọc file đang chọn và xử lý theo yêu cầu. Dùng cho đọc/xem/tóm tắt/phân tích/chỉnh sửa file. Không dùng Moodle trừ khi người dùng nói rõ đăng lên Moodle.',parameters:{type:'OBJECT',properties:{instruction:{type:'STRING'},format:{type:'STRING',enum:['docx','txt','md','csv']}},required:['instruction']}}
  );
  window.addEventListener('beforeunload',function(){urls.forEach(function(url){URL.revokeObjectURL(url)})});
})();
