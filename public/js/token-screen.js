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
        elDisplay.setAttribute('hidden', 'hidden');
        elKiosk.setAttribute('hidden', 'hidden');
        elPicker.removeAttribute('hidden');
        stopPoll();
    }

    function showDisplay() {
        elPicker.setAttribute('hidden', 'hidden');
        elDisplay.removeAttribute('hidden');
        if (cfg.controlsEnabled) {
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
        queueId = id;
        showDisplay();
        tickDisplay();
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
        var prev = document.getElementById('ts-btn-prev');
        var skip = document.getElementById('ts-btn-skip');
        var next = document.getElementById('ts-btn-next');
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

    function init() {
        wireKiosk();
        elDisplay.setAttribute('hidden', 'hidden');
        elKiosk.setAttribute('hidden', 'hidden');
        elPicker.removeAttribute('hidden');
        loadQueues();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
