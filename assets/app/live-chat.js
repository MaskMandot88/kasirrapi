(function () {
    const config = window.KasirRapiLiveChat || {};
    if (!config.endpoint) return;

    const appName = config.appName || 'KasirRapi';
    const sessionKey = 'kasirrapi_live_chat_session_v2';
    const historyPrefix = 'kasirrapi_live_chat_history_v2_';
    const idlePromptMs = 90000;
    const idleEndMs = 45000;
    const csNames = Array.isArray(config.csNames) && config.csNames.length ? config.csNames : ['Nadia', 'Rani', 'Dina', 'Maya', 'Laras'];

    let session = loadSession();
    let historyKey = getHistoryKey(session.id);
    let history = loadHistory(historyKey);
    let idlePromptTimer = null;
    let idleEndTimer = null;
    let typingBubble = null;
    let refs = {};

    const root = document.createElement('div');
    root.className = 'kr-live-chat';
    root.innerHTML = `
        <section class="kr-chat-panel" aria-label="Live chat KasirRapi">
            <header class="kr-chat-head">
                <div class="kr-chat-profile">
                    <img class="kr-chat-avatar" src="${escapeAttr(getAvatarUrl(session.csName))}" alt="${escapeAttr(session.csName)}">
                    <div class="kr-chat-meta">
                        <div class="kr-chat-title">${escapeHtml(session.csName)} dari ${escapeHtml(appName)}</div>
                        <div class="kr-chat-subtitle">CS virtual, bantu sampai tuntas</div>
                    </div>
                </div>
                <button type="button" class="kr-chat-close" aria-label="Tutup chat">x</button>
            </header>
            <div class="kr-chat-messages"></div>
            <form class="kr-chat-form">
                <textarea class="kr-chat-input" rows="1" placeholder="Tulis pertanyaan..." maxlength="1200"></textarea>
                <button type="submit" class="kr-chat-send">Kirim</button>
            </form>
        </section>
        <div class="kr-chat-promo">
            <button type="button" class="kr-chat-promo-close" aria-label="Tutup sapaan live chat">x</button>
            <button type="button" class="kr-chat-promo-launch" aria-label="Buka live chat">
                <img class="kr-chat-promo-image" src="${escapeAttr(config.launcherImageUrl || '')}" alt="Sampaikan pertanyaan anda di sini">
            </button>
        </div>
        <button type="button" class="kr-chat-toggle" aria-label="Buka live chat">
            <img class="kr-chat-toggle-icon" src="${escapeAttr(config.collapsedIconUrl || '')}" alt="" aria-hidden="true">
            <span class="kr-chat-toggle-dot"></span>
        </button>
    `;

    document.addEventListener('DOMContentLoaded', function () {
        document.body.appendChild(root);

        refs = {
            toggle: root.querySelector('.kr-chat-toggle'),
            promo: root.querySelector('.kr-chat-promo'),
            promoLaunch: root.querySelector('.kr-chat-promo-launch'),
            promoClose: root.querySelector('.kr-chat-promo-close'),
            close: root.querySelector('.kr-chat-close'),
            messages: root.querySelector('.kr-chat-messages'),
            form: root.querySelector('.kr-chat-form'),
            input: root.querySelector('.kr-chat-input'),
            send: root.querySelector('.kr-chat-send'),
            avatar: root.querySelector('.kr-chat-avatar'),
            title: root.querySelector('.kr-chat-title')
        };

        if (history.length === 0) {
            history = [greetingMessage()];
            saveHistory();
        }

        renderHistory();
        if (session.awaitingClose) renderSessionActions();
        scheduleIdlePrompt();

        refs.promoLaunch.addEventListener('click', openChat);

        refs.promoClose.addEventListener('click', function (event) {
            event.stopPropagation();
            hidePromo();
        });

        refs.toggle.addEventListener('click', openChat);

        refs.close.addEventListener('click', function () {
            hidePromo();
            root.classList.remove('open');
        });

        refs.input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                refs.form.requestSubmit();
            }
        });

        refs.form.addEventListener('submit', function (event) {
            event.preventDefault();

            const text = refs.input.value.trim();
            if (!text) return;

            if (session.ended) {
                startNewConversation();
            }

            clearIdleTimers();
            session.awaitingClose = false;
            session.lastActivity = Date.now();
            saveSession();

            history.push({ role: 'user', text });
            trimHistory();
            saveHistory();
            renderHistory();

            refs.input.value = '';
            refs.send.disabled = true;
            refs.send.textContent = '...';
            typingBubble = showTyping(session.csName);

            fetch(config.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    message: text,
                    history: history.slice(-8),
                    cs_name: session.csName,
                    session_id: session.id
                })
            })
                .then(function (response) {
                    return response.json().catch(function () {
                        return { success: false, message: 'Respons server tidak valid.' };
                    });
                })
                .then(function (data) {
                    removeTyping(typingBubble);
                    typingBubble = null;

                    const reply = data.success ? data.reply : (data.message || 'Live chat belum bisa menjawab saat ini.');
                    const parts = data.success ? splitReply(reply) : [reply];

                    return appendModelReplies(parts).then(function () {
                        if (data.success && data.escalate) {
                            showEscalationForm(text);
                        } else {
                            scheduleIdlePrompt();
                        }
                    });
                })
                .catch(function () {
                    removeTyping(typingBubble);
                    typingBubble = null;
                    return appendModelReplies([
                        'Maaf, koneksi ke live chat gagal. Pastikan localhost/server bisa mengakses internet dan config Gemini sudah benar.'
                    ]).then(scheduleIdlePrompt);
                })
                .finally(function () {
                    refs.send.disabled = false;
                    refs.send.textContent = 'Kirim';
                    refs.input.focus();
                });
        });
    });

    function openChat() {
        hidePromo();
        if (session.ended) {
            startNewConversation();
        }
        root.classList.add('open');
        setTimeout(function () { refs.input.focus(); }, 80);
    }

    function hidePromo() {
        root.classList.add('promo-hidden');
    }

    function loadSession() {
        try {
            const parsed = JSON.parse(localStorage.getItem(sessionKey) || '{}');
            if (parsed && parsed.id && parsed.csName && !parsed.ended) {
                return {
                    id: String(parsed.id),
                    csName: String(parsed.csName),
                    startedAt: Number(parsed.startedAt) || Date.now(),
                    lastActivity: Number(parsed.lastActivity) || Date.now(),
                    awaitingClose: Boolean(parsed.awaitingClose),
                    ended: false
                };
            }
        } catch (error) {
            return createSession();
        }
        return createSession();
    }

    function createSession() {
        return {
            id: Date.now().toString(36) + Math.random().toString(36).slice(2, 8),
            csName: randomCsName(),
            startedAt: Date.now(),
            lastActivity: Date.now(),
            awaitingClose: false,
            ended: false
        };
    }

    function startNewConversation() {
        clearIdleTimers();
        session = createSession();
        historyKey = getHistoryKey(session.id);
        history = [greetingMessage()];
        saveSession();
        saveHistory();
        updateHeader();
        renderHistory();
    }

    function endSession(message) {
        clearIdleTimers();
        session.awaitingClose = false;
        session.ended = true;
        session.lastActivity = Date.now();
        saveSession();

        appendModelReplies([
            message || 'Baik, sesi chat ini saya akhiri dulu ya. Kalau butuh bantuan lagi, buka chat ini dan mulai sesi baru.'
        ]).then(function () {
            refs.input.placeholder = 'Tulis pesan untuk memulai sesi baru...';
        });
    }

    function saveSession() {
        try {
            localStorage.setItem(sessionKey, JSON.stringify(session));
        } catch (error) {
            return false;
        }
        return true;
    }

    function getHistoryKey(sessionId) {
        return historyPrefix + sessionId;
    }

    function loadHistory(key) {
        try {
            const parsed = JSON.parse(localStorage.getItem(key) || '[]');
            return Array.isArray(parsed) ? parsed.filter(isValidMessage).slice(-18) : [];
        } catch (error) {
            return [];
        }
    }

    function saveHistory() {
        try {
            localStorage.setItem(historyKey, JSON.stringify(history.slice(-18)));
        } catch (error) {
            return false;
        }
        return true;
    }

    function greetingMessage() {
        return {
            role: 'model',
            text: `Halo Kak, saya ${session.csName}, ada yang bisa ${session.csName} bantu?`
        };
    }

    function randomCsName() {
        return csNames[Math.floor(Math.random() * csNames.length)];
    }

    function getAvatarUrl(name) {
        if (config.avatars && typeof config.avatars === 'object' && config.avatars[name]) {
            return config.avatars[name];
        }
        return config.avatarUrl || '';
    }

    function updateHeader() {
        if (refs.avatar) {
            refs.avatar.src = getAvatarUrl(session.csName);
            refs.avatar.alt = session.csName;
        }
        if (refs.title) {
            refs.title.textContent = `${session.csName} dari ${appName}`;
        }
        if (refs.input) {
            refs.input.placeholder = 'Tulis pertanyaan...';
        }
    }

    function isValidMessage(item) {
        return item && (item.role === 'user' || item.role === 'model') && typeof item.text === 'string';
    }

    function trimHistory() {
        history.splice(0, Math.max(0, history.length - 18));
    }

    function renderHistory() {
        refs.messages.innerHTML = '';
        history.forEach(function (item) {
            refs.messages.appendChild(createBubble(item.role, item.text));
        });
        refs.messages.scrollTop = refs.messages.scrollHeight;
    }

    function createBubble(role, text) {
        const bubble = document.createElement('div');
        bubble.className = 'kr-chat-bubble ' + (role === 'user' ? 'user' : 'model');
        bubble.textContent = text;
        return bubble;
    }

    function showTyping(name) {
        const bubble = document.createElement('div');
        bubble.className = 'kr-chat-bubble model kr-chat-typing';
        bubble.innerHTML = `<span>${escapeHtml(name)} sedang mengetik</span><i></i><i></i><i></i>`;
        refs.messages.appendChild(bubble);
        refs.messages.scrollTop = refs.messages.scrollHeight;
        return bubble;
    }

    function removeTyping(bubble) {
        if (bubble && bubble.parentNode) {
            bubble.parentNode.removeChild(bubble);
        }
    }

    function appendModelReplies(parts) {
        const cleanParts = parts.map(function (part) {
            return String(part || '').trim();
        }).filter(Boolean);

        if (cleanParts.length === 0) {
            cleanParts.push('Maaf, saya belum mendapat jawaban yang utuh. Bisa ulangi pertanyaannya sedikit lebih spesifik?');
        }

        return new Promise(function (resolve) {
            let index = 0;

            function next() {
                if (index >= cleanParts.length) {
                    resolve();
                    return;
                }

                const part = cleanParts[index];
                history.push({ role: 'model', text: part });
                trimHistory();
                saveHistory();
                renderHistory();
                index += 1;

                if (index >= cleanParts.length) {
                    resolve();
                    return;
                }

                const typing = showTyping(session.csName);
                window.setTimeout(function () {
                    removeTyping(typing);
                    next();
                }, Math.min(1200, Math.max(450, part.length * 12)));
            }

            next();
        });
    }

    function splitReply(text) {
        const value = String(text || '').trim();
        if (!value) return [];

        const paragraphs = value.split(/\n{2,}/).map(function (part) {
            return part.trim();
        }).filter(Boolean);

        if (paragraphs.length > 1) {
            const parts = [];
            paragraphs.forEach(function (paragraph) {
                splitLongText(paragraph, 420).forEach(function (part) {
                    parts.push(part);
                });
            });
            return parts;
        }

        return splitLongText(value, 360);
    }

    function splitLongText(text, maxLength) {
        if (text.length <= maxLength) return [text];

        const sentences = text.match(/[^.!?]+[.!?]+(?:\s+|$)|[^.!?]+$/g) || [text];
        const chunks = [];
        let current = '';

        sentences.forEach(function (sentence) {
            const clean = sentence.trim();
            if (!clean) return;

            if ((current + ' ' + clean).trim().length > maxLength && current) {
                chunks.push(current.trim());
                current = clean;
                return;
            }

            current = (current + ' ' + clean).trim();
        });

        if (current) chunks.push(current.trim());
        return chunks;
    }

    function scheduleIdlePrompt() {
        clearIdleTimers();
        if (session.ended || session.awaitingClose || !history.some(function (item) { return item.role === 'user'; })) return;

        idlePromptTimer = window.setTimeout(function () {
            offerEndSession();
        }, idlePromptMs);
    }

    function clearIdleTimers() {
        if (idlePromptTimer) {
            window.clearTimeout(idlePromptTimer);
            idlePromptTimer = null;
        }
        if (idleEndTimer) {
            window.clearTimeout(idleEndTimer);
            idleEndTimer = null;
        }
    }

    function offerEndSession() {
        if (session.ended || session.awaitingClose) return;

        session.awaitingClose = true;
        session.lastActivity = Date.now();
        saveSession();

        appendModelReplies([
            'Apakah bantuan dari saya sudah cukup? Kalau sudah, sesi chat ini bisa saya akhiri dulu ya.'
        ]).then(function () {
            renderSessionActions();
            idleEndTimer = window.setTimeout(function () {
                endSession('Karena belum ada respons, sesi chat ini saya akhiri dulu ya. Kalau butuh bantuan lagi, buka chat ini dan mulai sesi baru.');
            }, idleEndMs);
        });
    }

    function renderSessionActions() {
        if (refs.messages.querySelector('.kr-session-actions') || session.ended) return;

        const actions = document.createElement('div');
        actions.className = 'kr-session-actions';
        actions.innerHTML = `
            <button type="button" class="kr-session-end">Sudah cukup</button>
            <button type="button" class="kr-session-continue">Lanjut tanya</button>
        `;
        refs.messages.appendChild(actions);
        refs.messages.scrollTop = refs.messages.scrollHeight;

        actions.querySelector('.kr-session-end').addEventListener('click', function () {
            endSession('Baik, sesi chat ini saya akhiri dulu ya. Terima kasih sudah menghubungi KasirRapi.');
        });

        actions.querySelector('.kr-session-continue').addEventListener('click', function () {
            clearIdleTimers();
            session.awaitingClose = false;
            session.lastActivity = Date.now();
            saveSession();
            renderHistory();
            appendModelReplies(['Siap, saya lanjut bantu. Silakan tulis kendala atau pertanyaan berikutnya.']).then(scheduleIdlePrompt);
            refs.input.focus();
        });
    }

    function showEscalationForm(lastUserMessage) {
        if (!config.escalationEndpoint || refs.messages.querySelector('.kr-escalation-box')) return;

        const box = document.createElement('div');
        box.className = 'kr-escalation-box';
        box.innerHTML = `
            <div class="kr-escalation-title">Saya teruskan ke tim teknis ya.</div>
            <p>Masukkan email akun KasirRapi yang terdaftar supaya tim bisa cek data toko yang tepat.</p>
            <form class="kr-escalation-form">
                <input class="kr-escalation-input" type="email" placeholder="email akun terdaftar" required>
                <button class="kr-escalation-button" type="submit">Buat Tiket</button>
            </form>
            <div class="kr-escalation-error" aria-live="polite"></div>
        `;
        refs.messages.appendChild(box);
        refs.messages.scrollTop = refs.messages.scrollHeight;

        const form = box.querySelector('.kr-escalation-form');
        const input = box.querySelector('.kr-escalation-input');
        const button = box.querySelector('.kr-escalation-button');
        const error = box.querySelector('.kr-escalation-error');

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const email = input.value.trim();
            if (!email) return;

            button.disabled = true;
            button.textContent = 'Mengirim...';
            error.textContent = '';

            fetch(config.escalationEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email,
                    message: lastUserMessage,
                    history: history.slice(-12),
                    cs_name: session.csName,
                    session_id: session.id
                })
            })
                .then(function (response) {
                    return response.json().catch(function () {
                        return { success: false, message: 'Respons server tidak valid.' };
                    });
                })
                .then(function (data) {
                    if (!data.success) {
                        error.textContent = data.message || 'Tiket belum bisa dibuat. Coba beberapa saat lagi.';
                        return;
                    }

                    appendModelReplies([data.message || 'Tiket sudah masuk ke tim teknis KasirRapi.']).then(function () {
                        endSession('Saya akhiri sesi chat ini dulu ya. Tim teknis akan lanjut membantu lewat email terdaftar.');
                    });
                })
                .catch(function () {
                    error.textContent = 'Koneksi gagal. Pastikan server lokal bisa mengakses endpoint eskalasi.';
                })
                .finally(function () {
                    button.disabled = false;
                    button.textContent = 'Buat Tiket';
                });
        });
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function escapeAttr(value) {
        return escapeHtml(value);
    }
})();
