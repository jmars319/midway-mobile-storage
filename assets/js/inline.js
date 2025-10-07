// Consolidated inline scripts previously embedded in index.php
(function(){
    // Storage quote confirmation helper
    (function(){
        try {
            var params = new URLSearchParams(window.location.hash.replace(/^#/, '?'));
            if (!params || params.get('success') !== '1') return;
            var wrap = document.getElementById('storage-quote-confirm');
            var outer = document.getElementById('storage-quote-confirm-msg');
            var text = document.getElementById('storage-quote-confirm-text');
            if (!wrap || !outer || !text) return;

            var dismissKey = 'storage_quote_confirm_dismissed';
            if (localStorage.getItem(dismissKey) === '1') return;

            text.textContent = 'Thank you! Your quote request has been received. We will contact you shortly with a custom estimate.';
            wrap.classList.add('show');
            try { outer.focus({preventScroll:true}); } catch(e) { /* no-op */ void 0; }

            var close = document.getElementById('storage-quote-confirm-close');
            if (close) {
                close.addEventListener('click', function(){
                    wrap.classList.remove('show');
                    try { localStorage.setItem(dismissKey, '1'); } catch(e){ void 0; }
                });
            }

            try { history.replaceState(null, '', window.location.pathname + window.location.search + '#storage-quote'); } catch(e){ void 0; }
        } catch(e) { /* non-fatal */ }
    })();

    // Menu card expand/collapse
    (function(){
        function toggleForButton(btn){
            var card = btn.closest('.menu-card'); if (!card) return;
            var expanded = card.classList.toggle('expanded');
            btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            var label = btn.querySelector('.expand-label'); if (label) label.textContent = expanded ? 'Less' : 'View All';
            return expanded;
        }

        document.addEventListener('click', function(e){
            var btn = e.target.closest && e.target.closest('.expand-btn');
            if (!btn) return;
            toggleForButton(btn);
        });

        document.addEventListener('keydown', function(e){
            if (!e.target) return;
            if (!e.target.classList || !e.target.classList.contains('expand-btn')) return;
            if (e.key === 'Enter') { e.preventDefault(); toggleForButton(e.target); }
            if (e.key === ' ') { e.preventDefault(); toggleForButton(e.target); }
        });
    })();

    // Accessibility helpers + contact modal
    (function(){
        try {
            var navLinks = document.querySelectorAll('.nav-link');
            var sections = Array.from(navLinks).map(function(a){
                var href = a.getAttribute('href') || ''; if (!href.startsWith('#')) return null; return document.querySelector(href);
            });
            const updateCurrent = function(){
                var top = window.scrollY + 96;
                for (var i=0;i<navLinks.length;i++){
                    var a = navLinks[i]; var sec = sections[i];
                    if (!sec) { a.removeAttribute('aria-current'); continue; }
                    var rect = sec.getBoundingClientRect();
                    var inView = (rect.top + window.scrollY) <= top && (rect.bottom + window.scrollY) > top;
                    if (inView) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current');
                }
            };
            window.addEventListener('scroll', updateCurrent, {passive:true});
            window.addEventListener('resize', updateCurrent);
            updateCurrent();

            var mapBtns = document.querySelectorAll('.map-links a[aria-pressed]');
            mapBtns.forEach(function(b){
                b.addEventListener('click', function(){
                    try { b.setAttribute('aria-pressed','true'); } catch(e){ void 0; }
                    setTimeout(function(){ try { b.setAttribute('aria-pressed','false'); } catch(e){ void 0; } }, 1200);
                });
            });

            var mapLinks = document.querySelector('.map-links');
            if (mapLinks) {
                var iframe = document.querySelector('.about-map');
                var reveal = function(){ mapLinks.classList.add('visible'); };
                if (iframe) {
                    if (iframe.addEventListener) iframe.addEventListener('load', reveal); else iframe.onload = reveal;
                    setTimeout(reveal, 1200);
                } else { reveal(); }
            }

            var modal = document.getElementById('contact-modal');
            var footerLink = document.getElementById('footer-contact-link');
            var form = document.getElementById('footer-contact-form');
            var backdrop = modal ? (modal.querySelector('.modal-backdrop') || document.getElementById('contact-modal-backdrop')) : document.getElementById('contact-modal-backdrop');
            var closeBtn = modal ? modal.querySelector('.modal-close') : null;

            var removeFocusTrap = null;
            const openModal = function(e){
                if (e && e.preventDefault) e.preventDefault();
                var opener = (e && e.currentTarget) ? e.currentTarget : (e && e.target) ? e.target : null;
                if (e && e.dataset && e.dataset.contactMessage) opener = e;
                if (!modal || !form) return;
                modal.setAttribute('aria-hidden','false'); modal.classList.add('open'); document.body.classList.add('scroll-lock');
                try {
                    var msgNode = form.querySelector('[name="message"]');
                    if (opener && opener.dataset && opener.dataset.contactMessage && msgNode) {
                        msgNode.value = opener.dataset.contactMessage;
                        msgNode.focus();
                    } else {
                        let firstField = form.querySelector('[name="first_name"]'); if (firstField) firstField.focus();
                    }
                } catch(e) { try { let firstField = form.querySelector('[name="first_name"]'); if (firstField) firstField.focus(); } catch(e) { void 0; } }
                removeFocusTrap = trapFocus(modal);
            };
            const closeModal = function(){ if (!modal) return; modal.setAttribute('aria-hidden','true'); modal.classList.remove('open'); document.body.classList.remove('scroll-lock'); if (typeof removeFocusTrap === 'function') { removeFocusTrap(); removeFocusTrap = null; } };

            if (footerLink) footerLink.addEventListener('click', openModal);
            var extraOpeners = document.querySelectorAll('.open-contact');
            extraOpeners.forEach(function(el){ if (el !== footerLink) el.addEventListener('click', openModal); });
            if (backdrop) backdrop.addEventListener('click', closeModal);
            if (closeBtn) closeBtn.addEventListener('click', closeModal);

            var observer = new MutationObserver(function(m){
                m.forEach(function(rec){
                    if (rec.addedNodes && rec.addedNodes.length) {
                        rec.addedNodes.forEach(function(n){
                            if (n.classList && n.classList.contains('form-success')) { setTimeout(closeModal, 900); }
                        });
                    }
                });
            });
            if (form && form.parentNode) observer.observe(form.parentNode, { childList: true });

            if (form) form.setAttribute('action', '/contact.php');

            const trapFocus = function(modalEl) {
                var focusableSelectors = 'a[href], area[href], input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])';
                var focusable = Array.from(modalEl.querySelectorAll(focusableSelectors)).filter(function(el){ return el.offsetParent !== null; });
                if (!focusable.length) return function(){};
                var first = focusable[0]; var last = focusable[focusable.length - 1];
                const keyHandler = function(e) {
                    if (e.key === 'Tab') {
                        if (e.shiftKey) { if (document.activeElement === first) { e.preventDefault(); last.focus(); } }
                        else { if (document.activeElement === last) { e.preventDefault(); first.focus(); } }
                    }
                    if (e.key === 'Escape') { closeModal(); }
                };
                document.addEventListener('keydown', keyHandler);
                return function remove() { document.removeEventListener('keydown', keyHandler); };
            }
        } catch(e) { /* non-fatal */ }
    })();

    // Hours modal (reads hours from window.__HOURS_DATA)
    (function(){
        var hoursLink = document.getElementById('footer-hours-link');
        if (!hoursLink) return;
        var hoursData = window.__HOURS_DATA || {};

        var modal = null, backdrop = null, closeBtn = null, popupWin = null;

        function buildModal() {
            try {
                var html = ''+
                '<div id="hours-modal" class="modal" role="dialog" aria-hidden="true" aria-labelledby="hours-modal-title">'+
                    '<div class="modal-backdrop" id="hours-modal-backdrop"></div>'+
                    '<div class="modal-panel" role="document">'+
                        '<button type="button" class="modal-close" aria-label="Close hours">✕</button>'+
                        '<h2 id="hours-modal-title">Hours</h2>'+
                        '<div class="card">'+
                            '<div class="hours-list">';
                var daysMap = {0:'sunday',1:'monday',2:'tuesday',3:'wednesday',4:'thursday',5:'friday',6:'saturday'};
                var todayKey = daysMap[new Date().getDay()];
                for (var d in hoursData) {
                    if (!Object.prototype.hasOwnProperty.call(hoursData, d)) continue;
                    var isToday = (d.toLowerCase() === todayKey);
                    var cls = isToday ? 'hours-today' : '';
                    var badge = isToday ? ' <span class="today-badge">Today</span>' : '';
                    html += '<div class="'+cls+' hours-row"><div class="text-capitalize">'+d.replace(/_/g,' ') + badge +'</div><div>'+hoursData[d]+'</div></div>';
                }
                html +=      '</div>'+
                        '</div>'+
                    '</div>'+
                '</div>';
                var wrap = document.createElement('div'); wrap.innerHTML = html;
                document.body.appendChild(wrap.firstChild);
                modal = document.getElementById('hours-modal');
                backdrop = document.getElementById('hours-modal-backdrop');
                closeBtn = modal ? modal.querySelector('.modal-close') : null;
                return !!modal;
            } catch (e) {
                return false;
            }
        }

        function openPopupFallback() {
            try {
                var winOpts = 'width=420,height=520,toolbar=no,menubar=no,location=no,status=no,resizable=yes,scrollbars=yes';
                popupWin = window.open('', 'BusinessHours', winOpts);
                if (!popupWin) { alert(formatHoursPlain()); return; }
                var doc = popupWin.document;
                doc.open();
                doc.write('<!doctype html><html><head><meta charset="utf-8"><title>Hours</title>');
                doc.write('<meta name="viewport" content="width=device-width,initial-scale=1">');
                doc.write('<style>body{font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;padding:1rem;color:var(--admin-modal-text)}h1{font-size:1.25rem;margin:0 0 .5rem} .hours-list div{display:flex;justify-content:space-between;padding:.35rem 0;border-bottom:1px dashed #e6eef8} .today{background:rgba(245,158,11,0.06);padding:.35rem;border-radius:6px;font-weight:700}</style>');
                doc.write('</head><body>');
                doc.write('<h1>Hours</h1>');
                doc.write('<div class="hours-list">');
                var daysMap = {0:'sunday',1:'monday',2:'tuesday',3:'wednesday',4:'thursday',5:'friday',6:'saturday'};
                var todayKey = daysMap[new Date().getDay()];
                for (var d in hoursData) {
                    if (!Object.prototype.hasOwnProperty.call(hoursData, d)) continue;
                    var isToday = (d.toLowerCase() === todayKey);
                    var cls = isToday ? 'today' : '';
                    doc.write('<div class="'+cls+'"><div class="text-capitalize">'+d.replace(/_/g,' ')+(isToday? ' <strong>Today</strong>':'')+'</div><div>'+hoursData[d]+'</div></div>');
                }
                doc.write('</div>');
                doc.write('</body></html>');
                doc.close();
                popupWin.focus();
            } catch (e) {
                alert(formatHoursPlain());
            }
        }

        function formatHoursPlain() {
            var out = '';
                for (var d in hoursData) { if (!Object.prototype.hasOwnProperty.call(hoursData, d)) continue; out += d.replace(/_/g,' ') + ': ' + hoursData[d] + '\n'; }
            return out || 'No hours available';
        }

        function openModal() {
            if (!modal) {
                if (!buildModal()) { openPopupFallback(); return; }
                if (backdrop) backdrop.addEventListener('click', closeModal);
                if (closeBtn) closeBtn.addEventListener('click', closeModal);
            }
            modal.setAttribute('aria-hidden','false'); modal.classList.add('open'); document.body.classList.add('scroll-lock');
            try { if (closeBtn) closeBtn.focus(); } catch(e){ void 0; }
        }

        function closeModal() {
            if (modal) { modal.setAttribute('aria-hidden','true'); modal.classList.remove('open'); document.body.classList.remove('scroll-lock'); }
            if (popupWin && !popupWin.closed) { try { popupWin.close(); } catch(e){ void 0; } popupWin = null; }
        }

        hoursLink.addEventListener('click', function(e){ e.preventDefault(); openModal(); });
    })();

    // Footer fixed toggling
    (function(){
        function updateFooterFixed() {
            var footer = document.querySelector('.footer');
            if (!footer) return;
            footer.classList.remove('footer--fixed');
            document.body.classList.remove('footer-has-fixed-footer');
            var required = footer.scrollHeight;
            var cssVal = getComputedStyle(document.documentElement).getPropertyValue('--footer-height') || '96px';
            var footerHeight = parseInt(cssVal, 10) || 96;
            if (required <= footerHeight) {
                footer.classList.add('footer--fixed');
                document.body.classList.add('footer-has-fixed-footer');
            } else {
                footer.classList.remove('footer--fixed');
                document.body.classList.remove('footer-has-fixed-footer');
            }
        }
        window.addEventListener('load', updateFooterFixed);
        window.addEventListener('resize', function(){ setTimeout(updateFooterFixed, 120); });
        window.addEventListener('DOMContentLoaded', function(){ setTimeout(updateFooterFixed, 200); });
    })();

    // No guest advisory needed for the storage quote form

})();
