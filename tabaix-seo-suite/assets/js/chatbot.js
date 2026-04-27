/**
 * Ultimate AI Master — Frontend Chatbot JavaScript
 */
(function () {
    'use strict';

    const cfg = window.uamChatbot || {};

    const Widget = document.getElementById('uam-chatbot-widget');
    const Toggle = document.getElementById('uam-chatbot-toggle');
    const Panel = document.getElementById('uam-chatbot-panel');
    const Messages = document.getElementById('uam-chatbot-messages');
    const Input = document.getElementById('uam-chatbot-input');
    const SendBtn = document.getElementById('uam-chatbot-send');
    const Typing = document.getElementById('uam-chatbot-typing');
    const ChatIcon = document.getElementById('uam-chat-icon');
    const CloseIcon = document.getElementById('uam-close-icon');

    if (!Widget || !Toggle || !Panel) return;

    let isOpen = false;
    let isWaiting = false;

    // ── Toggle Panel ─────────────────────────────────────────────────────
    function openChat() {
        isOpen = true;
        Panel.classList.add('uam-panel-open');
        Panel.removeAttribute('aria-hidden');
        ChatIcon.style.display = 'none';
        CloseIcon.style.display = 'block';
        Input.focus();
        if (!Messages.children.length) {
            addBotMessage(cfg.greeting || 'Hello! How can I help you today?');
        }
    }

    function closeChat() {
        isOpen = false;
        Panel.classList.remove('uam-panel-open');
        Panel.setAttribute('aria-hidden', 'true');
        ChatIcon.style.display = 'block';
        CloseIcon.style.display = 'none';
    }

    Toggle.addEventListener('click', () => isOpen ? closeChat() : openChat());

    // ── Message Renderers ─────────────────────────────────────────────────
    function addBotMessage(text) {
        const bubble = document.createElement('div');
        bubble.className = 'uc-bubble uc-bot';
        bubble.textContent = text;
        Messages.appendChild(bubble);
        scrollToBottom();
        return bubble;
    }

    function addUserMessage(text) {
        const bubble = document.createElement('div');
        bubble.className = 'uc-bubble uc-user';
        bubble.textContent = text;
        Messages.appendChild(bubble);
        scrollToBottom();
    }

    function scrollToBottom() {
        Messages.scrollTop = Messages.scrollHeight;
    }

    function showTyping() {
        Typing.classList.remove('uam-hidden');
        Messages.appendChild(Typing);
        scrollToBottom();
    }

    function hideTyping() {
        Typing.classList.add('uam-hidden');
    }

    // ── Send Message ──────────────────────────────────────────────────────
    function sendMessage() {
        const text = Input.value.trim();
        if (!text || isWaiting) return;

        addUserMessage(text);
        Input.value = '';
        autoResize();
        isWaiting = true;
        SendBtn.disabled = true;
        showTyping();

        const formData = new FormData();
        formData.append('action', 'uam_chatbot_message');
        formData.append('nonce', cfg.nonce || '');
        formData.append('message', text);

        fetch(cfg.ajaxUrl || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            body: formData,
        })
            .then(res => res.json())
            .then(data => {
                hideTyping();
                isWaiting = false;
                SendBtn.disabled = false;
                if (data.success) {
                    addBotMessage(data.data.result || 'I received your message!');
                } else {
                    addBotMessage('Sorry, I encountered an issue. Please try again.');
                }
            })
            .catch(() => {
                hideTyping();
                isWaiting = false;
                SendBtn.disabled = false;
                addBotMessage('Connection error. Please check your internet connection.');
            });
    }

    // ── Events ────────────────────────────────────────────────────────────
    SendBtn.addEventListener('click', sendMessage);

    Input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Auto-resize textarea
    function autoResize() {
        Input.style.height = 'auto';
        Input.style.height = Math.min(Input.scrollHeight, 120) + 'px';
    }

    Input.addEventListener('input', autoResize);

    // Close on outside click
    document.addEventListener('click', function (e) {
        if (isOpen && !Widget.contains(e.target)) {
            closeChat();
        }
    });

    // Keyboard accessibility
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen) closeChat();
    });

})();
