(function(){
  'use strict';
  var button=document.getElementById('knowledgeUploadButton');
  var input=document.getElementById('knowledgeUploadInput');
  var addTextButton=document.getElementById('knowledgeAddTextButton');
  var refreshButton=document.getElementById('knowledgeRefreshButton');
  var list=document.getElementById('serverKnowledgeList');
  var count=document.getElementById('knowledgeServerCount');
  var status=document.getElementById('knowledgeServerStatus');
  var badge=document.getElementById('knowledgeServerBadge');
  var unansweredList=document.getElementById('knowledgeUnansweredList');
  var unansweredCount=document.getElementById('knowledgeUnansweredCount');
  if(!button||!input||!list)return;

  function esc(value){return String(value==null?'':value).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[c]})}
  function message(title,detail,error){status.querySelector('strong').textContent=title;status.querySelector('span:not(.notice-icon)').textContent=detail||'';status.classList.toggle('moodle-config-error',!!error)}
  function request(action,options){var config=options||{},url='api/knowledge.php?action='+encodeURIComponent(action);return fetch(url,config).then(function(response){return response.text().then(function(raw){var data={};try{data=raw?JSON.parse(raw):{}}catch(ignore){}if(!response.ok||data.ok===false)throw new Error(data.error||'Không thể kết nối kho dữ liệu AI.');return data})})}
  function postJson(action,data){return request(action,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data||{})})}

  function render(data){
    var items=data.sources||[];
    count.textContent=(data.active_source_count||0)+' đã duyệt · '+(items.length-(data.active_source_count||0))+' bản nháp';
    badge.textContent='● '+(data.chunk_count||0)+' đoạn đang dùng';
    list.innerHTML=items.length?items.map(function(item){
      var state=item.active?'Đang dùng':'Chờ duyệt';
      var actions='<label class="check-row" style="white-space:nowrap"><input type="checkbox" data-knowledge-toggle="'+esc(item.id)+'" '+(item.active?'checked':'')+'> Dùng cho AI</label>';
      if(item.origin==='upload')actions+='<button class="btn btn-sm" data-knowledge-delete="'+esc(item.id)+'" data-knowledge-title="'+esc(item.title)+'">Xóa</button>';
      return '<div class="source-item" style="display:block"><div style="display:flex;align-items:flex-start;gap:10px"><span class="source-type">'+(item.active?'✦':'○')+'</span><span style="min-width:0;flex:1"><h4>'+esc(item.title)+'</h4><p>'+esc(item.type)+' · '+esc(item.year||'Chưa rõ năm')+' · '+state+(item.updated_by?' · '+esc(item.updated_by):'')+'</p></span><span class="actions">'+actions+'</span></div><details style="margin:10px 0 0 40px"><summary style="cursor:pointer;color:#376b4c;font-size:11px;font-weight:800">Xem nội dung AI đã đọc</summary><p style="margin-top:9px;white-space:pre-wrap;line-height:1.55">'+esc(item.excerpt||'Không có nội dung xem trước.')+'</p></details></div>';
    }).join(''):'<div class="empty">Chưa có nguồn kiến thức trên máy chủ.</div>';
  }

  function renderUnanswered(data){
    var items=data.items||[];
    unansweredCount.textContent=items.length+' câu gần đây';
    unansweredList.innerHTML=items.length?items.map(function(item){return '<div class="source-item"><span class="source-type">?</span><span style="min-width:0;flex:1"><h4>'+esc(item.question)+'</h4><p>'+esc(item.channel==='zalo'?'Zalo':'Website')+' · '+esc(item.created_at||'Chưa rõ thời gian')+'</p></span><button class="btn btn-sm" data-answer-question="'+esc(item.question)+'">Bổ sung</button></div>'}).join(''):'<div class="empty">Chưa có câu hỏi nào bị bỏ qua.</div>';
  }

  function load(){
    Promise.all([request('list'),request('unanswered')]).then(function(results){render(results[0]);renderUnanswered(results[1]);message('Kho dữ liệu Agent đã kết nối',(results[0].active_source_count||0)+' nguồn đã được duyệt cho AI sử dụng.',false)}).catch(function(error){message('Chưa kết nối được kho dữ liệu Agent',error.message,true);list.innerHTML='<div class="empty">'+esc(error.message)+'</div>';if(unansweredList)unansweredList.innerHTML='<div class="empty">'+esc(error.message)+'</div>';count.textContent='Không tải được'})
  }

  function saveText(question){
    var title=window.prompt('Tên nguồn kiến thức:','Câu trả lời tuyển sinh');if(!title)return;
    var suggested=question?'Câu hỏi: '+question+'\nTrả lời đã được nhà trường xác nhận: ':'';
    var text=window.prompt('Nhập nội dung đã được xác nhận:',suggested);if(!text||text.trim().length<20)return;
    postJson('save-text',{title:title,type:'FAQ',text:text}).then(function(data){message('Đã lưu bản nháp',data.message,false);load()}).catch(function(error){message('Không lưu được nội dung',error.message,true)})
  }

  button.onclick=function(){input.click()};
  if(addTextButton)addTextButton.onclick=function(){saveText('')};
  if(refreshButton)refreshButton.onclick=load;
  input.onchange=function(){
    var files=Array.prototype.slice.call(input.files||[]);if(!files.length)return;button.disabled=true;var done=0;
    message('Đang đọc '+files.length+' tài liệu','File sẽ được lưu thành bản nháp để kiểm tra trước khi sử dụng.',false);
    files.reduce(function(chain,file){return chain.then(function(){var body=new FormData();body.append('file',file);body.append('type','Tuyển sinh');return request('upload',{method:'POST',body:body}).then(function(){done++;message('Đã tạo '+done+'/'+files.length+' bản nháp',file.name,false)})})},Promise.resolve()).then(function(){input.value='';button.disabled=false;load()}).catch(function(error){input.value='';button.disabled=false;message('Không nhập được tài liệu',error.message,true);load()})
  };

  list.addEventListener('change',function(event){
    var checkbox=event.target.closest('[data-knowledge-toggle]');if(!checkbox)return;
    if(checkbox.checked&&!window.confirm('Bạn đã kiểm tra nội dung trích xuất và muốn cho Agent sử dụng nguồn này?')){checkbox.checked=false;return}
    checkbox.disabled=true;postJson('toggle',{id:checkbox.getAttribute('data-knowledge-toggle'),active:checkbox.checked}).then(function(data){message(checkbox.checked?'Đã duyệt nguồn':'Đã tạm ngưng nguồn',data.message,false);load()}).catch(function(error){checkbox.checked=!checkbox.checked;checkbox.disabled=false;message('Không đổi được trạng thái',error.message,true)})
  });
  list.addEventListener('click',function(event){
    var remove=event.target.closest('[data-knowledge-delete]');if(!remove)return;
    if(!window.confirm('Xóa nguồn “'+remove.getAttribute('data-knowledge-title')+'”? Thao tác này sẽ loại nguồn khỏi kho Agent.'))return;
    remove.disabled=true;postJson('delete',{id:remove.getAttribute('data-knowledge-delete')}).then(function(data){message('Đã xóa nguồn',data.message,false);load()}).catch(function(error){remove.disabled=false;message('Không xóa được nguồn',error.message,true)})
  });
  if(unansweredList)unansweredList.addEventListener('click',function(event){var answer=event.target.closest('[data-answer-question]');if(answer)saveText(answer.getAttribute('data-answer-question'))});
  load();
})();
