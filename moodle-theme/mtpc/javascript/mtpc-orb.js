(function() {
    'use strict';

    if (!window.M || !M.cfg || !M.cfg.sesskey || document.getElementById('mtpcOrb')) return;

    var root = document.createElement('section');
    root.id = 'mtpcOrb';
    root.className = 'mtpc-orb';
    root.setAttribute('aria-label', 'Trợ lý Moodle Nhi');
    root.innerHTML =
        '<button type="button" class="mtpc-orb-launch" aria-label="Mở trợ lý Moodle Nhi" aria-expanded="false">' +
            '<span class="mtpc-orb-ring" aria-hidden="true"></span><span class="mtpc-orb-core" aria-hidden="true"><i></i></span>' +
        '</button>' +
        '<div class="mtpc-orb-panel" hidden role="dialog" aria-labelledby="mtpcOrbTitle" aria-describedby="mtpcOrbStatus">' +
            '<header class="mtpc-orb-header"><div><h2 id="mtpcOrbTitle">Nhi · Trợ lý Moodle</h2><p>Hỗ trợ quản trị ngay trong Moodle</p></div>' +
                '<button type="button" class="mtpc-orb-close" aria-label="Đóng trợ lý Moodle">×</button></header>' +
            '<div class="mtpc-orb-transcript" aria-live="polite" aria-label="Nội dung cuộc trò chuyện"></div>' +
            '<p id="mtpcOrbStatus" class="mtpc-orb-status" role="status">Sẵn sàng hỗ trợ</p>' +
            '<form class="mtpc-orb-form"><label class="sr-only" for="mtpcOrbInput">Nhập yêu cầu cho Nhi</label>' +
                '<input id="mtpcOrbInput" name="message" type="text" autocomplete="off" placeholder="Ví dụ: xem các khóa học Moodle…">' +
                '<button type="submit" class="mtpc-orb-send" aria-label="Gửi yêu cầu">Gửi</button></form>' +
            '<div class="mtpc-orb-hints" aria-label="Gợi ý yêu cầu"><button type="button" data-orb-prompt="Kiểm tra trạng thái Moodle">Trạng thái Moodle</button>' +
                '<button type="button" data-orb-prompt="Liệt kê các khóa học Moodle">Danh sách khóa học</button></div>' +
        '</div>';
    document.body.appendChild(root);

    var launch = root.querySelector('.mtpc-orb-launch');
    var panel = root.querySelector('.mtpc-orb-panel');
    var close = root.querySelector('.mtpc-orb-close');
    var form = root.querySelector('.mtpc-orb-form');
    var input = root.querySelector('#mtpcOrbInput');
    var transcript = root.querySelector('.mtpc-orb-transcript');
    var status = root.querySelector('.mtpc-orb-status');
    var send = root.querySelector('.mtpc-orb-send');
    var busy = false;

    function setStatus(text, state) {
        status.textContent = text;
        root.classList.toggle('is-thinking', state === 'thinking');
    }

    function addMessage(role, text) {
        var item = document.createElement('div');
        item.className = 'mtpc-orb-message ' + role;
        item.textContent = text;
        transcript.appendChild(item);
        transcript.scrollTop = transcript.scrollHeight;
    }

    function addPendingActions() {
        var actions = document.createElement('div');
        actions.className = 'mtpc-orb-confirm';
        actions.innerHTML = '<button type="button" data-orb-confirm="XÁC NHẬN">Xác nhận</button><button type="button" data-orb-confirm="HỦY">Hủy</button>';
        transcript.appendChild(actions);
        transcript.scrollTop = transcript.scrollHeight;
    }

    function setOpen(open) {
        panel.hidden = !open;
        launch.setAttribute('aria-expanded', open ? 'true' : 'false');
        root.classList.toggle('is-open', open);
        if (open) input.focus();
        else launch.focus();
    }

    async function ask(text) {
        if (busy || !text) return;
        busy = true;
        input.disabled = true;
        send.disabled = true;
        addMessage('user', text);
        setStatus('Nhi đang xử lý…', 'thinking');
        try {
            var response = await fetch(M.cfg.wwwroot + '/local/mtpcbridge/moodle-orb.php?sesskey=' + encodeURIComponent(M.cfg.sesskey), {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                credentials: 'same-origin',
                body: JSON.stringify({text: text})
            });
            var data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.error || 'Không nhận được phản hồi từ Nhi.');
            if (data.reply) addMessage('assistant', data.reply);
            if (data.pending) addPendingActions();
            setStatus(data.pending ? 'Đang chờ xác nhận' : 'Sẵn sàng hỗ trợ', 'idle');
        } catch (error) {
            addMessage('assistant', error.message || 'Nhi chưa kết nối được. Hãy thử lại sau ít giây.');
            setStatus('Có lỗi kết nối · hãy thử lại', 'idle');
        } finally {
            busy = false;
            input.disabled = false;
            send.disabled = false;
            input.value = '';
            input.focus();
        }
    }

    launch.addEventListener('click', function() {
        setOpen(panel.hidden);
        if (!transcript.children.length) addMessage('assistant', 'Chào anh/chị! Em có thể hỗ trợ tra cứu và quản trị Moodle.');
    });
    close.addEventListener('click', function() { setOpen(false); });
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        ask(input.value.trim());
    });
    root.addEventListener('click', function(event) {
        var prompt = event.target.closest('[data-orb-prompt]');
        if (prompt) { setOpen(true); ask(prompt.getAttribute('data-orb-prompt')); return; }
        var confirmButton = event.target.closest('[data-orb-confirm]');
        if (confirmButton) ask(confirmButton.getAttribute('data-orb-confirm'));
    });
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && !panel.hidden) setOpen(false);
    });
}());
