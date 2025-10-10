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

    // Legal modal loader: fetch privacy/terms and display inside a modal
    (function(){
        document.addEventListener('DOMContentLoaded', function(){
            var legalModal = document.getElementById('legal-modal');
            var legalBackdrop = legalModal ? legalModal.querySelector('.modal-backdrop') : null;
            var legalContent = document.getElementById('legal-modal-content');
            var legalClose = legalModal ? legalModal.querySelector('.modal-close') : null;

            function openLegal(html) {
                if (!legalModal || !legalContent) return;
                // ensure the close button exists and is not overwritten by fetched html
                legalContent.innerHTML = html;
                // clone close template into the modal-panel if close button missing
                var tmpl = document.getElementById('tmpl-modal-close');
                var panel = legalModal.querySelector('.modal-panel');
                if (panel && tmpl && !panel.querySelector('.modal-close')) {
                    var clone = tmpl.content.cloneNode(true);
                    panel.insertBefore(clone, panel.firstChild);
                }
                // optionally set a heading if missing
                var h = legalContent.querySelector('h1'); if (h) h.id = 'legal-modal-title';
                legalModal.setAttribute('aria-hidden','false'); legalModal.classList.add('open'); document.body.classList.add('scroll-lock');
                if (legalClose && typeof legalClose.focus === 'function') legalClose.focus();
                // local trapFocus for legal modal (isolated copy)
                var focusableSelectors = 'a[href], area[href], input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])';
                var focusable = Array.from(legalModal.querySelectorAll(focusableSelectors)).filter(function(el){ return el.offsetParent !== null; });
                var first = focusable.length ? focusable[0] : legalModal;
                var last = focusable.length ? focusable[focusable.length - 1] : legalModal;
                var keyHandler = function(e){
                    if (e.key === 'Tab'){
                        if (e.shiftKey) { if (document.activeElement === first) { e.preventDefault(); last.focus(); } }
                        else { if (document.activeElement === last) { e.preventDefault(); first.focus(); } }
                    }
                    if (e.key === 'Escape') { closeLegal(); }
                };
                document.addEventListener('keydown', keyHandler);
                // store remover for potential future use (cleanup when closing)
                legalModal._legalKeyHandler = keyHandler;
            }

            function closeLegal() {
                if (!legalModal) return;
                legalModal.setAttribute('aria-hidden','true'); legalModal.classList.remove('open'); document.body.classList.remove('scroll-lock');
                if (legalModal._legalKeyHandler) { document.removeEventListener('keydown', legalModal._legalKeyHandler); legalModal._legalKeyHandler = null; }
                // clear content to reduce memory
                if (legalContent) legalContent.innerHTML = '';
            }

            // Handle clicks on links that should open legal pages in modal
            document.querySelectorAll('.open-legal').forEach(function(a){
                a.addEventListener('click', function(e){
                    e.preventDefault();
                    var src = a.getAttribute('data-src') || a.getAttribute('href');
                    if (!src) return;
                    // Fetch the page and extract the main content
                    fetch(src, { credentials: 'same-origin' }).then(function(res){ return res.text(); }).then(function(text){
                        try {
                            var doc = new DOMParser().parseFromString(text, 'text/html');
                            var main = doc.querySelector('main') || doc.querySelector('.container') || doc.body;
                            var html = main ? main.innerHTML : text;
                            openLegal(html, a.textContent || 'Legal');
                        } catch (e) { openLegal(text, a.textContent || 'Legal'); }
                    }).catch(function(){ openLegal('<p>Unable to load content. Please visit the page directly.</p><p><a href="'+src+'">Open page</a></p>', a.textContent || 'Legal'); });
                });
            });

            if (legalBackdrop) legalBackdrop.addEventListener('click', closeLegal);
            // Delegate close handling to the modal container so the handler works
            // even if the close button is replaced or the DOM mutates.
            if (legalModal) {
                legalModal.addEventListener('pointerdown', function(e){
                    var btn = e.target.closest && e.target.closest('.modal-close');
                    if (btn && legalModal.contains(btn)) { 
                        // ripple feedback: create a small element and animate
                        var ripple = btn.querySelector('.ripple');
                        if (!ripple) { ripple = document.createElement('span'); ripple.className = 'ripple'; btn.appendChild(ripple); }
                        // trigger animation
                        btn.classList.remove('ripple-animate');
                        // force reflow
                        void btn.offsetWidth;
                        btn.classList.add('ripple-animate');
                        e.preventDefault(); e.stopPropagation(); closeLegal(); 
                    }
                });
                // click fallback for environments where pointer events may not fire
                legalModal.addEventListener('click', function(e){
                    var btn = e.target.closest && e.target.closest('.modal-close');
                    if (btn && legalModal.contains(btn)) { e.preventDefault(); e.stopPropagation(); closeLegal(); }
                });
            }
            // support Escape key to close
            document.addEventListener('keydown', function(e){ if (e.key === 'Escape') { if (legalModal && legalModal.classList.contains('open')) closeLegal(); } });
        });
    })();

    // Menu card expand/collapse
    (function(){
        // menu-card toggle handled elsewhere; helper removed to avoid unused function warnings

        document.addEventListener('DOMContentLoaded', function() {
            // contact modal open/close
            var openContact = document.querySelectorAll('.open-contact');
            var contactModal = document.getElementById('contact-modal');
            var contactBackdrop = document.getElementById('contact-modal-backdrop');
            var closeButtons = contactModal ? contactModal.querySelectorAll('.close-modal') : [];

            function openModal() {
                contactModal.classList.add('open');
                contactBackdrop.classList.add('open');
                var first = contactModal.querySelector('input, textarea, select');
                if (first) first.focus();
            }

            function closeModal() {
                contactModal.classList.remove('open');
                contactBackdrop.classList.remove('open');
            }

            openContact.forEach(function(el){ el.addEventListener('click', function(e){ e.preventDefault(); openModal(); }); });
            contactBackdrop && contactBackdrop.addEventListener('click', closeModal);
            closeButtons.forEach(function(btn){ btn.addEventListener('click', closeModal); });

            // Expand/collapse behavior for public unit/menu cards
            // When expanded we present the card centered in a modal-like backdrop
            var menuModalState = { openCard: null, origParent: null, origNext: null, backdrop: null, keyHandler: null, opener: null };

            function openMenuModal(card, btn) {
                if (!card) return;
                // if already open, no-op
                if (menuModalState.openCard) return;
                var lab = btn ? btn.querySelector('.expand-label') : null;
                if (lab) lab.textContent = 'Show less';
                if (btn) btn.setAttribute('aria-expanded', 'true');

                // store original position so we can restore later
                menuModalState.origParent = card.parentNode;
                menuModalState.origNext = card.nextSibling;

                // remember what element opened the modal so we can return focus on close
                menuModalState.opener = btn || document.activeElement;

                // create backdrop if missing
                var backdrop = document.querySelector('.menu-modal-backdrop');
                if (!backdrop) {
                    backdrop = document.createElement('div');
                    backdrop.className = 'menu-modal-backdrop';
                    // mark initial accessibility attributes on the backdrop
                    backdrop.setAttribute('aria-hidden', 'true');
                    var panel = document.createElement('div');
                    panel.className = 'modal-panel';
                    // provide dialog semantics on the panel for assistive tech
                    panel.setAttribute('role', 'dialog');
                    panel.setAttribute('aria-modal', 'true');
                    panel.setAttribute('tabindex', '-1');
                    backdrop.appendChild(panel);
                    document.body.appendChild(backdrop);
                } else {
                    // ensure panel has the proper ARIA attributes if backdrop existed
                    var existingPanel = backdrop.querySelector('.modal-panel');
                    if (existingPanel && !existingPanel.getAttribute('role')) {
                        existingPanel.setAttribute('role', 'dialog');
                        existingPanel.setAttribute('aria-modal', 'true');
                        existingPanel.setAttribute('tabindex', '-1');
                    }
                }
                menuModalState.backdrop = backdrop;

                // move the card into the modal panel
                var panelEl = backdrop.querySelector('.modal-panel');
                panelEl.appendChild(card);
                // mark modal mode and expanded
                card.classList.add('expanded');
                card.classList.add('modal-mode');
                card.classList.add('modal-panel');
                // show backdrop (and expose to AT) 
                backdrop.classList.add('open');
                backdrop.setAttribute('aria-hidden', 'false');
                document.body.classList.add('scroll-lock');
                menuModalState.openCard = card;

                // clicking backdrop (outside panel) should close
                backdrop.addEventListener('click', backdropClickHandler);

                // focus trap: keep keyboard navigation inside the modal panel
                var focusableSelectors = 'a[href], area[href], input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])';
                var focusable = Array.from(panelEl.querySelectorAll(focusableSelectors)).filter(function(el){ return el.offsetParent !== null; });
                var firstFocusable = focusable.length ? focusable[0] : panelEl;
                var lastFocusable = focusable.length ? focusable[focusable.length - 1] : panelEl;
                try { firstFocusable.focus(); } catch(e) { /* ignore */ }

                menuModalState.keyHandler = function(e){
                    if (e.key === 'Escape') { e.preventDefault(); closeMenuModal(); return; }
                    if (e.key === 'Tab') {
                        // if no focusable items, do nothing
                        if (focusable.length === 0) { e.preventDefault(); return; }
                        if (e.shiftKey) {
                            if (document.activeElement === firstFocusable) { e.preventDefault(); lastFocusable.focus(); }
                        } else {
                            if (document.activeElement === lastFocusable) { e.preventDefault(); firstFocusable.focus(); }
                        }
                    }
                };
                document.addEventListener('keydown', menuModalState.keyHandler);
            }

            function backdropClickHandler(e) {
                // if click outside the modal-panel, close
                var panel = menuModalState.backdrop && menuModalState.backdrop.querySelector('.modal-panel');
                if (!panel) return;
                if (!panel.contains(e.target)) closeMenuModal();
            }

            function closeMenuModal() {
                var card = menuModalState.openCard;
                if (!card) return;
                var backdrop = menuModalState.backdrop;
                // remove modal classes
                card.classList.remove('modal-mode');
                card.classList.remove('modal-panel');
                card.classList.remove('expanded');
                // restore to original location
                if (menuModalState.origParent) {
                    if (menuModalState.origNext && menuModalState.origNext.parentNode === menuModalState.origParent) {
                        menuModalState.origParent.insertBefore(card, menuModalState.origNext);
                    } else {
                        menuModalState.origParent.appendChild(card);
                    }
                }
                // update button aria/label if present
                var btn = card.querySelector('.expand-btn');
                if (btn) { btn.setAttribute('aria-expanded', 'false'); var lab = btn.querySelector('.expand-label'); if (lab) lab.textContent = 'See all units'; }

                // hide and cleanup backdrop with exit animation
                if (backdrop) {
                    // add closing class to trigger CSS exit animation
                    backdrop.classList.remove('open');
                    // mark as closing and hide from assistive tech
                    backdrop.classList.add('closing');
                    backdrop.setAttribute('aria-hidden', 'true');
                    backdrop.removeEventListener('click', backdropClickHandler);
                    // after animation, remove closing class
                    setTimeout(function(){ backdrop.classList.remove('closing'); }, 260);
                }
                document.body.classList.remove('scroll-lock');
                if (menuModalState.keyHandler) {
                    document.removeEventListener('keydown', menuModalState.keyHandler);
                    menuModalState.keyHandler = null;
                }
                // restore focus to the opener (if any) for accessibility
                try {
                    if (menuModalState.opener && typeof menuModalState.opener.focus === 'function') {
                        menuModalState.opener.focus();
                    }
                } catch(e) { /* ignore focus failures */ }
                menuModalState.opener = null;
                menuModalState.openCard = null;
            }

            function toggleMenuCard(card, btn) {
                if (!card) return;
                if (menuModalState.openCard) {
                    closeMenuModal();
                } else {
                    openMenuModal(card, btn);
                }
            }

            // Button toggles
            var expandBtns = Array.from(document.querySelectorAll('.menu-grid .expand-btn'));
            expandBtns.forEach(function(b){
                b.addEventListener('click', function(e){
                    e.preventDefault();
                    var card = b.closest('.menu-card');
                    toggleMenuCard(card, b);
                });
            });

            // Also allow clicking the card body to toggle (but ignore clicks on links/buttons/inputs inside)
            var cards = Array.from(document.querySelectorAll('.menu-grid .menu-card'));
            cards.forEach(function(c){
                c.addEventListener('click', function(e){
                    var target = e.target;
                    // ignore interactive elements
                    if (target.closest('a, button, input, textarea, select')) return;
                    // only toggle when clicking the card body area (not images)
                    var btn = c.querySelector('.expand-btn');
                    toggleMenuCard(c, btn);
                });
            });

            // small DOM helpers
            function showFieldError(field, message) {
                var p = field.closest('.form-row') || field.parentNode;
                var err = p.querySelector('.field-error');
                if (!err) { err = document.createElement('div'); err.className = 'field-error'; p.appendChild(err); }
                err.textContent = message;
                field.classList.add('has-error');
            }
            function clearFieldError(field) {
                var p = field.closest('.form-row') || field.parentNode;
                var err = p.querySelector('.field-error');
                if (err) err.textContent = '';
                field.classList.remove('has-error');
            }

            function validateEmail(email) {
                // simple RFC-like regex for common formats
                var re = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@(([^<>()[\]\\.,;:\s@"]+\.)+[^<>()[\]\\.,;:\s@"]{2,})$/i;
                // normalize: remove unnecessary escape before double-quote for compatibility
                re = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@(([^<>()[\]\\.,;:\s@"]+\.)+[^<>()[\]\\.,;:\s@"]{2,})$/i;
                return re.test(String(email).toLowerCase());
            }

            // thank-you micro-interaction: display check and simple confetti burst
            function playThankYouAnimation(container) {
                var wrap = document.createElement('div');
                wrap.className = 'thankyou-wrap';
                wrap.innerHTML = '<div class="checkmark">\n  <svg viewBox="0 0 52 52">\n    <path d="M14 27 L21 34 L38 17" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>\n  </svg>\n</div>';
                container.appendChild(wrap);
                // simple confetti — small colored spans
                var colors = ['#ff5a5f','#7bd389','#ffd36e','#5cc1ff'];
                for (var i=0;i<12;i++){
                    var c = document.createElement('span'); c.className='confetti'; c.style.backgroundColor = colors[i%colors.length];
                    c.style.left = (20 + Math.random()*60) + '%';
                    c.style.top = (30 + Math.random()*40) + '%';
                    wrap.appendChild(c);
                    // remove after animation
                    (function(el){ setTimeout(function(){ el.remove(); }, 1400); })(c);
                }
                setTimeout(function(){ wrap.classList.add('fade'); setTimeout(function(){ wrap.remove(); }, 500); }, 1000);
            }

            // AJAX contact form submit with client-side validation and optional reCAPTCHA v3
            var contactForm = document.getElementById('footer-contact-form');
            if (contactForm) {
                contactForm.addEventListener('submit', function(e){
                    e.preventDefault();
                    var submitBtn = contactForm.querySelector('button[type=submit]');
                    var msg = contactForm.querySelector('.form-message');
                    // clear previous
                    msg.textContent = ''; msg.className = 'form-message';
                    var firstName = contactForm.querySelector('[name=first_name]');
                    var email = contactForm.querySelector('[name=email]');
                    var message = contactForm.querySelector('[name=message]');

                    // basic validation
                    var ok = true;
                    [firstName, email, message].forEach(function(f){ if (!f) return; clearFieldError(f); });
                    if (firstName && !firstName.value.trim()) { showFieldError(firstName, 'Please enter your name'); ok = false; }
                    if (email && !email.value.trim()) { showFieldError(email, 'Please enter your email'); ok = false; }
                    else if (email && !validateEmail(email.value.trim())) { showFieldError(email, 'Please enter a valid email'); ok = false; }
                    if (message && !message.value.trim()) { showFieldError(message, 'Please enter a message'); ok = false; }
                    if (!ok) return;

                    // disable submit
                    if (submitBtn) { submitBtn.disabled = true; submitBtn.classList.add('is-loading'); }

                    function doSubmit() {
                        var fd = new FormData(contactForm);
                        fetch('/contact.php', { method: 'POST', body: fd }).then(function(res){ return res.json(); }).then(function(json){
                            if (json.success) {
                                msg.classList.add('success');
                                msg.textContent = json.message || 'Thanks! We received your message.';
                                contactForm.reset();
                                // play tiny animation
                                playThankYouAnimation(contactForm);
                                setTimeout(function(){ closeModal(); }, 1200);
                            } else {
                                msg.classList.add('error');
                                msg.textContent = json.message || 'There was a problem. Please try again.';
                            }
                        }).catch(function(){
                            msg.classList.add('error');
                            msg.textContent = 'Network error. Please try again.';
                        }).finally(function(){ if (submitBtn) { submitBtn.disabled = false; submitBtn.classList.remove('is-loading'); } });
                    }

                    // No reCAPTCHA configured: submit directly
                    doSubmit();
                });
            }
        });
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

            // AJAX submit for modal contact form (uses same endpoint but stays in modal)
            if (form) {
                form.addEventListener('submit', function(e){
                    e.preventDefault();
                    var submitBtn = form.querySelector('button[type="submit"]');
                    var originalText = submitBtn ? submitBtn.textContent : null;
                    if (submitBtn) { submitBtn.textContent = 'Sending...'; submitBtn.disabled = true; }
                    var fd = new FormData(form);
                    fetch(form.getAttribute('action') || '/contact.php', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function(res){ return res.text(); }).then(function(text){
                        var data = null; try { data = text ? JSON.parse(text) : null; } catch(e){ data = null; }
                        var container = form.parentNode;
                        // remove previous messages
                        var prev = container.querySelector('.form-message'); if (prev) prev.remove();
                        if (data && data.success) {
                            var ok = document.createElement('div'); ok.className = 'form-message form-success'; ok.textContent = data.message || 'Thanks — your message was sent.'; container.insertBefore(ok, form.nextSibling);
                            form.reset(); setTimeout(function(){ closeModal(); ok.classList.add('fade-out'); setTimeout(function(){ ok.remove(); }, 350); }, 1400);
                        } else {
                            var err = document.createElement('div'); err.className = 'form-message form-error'; err.textContent = (data && data.message) ? data.message : 'Submission failed. Please try again.'; container.insertBefore(err, form.nextSibling);
                            setTimeout(function(){ try { err.classList.add('fade-out'); setTimeout(function(){ err.remove(); }, 350); } catch(e){ console.error(e); } }, 5000);
                        }
                    }).catch(function(err){ var container = form.parentNode; var errEl = document.createElement('div'); errEl.className='form-message form-error'; errEl.textContent = 'Network error: ' + err.message; container.insertBefore(errEl, form.nextSibling); setTimeout(function(){ errEl.classList.add('fade-out'); setTimeout(function(){ errEl.remove(); }, 350); }, 5000); }).finally(function(){ if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalText; } });
                });
            }

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
                        '<button type="button" class="modal-close" aria-label="Close hours">'+
                            '<svg class="icon-close" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'+
                                '<path d="M6 6 L18 18 M6 18 L18 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none" />'+
                            '</svg>'+
                        '</button>'+
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
