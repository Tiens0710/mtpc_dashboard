(function() {
    'use strict';

    if (!window.M || !M.cfg || !M.cfg.sesskey || document.getElementById('mtpcOrb')) return;

    var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
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
        '<div class="mtpc-orb-voice-copy" id="mtpcOrbVoiceStage" role="dialog" aria-label="Trò chuyện bằng giọng nói với Nhi">' +
            '<div class="mtpc-orb-voice-head"><div><strong>Nhi · Trợ lý học tập</strong><p id="mtpcOrbVoiceStatus">Chạm Orb để bắt đầu nói</p></div>' +
                '<button type="button" class="mtpc-orb-sound-toggle" aria-label="Tắt âm thanh phản hồi" aria-pressed="true">Âm thanh: Bật</button></div>' +
            '<div class="mtpc-orb-voice-transcript" aria-live="polite" aria-label="Nội dung cuộc trò chuyện"></div>' +
            '<form class="mtpc-orb-voice-form"><label class="sr-only" for="mtpcOrbVoiceInput">Nhập yêu cầu cho Nhi</label>' +
                '<input id="mtpcOrbVoiceInput" name="message" type="text" autocomplete="off" placeholder="Nhập yêu cầu nếu bạn không dùng giọng nói…">' +
                '<button type="submit" aria-label="Gửi yêu cầu">➜</button></form>' +
        '</div>' +
        '<button type="button" class="mtpc-orb-voice-close" aria-label="Đóng Orb">×</button>' +
        '<button type="button" class="mtpc-orb-chat-toggle" aria-label="Mở Orb để nhập văn bản" aria-controls="mtpcOrbVoiceStage" aria-expanded="false"><span aria-hidden="true">⌨</span></button>' +
        '<div class="mtpc-orb-panel" id="mtpcOrbPanel" hidden role="dialog" aria-labelledby="mtpcOrbTitle" aria-describedby="mtpcOrbStatus">' +
            '<header class="mtpc-orb-header"><div><h2 id="mtpcOrbTitle">Nhi · Trợ lý Moodle</h2><p>Chạm Orb để nói · tra cứu học tập của em</p></div>' +
                '<button type="button" class="mtpc-orb-close" aria-label="Đóng cuộc trò chuyện">×</button></header>' +
            '<div class="mtpc-orb-transcript" aria-live="polite" aria-label="Nội dung cuộc trò chuyện"></div>' +
            '<p id="mtpcOrbStatus" class="mtpc-orb-status" role="status">Sẵn sàng lắng nghe</p>' +
            '<form class="mtpc-orb-form"><label class="sr-only" for="mtpcOrbInput">Nhập yêu cầu cho Nhi</label>' +
                '<button type="button" class="mtpc-orb-mic" aria-label="Bắt đầu nói" title="Nói với Nhi">●</button>' +
                '<input id="mtpcOrbInput" name="message" type="text" autocomplete="off" placeholder="Hoặc nhập yêu cầu…">' +
                '<button type="submit" class="mtpc-orb-send" aria-label="Gửi yêu cầu">➜</button></form>' +
            '<div class="mtpc-orb-footer"><span class="mtpc-orb-live-dot" aria-hidden="true"></span><span>Giọng nói và văn bản dùng chung một cuộc trò chuyện</span></div>' +
        '</div>';
    document.body.appendChild(root);

    var launch = root.querySelector('.mtpc-orb-launch');
    var chatToggle = root.querySelector('.mtpc-orb-chat-toggle');
    var voiceClose = root.querySelector('.mtpc-orb-voice-close');
    var voiceStatus = root.querySelector('#mtpcOrbVoiceStatus');
    var voiceTranscript = root.querySelector('.mtpc-orb-voice-transcript');
    var soundToggle = root.querySelector('.mtpc-orb-sound-toggle');
    var voiceForm = root.querySelector('.mtpc-orb-voice-form');
    var voiceInput = root.querySelector('#mtpcOrbVoiceInput');
    var voiceSend = voiceForm.querySelector('button[type="submit"]');
    var panel = root.querySelector('.mtpc-orb-panel');
    var close = root.querySelector('.mtpc-orb-close');
    var mic = root.querySelector('.mtpc-orb-mic');
    var form = root.querySelector('.mtpc-orb-form');
    var input = root.querySelector('#mtpcOrbInput');
    var transcript = root.querySelector('.mtpc-orb-transcript');
    var status = root.querySelector('.mtpc-orb-status');
    var send = root.querySelector('.mtpc-orb-send');
    var busy = false;
    var recognition = null;
    var listening = false;
    var speechText = '';
    var soundEnabled = true;

    function setVoiceState(state, message) {
        launch.setAttribute('data-voice-state', state);
        launch.setAttribute('aria-label', state === 'listening' ? 'Đang nghe, bấm để dừng' : 'Bấm Orb để nói với Nhi');
        var stateMessage = message || (state === 'listening' ? 'Đang nghe…' : state === 'thinking' ? 'Nhi đang xử lý…' : state === 'speaking' ? 'Nhi đang trả lời…' : 'Sẵn sàng lắng nghe');
        status.textContent = stateMessage;
        voiceStatus.textContent = stateMessage;
        root.classList.toggle('is-thinking', state === 'thinking');
        root.classList.toggle('is-listening', state === 'listening');
        root.classList.toggle('is-speaking', state === 'speaking');
    }

    function addMessage(role, text) {
        var item = document.createElement('div');
        item.className = 'mtpc-orb-message ' + role;
        item.textContent = text;
        transcript.appendChild(item);
        transcript.scrollTop = transcript.scrollHeight;
        var voiceItem = item.cloneNode(true);
        voiceTranscript.appendChild(voiceItem);
        voiceTranscript.scrollTop = voiceTranscript.scrollHeight;
    }

    function ensureWelcome() {
        if (!transcript.children.length) addMessage('assistant', 'Chào em! Nhi có thể giúp tra cứu khóa học, bài học, bài tập, điểm và tiến độ của em trên Moodle.');
    }

    function addPendingActions() {
        var actions = document.createElement('div');
        actions.className = 'mtpc-orb-confirm';
        actions.innerHTML = '<button type="button" data-orb-confirm="XÁC NHẬN">Xác nhận</button><button type="button" data-orb-confirm="HỦY">Hủy</button>';
        transcript.appendChild(actions);
        transcript.scrollTop = transcript.scrollHeight;
    }

    function setOpen(open, focusInput) {
        panel.hidden = !open;
        launch.setAttribute('aria-expanded', open || root.classList.contains('is-voice-open') ? 'true' : 'false');
        chatToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        root.classList.toggle('is-open', open);
        if (open) {
            ensureWelcome();
            if (focusInput !== false) input.focus();
        } else if (!listening && focusInput !== false) {
            launch.focus();
        }
    }

    function setVoiceOpen(open) {
        root.classList.toggle('is-voice-open', open);
        document.body.classList.toggle('mtpc-orb-voice-active', open);
        launch.setAttribute('aria-expanded', open || !panel.hidden ? 'true' : 'false');
        if (open) {
            setOpen(false, false);
            ensureWelcome();
            window.setTimeout(function() { launch.focus(); }, 0);
        } else {
            if (window.speechSynthesis) window.speechSynthesis.cancel();
            setVoiceState('idle', 'Sẵn sàng lắng nghe');
            launch.focus();
        }
    }

    function speakReply(text) {
        if (!soundEnabled || !text || !window.speechSynthesis || !window.SpeechSynthesisUtterance) {
            setVoiceState('idle', 'Sẵn sàng lắng nghe');
            return;
        }
        window.speechSynthesis.cancel();
        window.speechSynthesis.resume();
        var utterance = new window.SpeechSynthesisUtterance(text);
        utterance.lang = 'vi-VN';
        utterance.rate = .98;
        utterance.pitch = 1;
        var voices = window.speechSynthesis.getVoices();
        for (var voiceIndex = 0; voiceIndex < voices.length; voiceIndex += 1) {
            if ((voices[voiceIndex].lang || '').toLowerCase().indexOf('vi') === 0) {
                utterance.voice = voices[voiceIndex];
                break;
            }
        }
        utterance.onstart = function() { setVoiceState('speaking', 'Nhi đang trả lời…'); };
        utterance.onend = function() { setVoiceState('idle', 'Sẵn sàng lắng nghe'); };
        utterance.onerror = function(event) {
            setVoiceState('idle', event.error === 'not-allowed' ? 'Trình duyệt đang chặn âm thanh · hãy bấm lại Orb' : 'Không phát được âm thanh · phản hồi vẫn hiển thị bên dưới');
        };
        window.speechSynthesis.speak(utterance);
    }

    async function ask(text, shouldSpeak, showOnVoiceStage) {
        if (busy || !text) return;
        busy = true;
        input.disabled = true;
        mic.disabled = true;
        send.disabled = true;
        voiceInput.disabled = true;
        voiceSend.disabled = true;
        addMessage('user', text);
        setVoiceState('thinking', 'Nhi đang xử lý…');
        try {
            var response = await fetch(M.cfg.wwwroot + '/local/mtpcbridge/moodle-orb.php?sesskey=' + encodeURIComponent(M.cfg.sesskey), {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                credentials: 'same-origin',
                body: JSON.stringify({text: text})
            });
            var data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.error || 'Không nhận được phản hồi từ Nhi.');
            if (data.reply) {
                addMessage('assistant', data.reply);
            }
            if (data.pending) addPendingActions();
            if (data.reply && !data.pending && root.classList.contains('is-voice-open') && soundEnabled) speakReply(data.reply);
            else setVoiceState('idle', data.pending ? 'Đang chờ xác nhận' : 'Sẵn sàng lắng nghe');
        } catch (error) {
            var errorMessage = error.message || 'Nhi chưa kết nối được. Hãy thử lại sau ít giây.';
            addMessage('assistant', errorMessage);
            setVoiceState('idle', 'Có lỗi kết nối · hãy thử lại');
        } finally {
            busy = false;
            input.disabled = false;
            mic.disabled = false;
            send.disabled = false;
            voiceInput.disabled = false;
            voiceSend.disabled = false;
            input.value = '';
            voiceInput.value = '';
        }
    }

    function stopListening() {
        if (recognition && listening) recognition.stop();
    }

    function startListening() {
        if (busy) return;
        setVoiceOpen(true);
        ensureWelcome();
        if (!SpeechRecognition) {
            setVoiceState('idle', 'Trình duyệt chưa hỗ trợ nói · hãy nhập văn bản');
            return;
        }
        if (listening) {
            stopListening();
            return;
        }
        speechText = '';
        recognition = new SpeechRecognition();
        recognition.lang = 'vi-VN';
        recognition.continuous = false;
        recognition.interimResults = true;
        recognition.onstart = function() {
            listening = true;
            setVoiceState('listening', 'Đang nghe…');
        };
        recognition.onresult = function(event) {
            var interim = '';
            for (var index = event.resultIndex; index < event.results.length; index += 1) {
                var phrase = event.results[index][0].transcript;
                if (event.results[index].isFinal) speechText += phrase;
                else interim += phrase;
            }
            var heard = interim ? 'Nghe: ' + interim : 'Đang nghe…';
            status.textContent = heard;
            voiceStatus.textContent = heard;
        };
        recognition.onerror = function(event) {
            listening = false;
            setVoiceState('idle', event.error === 'not-allowed' ? 'Hãy cho phép microphone để nói với Nhi' : 'Không nghe rõ · thử lại hoặc nhập văn bản');
        };
        recognition.onend = function() {
            listening = false;
            var text = speechText.trim();
            speechText = '';
            if (text) ask(text, true);
            else if (status.textContent.indexOf('Hãy cho phép') !== 0) setVoiceState('idle', 'Sẵn sàng lắng nghe');
        };
        setVoiceState('idle', 'Đang mở microphone…');
        window.requestAnimationFrame(function() {
            try {
                recognition.start();
            } catch (error) {
                listening = false;
                setVoiceState('idle', 'Microphone đang bận · hãy thử lại');
            }
        });
    }

    launch.addEventListener('click', function() {
        if (listening) stopListening();
        else startListening();
    });
    mic.addEventListener('click', function() { setOpen(false, false); startListening(); });
    chatToggle.addEventListener('click', function() {
        setVoiceOpen(true);
        window.setTimeout(function() { voiceInput.focus(); }, 0);
    });
    voiceClose.addEventListener('click', function() {
        speechText = '';
        if (listening) stopListening();
        setVoiceOpen(false);
    });
    soundToggle.addEventListener('click', function() {
        soundEnabled = !soundEnabled;
        soundToggle.textContent = soundEnabled ? 'Âm thanh: Bật' : 'Âm thanh: Tắt';
        soundToggle.setAttribute('aria-pressed', soundEnabled ? 'true' : 'false');
        soundToggle.setAttribute('aria-label', soundEnabled ? 'Tắt âm thanh phản hồi' : 'Bật âm thanh phản hồi');
        if (!soundEnabled && window.speechSynthesis) window.speechSynthesis.cancel();
        setVoiceState('idle', soundEnabled ? 'Âm thanh đã bật' : 'Âm thanh đã tắt');
    });
    close.addEventListener('click', function() { setOpen(false); });
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        ask(input.value.trim(), false);
    });
    voiceForm.addEventListener('submit', function(event) {
        event.preventDefault();
        ask(voiceInput.value.trim(), false, true);
    });
    root.addEventListener('click', function(event) {
        var confirmButton = event.target.closest('[data-orb-confirm]');
        if (confirmButton) ask(confirmButton.getAttribute('data-orb-confirm'), false);
    });
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            speechText = '';
            if (listening) stopListening();
            if (!panel.hidden) setOpen(false);
            if (root.classList.contains('is-voice-open')) setVoiceOpen(false);
        }
    });
}());
