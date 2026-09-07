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
        '<div class="mtpc-orb-voice-copy" role="dialog" aria-label="Trò chuyện bằng giọng nói với Nhi">' +
            '<strong>Nhi · Trợ lý học tập</strong><p id="mtpcOrbVoiceStatus">Chạm Orb để bắt đầu nói</p>' +
            '<div class="mtpc-orb-voice-reply" aria-live="polite"></div>' +
            '<button type="button" class="mtpc-orb-use-text">Nhập bằng văn bản</button>' +
        '</div>' +
        '<button type="button" class="mtpc-orb-voice-close" aria-label="Đóng Orb">×</button>' +
        '<button type="button" class="mtpc-orb-chat-toggle" aria-label="Mở cuộc trò chuyện với Nhi" aria-controls="mtpcOrbPanel" aria-expanded="false"><span aria-hidden="true">⌄</span></button>' +
        '<div class="mtpc-orb-panel" id="mtpcOrbPanel" hidden role="dialog" aria-labelledby="mtpcOrbTitle" aria-describedby="mtpcOrbStatus">' +
            '<header class="mtpc-orb-header"><div><h2 id="mtpcOrbTitle">Nhi · Trợ lý Moodle</h2><p>Chạm Orb để nói · tra cứu học tập của em</p></div>' +
                '<button type="button" class="mtpc-orb-close" aria-label="Đóng cuộc trò chuyện">×</button></header>' +
            '<div class="mtpc-orb-transcript" aria-live="polite" aria-label="Nội dung cuộc trò chuyện"></div>' +
            '<p id="mtpcOrbStatus" class="mtpc-orb-status" role="status">Sẵn sàng lắng nghe</p>' +
            '<form class="mtpc-orb-form"><label class="sr-only" for="mtpcOrbInput">Nhập yêu cầu cho Nhi</label>' +
                '<button type="button" class="mtpc-orb-mic" aria-label="Bắt đầu nói" title="Nói với Nhi">●</button>' +
                '<input id="mtpcOrbInput" name="message" type="text" autocomplete="off" placeholder="Hoặc nhập yêu cầu…">' +
                '<button type="submit" class="mtpc-orb-send" aria-label="Gửi yêu cầu">➜</button></form>' +
            '<div class="mtpc-orb-hints" aria-label="Gợi ý yêu cầu"><button type="button" data-orb-prompt="Liệt kê các khóa học tôi đã ghi danh">Khóa học của tôi</button>' +
                '<button type="button" data-orb-prompt="Xem điểm của tôi">Điểm của tôi</button></div>' +
            '<div class="mtpc-orb-footer"><span class="mtpc-orb-live-dot" aria-hidden="true"></span><span>Giọng nói và văn bản dùng chung một cuộc trò chuyện</span></div>' +
        '</div>';
    document.body.appendChild(root);

    var launch = root.querySelector('.mtpc-orb-launch');
    var chatToggle = root.querySelector('.mtpc-orb-chat-toggle');
    var voiceClose = root.querySelector('.mtpc-orb-voice-close');
    var useText = root.querySelector('.mtpc-orb-use-text');
    var voiceStatus = root.querySelector('#mtpcOrbVoiceStatus');
    var voiceReply = root.querySelector('.mtpc-orb-voice-reply');
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
        if (!text || !window.speechSynthesis || !window.SpeechSynthesisUtterance) {
            setVoiceState('idle', 'Sẵn sàng lắng nghe');
            return;
        }
        window.speechSynthesis.cancel();
        var utterance = new window.SpeechSynthesisUtterance(text);
        utterance.lang = 'vi-VN';
        utterance.rate = .98;
        utterance.pitch = 1;
        utterance.onstart = function() { setVoiceState('speaking', 'Nhi đang trả lời…'); };
        utterance.onend = function() { setVoiceState('idle', 'Sẵn sàng lắng nghe'); };
        utterance.onerror = function() { setVoiceState('idle', 'Sẵn sàng lắng nghe'); };
        window.speechSynthesis.speak(utterance);
    }

    async function ask(text, shouldSpeak) {
        if (busy || !text) return;
        busy = true;
        input.disabled = true;
        mic.disabled = true;
        send.disabled = true;
        addMessage('user', text);
        if (shouldSpeak) voiceReply.textContent = '';
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
                if (shouldSpeak) voiceReply.textContent = data.reply;
            }
            if (data.pending) addPendingActions();
            if (shouldSpeak && data.reply && !data.pending && root.classList.contains('is-voice-open')) speakReply(data.reply);
            else setVoiceState('idle', data.pending ? 'Đang chờ xác nhận' : 'Sẵn sàng lắng nghe');
        } catch (error) {
            var errorMessage = error.message || 'Nhi chưa kết nối được. Hãy thử lại sau ít giây.';
            addMessage('assistant', errorMessage);
            if (shouldSpeak) voiceReply.textContent = errorMessage;
            setVoiceState('idle', 'Có lỗi kết nối · hãy thử lại');
        } finally {
            busy = false;
            input.disabled = false;
            mic.disabled = false;
            send.disabled = false;
            input.value = '';
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
        setVoiceOpen(false);
        setOpen(panel.hidden);
    });
    useText.addEventListener('click', function() {
        speechText = '';
        if (listening) stopListening();
        setVoiceOpen(false);
        setOpen(true);
    });
    voiceClose.addEventListener('click', function() {
        speechText = '';
        if (listening) stopListening();
        setVoiceOpen(false);
    });
    close.addEventListener('click', function() { setOpen(false); });
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        ask(input.value.trim(), false);
    });
    root.addEventListener('click', function(event) {
        var prompt = event.target.closest('[data-orb-prompt]');
        if (prompt) { setOpen(true); ask(prompt.getAttribute('data-orb-prompt'), false); return; }
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
