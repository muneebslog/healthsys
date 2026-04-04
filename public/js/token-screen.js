/**
 * Token display for legacy browsers: polling + XMLHttpRequest, no ES modules.
 */
(function () {
    'use strict';

    var cfg = window.HMS_TOKEN_SCREEN || {};
    var pollMs = typeof cfg.pollMs === 'number' ? cfg.pollMs : 4000;
    /* In-memory only: reload always shows the queue picker (no ?queue_id= restore). */
    var queueId = null;
    var pollTimer = null;
    var secret = cfg.controlSecret || '';
    /** Avoid repeating the same announcement on every poll; reset when queue changes. */
    var lastAnnounceSignature = null;

    var elPicker = document.getElementById('ts-picker');
    var elPickerCards = document.getElementById('ts-picker-cards');
    var elPickerEmpty = document.getElementById('ts-picker-empty');
    var elPickerError = document.getElementById('ts-picker-error');
    var elDisplay = document.getElementById('ts-display');
    var elDoctor = document.getElementById('ts-doctor');
    var elService = document.getElementById('ts-service');
    var elPatient = document.getElementById('ts-patient');
    var elToken = document.getElementById('ts-token');
    var elWaiting = document.getElementById('ts-waiting-count');
    var elDisplayError = document.getElementById('ts-display-error');
    var elKiosk = document.getElementById('ts-kiosk');
    var elDebug = document.getElementById('ts-debug');
    var canFullscreen = !!(
        document.fullscreenEnabled ||
        document.webkitFullscreenEnabled ||
        document.mozFullScreenEnabled ||
        document.msFullscreenEnabled
    );

    function getQueryParam(name) {
        var q = window.location.search || '';
        if (!q) {
            return null;
        }
        var parts = q.replace(/^\?/, '').split('&');
        for (var i = 0; i < parts.length; i++) {
            var kv = parts[i].split('=');
            if (decodeURIComponent(kv[0] || '') === name) {
                return decodeURIComponent(kv[1] || '');
            }
        }
        return null;
    }

    function getComputedPx(el, prop) {
        try {
            var cs = window.getComputedStyle(el);
            return cs && cs.getPropertyValue(prop) ? String(cs.getPropertyValue(prop)).trim() : '';
        } catch (e) {
            return '';
        }
    }

    function renderDebug() {
        if (!elDebug) {
            return;
        }
        var enabled = getQueryParam('debug') === '1';
        if (!enabled) {
            elDebug.setAttribute('hidden', 'hidden');
            return;
        }
        var info = [];
        info.push('TokenScreen debug=1');
        info.push('UA: ' + (navigator.userAgent || ''));
        info.push('platform: ' + (navigator.platform || '') + ' | vendor: ' + (navigator.vendor || ''));
        info.push('screen: ' + (screen ? (screen.width + 'x' + screen.height) : ''));
        info.push('inner: ' + (window.innerWidth + 'x' + window.innerHeight));
        info.push('devicePixelRatio: ' + (window.devicePixelRatio || 1));
        info.push('fullscreen: ' + (isFullscreen() ? 'yes' : 'no'));
        info.push('token font-size: ' + (elToken ? getComputedPx(elToken, 'font-size') : ''));
        info.push('token line-height: ' + (elToken ? getComputedPx(elToken, 'line-height') : ''));
        info.push('meta viewport: ' + (document.querySelector('meta[name="viewport"]') ? (document.querySelector('meta[name="viewport"]').getAttribute('content') || '') : ''));
        elDebug.textContent = info.join('\n');
        elDebug.removeAttribute('hidden');
    }

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') || '' : '';
    }

    function parseJson(text) {
        try {
            return JSON.parse(text);
        } catch (e) {
            return null;
        }
    }

    function httpGet(url, onDone) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) {
                return;
            }
            onDone(xhr.status, xhr.responseText);
        };
        xhr.send(null);
    }

    function httpPost(url, onDone) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('X-CSRF-TOKEN', getCsrfToken());
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        if (secret) {
            xhr.setRequestHeader('X-HMS-Control-Secret', secret);
        }
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) {
                return;
            }
            onDone(xhr.status, xhr.responseText);
        };
        xhr.send('{}');
    }

    function showPicker() {
        lastAnnounceSignature = null;
        elDisplay.setAttribute('hidden', 'hidden');
        if (cfg.controlsEnabled || canFullscreen) {
            elKiosk.removeAttribute('hidden');
        } else {
            elKiosk.setAttribute('hidden', 'hidden');
        }
        elPicker.removeAttribute('hidden');
        stopPoll();
    }

    function showDisplay() {
        elPicker.setAttribute('hidden', 'hidden');
        elDisplay.removeAttribute('hidden');
        if (cfg.controlsEnabled || canFullscreen) {
            elKiosk.removeAttribute('hidden');
        }
        startPoll();
    }

    function renderPicker(rows) {
        elPickerCards.innerHTML = '';
        if (!rows || rows.length === 0) {
            elPickerEmpty.removeAttribute('hidden');
            return;
        }
        elPickerEmpty.setAttribute('hidden', 'hidden');
        for (var i = 0; i < rows.length; i++) {
            elPickerCards.appendChild(makeCard(rows[i]));
        }
    }

    function makeCard(row) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ts-card';
        var doc = row.doctor_name || '—';
        var svc = row.service_name || 'Service';
        btn.innerHTML =
            '<p class="ts-card-doctor">' + escapeHtml(doc) + '</p>' +
            '<p class="ts-card-service">' + escapeHtml(svc) + '</p>' +
            '<p class="ts-card-meta">Waiting: ' + String(row.remaining_count || 0) + ' · Tap to display</p>';
        btn.onclick = function () {
            selectQueue(String(row.queue_id));
        };
        return btn;
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function loadQueues() {
        elPickerError.setAttribute('hidden', 'hidden');
        httpGet(cfg.urls.queues, function (status, body) {
            if (status !== 200) {
                elPickerError.textContent = 'Could not load queues.';
                elPickerError.removeAttribute('hidden');
                return;
            }
            var data = parseJson(body);
            if (!data) {
                elPickerError.textContent = 'Invalid response.';
                elPickerError.removeAttribute('hidden');
                return;
            }
            renderPicker(data);
        });
    }

    function selectQueue(id) {
        lastAnnounceSignature = null;
        queueId = id;
        showDisplay();
        tickDisplay();
    }

    /**
     * Speak when the serving token changes (token number and/or patient name).
     * Uses the Web Speech API; no-op if unavailable (e.g. very old browsers).
     */
    function announceServing(p) {
        if (!window.speechSynthesis || typeof SpeechSynthesisUtterance === 'undefined') {
            return;
        }
        if (p.current_flow_token == null) {
            lastAnnounceSignature = null;
            return;
        }
        var name = p.patient_name && String(p.patient_name).trim() ? String(p.patient_name).trim() : '';
        var sig = String(p.current_flow_token) + '|' + name;
        if (sig === lastAnnounceSignature) {
            return;
        }
        lastAnnounceSignature = sig;
        var tokenLabel = 'T-' + String(p.current_flow_token);
        var text = name
            ? 'Now serving token ' + tokenLabel + ', ' + name + '.'
            : 'Now serving token ' + tokenLabel + '.';
        function runSpeak() {
            try {
                window.speechSynthesis.cancel();
                var u = new SpeechSynthesisUtterance(text);
                u.lang = document.documentElement.lang || 'en';
                u.rate = 0.95;
                window.speechSynthesis.speak(u);
            } catch (e) {
                /* ignore */
            }
        }
        if (window.speechSynthesis.getVoices().length) {
            runSpeak();
        } else {
            var done = false;
            function once() {
                if (done) {
                    return;
                }
                done = true;
                window.speechSynthesis.onvoiceschanged = null;
                runSpeak();
            }
            window.speechSynthesis.onvoiceschanged = once;
            window.setTimeout(once, 600);
        }
    }

    function applyDisplayPayload(p) {
        elDoctor.textContent = p.doctor_name || '—';
        elService.textContent = p.service_name || '';
        var name = p.patient_name;
        if (name && String(name).trim() !== '') {
            elPatient.textContent = String(name).trim();
            elPatient.removeAttribute('hidden');
        } else {
            elPatient.textContent = '';
            elPatient.setAttribute('hidden', 'hidden');
        }
        if (p.current_flow_token == null) {
            elToken.textContent = '—';
        } else {
            elToken.textContent = 'T-' + String(p.current_flow_token);
        }
        elWaiting.textContent = String(p.remaining_count != null ? p.remaining_count : 0);
        elDisplayError.setAttribute('hidden', 'hidden');
        announceServing(p);
    }

    function tickDisplay() {
        if (!queueId) {
            return;
        }
        var url = cfg.urls.dataBase + (cfg.urls.dataBase.indexOf('?') >= 0 ? '&' : '?') + 'queue_id=' + encodeURIComponent(queueId);
        httpGet(url, function (status, body) {
            if (status === 404 || status === 422) {
                elDisplayError.textContent = 'This queue is no longer available. Pick another.';
                elDisplayError.removeAttribute('hidden');
                showPicker();
                loadQueues();
                return;
            }
            if (status !== 200) {
                return;
            }
            var p = parseJson(body);
            if (p) {
                applyDisplayPayload(p);
            }
        });
    }

    function startPoll() {
        stopPoll();
        pollTimer = window.setInterval(tickDisplay, pollMs);
    }

    function stopPoll() {
        if (pollTimer != null) {
            window.clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function queueAction(pathTemplate) {
        if (!queueId) {
            return;
        }
        var url = pathTemplate.replace('__QUEUE__', encodeURIComponent(queueId));
        httpPost(url, function (status, body) {
            if (status === 200) {
                tickDisplay();
                return;
            }
            var j = parseJson(body);
            if (j && j.message) {
                window.alert(j.message);
            }
        });
    }

    function wireKiosk() {
        var fs = document.getElementById('ts-btn-fullscreen');
        var prev = document.getElementById('ts-btn-prev');
        var skip = document.getElementById('ts-btn-skip');
        var next = document.getElementById('ts-btn-next');
        if (fs) {
            if (!canFullscreen) {
                fs.setAttribute('hidden', 'hidden');
            } else {
                fs.onclick = function () {
                    toggleFullscreen(fs);
                };
            }
        }
        if (!cfg.controlsEnabled) {
            if (prev) { prev.setAttribute('hidden', 'hidden'); }
            if (skip) { skip.setAttribute('hidden', 'hidden'); }
            if (next) { next.setAttribute('hidden', 'hidden'); }
        }
        if (prev) {
            prev.onclick = function () {
                queueAction(cfg.urls.previous);
            };
        }
        if (skip) {
            skip.onclick = function () {
                queueAction(cfg.urls.skip);
            };
        }
        if (next) {
            next.onclick = function () {
                queueAction(cfg.urls.callNext);
            };
        }
    }

    function isFullscreen() {
        return !!(
            document.fullscreenElement ||
            document.webkitFullscreenElement ||
            document.mozFullScreenElement ||
            document.msFullscreenElement
        );
    }

    function requestFullscreen(el) {
        var fn =
            el.requestFullscreen ||
            el.webkitRequestFullscreen ||
            el.mozRequestFullScreen ||
            el.msRequestFullscreen;
        if (fn) {
            fn.call(el);
        }
    }

    function exitFullscreen() {
        var fn =
            document.exitFullscreen ||
            document.webkitExitFullscreen ||
            document.mozCancelFullScreen ||
            document.msExitFullscreen;
        if (fn) {
            fn.call(document);
        }
    }

    function updateFullscreenBtn(btn) {
        if (!btn) {
            return;
        }
        if (isFullscreen()) {
            btn.title = 'Exit full screen';
            btn.setAttribute('aria-label', 'Exit full screen');
            btn.textContent = '⤫';
        } else {
            btn.title = 'Full screen';
            btn.setAttribute('aria-label', 'Enter full screen');
            btn.textContent = '⛶';
        }
    }

    function toggleFullscreen(btn) {
        if (isFullscreen()) {
            exitFullscreen();
        } else {
            requestFullscreen(document.documentElement);
        }
        window.setTimeout(function () {
            updateFullscreenBtn(btn);
            renderDebug();
        }, 50);
    }

    function init() {
        wireKiosk();
        elDisplay.setAttribute('hidden', 'hidden');
        if (cfg.controlsEnabled || canFullscreen) {
            elKiosk.removeAttribute('hidden');
        } else {
            elKiosk.setAttribute('hidden', 'hidden');
        }
        elPicker.removeAttribute('hidden');
        loadQueues();
        renderDebug();
        window.addEventListener('resize', renderDebug);

        var fsBtn = document.getElementById('ts-btn-fullscreen');
        if (fsBtn) {
            updateFullscreenBtn(fsBtn);
            document.addEventListener('fullscreenchange', function () { updateFullscreenBtn(fsBtn); renderDebug(); });
            document.addEventListener('webkitfullscreenchange', function () { updateFullscreenBtn(fsBtn); renderDebug(); });
            document.addEventListener('mozfullscreenchange', function () { updateFullscreenBtn(fsBtn); renderDebug(); });
            document.addEventListener('MSFullscreenChange', function () { updateFullscreenBtn(fsBtn); renderDebug(); });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
