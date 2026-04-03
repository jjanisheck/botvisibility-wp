/**
 * BotVisibility Admin JavaScript
 *
 * All AJAX interactions, UI state, and dynamic rendering for the admin panel.
 * Expects global `botvisData` (ajaxUrl, nonce, homeUrl, levels, checks).
 */
(function () {
    'use strict';

    function botvisAjax(action, data) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', botvisData.nonce);
        if (data) Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
        return fetch(botvisData.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }

    function $(s) { return document.querySelector(s); }
    function $$(s) { return document.querySelectorAll(s); }

    var previousLevel = 0;

    function capturePreviousLevel() {
        var el = $('#botvis-results');
        if (!el) return;
        try {
            var c = JSON.parse(el.getAttribute('data-results'));
            if (c && typeof c.currentLevel === 'number') previousLevel = c.currentLevel;
        } catch (e) { /* no cached results */ }
    }

    /* 1. Scan Now */
    function initScanButton() {
        var btn = $('#botvis-scan-btn');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var area = $('#botvis-scan-area'), prog = $('#botvis-scanning-progress'), res = $('#botvis-results');
            if (prog) prog.style.display = '';
            if (res) res.style.display = 'none';
            btn.disabled = true;
            botvisAjax('botvis_scan').then(function (r) {
                if (prog) prog.style.display = 'none';
                btn.disabled = false;
                if (r.success && r.data) {
                    botvisRenderResults(r.data, area);
                    if (r.data.currentLevel > previousLevel && previousLevel > 0) botvisConfetti();
                    previousLevel = r.data.currentLevel;
                }
            }).catch(function () { if (prog) prog.style.display = 'none'; btn.disabled = false; });
        });
    }

    /* 2. Fix Button (per-check, delegated) */
    function initFixButtons() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.botvis-btn-fix');
            if (!btn) return;
            var cid = btn.getAttribute('data-check-id');
            if (!cid) return;
            btn.disabled = true; btn.textContent = 'Fixing\u2026';
            botvisAjax('botvis_fix', { check_id: cid }).then(function (r) {
                if (r.success) {
                    var item = btn.closest('.botvis-check-item');
                    if (item) {
                        item.classList.remove('botvis-status-fail');
                        item.classList.add('botvis-status-pass');
                        var ic = item.querySelector('.botvis-check-icon');
                        if (ic) { ic.textContent = '\u2713'; ic.classList.remove('botvis-icon-fail'); ic.classList.add('botvis-icon-pass'); }
                    }
                    btn.textContent = 'Fixed'; btn.classList.add('botvis-btn-success');
                    setTimeout(function () { btn.style.display = 'none'; }, 1500);
                } else { btn.disabled = false; btn.textContent = 'Fix'; }
            }).catch(function () { btn.disabled = false; btn.textContent = 'Fix'; });
        });
    }

    /* 2b. Enable/Disable Feature Button (L4 checks) */
    function initFeatureButtons() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.botvis-btn-enable');
            if (!btn) return;
            var fk = btn.getAttribute('data-feature-key');
            if (!fk) return;
            btn.disabled = true; btn.textContent = 'Enabling\u2026';
            botvisAjax('botvis_enable_feature', { feature_key: fk }).then(function (r) {
                if (r.success) {
                    btn.textContent = 'Enabled';
                    btn.classList.remove('botvis-btn-enable');
                    btn.classList.add('botvis-btn-success');
                    setTimeout(function () {
                        btn.textContent = 'Disable';
                        btn.classList.remove('botvis-btn-success');
                        btn.classList.add('botvis-btn-disable');
                        btn.setAttribute('data-feature-key', fk);
                        btn.disabled = false;
                    }, 1500);
                } else { btn.disabled = false; btn.textContent = 'Enable'; }
            }).catch(function () { btn.disabled = false; btn.textContent = 'Enable'; });
        });

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.botvis-btn-disable');
            if (!btn) return;
            var fk = btn.getAttribute('data-feature-key');
            if (!fk) return;
            btn.disabled = true; btn.textContent = 'Disabling\u2026';
            botvisAjax('botvis_disable_feature', { feature_key: fk }).then(function (r) {
                if (r.success) {
                    btn.textContent = 'Enable';
                    btn.classList.remove('botvis-btn-disable');
                    btn.classList.add('botvis-btn-enable');
                    btn.disabled = false;
                } else { btn.disabled = false; btn.textContent = 'Disable'; }
            }).catch(function () { btn.disabled = false; btn.textContent = 'Disable'; });
        });
    }

    /* 3. Fix All */
    function initFixAllButton() {
        var btn = $('#botvis-fix-all-btn');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var includeL4 = false;
            if (botvisData.agentFeatures && Object.keys(botvisData.agentFeatures).length > 0) {
                includeL4 = confirm('This will also enable Agent-Native features that add new REST endpoints and database tables to your site. Include Level 4 features?');
            }
            btn.disabled = true; btn.textContent = 'Fixing All\u2026';
            var payload = {};
            if (includeL4) payload.include_l4 = '1';
            botvisAjax('botvis_fix_all', payload).then(function (r) {
                if (r.success) { btn.textContent = 'Re-scanning\u2026'; var sb = $('#botvis-scan-btn'); if (sb) sb.click(); }
                btn.disabled = false; btn.textContent = 'Fix All';
            }).catch(function () { btn.disabled = false; btn.textContent = 'Fix All'; });
        });
    }

    /* 4. Check Card Expand/Collapse */
    function initCheckCardToggles() {
        document.addEventListener('click', function (e) {
            var hdr = e.target.closest('.botvis-check-header');
            if (!hdr) return;
            var item = hdr.closest('.botvis-check-item');
            var det = item ? item.querySelector('.botvis-check-details') : null;
            if (!det) return;
            var exp = hdr.getAttribute('aria-expanded') === 'true';
            hdr.setAttribute('aria-expanded', String(!exp));
            det.style.display = exp ? 'none' : '';
            var caret = hdr.querySelector('.botvis-caret');
            if (caret) caret.classList.toggle('botvis-caret-open', !exp);
        });
    }

    /* 5. File Toggle Switches */
    function initFileToggles() {
        document.addEventListener('change', function (e) {
            var t = e.target.closest('.botvis-toggle-file');
            if (!t) return;
            botvisAjax('botvis_toggle_file', { file_key: t.getAttribute('data-file-key'), enabled: t.checked ? '1' : '0' })
                .then(function (r) {
                    if (!r.success) return;
                    var row = t.closest('.botvis-file-row'), badge = row ? row.querySelector('.botvis-status-badge') : null;
                    if (badge) { badge.textContent = t.checked ? 'Active' : 'Disabled'; badge.className = 'botvis-status-badge ' + (t.checked ? 'botvis-badge-active' : 'botvis-badge-disabled'); }
                });
        });
    }

    /* 6. File Preview Modal */
    function initPreviewButtons() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.botvis-preview-btn');
            if (!btn) return;
            var fk = btn.getAttribute('data-file-key');
            if (!fk) return;
            botvisAjax('botvis_preview_file', { file_key: fk }).then(function (r) {
                if (r.success && r.data) openModal({ title: fk, content: r.data.content || '', readonly: true });
            });
        });
    }

    /* 7. File Edit Modal */
    function initEditButtons() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.botvis-edit-btn');
            if (!btn) return;
            var fk = btn.getAttribute('data-file-key');
            if (!fk) return;
            botvisAjax('botvis_preview_file', { file_key: fk }).then(function (r) {
                if (r.success && r.data) openModal({ title: fk, content: r.data.content || '', readonly: false, fileKey: fk });
            });
        });
    }

    /* 8. File Edit Save */
    function initModalSave() {
        document.addEventListener('click', function (e) {
            if (!e.target.closest('#botvis-modal-save')) return;
            var modal = $('#botvis-modal');
            if (!modal) return;
            var ta = modal.querySelector('.botvis-modal-editor'), fk = modal.getAttribute('data-file-key');
            if (!ta || !fk) return;
            var sb = $('#botvis-modal-save');
            if (sb) { sb.disabled = true; sb.textContent = 'Saving\u2026'; }
            botvisAjax('botvis_save_custom_content', { file_key: fk, content: ta.value })
                .then(function (r) {
                    if (r.success) closeModal();
                    if (sb) { sb.disabled = false; sb.textContent = 'Save'; }
                }).catch(function () { if (sb) { sb.disabled = false; sb.textContent = 'Save'; } });
        });
    }

    /* 9. File Export */
    function initExportButtons() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.botvis-export-btn');
            if (!btn) return;
            var fk = btn.getAttribute('data-file-key');
            if (!fk) return;
            btn.disabled = true; btn.textContent = 'Exporting\u2026';
            botvisAjax('botvis_export', { file_key: fk }).then(function (r) {
                if (r.success) {
                    var row = btn.closest('.botvis-file-row'), badge = row ? row.querySelector('.botvis-status-badge') : null;
                    if (badge) { badge.textContent = 'Static'; badge.className = 'botvis-status-badge botvis-badge-static'; }
                    btn.textContent = 'Exported'; btn.classList.add('botvis-btn-success');
                } else { btn.textContent = 'Export'; }
                btn.disabled = false;
            }).catch(function () { btn.disabled = false; btn.textContent = 'Export'; });
        });
    }

    /* 10. Enable All / Export All */
    function initBulkFileActions() {
        var eab = $('#botvis-enable-all-btn');
        if (eab) {
            eab.addEventListener('click', function () {
                var toggles = $$('.botvis-toggle-file'), promises = [];
                toggles.forEach(function (t) {
                    if (!t.checked) { t.checked = true; promises.push(botvisAjax('botvis_toggle_file', { file_key: t.getAttribute('data-file-key'), enabled: '1' })); }
                });
                if (!promises.length) return;
                eab.disabled = true; eab.textContent = 'Enabling\u2026';
                Promise.all(promises).then(function () {
                    eab.disabled = false; eab.textContent = 'Enable All';
                    toggles.forEach(function (t) {
                        var row = t.closest('.botvis-file-row'), b = row ? row.querySelector('.botvis-status-badge') : null;
                        if (b) { b.textContent = 'Active'; b.className = 'botvis-status-badge botvis-badge-active'; }
                    });
                });
            });
        }
        var xab = $('#botvis-export-all-btn');
        if (xab) {
            xab.addEventListener('click', function () {
                var keys = [];
                $$('.botvis-export-btn').forEach(function (b) { var k = b.getAttribute('data-file-key'); if (k) keys.push(k); });
                if (!keys.length) return;
                xab.disabled = true; xab.textContent = 'Exporting\u2026';
                var chain = Promise.resolve();
                keys.forEach(function (fk) { chain = chain.then(function () { return botvisAjax('botvis_export', { file_key: fk }); }); });
                chain.then(function () {
                    xab.disabled = false; xab.textContent = 'Export All';
                    $$('.botvis-export-btn').forEach(function (b) {
                        var row = b.closest('.botvis-file-row'), badge = row ? row.querySelector('.botvis-status-badge') : null;
                        if (badge) { badge.textContent = 'Static'; badge.className = 'botvis-status-badge botvis-badge-static'; }
                    });
                });
            });
        }
    }

    /* 11. Settings Form */
    function initSettingsForm() {
        var form = $('#botvis-settings-form');
        if (!form) return;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(form), payload = {};
            fd.forEach(function (v, k) {
                if (k.endsWith('[]')) { var ak = k.slice(0, -2); if (!payload[ak]) payload[ak] = []; payload[ak].push(v); }
                else payload[k] = v;
            });
            form.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
                if (!cb.checked && !cb.name.endsWith('[]')) payload[cb.name] = '0';
            });
            var sb = form.querySelector('button[type="submit"], input[type="submit"]');
            if (sb) { sb.disabled = true; sb.textContent = 'Saving\u2026'; }
            botvisAjax('botvis_save_settings', payload).then(function (r) {
                showNotice(r.success ? 'Settings saved successfully.' : 'Failed to save settings.', r.success ? 'success' : 'error');
                if (sb) { sb.disabled = false; sb.textContent = 'Save Settings'; }
            }).catch(function () {
                showNotice('An error occurred while saving.', 'error');
                if (sb) { sb.disabled = false; sb.textContent = 'Save Settings'; }
            });
        });
    }

    /* 12. Modal Management */
    function openModal(opts) {
        var m = $('#botvis-modal') || createModal();
        var ti = m.querySelector('.botvis-modal-title'), ed = m.querySelector('.botvis-modal-editor'), sb = $('#botvis-modal-save');
        if (ti) ti.textContent = opts.title || '';
        if (ed) { ed.value = opts.content || ''; ed.readOnly = !!opts.readonly; }
        if (sb) sb.style.display = opts.readonly ? 'none' : '';
        m.setAttribute('data-file-key', opts.fileKey || '');
        m.style.display = 'flex'; m.setAttribute('aria-hidden', 'false');
        document.body.classList.add('botvis-modal-open');
    }

    function closeModal() {
        var m = $('#botvis-modal');
        if (!m) return;
        m.style.display = 'none'; m.setAttribute('aria-hidden', 'true'); m.removeAttribute('data-file-key');
        document.body.classList.remove('botvis-modal-open');
        var ed = m.querySelector('.botvis-modal-editor');
        if (ed) ed.value = '';
    }

    function createModal() {
        var o = document.createElement('div');
        o.id = 'botvis-modal'; o.className = 'botvis-modal-overlay';
        o.setAttribute('aria-hidden', 'true'); o.style.display = 'none';
        o.innerHTML =
            '<div class="botvis-modal"><div class="botvis-modal-header">' +
            '<h3 class="botvis-modal-title"></h3>' +
            '<button type="button" class="botvis-modal-close" aria-label="Close">&times;</button></div>' +
            '<div class="botvis-modal-body"><textarea class="botvis-modal-editor" rows="20"></textarea></div>' +
            '<div class="botvis-modal-footer">' +
            '<button type="button" id="botvis-modal-save" class="botvis-btn botvis-btn-primary">Save</button>' +
            '<button type="button" class="botvis-modal-close botvis-btn botvis-btn-secondary">Cancel</button>' +
            '</div></div>';
        document.body.appendChild(o);
        return o;
    }

    function initModalClose() {
        document.addEventListener('click', function (e) {
            if (e.target.closest('.botvis-modal-close')) { closeModal(); return; }
            if (e.target.id === 'botvis-modal') closeModal();
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
    }

    /* 13. Confetti Animation */
    function botvisConfetti() {
        var ct = $('#botvis-scan-area');
        if (!ct) return;
        var colors = ['#ef4444','#f59e0b','#22c55e','#3b82f6','#8b5cf6','#ec4899','#14b8a6','#f97316'];
        var parts = [];
        for (var i = 0; i < 60; i++) {
            var p = document.createElement('div');
            p.className = 'botvis-confetti-particle';
            p.style.cssText = 'position:absolute;width:8px;height:8px;pointer-events:none;z-index:9999;opacity:1;top:-10px;' +
                'border-radius:' + (Math.random() > 0.5 ? '50%' : '2px') + ';' +
                'background:' + colors[i % colors.length] + ';left:' + (Math.random() * 100) + '%;' +
                'animation:botvisConfettiFall ' + (2 + Math.random() * 2) + 's ease-out forwards;' +
                'animation-delay:' + (Math.random() * 0.5) + 's;';
            ct.appendChild(p); parts.push(p);
        }
        if (!$('#botvis-confetti-style')) {
            var s = document.createElement('style'); s.id = 'botvis-confetti-style';
            s.textContent = '@keyframes botvisConfettiFall{0%{transform:translateY(0) rotate(0);opacity:1}100%{transform:translateY(400px) rotate(720deg);opacity:0}}';
            document.head.appendChild(s);
        }
        setTimeout(function () { parts.forEach(function (el) { if (el.parentNode) el.parentNode.removeChild(el); }); }, 4000);
    }

    /* 14. Results Rendering */
    function botvisRenderResults(data, ct) {
        if (!data || !ct) return;
        var cl = data.currentLevel || 0, lv = cl > 0 ? botvisData.levels[cl] : null;
        var ln = lv ? 'Level ' + cl + ': ' + lv.name : 'Getting Started';
        var lc = lv ? lv.color : 'var(--text-inverse)';
        var ld = lv ? lv.description : 'Start by making your site discoverable to AI agents.';
        var tp = 0, ta = 0;
        Object.keys(data.levels).forEach(function (n) { tp += data.levels[n].passed; ta += data.levels[n].total - data.levels[n].na; });
        var pct = ta > 0 ? Math.round((tp / ta) * 100) : 0;

        var bh = '';
        Object.keys(data.levels).forEach(function (n) {
            var lp = data.levels[n], ap = lp.total - lp.na, bp = ap > 0 ? Math.round((lp.passed / ap) * 100) : 0;
            var df = lp.level || botvisData.levels[n] || {};
            bh += '<div class="botvis-level-bar"><div class="botvis-level-bar-label">' +
                '<span style="color:' + ea(df.color || '') + '">L' + n + ': ' + eh(df.name || '') + '</span>' +
                '<span>' + lp.passed + '/' + ap + '</span></div>' +
                '<div class="botvis-progress-track"><div class="botvis-progress-fill" style="width:0%;background:' + ea(df.color || '') + '" data-target-width="' + bp + '"></div></div></div>';
        });

        // Agent-Native (L4) section.
        var l4 = data.levels['4'] || data.levels[4];
        if (l4) {
            var l4a = l4.total - l4.na, l4p = l4a > 0 ? Math.round((l4.passed / l4a) * 100) : 0;
            var ans = data.agentNativeStatus || {};
            bh += '<div class="botvis-agent-native-section"><div class="botvis-level-bar"><div class="botvis-level-bar-label">' +
                '<span style="color:#8b5cf6">L4: Agent-Native' +
                (ans.achieved ? ' <span class="botvis-badge-achieved">Ready</span>' : '') +
                '</span><span>' + l4.passed + '/' + l4a + '</span></div>' +
                '<div class="botvis-progress-track"><div class="botvis-progress-fill" style="width:0%;background:#8b5cf6" data-target-width="' + l4p + '"></div></div></div></div>';
        }

        var ch = '';
        if (data.checks && data.checks.length) {
            ch = '<div class="botvis-check-list">';
            data.checks.forEach(function (c) {
                var ps = c.status === 'pass', na = c.status === 'na';
                var sc = ps ? 'botvis-status-pass' : (na ? 'botvis-status-na' : 'botvis-status-fail');
                var ic = ps ? 'botvis-icon-pass' : (na ? 'botvis-icon-na' : 'botvis-icon-fail');
                var sy = ps ? '\u2713' : (na ? '\u2014' : '\u2717');
                ch += '<div class="botvis-check-item ' + sc + '"><div class="botvis-check-header" aria-expanded="false" role="button" tabindex="0">' +
                    '<span class="botvis-check-icon ' + ic + '">' + sy + '</span>' +
                    '<span class="botvis-check-name">' + eh(c.name || '') + '</span>' +
                    '<span class="botvis-check-id">' + eh(c.id || '') + '</span>' +
                    '<span class="botvis-caret"></span></div>' +
                    '<div class="botvis-check-details" style="display:none"><p>' + eh(c.description || '') + '</p>' +
                    (c.details ? '<p class="botvis-check-extra">' + eh(c.details) + '</p>' : '') +
                    (!ps && !na ? (c.level === 4 && c.feature_key ? '<button type="button" class="botvis-btn botvis-btn-enable" data-feature-key="' + ea(c.feature_key) + '">Enable</button>' : (c.fixable !== false ? '<button type="button" class="botvis-btn botvis-btn-fix" data-check-id="' + ea(c.id) + '">Fix</button>' : '')) : '') +
                    '</div></div>';
            });
            ch += '</div>';
        }

        var ts = data.timestamp || new Date().toISOString();
        ct.innerHTML =
            '<div id="botvis-results" data-results="' + ea(JSON.stringify(data)) + '">' +
            '<div class="botvis-score-header"><div class="botvis-level-info">' +
            '<div class="botvis-level-name" style="color:' + ea(lc) + '">' + eh(ln) + '</div>' +
            '<div class="botvis-level-desc">' + eh(ld) + '</div></div>' +
            '<div class="botvis-score-number"><span class="botvis-score-value">' + tp + '</span>' +
            '<span class="botvis-score-total">/' + ta + '</span>' +
            '<div class="botvis-score-label">checks passed</div></div></div>' +
            '<div class="botvis-thermometer">' +
            '<div class="botvis-thermometer-fill" style="width:0%" data-target-width="' + pct + '"></div>' +
            '<div class="botvis-thermometer-needle" style="left:0%" data-target-left="' + pct + '"></div></div>' +
            '<div class="botvis-level-bars">' + bh + '</div>' + ch +
            '<div class="botvis-scan-meta">Last scanned: ' + eh(ts) + '</div></div>';

        requestAnimationFrame(function () { requestAnimationFrame(function () {
            var tf = ct.querySelector('.botvis-thermometer-fill');
            if (tf) { tf.style.transition = 'width 0.8s ease-out'; tf.style.width = tf.getAttribute('data-target-width') + '%'; }
            var tn = ct.querySelector('.botvis-thermometer-needle');
            if (tn) { tn.style.transition = 'left 0.8s ease-out'; tn.style.left = tn.getAttribute('data-target-left') + '%'; }
            ct.querySelectorAll('.botvis-progress-fill').forEach(function (f) {
                f.style.transition = 'width 0.6s ease-out'; f.style.width = f.getAttribute('data-target-width') + '%';
            });
        }); });
    }

    /* Notice helper */
    function showNotice(msg, type) {
        var ex = $('.botvis-notice');
        if (ex) ex.parentNode.removeChild(ex);
        var n = document.createElement('div');
        n.className = 'botvis-notice botvis-notice-' + (type || 'info');
        n.textContent = msg;
        var c = $('.botvis-content');
        if (c) c.insertBefore(n, c.firstChild);
        setTimeout(function () {
            if (!n.parentNode) return;
            n.style.opacity = '0'; n.style.transition = 'opacity 0.3s ease';
            setTimeout(function () { if (n.parentNode) n.parentNode.removeChild(n); }, 300);
        }, 4000);
    }

    /* Escaping helpers */
    function eh(s) { var d = document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML; }
    function ea(s) { return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    /* Init */
    document.addEventListener('DOMContentLoaded', function () {
        capturePreviousLevel();
        initScanButton();
        initFixButtons();
        initFeatureButtons();
        initFixAllButton();
        initCheckCardToggles();
        initFileToggles();
        initPreviewButtons();
        initEditButtons();
        initModalSave();
        initExportButtons();
        initBulkFileActions();
        initSettingsForm();
        initModalClose();
    });
})();
