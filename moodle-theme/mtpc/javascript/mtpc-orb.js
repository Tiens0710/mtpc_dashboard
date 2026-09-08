(function() {
    'use strict';

    if (!window.M || !M.cfg || !M.cfg.sesskey || document.getElementById('mtpcOrb')) return;
    if (Number(M.cfg.userId) === 0 || document.body.classList.contains('notloggedin')) return;

    var root = document.createElement('section');
    root.id = 'mtpcOrb';
    root.className = 'mtpc-orb';
    root.setAttribute('aria-label', 'Trợ lý Moodle Nhi');
    root.innerHTML =
        '<div class="mtpc-orb-backdrop" aria-hidden="true"></div>' +
        '<button type="button" class="mtpc-orb-launch ai-orb-stage" data-voice-state="idle" aria-label="Bấm Orb để nói với Nhi" aria-expanded="false">' +
            '<span class="ai-orb" aria-hidden="true"><span class="ai-orb-field"><i class="ai-orb-blob"></i><i class="ai-orb-blob"></i><i class="ai-orb-blob"></i></span>' +
                '<span class="ai-orb-rings"><i class="ai-orb-ring"></i><i class="ai-orb-ring"></i><i class="ai-orb-ring"></i></span>' +
                '<span class="ai-orb-particles"><i class="ai-orb-particle" style="--angle:14deg;--arc-speed:5.2s"></i><i class="ai-orb-particle" style="--angle:126deg;--arc-speed:6.4s"></i><i class="ai-orb-particle" style="--angle:246deg;--arc-speed:7.1s"></i></span>' +
                '<span class="ai-orb-eq"><i class="ai-eq-bar"></i><i class="ai-eq-bar"></i><i class="ai-eq-bar"></i><i class="ai-eq-bar"></i><i class="ai-eq-bar"></i><i class="ai-eq-bar"></i><i class="ai-eq-bar"></i></span>' +
                '<span class="ai-orb-core"></span></span>' +
        '</button>' +
        '<div class="mtpc-orb-voice-copy" id="mtpcOrbVoiceStage" role="dialog" aria-modal="false" aria-label="Trò chuyện với Nhi">' +
            '<div class="mtpc-orb-voice-head"><div><strong>Nhi · Trợ lý học tập</strong><p id="mtpcOrbVoiceStatus" role="status">Chạm Orb để bắt đầu nói</p></div></div>' +
            '<div class="mtpc-orb-voice-transcript" aria-live="polite" aria-label="Nội dung cuộc trò chuyện"></div>' +
            '<form class="mtpc-orb-voice-form"><label class="sr-only" for="mtpcOrbVoiceInput">Nhập yêu cầu cho Nhi</label>' +
                '<input id="mtpcOrbVoiceInput" name="message" type="text" autocomplete="off" placeholder="Nhập yêu cầu nếu bạn không dùng giọng nói…">' +
                '<button type="submit" aria-label="Gửi yêu cầu">➜</button></form>' +
        '</div>' +
        '<button type="button" class="mtpc-orb-voice-close" aria-label="Đóng Orb">×</button>' +
        '<button type="button" class="mtpc-orb-chat-toggle" aria-label="Mở Orb" aria-controls="mtpcOrbVoiceStage" aria-expanded="false"><span aria-hidden="true">⌨</span></button>';
    document.body.appendChild(root);

    var launch = root.querySelector('.mtpc-orb-launch');
    var chatToggle = root.querySelector('.mtpc-orb-chat-toggle');
    var closeButton = root.querySelector('.mtpc-orb-voice-close');
    var status = root.querySelector('#mtpcOrbVoiceStatus');
    var transcript = root.querySelector('.mtpc-orb-voice-transcript');
    var form = root.querySelector('.mtpc-orb-voice-form');
    var input = root.querySelector('#mtpcOrbVoiceInput');
    var send = form.querySelector('button[type="submit"]');
    var liveSocket = null, liveReady = false, liveConnecting = null, audioContext = null;
    var micStream = null, micSource = null, micProcessor = null, micAnalyser = null, voiceFrame = null, silentGain = null;
    var playbackCursor = 0, playbackSources = [], greetingSent = false;
    var groundedTurn = {required: false, toolUsed: false, retryCount: 0, userText: ''};
    var drafts = {user: {text: '', node: null}, assistant: {text: '', node: null}};
    var VIEW_STORAGE_KEY = 'mtpcMoodleOrbViewV1:' + M.cfg.sesskey;

    var STUDENT_TOOLS = [{functionDeclarations: [{
        name: 'moodle_student_action',
        description: 'Tra cứu dữ liệu Moodle chỉ thuộc tài khoản học sinh đang đăng nhập. Công cụ chỉ đọc, không quản trị hoặc thay đổi dữ liệu.',
        parameters: {type: 'OBJECT', properties: {
            action: {type: 'STRING', enum: ['status', 'courses', 'today_summary', 'due_work', 'open_course', 'open_activity', 'progress_summary', 'grades_summary', 'course_contents', 'assignments', 'assignment', 'quizzes', 'grades', 'quiz_attempts', 'quiz_grades', 'course_completion', 'activity_completion', 'forums', 'announcements', 'calendar_events']},
            course_name: {type: 'STRING', description: 'Tên tự nhiên của khóa học mà học sinh đã ghi danh.'},
            course_id: {type: 'INTEGER'}, assignment_name: {type: 'STRING'}, assignment_id: {type: 'INTEGER'},
            quiz_name: {type: 'STRING'}, quiz_id: {type: 'INTEGER'}, activity_name: {type: 'STRING'},
            course_module_id: {type: 'INTEGER'}, announcement_title: {type: 'STRING'}, count: {type: 'INTEGER'},
            days: {type: 'INTEGER', description: 'Số ngày sắp tới cần xem, từ 1 đến 30.'},
            activity_type: {type: 'STRING', enum: ['assign', 'quiz', 'page', 'url', 'forum', 'resource']}
        }, required: ['action']}
    }]}];

    var LIVE_SYSTEM_INSTRUCTION =
        'Bạn là Nhi, trợ lý học tập đang trò chuyện trực tiếp trong Moodle của Trường Trung cấp Miền Tây. ' +
        'Luôn nói tiếng Việt tự nhiên, rõ dấu, thân thiện như một trợ lý nữ người Việt miền Nam. ' +
        'Trước khi phát âm, chuyển nội dung sang văn nói: câu ngắn, mỗi câu một ý, ngắt nghỉ tự nhiên, tốc độ vừa phải. ' +
        'Không đọc markdown, địa chỉ trang web, tên biến, mã kỹ thuật hoặc danh sách dài thành lời. Ưu tiên trả lời từ một đến ba câu. Không nhắc tên mô hình hay trạng thái kỹ thuật. ' +
        'Phạm vi duy nhất của bạn là dữ liệu và các trang nằm trong Moodle đang mở. Không hỗ trợ website trường, tư vấn tuyển sinh, email, Zalo hoặc dịch vụ bên ngoài Moodle. Nếu học sinh hỏi ngoài phạm vi, nói ngắn rằng bạn chỉ hỗ trợ việc học trong Moodle. Mỗi lượt người dùng, kể cả câu nối tiếp như “xem hết”, “mở nó” hoặc “còn gì nữa”, đều phải gọi moodle_student_action lại trước khi trả lời. Mọi thông tin thực tế về khóa học, thông báo, lịch, bài tập, bài kiểm tra, điểm và tiến độ phải dựa duy nhất trên kết quả công cụ của chính lượt hiện tại; không suy đoán hoặc dùng trí nhớ hội thoại thay cho dữ liệu Moodle. Không tự hướng học sinh sang website bên ngoài, kể cả khi nội dung thông báo có nhắc đến website. ' +
        'Chỉ dùng moodle_student_action để đọc các khóa học mà chính học sinh đang đăng nhập đã ghi danh. Dùng announcements không kèm khóa học khi em muốn xem thông báo trong tất cả khóa học đã ghi danh. Dùng today_summary khi em hỏi hôm nay hoặc sắp tới cần làm gì; due_work cho bài sắp đến hạn hoặc quá hạn; progress_summary cho tiến độ; grades_summary cho tổng kết điểm; open_course khi em yêu cầu mở khóa học; open_activity khi em yêu cầu mở bài học, bài tập hoặc bài kiểm tra cụ thể. Các action mở trang chỉ được dùng với dữ liệu đã xác minh và không thay đổi Moodle. Chỉ nói đã mở hoặc đã chuyển trang khi kết quả công cụ có redirect_url. ' +
        'Ưu tiên tên tự nhiên và không tự đoán dữ liệu. Tuyệt đối không tạo, sửa, xóa, ghi danh, chấm điểm, gửi tin, xem người dùng khác hoặc dữ liệu của học sinh khác. ' +
        'Nếu được yêu cầu quản trị Moodle, giải thích ngắn rằng Orb học sinh chỉ có quyền tra cứu dữ liệu học tập của chính em.';
    var REALTIME_INPUT_CONFIG = {automaticActivityDetection: {disabled: false, startOfSpeechSensitivity: 'START_SENSITIVITY_LOW', endOfSpeechSensitivity: 'END_SENSITIVITY_LOW', prefixPaddingMs: 250, silenceDurationMs: 1400}};

    function setState(stateName, message) {
        launch.setAttribute('data-voice-state', stateName || 'idle');
        launch.setAttribute('aria-label', stateName === 'listening' ? 'Nhi đang lắng nghe' : stateName === 'speaking' ? 'Nhi đang trả lời' : stateName === 'thinking' ? 'Nhi đang xử lý' : 'Bấm Orb để nói với Nhi');
        status.textContent = message || (stateName === 'listening' ? 'Nhi đang lắng nghe' : stateName === 'speaking' ? 'Nhi đang trả lời' : stateName === 'thinking' ? 'Nhi đang xử lý' : 'Chạm Orb để bắt đầu nói');
        root.classList.toggle('is-listening', stateName === 'listening');
        root.classList.toggle('is-speaking', stateName === 'speaking');
        root.classList.toggle('is-thinking', stateName === 'thinking');
    }

    function getAudioContext() {
        if (!audioContext || audioContext.state === 'closed') audioContext = new (window.AudioContext || window.webkitAudioContext)();
        return audioContext;
    }
    function fromBase64(value) {
        var binary = window.atob(value), bytes = new Uint8Array(binary.length);
        for (var i = 0; i < binary.length; i += 1) bytes[i] = binary.charCodeAt(i);
        return new Int16Array(bytes.buffer);
    }
    function toBase64(buffer) {
        var bytes = new Uint8Array(buffer), binary = '', chunk = 8192;
        for (var i = 0; i < bytes.length; i += chunk) binary += String.fromCharCode.apply(null, bytes.subarray(i, Math.min(i + chunk, bytes.length)));
        return window.btoa(binary);
    }
    function downsample(samples, inputRate) {
        var ratio = inputRate / 16000, length = Math.max(1, Math.floor(samples.length / ratio)), result = new Int16Array(length);
        for (var i = 0; i < length; i += 1) {
            var start = Math.floor(i * ratio), end = Math.min(samples.length, Math.floor((i + 1) * ratio)), sum = 0, count = 0;
            for (var j = start; j < end; j += 1) { sum += samples[j]; count += 1; }
            var sample = Math.max(-1, Math.min(1, count ? sum / count : 0));
            result[i] = sample < 0 ? sample * 32768 : sample * 32767;
        }
        return result;
    }
    function playAudio(base64) {
        if (!base64) return;
        var context = getAudioContext(), pcm = fromBase64(base64), floats = new Float32Array(pcm.length);
        for (var i = 0; i < pcm.length; i += 1) floats[i] = pcm[i] / 32768;
        var buffer = context.createBuffer(1, floats.length, 24000);
        buffer.copyToChannel(floats, 0);
        var source = context.createBufferSource();
        source.buffer = buffer; source.connect(context.destination);
        var startAt = Math.max(context.currentTime + 0.025, playbackCursor);
        playbackCursor = startAt + buffer.duration; playbackSources.push(source);
        source.onended = function() {
            playbackSources = playbackSources.filter(function(item) { return item !== source; });
            if (!playbackSources.length) setState('listening', 'Nhi đang lắng nghe');
        };
        setState('speaking', 'Nhi đang trả lời'); source.start(startAt); context.resume().catch(function() {});
    }

    function mergeTranscript(current, incoming) {
        incoming = String(incoming || '');
        if (!current) return incoming;
        if (incoming === current || incoming.indexOf(current) === 0) return incoming;
        return current + incoming;
    }
    function finishTranscript(role) {
        var draft = drafts[role] || drafts.assistant;
        if (!draft.node) return;
        draft.node.classList.remove('is-draft'); draft.text = ''; draft.node = null;
    }
    function appendTranscript(role, text, finished) {
        if (!text) return;
        var draft = drafts[role] || drafts.assistant;
        if (!draft.node) {
            draft.node = document.createElement('div');
            draft.node.className = 'mtpc-orb-message ' + (role === 'user' ? 'user ' : '') + 'is-draft';
            var label = document.createElement('span'), content = document.createElement('span');
            label.className = 'mtpc-orb-role'; label.textContent = role === 'user' ? 'Bạn' : 'Nhi';
            content.className = 'mtpc-orb-message-text'; draft.node.appendChild(label); draft.node.appendChild(content); transcript.appendChild(draft.node);
        }
        draft.text = mergeTranscript(draft.text, text);
        draft.node.querySelector('.mtpc-orb-message-text').textContent = draft.text;
        transcript.scrollTop = transcript.scrollHeight;
        if (finished) finishTranscript(role);
    }
    function beginGroundedTurn(text) {
        groundedTurn.required = true;
        groundedTurn.toolUsed = false;
        groundedTurn.retryCount = 0;
        groundedTurn.userText = String(text || '').trim();
    }
    function removeAssistantDraft() {
        if (drafts.assistant.node && drafts.assistant.node.parentNode) drafts.assistant.node.parentNode.removeChild(drafts.assistant.node);
        drafts.assistant = {text: '', node: null};
    }
    function retryUngroundedTurn() {
        removeAssistantDraft();
        if (!liveSocket || liveSocket.readyState !== WebSocket.OPEN) return;
        if (groundedTurn.retryCount < 1) {
            groundedTurn.retryCount += 1;
            setState('thinking', 'Nhi đang kiểm tra lại trên Moodle');
            liveSocket.send(JSON.stringify({clientContent: {turns: [{role: 'user', parts: [{text:
                'Bắt buộc gọi moodle_student_action để kiểm tra Moodle cho yêu cầu vừa rồi trước khi trả lời. Không được trả lời từ lịch sử. Yêu cầu cần kiểm tra: ' + (groundedTurn.userText || 'yêu cầu vừa nói')
            }]}], turnComplete: true}}));
            return;
        }
        appendTranscript('assistant', 'Nhi chưa xác minh được dữ liệu này trên Moodle nên không muốn trả lời đoán. Em thử hỏi lại rõ tên khóa học hoặc nội dung cần xem nhé.', true);
        groundedTurn = {required: false, toolUsed: false, retryCount: 0, userText: ''};
        setState('listening', 'Nhi đang lắng nghe');
    }
    function persistOrbView(forceOpen) {
        try {
            var messages = Array.prototype.slice.call(transcript.querySelectorAll('.mtpc-orb-message:not(.is-draft)')).slice(-12).map(function(node) {
                var content = node.querySelector('.mtpc-orb-message-text');
                return {role: node.classList.contains('user') ? 'user' : 'assistant', text: String(content ? content.textContent : '').slice(0, 2000)};
            }).filter(function(message) { return message.text !== ''; });
            window.sessionStorage.setItem(VIEW_STORAGE_KEY, JSON.stringify({
                open: forceOpen === true || root.classList.contains('is-voice-open'),
                messages: messages,
                savedAt: Date.now()
            }));
        } catch (error) {}
    }
    function restoreOrbView() {
        var saved;
        try { saved = JSON.parse(window.sessionStorage.getItem(VIEW_STORAGE_KEY) || 'null'); }
        catch (error) { window.sessionStorage.removeItem(VIEW_STORAGE_KEY); return; }
        if (!saved || !saved.open || !saved.savedAt || Date.now() - saved.savedAt > 15 * 60 * 1000) {
            window.sessionStorage.removeItem(VIEW_STORAGE_KEY);
            return;
        }
        (Array.isArray(saved.messages) ? saved.messages : []).forEach(function(message) {
            if (!message || (message.role !== 'user' && message.role !== 'assistant')) return;
            appendTranscript(message.role, String(message.text || '').slice(0, 2000), true);
        });
        greetingSent = Boolean(saved.messages && saved.messages.length);
        root.classList.add('is-voice-open');
        document.body.classList.add('mtpc-orb-voice-active');
        launch.setAttribute('aria-expanded', 'true');
        chatToggle.setAttribute('aria-expanded', 'true');
        setState('idle', 'Đã mở trang mới · nhập hoặc chạm Orb để tiếp tục');
        transcript.scrollTop = transcript.scrollHeight;
    }

    function meter(analyser) {
        if (voiceFrame) window.cancelAnimationFrame(voiceFrame);
        var data = new Uint8Array(analyser.fftSize);
        function tick() {
            if (!micAnalyser || !micStream) return;
            analyser.getByteTimeDomainData(data); var sum = 0;
            for (var i = 0; i < data.length; i += 1) { var value = (data[i] - 128) / 128; sum += value * value; }
            launch.style.setProperty('--voice-level', Math.min(0.95, Math.sqrt(sum / data.length) * 5.2).toFixed(3));
            voiceFrame = window.requestAnimationFrame(tick);
        }
        tick();
    }
    function stopMic() {
        if (voiceFrame) window.cancelAnimationFrame(voiceFrame);
        voiceFrame = null; micAnalyser = null; launch.style.setProperty('--voice-level', '0');
        if (micProcessor) { micProcessor.onaudioprocess = null; micProcessor.disconnect(); }
        if (micSource) micSource.disconnect(); if (silentGain) silentGain.disconnect();
        if (micStream) micStream.getTracks().forEach(function(track) { track.stop(); });
        micProcessor = null; micSource = null; silentGain = null; micStream = null;
    }
    function startMic() {
        if (micStream) return Promise.resolve();
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return Promise.reject(new Error('Microphone unavailable'));
        setState('thinking', 'Đang xin quyền microphone');
        return navigator.mediaDevices.getUserMedia({audio: {echoCancellation: true, noiseSuppression: true, autoGainControl: true}}).then(function(stream) {
            micStream = stream; var context = getAudioContext();
            return context.resume().then(function() {
                micAnalyser = context.createAnalyser(); micAnalyser.fftSize = 128; micAnalyser.smoothingTimeConstant = 0.72; meter(micAnalyser);
                micSource = context.createMediaStreamSource(stream); micProcessor = context.createScriptProcessor(2048, 1, 1); silentGain = context.createGain(); silentGain.gain.value = 0;
                micSource.connect(micAnalyser); micSource.connect(micProcessor); micProcessor.connect(silentGain); silentGain.connect(context.destination);
                micProcessor.onaudioprocess = function(event) {
                    if (!liveReady || !liveSocket || liveSocket.readyState !== WebSocket.OPEN) return;
                    var pcm = downsample(event.inputBuffer.getChannelData(0), context.sampleRate);
                    liveSocket.send(JSON.stringify({realtimeInput: {audio: {data: toBase64(pcm.buffer), mimeType: 'audio/pcm;rate=16000'}}}));
                };
                setState('listening', 'Nhi đang lắng nghe');
            });
        });
    }
    function readSocket(data, callback) {
        if (typeof data === 'string') return callback(data);
        if (data instanceof Blob) { var reader = new FileReader(); reader.onload = function() { callback(String(reader.result || '')); }; reader.readAsText(data); return; }
        if (data instanceof ArrayBuffer) return callback(new TextDecoder().decode(data));
        callback('');
    }

    function runStudentTool(name, args) {
        if (name !== 'moodle_student_action') return Promise.resolve({ok: false, error: 'Orb học sinh không được dùng công cụ quản trị.'});
        return fetch(M.cfg.wwwroot + '/local/mtpcbridge/moodle-orb.php?sesskey=' + encodeURIComponent(M.cfg.sesskey), {
            method: 'POST', headers: {'Content-Type': 'application/json'}, credentials: 'same-origin',
            body: JSON.stringify({mode: 'tool', name: name, args: args || {}})
        }).then(function(response) { return response.json().then(function(data) {
            if (!response.ok || !data.ok) throw new Error(data.error || 'Không tra cứu được dữ liệu Moodle.');
            return data.result;
        }); });
    }
    function sendToolResponses(calls) {
        if (!liveSocket || liveSocket.readyState !== WebSocket.OPEN) return;
        Promise.all(calls.map(function(call) {
            var args = {};
            try { args = typeof call.args === 'string' ? JSON.parse(call.args) : call.args || {}; }
            catch (error) { return {id: call.id, name: call.name, response: {result: {ok: false, error: 'Tham số công cụ không hợp lệ.'}}}; }
            return runStudentTool(call.name, args).then(function(result) {
                if (result && result.redirect_url && (result.open_course || result.navigate)) {
                    var base = new URL(M.cfg.wwwroot.replace(/\/$/, '') + '/');
                    var target = new URL(result.redirect_url, base);
                    if (target.origin === base.origin && target.pathname.indexOf(base.pathname) === 0) {
                        persistOrbView(true);
                        window.setTimeout(function() { window.location.assign(target.href); }, 350);
                    }
                }
                return {id: call.id, name: call.name, response: {result: result}};
            })
                .catch(function(error) { return {id: call.id, name: call.name, response: {result: {ok: false, error: error.message || 'Không thể tra cứu Moodle.'}}}; });
        })).then(function(functionResponses) {
            if (liveSocket && liveSocket.readyState === WebSocket.OPEN) liveSocket.send(JSON.stringify({toolResponse: {functionResponses: functionResponses}}));
        });
    }
    function handleLive(message) {
        if (message.error) { console.error('[MTPC_MOODLE_GEMINI_LIVE]', message.error); setState('idle', 'Nhi đang bận · em thử lại nhé'); return; }
        if (message.toolCall && message.toolCall.functionCalls) { groundedTurn.toolUsed = true; setState('thinking', 'Nhi đang tra cứu Moodle'); sendToolResponses(message.toolCall.functionCalls); return; }
        var content = message.serverContent; if (!content) return;
        var inputTranscription = content.inputTranscription || {}, outputTranscription = content.outputTranscription || {};
        if (inputTranscription.text) {
            if (!groundedTurn.required) beginGroundedTurn(inputTranscription.text);
            else groundedTurn.userText = mergeTranscript(groundedTurn.userText, inputTranscription.text);
            appendTranscript('user', inputTranscription.text, Boolean(inputTranscription.finished));
        }
        if (inputTranscription.finished) finishTranscript('user');
        var mayAnswer = !groundedTurn.required || groundedTurn.toolUsed;
        if (mayAnswer && outputTranscription.text) appendTranscript('assistant', outputTranscription.text, Boolean(outputTranscription.finished));
        if (mayAnswer && outputTranscription.finished) finishTranscript('assistant');
        var parts = content.modelTurn && content.modelTurn.parts ? content.modelTurn.parts : [];
        if (mayAnswer) parts.forEach(function(part) { if (part.inlineData && part.inlineData.data) playAudio(part.inlineData.data); if (part.text && !outputTranscription.text) appendTranscript('assistant', part.text, false); });
        if (content.turnComplete) {
            finishTranscript('user');
            if (groundedTurn.required && !groundedTurn.toolUsed) { retryUngroundedTurn(); return; }
            finishTranscript('assistant');
            groundedTurn = {required: false, toolUsed: false, retryCount: 0, userText: ''};
            if (!playbackSources.length) setState('listening', 'Nhi đang lắng nghe');
        }
    }

    function connectLive() {
        if (liveReady && liveSocket && liveSocket.readyState === WebSocket.OPEN) {
            if (!micStream) startMic().catch(function() { setState('idle', 'Em có thể nhập yêu cầu bằng văn bản'); });
            return Promise.resolve(true);
        }
        if (liveConnecting) return liveConnecting;
        setState('thinking', 'Đang kết nối với Nhi');
        liveConnecting = fetch(M.cfg.wwwroot + '/local/mtpcbridge/moodle-live-token.php?sesskey=' + encodeURIComponent(M.cfg.sesskey), {method: 'POST', headers: {'Content-Type': 'application/json'}, credentials: 'same-origin'})
            .then(function(response) { return response.json().then(function(data) { if (!response.ok || !data.token) throw new Error(data.error || 'Không lấy được phiên âm thanh.'); return data; }); })
            .then(function(token) { return new Promise(function(resolve, reject) {
                var done = false, timer = window.setTimeout(function() { if (!done) { done = true; reject(new Error('Live setup timeout')); } }, 15000);
                liveSocket = new WebSocket('wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1beta.GenerativeService.BidiGenerateContentConstrained?access_token=' + encodeURIComponent(token.token));
                liveSocket.addEventListener('open', function() { liveSocket.send(JSON.stringify({setup: {
                    model: 'models/' + (token.model || 'gemini-3.1-flash-live-preview'),
                    generationConfig: {responseModalities: ['AUDIO'], speechConfig: {voiceConfig: {prebuiltVoiceConfig: {voiceName: token.voice || 'Zephyr'}}}},
                    realtimeInputConfig: REALTIME_INPUT_CONFIG, tools: STUDENT_TOOLS,
                    toolConfig: {functionCallingConfig: {mode: 'VALIDATED', allowedFunctionNames: ['moodle_student_action']}},
                    inputAudioTranscription: {}, outputAudioTranscription: {}, sessionResumption: {},
                    systemInstruction: {parts: [{text: LIVE_SYSTEM_INSTRUCTION}]}
                }})); });
                liveSocket.addEventListener('message', function(event) { readSocket(event.data, function(raw) {
                    var message; try { message = JSON.parse(raw); } catch (error) { return; }
                    if (message.setupComplete && !done) {
                        done = true; window.clearTimeout(timer); liveReady = true;
                        window.__MTPC_MOODLE_GEMINI_LIVE__ = {model: token.model, voice: token.voice || 'Zephyr', audio: 'native-pcm', tools: ['moodle_student_action'], role: 'student'};
                        resolve(true);
                    }
                    handleLive(message);
                }); });
                liveSocket.addEventListener('error', function() { if (!done) { done = true; window.clearTimeout(timer); reject(new Error('Gemini Live socket error')); } });
                liveSocket.addEventListener('close', function() { liveReady = false; liveSocket = null; stopMic(); if (root.classList.contains('is-voice-open')) setState('idle', 'Kết nối đã đóng · chạm Orb để thử lại'); });
            }); })
            .then(function() { return startMic().catch(function(error) { console.warn('[MTPC_MOODLE_MIC_FALLBACK]', error); setState('idle', 'Em có thể nhập yêu cầu bằng văn bản'); return true; }); })
            .then(function() { return true; }).finally(function() { liveConnecting = null; });
        return liveConnecting;
    }

    function sendText() {
        var text = String(input.value || '').trim(); if (!text || send.disabled) return;
        input.value = ''; input.disabled = true; send.disabled = true; appendTranscript('user', text, true); beginGroundedTurn(text); setState('thinking', 'Nhi đang xử lý');
        getAudioContext().resume().catch(function() {});
        connectLive().then(function() {
            if (!liveSocket || liveSocket.readyState !== WebSocket.OPEN) throw new Error('Live socket unavailable');
            liveSocket.send(JSON.stringify({clientContent: {turns: [{role: 'user', parts: [{text: text}]}], turnComplete: true}}));
        }).catch(function(error) {
            console.error('[MTPC_MOODLE_TEXT_INPUT]', error); appendTranscript('assistant', 'Nhi chưa kết nối được. Em thử gửi lại sau ít giây nhé.', true); setState('idle', 'Chưa kết nối được với Nhi');
        }).finally(function() { input.disabled = false; send.disabled = false; input.focus(); });
    }
    function openOrb(focusInput) {
        root.classList.add('is-voice-open'); document.body.classList.add('mtpc-orb-voice-active'); launch.setAttribute('aria-expanded', 'true'); chatToggle.setAttribute('aria-expanded', 'true');
        if (!greetingSent) {
            greetingSent = true;
            appendTranscript('assistant', 'Chào em! Nhi sẵn sàng hỗ trợ tra cứu việc học của em trên Moodle.', true);
        }
        getAudioContext().resume().catch(function() {});
        connectLive().catch(function(error) { console.error('[MTPC_MOODLE_GEMINI_LIVE]', error); setState('idle', 'Nhi chưa kết nối được · em thử lại nhé'); });
        if (focusInput) window.setTimeout(function() { input.focus(); }, 0);
    }
    function closeLive() {
        stopMic(); liveReady = false; playbackSources.forEach(function(source) { try { source.stop(); } catch (error) {} }); playbackSources = []; playbackCursor = 0;
        if (liveSocket) { try { liveSocket.close(); } catch (error) {} liveSocket = null; }
        root.classList.remove('is-voice-open'); document.body.classList.remove('mtpc-orb-voice-active'); launch.setAttribute('aria-expanded', 'false'); chatToggle.setAttribute('aria-expanded', 'false');
        try { window.sessionStorage.removeItem(VIEW_STORAGE_KEY); } catch (error) {}
        setState('idle', 'Chạm Orb để bắt đầu nói'); launch.focus();
    }

    launch.addEventListener('click', function() { openOrb(false); });
    chatToggle.addEventListener('click', function() { openOrb(true); });
    closeButton.addEventListener('click', closeLive);
    form.addEventListener('submit', function(event) { event.preventDefault(); sendText(); });
    document.addEventListener('keydown', function(event) { if (event.key === 'Escape' && root.classList.contains('is-voice-open')) closeLive(); });
    window.addEventListener('pagehide', function() { if (root.classList.contains('is-voice-open')) persistOrbView(true); });
    restoreOrbView();
}());
