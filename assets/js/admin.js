// Admin UI JS (extracted from admin/index.php)
/**
 * assets/js/admin.js
 * Client-side admin UI helpers used by the PHP admin pages. This
 * script runs inside a trusted admin session in the browser and
 * performs no critical security checks itself (CSRF and auth are
 * enforced server-side). Keep this file small and defensive:
 *
 * Responsibilities:
 *  - Render schema-driven form editors and the menu editor UI
 *  - Provide image picking, upload helpers, and trash/restore flows
 *  - Perform client-side validation to improve UX, but rely on
 *    server-side validation for authoritative checks.
 */

(function(){
  // Toast / modal helpers
  function showToast(msg, type='default', timeout=3500){
    const c = document.getElementById('toast-container');
    if (!c) return;
    const el = document.createElement('div'); el.className='toast '+(type==='success'? 'success': type==='error' ? 'error':''); el.textContent=msg;
  c.appendChild(el);
  setTimeout(()=>{ el.classList.add('fade-out'); setTimeout(()=>el.remove(),350) }, timeout);
  }
  function showConfirm(message){
    return new Promise((resolve)=>{
      const backdrop = document.getElementById('modal-backdrop');
      const body = document.getElementById('modal-body');
      const ok = document.getElementById('modal-ok');
      const cancel = document.getElementById('modal-cancel');
      const closeBtn = document.getElementById('modal-close');
      if (!backdrop || !body || !ok || !cancel) return resolve(false);
      // compose modal body
      body.innerHTML = '';
      const p = document.createElement('div'); p.textContent = message; body.appendChild(p);
  // remember previous focus so we can restore it
  const previouslyFocused = document.activeElement;
  backdrop.classList.add('open');

      // focus first focusable element in the modal
      const focusable = backdrop.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
      if (focusable && focusable.length) focusable[0].focus();

  function cleanup(){
    backdrop.classList.remove('open');
    ok.removeEventListener('click', onOk);
    cancel.removeEventListener('click', onCancel);
    if (closeBtn) closeBtn.removeEventListener('click', onClose);
    document.removeEventListener('keydown', onKey);
    // restore previous focus
  try { if (previouslyFocused && previouslyFocused.focus) previouslyFocused.focus(); } catch (e) { void 0; }
  }
      function onOk(){ cleanup(); resolve(true); }
      function onCancel(){ cleanup(); resolve(false); }
      function onClose(){ cleanup(); resolve(false); }

      function onKey(e){
        if (e.key === 'Escape') { onClose(); }
        // simple tab trap
        if (e.key === 'Tab') {
          const nodes = Array.prototype.slice.call(focusable || []);
          if (!nodes.length) return;
          const idx = nodes.indexOf(document.activeElement);
          if (e.shiftKey) {
            if (idx === 0) { nodes[nodes.length-1].focus(); e.preventDefault(); }
          } else {
            if (idx === nodes.length - 1) { nodes[0].focus(); e.preventDefault(); }
          }
        }
      }

      ok.addEventListener('click', onOk);
      cancel.addEventListener('click', onCancel);
      if (closeBtn) closeBtn.addEventListener('click', onClose);
      document.addEventListener('keydown', onKey);
    });
  }

  // expose a global helper so other inline/admin scripts can use the same modal
  try { window.showAdminConfirm = showConfirm; window.showAdminToast = showToast; } catch(e){ /* no-op */ void 0; }

  // Main
  const sectionSelect = document.getElementById('section-select');
  const schemaFields = document.getElementById('schema-fields');
  const saveForm = document.getElementById('schema-form');
  const uploadForm = document.getElementById('upload-form');

  const siteContent = window.__siteContent || {};
  const schemaUrl = window.__schemaUrl || 'content-schemas.json';

  let schemas = {};

  function fetchSchemas(){
    return fetch(schemaUrl).then(r=>{ if (!r.ok) throw new Error('Failed to load schemas'); return r.json(); }).catch(()=>({}));
  }

  // Small utility: remove duplicate entries by 'relative' path while
  // preserving order. Accepts an array of file objects or strings and
  // returns a normalized array of objects { relative, url }.
  function dedupeFiles(entries) {
    if (!entries || !entries.length) return [];
    const seen = new Set();
    const out = [];
    entries.forEach(e => {
      let rel = null, url = null;
      if (!e) return;
      if (typeof e === 'string') { rel = e; url = (window.ADMIN_UPLOADS_BASE || '../uploads/images/') + e; }
      else if (typeof e === 'object') { rel = e.relative || ''; url = e.url || ((window.ADMIN_UPLOADS_BASE || '../uploads/images/') + (e.relative || '')); }
      rel = (rel || '').trim(); if (!rel) return;
      if (seen.has(rel)) return;
      seen.add(rel);
      out.push({ relative: rel, url: url });
    });
    return out;
  }

  function makeOption(val, label){ const o = document.createElement('option'); o.value=val; o.textContent=label||val; return o; }

  function renderField(field, value){
  const wrap = document.createElement('div'); wrap.className = 'field-wrap';
  const label = document.createElement('label'); label.className = 'field-label'; label.textContent = field.label || field.key;
    wrap.appendChild(label);
    let input;
      if (field.type === 'textarea') {
        input = document.createElement('textarea'); input.className = 'field-textarea field-textarea-lg';
      input.name = field.key;
      input.value = Array.isArray(value) ? value.join('\n') : (value || '');
    } else if (field.type === 'image'){
  const row = document.createElement('div'); row.className = 'field-row';
  input = document.createElement('input'); input.type='text'; input.name = field.key; input.className = 'field-input-flex'; input.value = value || '';
      input.setAttribute('data-field-type','image');
      // If the current editor section is 'units' prefer gallery images for picks
      try {
        const cur = (sectionSelect && sectionSelect.value) ? String(sectionSelect.value).toLowerCase() : '';
        if (cur && cur.indexOf('unit') !== -1) {
          input.dataset.context = 'gallery';
        }
      } catch (e) { /* ignore */ }
      const pick = document.createElement('button'); pick.type='button'; pick.textContent='Pick'; pick.addEventListener('click', ()=> openImagePicker(input));
      row.appendChild(input); row.appendChild(pick); wrap.appendChild(row);
      return wrap;
    } else {
  input = document.createElement('input'); input.type='text'; input.name = field.key; input.className = 'field-input'; input.value = value || '';
    }
    wrap.appendChild(input);
    return wrap;
  }

  function openImagePicker(targetInput){
    const backdrop = document.getElementById('modal-backdrop');
    const body = document.getElementById('modal-body');
    if (!backdrop || !body) return;
    body.innerHTML = '<div class="picker-grid"></div>';
    const grid = body.firstChild;
  backdrop.classList.add('open');
  // NOTE: list-images.php returns a JSON list of filenames in
  // uploads/images. This UI trusts that the admin session is
  // authenticated; the server-side endpoint enforces auth and CSRF
  // where appropriate. The picker only displays filenames — the
  // chosen value is written into the text input for later saving.
  // Determine if this picker should be constrained to hero images.
  // Use multiple heuristics: input name contains 'hero' OR the currently
  // selected section in the editor is the 'hero' section.
  // allow callers to ask for gallery-specific images via data-context
  const requestedContext = targetInput && targetInput.dataset && targetInput.dataset.context ? targetInput.dataset.context : null;
  const inputLooksLikeHero = (targetInput && targetInput.name && /hero/i.test(targetInput.name));
  const sectionLooksLikeHero = (typeof sectionSelect !== 'undefined' && sectionSelect && sectionSelect.value && /hero/i.test(sectionSelect.value));
  const isHero = inputLooksLikeHero || sectionLooksLikeHero;
  const contextQuery = requestedContext ? ('?context=' + encodeURIComponent(requestedContext)) : (isHero ? '?context=hero' : '');
  const listUrl = 'list-images.php' + contextQuery;
  fetch(listUrl).then(r=>r.json()).then(j=>{
      if (!j || !Array.isArray(j.files)) { grid.innerHTML = '<i>No images</i>'; return; }
    const allowedExt = ['png','jpg','jpeg','gif','webp','svg','ico'];
    // Normalize entries and dedupe: server may return strings or objects { relative, url }
    const files = dedupeFiles(j.files || []);
    if (!files.length) { grid.innerHTML = '<i>No images</i>'; return; }
    files.forEach(entry => {
      const rel = (entry && entry.relative) ? entry.relative : '';
      const url = (entry && entry.url) ? entry.url : ((window.ADMIN_UPLOADS_BASE || '../uploads/images/') + rel);
      if (!rel) return;
      // defensive client-side filter: skip hidden files and non-image extensions
      if (rel.charAt(0) === '.') return;
      const ext = (rel.split('.').pop() || '').toLowerCase();
      if (!ext || allowedExt.indexOf(ext) === -1) return;

      const thumb = document.createElement('div'); thumb.className = 'thumb';
      const img = document.createElement('img'); img.src = url; img.className = 'img-120x80';
      // fallback placeholder (SVG data URI) in case image fails to load
      img.onerror = function(){ this.onerror=null; this.src = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="120" height="80"><rect width="100%" height="100%" fill="%23f3f4f6"/><text x="50%" y="50%" fill="%23949" font-size="10" text-anchor="middle" dy=".3em">No preview</text></svg>'; };
      const lab = document.createElement('div'); lab.textContent = rel; lab.className = 'thumb-label';
      thumb.appendChild(img); thumb.appendChild(lab);
      thumb.addEventListener('click', ()=>{ targetInput.value = rel; targetInput.dispatchEvent(new Event('input', { bubbles: true })); backdrop.classList.remove('open');
        // if this input belongs to the menu admin, update any preview immediately
        try { if (typeof renderPreview === 'function') window.setTimeout(renderPreview, 80); } catch(e){ /* ignore */ }
      });
      grid.appendChild(thumb);
    });
  }).catch(()=>{ grid.innerHTML = '<i>Failed to load</i>'; });
  const ok = document.getElementById('modal-ok'); const cancel = document.getElementById('modal-cancel'); const closeBtn = document.getElementById('modal-close');
  const cleanup = function(){ backdrop.classList.remove('open'); ok && ok.removeEventListener('click', onOk); cancel && cancel.removeEventListener('click', onCancel); if (closeBtn) closeBtn.removeEventListener('click', onClose); };
    const onOk = function(){ cleanup(); };
    const onCancel = function(){ cleanup(); };
    const onClose = function(){ cleanup(); };
      ok && ok.addEventListener('click', onOk); cancel && cancel.addEventListener('click', onCancel); if (closeBtn) closeBtn.addEventListener('click', onClose);
  }

  function renderSection(sec){
    if (!schemaFields) return;
    schemaFields.innerHTML = '';
    const schema = schemas[sec];
    const data = siteContent[sec] || {};
    if (!schema) {
      const ta = document.createElement('textarea'); ta.className = 'field-textarea field-textarea-lg'; ta.value = JSON.stringify(data, null, 2);
      const hint = document.createElement('div'); hint.textContent = 'No schema for this section — edit raw JSON below.';
      schemaFields.appendChild(hint); schemaFields.appendChild(ta);
      return;
    }
    schema.fields.forEach(f=>{
      const val = (data[f.key] !== undefined) ? data[f.key] : '';
      const fld = renderField(f, val);
      schemaFields.appendChild(fld);
    });
  }

  function populateSections(){
    if (!sectionSelect) return;
    sectionSelect.innerHTML = '';
  // Keep separate management areas out of the Site Content Editor dropdown.
  // Exclude keys that have dedicated admin sections such as 'images' and 'menu'.
  const allKeys = [...Object.keys(schemas), ...Object.keys(siteContent)];
  const filtered = allKeys.filter(k => k !== 'images' && k !== 'menu' && k !== 'units');
    const keys = new Set(filtered);
    keys.forEach(k=> sectionSelect.appendChild(makeOption(k, (schemas[k] && schemas[k].label) ? schemas[k].label + ' ('+k+')' : k)));
  }

  fetchSchemas().then(s=>{ schemas = s || {}; populateSections(); if (sectionSelect){ sectionSelect.addEventListener('change', ()=> renderSection(sectionSelect.value));
      if (sectionSelect.options.length) sectionSelect.selectedIndex = 0; renderSection(sectionSelect.value); }
  });

  if (saveForm) {
    saveForm.addEventListener('submit', function(e){
      e.preventDefault();
      const sec = sectionSelect.value;
      const inputs = schemaFields.querySelectorAll('[name]');
      const out = {};
      inputs.forEach(inp=>{
        const name = inp.name;
        const val = inp.value;
        if (inp.tagName.toLowerCase() === 'textarea' && (name === 'items' || name === 'hours')) {
          out[name] = val.split(/\r?\n/).map(s=>s.trim()).filter(Boolean);
        } else {
          out[name] = val;
        }
      });
      const csrf = (document.querySelector('input[name="csrf_token"]') || {}).value || window.__csrfToken || '';
      const body = { section: sec, content: out, csrf_token: csrf };
      fetch('save-content.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
        .then(r=>r.json()).then(j=>{
          if (j && j.success) { showToast('Saved', 'success'); window.__siteContent[sec] = out; }
          else showToast('Save failed: '+(j.message||'unknown'),'error');
        }).catch(err=> showToast('Save error: '+err.message,'error'));
    });
  }

  // Attach AJAX upload handlers to any per-type image upload forms created in the image sections.
  function attachImageSectionUploadHandlers(){
    const forms = document.querySelectorAll('.image-section form');
    if (!forms || !forms.length) return;
    forms.forEach(form => {
      // avoid double-binding
      if (form.__ajax_bound) return; form.__ajax_bound = true;
      form.addEventListener('submit', function(e){
        e.preventDefault();
        const btn = form.querySelector('button[type="submit"]');
        const status = document.createElement('div'); status.className = 'small mt-05';
        btn && btn.setAttribute('disabled','disabled');
        if (btn) { btn.dataset.orig = btn.textContent; btn.textContent = 'Uploading...'; }
        // insert status after the form
        form.parentNode.insertBefore(status, form.nextSibling);
        // Client-side validation: size and type
        const fileInput = form.querySelector('input[type="file"][name="image"]');
        if (!fileInput || !fileInput.files || !fileInput.files.length) { status.textContent = 'Please choose a file'; btn && btn.removeAttribute('disabled'); return; }
        const file = fileInput.files[0];
        const maxSize = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSize) { status.textContent = 'File too large (max 5MB)'; btn && btn.removeAttribute('disabled'); return; }
        const allowed = ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml'];
        if (allowed.indexOf(file.type) === -1) { status.textContent = 'Unsupported file type'; btn && btn.removeAttribute('disabled'); return; }

        // Use XMLHttpRequest to get upload progress events
        const xhr = new XMLHttpRequest();
        const fd = new FormData(form);
        if (!fd.get('csrf_token')) {
          const token = (document.querySelector('input[name="csrf_token"]') || {}).value || window.__csrfToken || '';
          if (token) fd.append('csrf_token', token);
        }
  const progressBar = document.createElement('div'); progressBar.className = 'image-upload-progress'; progressBar.innerHTML = '<div class="bar"><div class="fill"></div></div>';
        form.parentNode.insertBefore(progressBar, form.nextSibling);

        xhr.upload.addEventListener('progress', function(e){
          if (e.lengthComputable) {
            const pct = Math.round((e.loaded / e.total) * 100);
            const fill = progressBar.querySelector('.fill'); if (fill) fill.style.setProperty('--w', pct + '%');
            status.textContent = 'Uploading: ' + pct + '%';
          }
        });
        xhr.addEventListener('load', function(){
          try {
            if (xhr.status === 401) {
              status.textContent = 'Not authenticated';
              showToast('Session expired — please log in again', 'error');
              // Optionally reload to show login page
              // window.location = 'login.php';
              return;
            }
            const ct = (xhr.getResponseHeader && xhr.getResponseHeader('Content-Type') || '').toLowerCase();
            let j;
            if (ct.indexOf('application/json') !== -1) {
              j = JSON.parse(xhr.responseText || '{}');
            } else {
              // Try to parse anyway; if it fails, surface a helpful error with a snippet
              try { j = JSON.parse(xhr.responseText || '{}'); }
              catch (e) {
                status.textContent = 'Upload response parse error';
                showToast('Upload response parse error — server returned non-JSON. See console for details.','error');
                console.error('Upload non-JSON response:', xhr.status, xhr.responseText);
                return;
              }
            }
            if (j && j.success) {
              status.textContent = 'Uploaded: ' + (j.filename || j.message || '');
              // update preview and per-section list
              const sect = form.closest('.image-section');
              if (sect) {
                const img = sect.querySelector('img');
                if (img) {
                  const src = j.thumbnail ? j.thumbnail : (j.url ? j.url : ((window.ADMIN_UPLOADS_BASE || '../uploads/images/') + j.filename));
                  img.src = src + (src.indexOf('?') === -1 ? ('?v=' + Date.now()) : ('&v=' + Date.now()));
                }
                // auto-close the section after success
                try { if (sect.hasAttribute('open')) sect.removeAttribute('open'); } catch(e){ void 0; }
              }
              // refresh global and per-section image lists
              refreshImageList();
              refreshPerSectionLists();
              showToast('Upload successful', 'success');
            } else {
              status.textContent = 'Upload failed: ' + (j && j.message ? j.message : 'unknown');
              showToast('Upload failed: ' + (j && j.message ? j.message : 'unknown'), 'error');
            }
          } catch (err) { status.textContent = 'Upload response parse error'; showToast('Upload response parse error','error'); }
        });
  xhr.addEventListener('error', function(){ status.textContent = 'Upload error'; showToast('Upload error','error'); });
  xhr.open('POST', 'upload-image.php');
  try { xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest'); xhr.setRequestHeader('Accept', 'application/json'); } catch(e){ /* some browsers may disallow setting certain headers for FormData but try */ }
  xhr.send(fd);
        // cleanup
        xhr.addEventListener('readystatechange', function(){ if (xhr.readyState === 4) { btn && btn.removeAttribute('disabled'); if (btn && btn.dataset && btn.dataset.orig) btn.textContent = btn.dataset.orig; setTimeout(()=>{ if (progressBar && progressBar.parentNode) progressBar.remove(); }, 700); } });
      });
    });
  }

  // run once at init and also after any dynamic changes
  attachImageSectionUploadHandlers();

  function refreshImageList() {
    const list = document.getElementById('image-list');
    if (!list) return;
    // show a loading state so the user sees progress in the modal
    list.innerHTML = '<div class="muted">Loading images&hellip;</div>';
    // Determine active tab or container to scope images. If an element with
    // id 'images-tab' (or data-active-tab) exists, use its data-type to filter.
    const activeTab = document.querySelector('[data-active-tab]');
    const activeType = activeTab ? (activeTab.getAttribute('data-type') || '').toLowerCase() : '';

    fetch('list-images.php').then(async (r) => {
      // detect non-OK or non-JSON responses and surface helpful debug info
      if (!r.ok) throw new Error('Server returned ' + r.status + ' ' + r.statusText);
      const ct = (r.headers.get('content-type') || '').toLowerCase();
      const text = await r.text();
      let j;
      if (ct.indexOf('application/json') !== -1) {
        try { j = JSON.parse(text); }
        catch (e) { throw new Error('Invalid JSON response'); }
      } else {
        // try to parse as JSON anyway, otherwise include a snippet of the response
        try { j = JSON.parse(text); }
        catch (e) { throw new Error('Unexpected response (not JSON): ' + (text || '').slice(0,200)); }
      }
      return j;
    }).then(j=>{
  if (!j || !Array.isArray(j.files)) { list.innerHTML = '<i>No images</i>'; return; }
  list.innerHTML = '';
  const allowedExt = ['png','jpg','jpeg','gif','webp','svg','ico'];
  // normalize and dedupe entries to objects { relative, url }
  const files = dedupeFiles(j.files);
  files.forEach(entry=>{
    const rel = (entry.relative || '').trim(); if (!rel) return;
    // skip hidden files and non-image types
    if (rel.charAt(0) === '.') return;
    const ext = (rel.split('.').pop() || '').toLowerCase(); if (!ext || allowedExt.indexOf(ext) === -1) return;

    // If an activeType is set, only show images that match the type.
    // Matching rules: first path segment equals type OR filename contains the type keyword.
    if (activeType) {
      const firstSeg = (rel.split('/')[0] || '').toLowerCase();
      const basename = (rel.split('/').pop() || '').toLowerCase();
      if (firstSeg !== activeType && basename.indexOf(activeType) === -1) return;
    }

  const row = document.createElement('div'); row.className = 'flex-row';
  const img = document.createElement('img'); img.src = entry.url; img.className = 'img-48';
    img.onerror = function(){ this.onerror=null; this.src = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="64" height="48"><rect width="100%" height="100%" fill="%23f3f4f6"/><text x="50%" y="50%" fill="%23949" font-size="10" text-anchor="middle" dy=".3em">No preview</text></svg>'; };
  const name = document.createElement('div'); name.textContent = rel; name.className = 'flex-1 text-ellipsis';
        const del = document.createElement('button'); del.type='button'; del.textContent='Delete'; del.className='btn btn-danger-muted'; del.addEventListener('click', async ()=>{
          if (!await showConfirm('Delete '+rel+'?')) return;
          const fd = new FormData(); fd.append('filename', rel); fd.append('csrf_token', (document.querySelector('input[name="csrf_token"]')||{}).value || window.__csrfToken || '');
          fetch('delete-image.php', { method: 'POST', body: fd }).then(r=>r.json()).then(res=>{
            if (res && res.success) { refreshImageList(); showToast('Moved to trash','success'); }
            else showToast('Delete failed: '+(res && res.message ? res.message : 'unknown'),'error');
          }).catch(()=>{ showToast('Delete failed','error'); });
        });
        const restoreBtn = document.createElement('button'); restoreBtn.type='button'; restoreBtn.textContent='Trash'; restoreBtn.disabled=true; restoreBtn.title='In images list';
  row.appendChild(img); row.appendChild(name); row.appendChild(del); row.appendChild(restoreBtn);
        list.appendChild(row);
      });
    }).catch(()=>{ list.innerHTML = '<i>Failed to list images</i>'; });
  }

  // Refresh per-section image containers (if present) to show images relevant to that section
  function refreshPerSectionLists(){
    const containers = document.querySelectorAll('.section-image-list');
    if (!containers || !containers.length) return;
    fetch('list-images.php').then(r=>r.json()).then(j=>{
      if (!j || !Array.isArray(j.files)) { containers.forEach(c=> c.innerHTML = '<i>No images</i>'); return; }
        // Normalize and dedupe file entries to objects { relative, url }
        const files = dedupeFiles(j.files);
        containers.forEach(c=>{
        const type = (c.getAttribute('data-type') || '').toLowerCase();
        c.innerHTML = '';
        // If the page contains a dedicated full-gallery container, prefer
        // rendering gallery images there instead of repeating them in the
        // compact per-section list. This avoids duplicate UI entries with
        // different styling (small vs full list) for the same files.
        if (type === 'gallery' && document.getElementById('gallery-full-list')) {
          // leave the per-section container empty (the full gallery is rendered separately)
          c.innerHTML = '';
          return;
        }

        // For gallery section, show all gallery images and provide a Delete control
        // Only consider files that match this container's data-type
        const matches = files.filter(fn => fn && fn.relative && (function(){
          const rel = fn.relative.toLowerCase();
          const first = rel.split('/')[0] || '';
          const base = rel.split('/').pop() || '';
          return first === type || base.indexOf(type) !== -1;
        })());
        if (type === 'gallery') {
          // Folder-first gallery matching: prefer files located under the 'gallery/' folder
          const folderMatches = files.filter(entry => {
            if (!entry || !entry.relative) return false;
            const rel = entry.relative.toLowerCase();
            // first path segment equals 'gallery'
            const first = rel.split('/')[0] || '';
            return first === 'gallery';
          });
          if (folderMatches.length) {
            matches.length = 0;
            folderMatches.forEach(m => matches.push(m));
          } else {
            // fallback to broader matching: basename starts with 'gallery-' or substring match
            const broad = files.filter(entry => {
              if (!entry || !entry.relative) return false;
              const rel = entry.relative.toLowerCase();
              const base = rel.split('/').pop() || '';
              if (base.startsWith('gallery-')) return true;
              if (rel.indexOf('gallery') !== -1) return true;
              return false;
            });
            matches.length = 0;
            broad.forEach(m => matches.push(m));
          }
          
          // Avoid showing the currently-selected image (the "Current:" preview)
          // as one of the selectable images in the per-section list. The
          // server stores the current image path in window.__siteContent.images[type]
          // (if available). Filter it out to prevent a duplicated visual entry
          // where the preview (small caption) appears above the upload form and
          // again in the list below.
          try {
            const currentImage = (window.__siteContent && window.__siteContent.images && window.__siteContent.images[type]) ? (window.__siteContent.images[type] || '') : '';
            if (currentImage) {
              const curNorm = (''+currentImage).replace(/^\/+/, '').toLowerCase();
              for (let i = matches.length - 1; i >= 0; i--) {
                const r = matches[i] && matches[i].relative ? (''+matches[i].relative).replace(/^\/+/, '').toLowerCase() : '';
                if (r && r === curNorm) matches.splice(i, 1);
              }
            }
          } catch (e) { /* defensive: if site content isn't available, ignore */ }

          // show full list of matches (no limit)
          matches.forEach(entry => {
            if (!entry || !entry.relative) return;
            const rel = entry.relative;
            const url = entry.url || ((window.ADMIN_UPLOADS_BASE || '../uploads/images/') + rel);
            const row = document.createElement('div'); row.className = 'flex-row-sm';
            const img = document.createElement('img'); img.src = url; img.className = 'img-96x64';
            img.onerror = function(){ this.onerror=null; this.src = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="96" height="64"><rect width="100%" height="100%" fill="%23f3f4f6"/><text x="50%" y="50%" fill="%23949" font-size="10" text-anchor="middle" dy=".3em">No preview</text></svg>'; };
            const meta = document.createElement('div'); meta.className = 'image-meta';
            const name = document.createElement('div'); name.textContent = rel; name.className = 'meta-name';
            meta.appendChild(name);
            const del = document.createElement('button'); del.type='button'; del.className='btn btn-danger-muted'; del.textContent='Delete'; del.addEventListener('click', async (ev)=>{
              ev.preventDefault(); ev.stopPropagation(); if (!await showConfirm('Delete '+rel+'?')) return;
              const fd = new FormData(); fd.append('filename', rel); fd.append('csrf_token', (document.querySelector('input[name="csrf_token"]')||{}).value || window.__csrfToken || '');
              fetch('delete-image.php', { method: 'POST', body: fd }).then(r=>r.json()).then(res=>{
                if (res && res.success) { showToast('Moved to trash','success'); refreshPerSectionLists(); refreshImageList(); }
                else showToast('Delete failed: '+(res && res.message ? res.message : 'unknown'),'error');
              }).catch(()=>{ showToast('Delete failed','error'); });
            });
            row.appendChild(img); row.appendChild(meta); row.appendChild(del);
            c.appendChild(row);
          });
        } else {
          // Simple heuristic: show images whose filename contains the type key when available, else show first 12 images
          const toShow = (matches.length ? matches.slice(0,12) : j.files.slice(0,12));
          toShow.forEach(f=>{
            let rel = null, url = null;
            if (typeof f === 'string') { rel = f; url = (window.ADMIN_UPLOADS_BASE || '../uploads/images/') + f; }
            else if (typeof f === 'object') { rel = f.relative || ''; url = f.url || ((window.ADMIN_UPLOADS_BASE || '../uploads/images/') + (f.relative || '')); }
            if (!rel) return;
            const ext = (rel.split('.').pop() || '').toLowerCase(); if (['png','jpg','jpeg','gif','webp','svg','ico'].indexOf(ext) === -1) return;
            const el = document.createElement('div'); el.className = 'el-inline';
            const img = document.createElement('img'); img.src = url; img.className = 'img-80x60';
            img.onerror = function(){ this.onerror=null; this.src = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="80" height="60"><rect width="100%" height="100%" fill="%23f3f4f6"/><text x="50%" y="50%" fill="%23949" font-size="8" text-anchor="middle" dy=".3em">No preview</text></svg>'; };
            const caption = document.createElement('div'); caption.textContent = rel; caption.className = 'caption-small text-ellipsis';
            el.appendChild(img); el.appendChild(caption);
            el.addEventListener('click', ()=>{
              const sect = c.closest('.image-section'); if (!sect) return;
              const imgText = sect.querySelector('input[data-field-type="image"]');
              if (imgText) { imgText.value = rel; imgText.dispatchEvent(new Event('input',{bubbles:true})); showToast('Selected '+rel,'default'); }
            });
            c.appendChild(el);
          });
        }
      });
    }).catch(()=>{ containers.forEach(c=> c.innerHTML = '<i>Failed to load</i>'); });
  }

  // Load trashed images into a separate view
  async function refreshTrashList() {
    const list = document.getElementById('image-list');
    if (!list) return;
    try {
      const r = await fetch('list-trash.php');
      const j = await r.json();
      if (!j || !Array.isArray(j.items)) { list.innerHTML = '<i>No trash</i>'; return; }
      list.innerHTML = '';
      j.items.forEach(it=>{
  const row = document.createElement('div'); row.className = 'flex-row';
  const img = document.createElement('img'); img.src = '../uploads/trash/'+it.trash_name; img.className = 'img-48';
  const name = document.createElement('div'); name.textContent = it.meta && it.meta.original ? it.meta.original : it.trash_name; name.className = 'flex-1 text-ellipsis';
  const restore = document.createElement('button'); restore.type='button'; restore.textContent='Restore'; restore.addEventListener('click', async ()=>{
          if (!await showConfirm('Restore '+name.textContent+'?')) return;
          const fd = new FormData(); fd.append('trash_name', it.trash_name); fd.append('csrf_token', (document.querySelector('input[name="csrf_token"]')||{}).value || window.__csrfToken || '');
          fetch('restore-image.php', { method: 'POST', body: fd }).then(r=>r.json()).then(res=>{
            if (res && res.success) { showToast('Restored','success'); refreshTrashList(); refreshImageList(); }
            else showToast('Restore failed','error');
          });
        });
        row.appendChild(img); row.appendChild(name); row.appendChild(restore);
        list.appendChild(row);
      });
    } catch(e) { list.innerHTML = '<i>Failed to load trash</i>'; }
  }

  // add a simple toggle to switch between Images and Trash
  function ensureTrashToggle() {
    const container = document.getElementById('schema-form-wrap') || document.body;
    if (!container) return;
    if (document.getElementById('img-toggle')) return;
  const t = document.createElement('div'); t.id='img-toggle'; t.className = 'flex-row-sm';
  const showImgs = document.createElement('button'); showImgs.type='button'; showImgs.className='btn btn-ghost'; showImgs.textContent='Images'; showImgs.addEventListener('click', ()=>{ refreshImageList(); });
  const showTrash = document.createElement('button'); showTrash.type='button'; showTrash.className='btn btn-ghost'; showTrash.textContent='Trash'; showTrash.addEventListener('click', ()=>{ refreshTrashList(); });
    t.appendChild(showImgs); t.appendChild(showTrash);
    container.parentNode.insertBefore(t, container.nextSibling);
  }

  // Note: global #image-list removed from template to avoid duplicate listings.
  // Per-section `.section-image-list` elements are used instead. We no longer
  // auto-create a global image list container here.
  // However, keep an optional hidden global list and wire a toggle button
  // in the admin template (#show-all-images-btn) to reveal it on demand.
  // Acquire or create the global imageArea but ensure it is detached from
  // the document so it does not render under the tabs unless intentionally
  // attached into the modal. If an #image-list already exists in the DOM,
  // remove it immediately.
  let imageArea = document.getElementById('image-list');
  if (imageArea) {
    // detach existing node so it doesn't show on page load
    try { if (imageArea.parentNode) imageArea.parentNode.removeChild(imageArea); } catch (e) { /* ignore */ }
  } else {
    imageArea = document.createElement('div'); imageArea.id = 'image-list';
  }
  // force hidden when detached (inline style is highest priority)
  imageArea.style.display = 'none';
  let showAllMode = false;
  const showAllBtn = document.getElementById('show-all-images-btn');
  if (showAllBtn) {
    // helper to count total images (used to show a badge on the button)
    async function refreshImageCount(){
      try {
        const r = await fetch('list-images.php'); const j = await r.json();
        const count = Array.isArray(j.files) ? j.files.length : 0;
        showAllBtn.textContent = showAllMode ? 'Hide all images ('+count+')' : 'Show all images ('+count+')';
      } catch(e) { /* ignore */ }
    }
    // create images modal container if missing
    let modalBackdrop = document.getElementById('images-modal-backdrop');
    if (!modalBackdrop) {
      modalBackdrop = document.createElement('div');
      modalBackdrop.id = 'images-modal-backdrop';
  // rely on CSS for backdrop/modal layout; fallback classes are provided in CSS

  const modal = document.createElement('div'); modal.id = 'images-modal';
  modal.innerHTML = '<div class="modal-header"><strong>All images</strong><button class="close-btn" aria-label="Close images modal"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button></div><div id="images-modal-body"></div>';
  // Rely on CSS for modal layout (styles are defined in admin.modal.css)
  modal.classList.add('images-modal-content');
      // modal styles moved to CSS; append modal/backdrop to the document
      modalBackdrop.appendChild(modal);
      document.body.appendChild(modalBackdrop);
      const closeBtn = modalBackdrop.querySelector('.close-btn');
      if (closeBtn) {
        closeBtn.setAttribute('aria-label', 'Close images modal');
  closeBtn.addEventListener('click', ()=>{ modalBackdrop.classList.remove('images-modal-open'); modalBackdrop.style.display = 'none'; showAllMode = false; try { if (imageArea.parentNode) imageArea.parentNode.removeChild(imageArea); } catch(e){}; refreshImageCount(); });
      }
      // bind Escape to close modal once
      if (!modalBackdrop.__keyBound) {
        modalBackdrop.__keyBound = true;
        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape' && modalBackdrop.classList.contains('images-modal-open')) {
            modalBackdrop.classList.remove('images-modal-open');
            modalBackdrop.style.display = 'none';
            showAllMode = false;
            try { if (imageArea.parentNode) imageArea.parentNode.removeChild(imageArea); } catch(e){}
            try { refreshImageCount(); } catch (err) { void 0; }
          }
        });
      }
    }

    showAllBtn.addEventListener('click', async function(){
      showAllMode = !showAllMode;
      const body = document.getElementById('images-modal-body');
      if (showAllMode) {
        // show modal with image list
        modalBackdrop.classList.add('images-modal-open');
        // ensure it displays as a centered flex container regardless of CSS
        modalBackdrop.style.display = 'flex';
        modalBackdrop.style.alignItems = 'center';
        modalBackdrop.style.justifyContent = 'center';
        // clear modal body then attach imageArea and make visible
        body.innerHTML = '';
        imageArea.style.display = 'block';
        body.appendChild(imageArea);
        // ensure modal itself centers
        try { modal.style.margin = 'auto'; modal.style.display = 'block'; } catch(e) { /* ignore if modal not set */ }
        imageArea.classList.add('show');
        await refreshImageList();
      } else {
        // hide modal and detach the imageArea so it can't appear under tabs
        modalBackdrop.classList.remove('images-modal-open');
        modalBackdrop.style.display = 'none';
        try { if (imageArea.parentNode) imageArea.parentNode.removeChild(imageArea); } catch (e) { /* ignore */ }
        imageArea.classList.remove('show');
        imageArea.style.display = 'none';
      }
      await refreshImageCount();
    });
    // initial count display
    refreshImageCount();
  }
  // Ensure trash toggle is present and refresh lists regardless of upload form presence
  ensureTrashToggle();
  // Wire up image-section <details> toggles to scope the global image list
  function wireImageSectionToggles() {
    const sections = document.querySelectorAll('.image-section');
    if (!sections || !sections.length) return;
  // only scope the global image list if a global container exists and showAllMode is false
  const scopeEl = (showAllMode ? null : document.getElementById('image-list'));
    sections.forEach(sec => {
      // find the per-section container to infer data-type
      const list = sec.querySelector('.section-image-list');
      const type = (list && list.getAttribute('data-type')) ? list.getAttribute('data-type').toLowerCase() : '';
      // toggle event fires when <details> open state changes
      sec.addEventListener('toggle', () => {
        if (scopeEl) {
          if (sec.open) {
            if (type) scopeEl.setAttribute('data-active-tab', type);
          } else {
            // if any other section is open, set that one; otherwise clear
            const other = document.querySelector('.image-section[open] .section-image-list');
            if (other) {
              const othType = (other.getAttribute('data-type') || '').toLowerCase();
              if (othType) scopeEl.setAttribute('data-active-tab', othType); else scopeEl.removeAttribute('data-active-tab');
            } else {
              scopeEl.removeAttribute('data-active-tab');
            }
          }
  }
  // refresh per-section lists; only refresh global list if it exists and showAllMode is false
  try { if (scopeEl) refreshImageList(); refreshPerSectionLists(); } catch(e) { void 0; }
      });
    });
    // initial pass: set attribute if any section already open
    const initOpen = document.querySelector('.image-section[open] .section-image-list');
    if (initOpen) {
      const it = (initOpen.getAttribute('data-type') || '').toLowerCase(); if (it) scopeEl.setAttribute('data-active-tab', it);
    }
  }
  wireImageSectionToggles();
  // initial population: only refresh per-section lists (global image list
  // remains detached and is populated when the modal is opened).
  refreshPerSectionLists();
  if (document.getElementById('gallery-full-list')) refreshGalleryFullList();

  // Render a full gallery list (all files under gallery/) in the #gallery-full-list container
  async function refreshGalleryFullList(){
    const wrap = document.getElementById('gallery-full-list');
    if (!wrap) return;
    wrap.innerHTML = '';
    try {
      const r = await fetch('list-images.php');
      const j = await r.json();
      if (!j || !Array.isArray(j.files)) { wrap.innerHTML = '<i>No images</i>'; return; }
  // Normalize and dedupe entries to objects
  const files = dedupeFiles(j.files);
      const galleryFiles = files.filter(f => f.relative && f.relative.split('/')[0] === 'gallery');
      if (!galleryFiles.length) { wrap.innerHTML = '<i>No gallery images found</i>'; return; }
      galleryFiles.forEach(f => {
  const row = document.createElement('div'); row.className = 'flex-row-sm';
  const img = document.createElement('img'); img.src = f.url; img.className = 'img-120x80'; img.onerror = function(){ this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="120" height="80"><rect width="100%" height="100%" fill="%23f3f4f6"/><text x="50%" y="50%" fill="%23949" font-size="10" text-anchor="middle" dy=".3em">No preview</text></svg>'; };
  const meta = document.createElement('div'); meta.className = 'image-meta'; meta.textContent = f.relative;
        const del = document.createElement('button'); del.type='button'; del.className='btn btn-danger-muted'; del.textContent='Delete'; del.addEventListener('click', async ()=>{
          if (!await showConfirm('Delete '+f.relative+'?')) return;
          const fd = new FormData(); fd.append('filename', f.relative); fd.append('csrf_token', (document.querySelector('input[name="csrf_token"]')||{}).value || window.__csrfToken || '');
          fetch('delete-image.php', { method:'POST', body: fd }).then(r=>r.json()).then(res=>{
            if (res && res.success) { showToast('Moved to trash','success'); refreshPerSectionLists(); refreshImageList(); refreshGalleryFullList(); }
            else showToast('Delete failed: '+(res && res.message ? res.message : 'unknown'),'error');
          }).catch(()=>{ showToast('Delete failed','error'); });
        });
        row.appendChild(img); row.appendChild(meta); row.appendChild(del);
        wrap.appendChild(row);
      });
    } catch (e) { wrap.innerHTML = '<i>Failed to load gallery list</i>'; }
  }

  // expose a couple helpers for debugging
  // and implement Menu Management editor in the admin UI
  window.adminHelpers = { refreshImageList };

  // Menu admin: render editable sections with items, add/delete/reorder, and save whole array
  function initMenuAdmin(){
    const container = document.getElementById('menu-admin');
    console.debug && console.debug('initMenuAdmin: container=', container);
    if (!container) return;
    let listEl = document.getElementById('menu-list');
    const addBtn = document.getElementById('add-menu-item');
    if (!listEl) {
      listEl = document.createElement('div'); listEl.id = 'menu-list'; container.appendChild(listEl);
    }

    // support array-of-sections or legacy flat array
    let menuData = [];
    const rawMenu = window.__siteContent && window.__siteContent.menu;
    if (Array.isArray(rawMenu) && rawMenu.length && rawMenu[0] && rawMenu[0].items !== undefined) {
      menuData = JSON.parse(JSON.stringify(rawMenu));
    } else if (Array.isArray(rawMenu)) {
      menuData = [{ title: 'Menu', id: 'menu', items: JSON.parse(JSON.stringify(rawMenu)) }];
    } else {
      // default sections (so admin sees the sections immediately)
      menuData = [
        { title: "Small Units", id: 'small-units', items: [] },
        { title: "Medium Units", id: 'medium-units', items: [] },
        { title: "Large Units", id: 'large-units', items: [] }
      ];
    }
  console.debug && console.debug('initMenuAdmin: menuData.length=', menuData.length, 'rawMenu=', rawMenu);
  // Ensure each section has a stable id before rendering so persisted
  // expanded/collapsed state matches current sections. Use index-based
  // ids for any missing ones to avoid time-based churn between renders.
  menuData.forEach(function(s, i){ if (!s.id) s.id = 'section-' + (s.title ? s.title.replace(/\s+/g,'-').toLowerCase() : 'idx') + '-' + i; });

  function makeInput(value, placeholder){ const i = document.createElement('input'); i.type='text'; i.value = value || ''; i.placeholder = placeholder || ''; i.className = 'field-input'; return i; }
  function makeTextarea(value, placeholder){ const t = document.createElement('textarea'); t.value = value || ''; t.placeholder = placeholder || ''; t.className = 'field-textarea min-h-60'; return t; }

    // persist expanded sections in localStorage. Default: none expanded (all collapsed).
    const STORAGE_KEY = 'admin.menu.expandedSections';
    let expandedSections = new Set();
    try {
      const stored = localStorage.getItem(STORAGE_KEY);
      if (stored) expandedSections = new Set(JSON.parse(stored));
    } catch (e) { expandedSections = new Set(); }
  // track last added item so we can highlight it after render
  let lastAdded = null;

    function render(){
      console.debug && console.debug('menu.render: about to render; menuData.length=', menuData.length);
      listEl.innerHTML = '';
      if (!menuData.length) {
        const hint = document.createElement('div'); hint.textContent = 'No sections yet. Click "Add Section" to create one.'; hint.className = 'muted'; listEl.appendChild(hint);
      }

      // render sections
  // Build a quick set of current section ids so we can detect stale stored state
  const currentIds = new Set(menuData.map(s => s.id));
  menuData.forEach((section, sidx) => {
        // ensure each section has a stable id to track collapsed state across renders
        if (!section.id) {
          section.id = 'section-' + Date.now() + '-' + Math.floor(Math.random() * 1000);
        }
  const secWrap = document.createElement('div'); secWrap.className = 'section-wrap';
  // identify the wrapper so we can find it after re-rendering
  secWrap.dataset.sectionId = section.id;
  const header = document.createElement('div'); header.className = 'menu-section-header';
  const left = document.createElement('div'); left.className = 'flex-1';
  const titleIn = makeInput(section.title||'', 'Section title'); titleIn.addEventListener('input', ()=> menuData[sidx].title = titleIn.value);
  left.appendChild(titleIn);
        // section-level details: support multiple detail lines (array)
        if (!Array.isArray(section.details) && section.details !== undefined && section.details !== null) {
          // normalize string -> array
          section.details = typeof section.details === 'string' ? [section.details] : [];
        }
        if (!Array.isArray(section.details)) section.details = [];

  const detailsContainer = document.createElement('div'); detailsContainer.className = 'section-details-admin';

        function renderDetailsAdmin() {
          detailsContainer.innerHTML = '';
      section.details.forEach((d, di) => {
        const row = document.createElement('div'); row.className = 'menu-item-row';
            const ta = document.createElement('textarea'); ta.className = 'field-textarea min-h-48'; ta.value = d || ''; ta.placeholder = 'Detail for section (shown in expanded view)';
            ta.addEventListener('input', ()=> { menuData[sidx].details[di] = ta.value; });
            const rem = document.createElement('button'); rem.type='button'; rem.className='btn btn-danger-muted'; rem.textContent='Remove'; rem.addEventListener('click', async ()=>{ if (!await showConfirm('Remove this section detail?')) return; menuData[sidx].details.splice(di,1); render(); });
            row.appendChild(ta); row.appendChild(rem);
            detailsContainer.appendChild(row);
          });
          const add = document.createElement('button'); add.type='button'; add.className='btn btn-ghost'; add.textContent='Add section detail'; add.addEventListener('click', ()=>{ menuData[sidx].details.push(''); render(); });
          detailsContainer.appendChild(add);
        }
        renderDetailsAdmin();
  left.appendChild(detailsContainer);

  // section-level image: single image used for the whole section (admin can pick/upload)
  const secImgIn = makeInput(section.image||'', 'filename.jpg or https://...');
  secImgIn.title = 'Section image filename within uploads/images or a full URL';
  // prefer gallery images when picking for menu sections
  secImgIn.dataset.context = 'gallery';
  secImgIn.setAttribute('data-field-type','image');
  const secPick = document.createElement('button'); secPick.type='button'; secPick.textContent='Pick'; secPick.className='btn btn-ghost'; secPick.addEventListener('click', ()=> openImagePicker(secImgIn));
  const secImgRow = document.createElement('div'); secImgRow.className = 'flex-row-sm'; secImgRow.appendChild(secImgIn); secImgRow.appendChild(secPick);
  const secPreview = document.createElement('img'); secPreview.className = 'preview-img'; if (secImgIn.value) secPreview.src = (window.ADMIN_UPLOADS_BASE || '../uploads/images/') + secImgIn.value;
  secImgIn.addEventListener('input', ()=>{ menuData[sidx].image = secImgIn.value; if (secImgIn.value) secPreview.src = (window.ADMIN_UPLOADS_BASE || '../uploads/images/') + secImgIn.value; else secPreview.removeAttribute('src'); renderPreview(); });
  // collapsible preview wrapper for section-level image
  const secPreviewWrap = document.createElement('div'); secPreviewWrap.className = 'preview-collapsible-wrap';
  const secPreviewToggle = document.createElement('button'); secPreviewToggle.type='button'; secPreviewToggle.className='btn btn-ghost small preview-toggle'; secPreviewToggle.setAttribute('aria-pressed','false');
  // caret SVG + label
  secPreviewToggle.innerHTML = '<span class="preview-label">Preview</span> <svg class="caret" width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  const secPreviewBody = document.createElement('div'); secPreviewBody.className = 'preview-collapsible';
  secPreviewBody.appendChild(secPreview);
  secPreviewWrap.appendChild(secImgRow);
  secPreviewWrap.appendChild(secPreviewToggle);
  secPreviewWrap.appendChild(secPreviewBody);
  // toggle behavior
  secPreviewToggle.addEventListener('click', function(){
    const isOpen = secPreviewBody.classList.toggle('expanded');
    secPreviewToggle.setAttribute('aria-pressed', isOpen ? 'true' : 'false');
    secPreviewToggle.classList.toggle('open', isOpen);
  });
  left.appendChild(secPreviewWrap);

  header.appendChild(left);

  const hdrControls = document.createElement('div'); hdrControls.className = 'flex-row-sm';
        // collapse/expand toggle using persisted expandedSections (localStorage)
        const sectionId = section.id || ('section-' + sidx);
  const toggleBtn = document.createElement('button'); toggleBtn.type='button'; toggleBtn.className='menu-toggle'; toggleBtn.title = 'Collapse / expand';
        toggleBtn.setAttribute('aria-expanded','false');
        // use a chevron SVG that rotates when expanded
        toggleBtn.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><polyline points="6 9 12 15 18 9" stroke-linecap="round" stroke-linejoin="round"></polyline></svg>';
        toggleBtn.addEventListener('click', ()=>{
          try {
            if (expandedSections.has(sectionId)) {
              expandedSections.delete(sectionId);
              itemsWrap.classList.remove('expanded');
              toggleBtn.setAttribute('aria-expanded','false');
            } else {
              expandedSections.add(sectionId);
              itemsWrap.classList.add('expanded');
              toggleBtn.setAttribute('aria-expanded','true');
            }
            localStorage.setItem(STORAGE_KEY, JSON.stringify(Array.from(expandedSections)));
          } catch (e) { /* ignore storage errors */ }
        });

  const addItemBtn = document.createElement('button'); addItemBtn.type='button'; addItemBtn.textContent='Add Item'; addItemBtn.className='btn btn-ghost'; addItemBtn.addEventListener('click', ()=>{
      // push an empty item and ensure the section is expanded so the new item is visible
  menuData[sidx].items.push({ title:'', short:'', description:'', image:'', price: '', quantities: [] });
  // mark the newly added item so render() can highlight it
  try { lastAdded = { sectionId: sectionId, index: menuData[sidx].items.length - 1 }; } catch(e) { lastAdded = null; }
      try {
        expandedSections.add(sectionId);
        localStorage.setItem(STORAGE_KEY, JSON.stringify(Array.from(expandedSections)));
      } catch (e) { /* ignore storage errors */ }
      render();
      // after rendering, focus the first input in the newly added item and ensure it's visible
      setTimeout(function(){
        try {
          var el = document.querySelector('[data-section-id="' + sectionId + '"]');
          if (el) {
            var firstInput = el.querySelector('.menu-section-items .menu-item-row input');
            if (firstInput && typeof firstInput.focus === 'function') { firstInput.focus(); }
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
        } catch (e) { /* ignore */ }
      }, 60);
  });
    const upS = document.createElement('button'); upS.type='button'; upS.textContent='↑'; upS.title='Move section up'; upS.className='btn btn-ghost'; upS.addEventListener('click', ()=>{ if (sidx<=0) return; [menuData[sidx-1], menuData[sidx]] = [menuData[sidx], menuData[sidx-1]]; render(); });
    const downS = document.createElement('button'); downS.type='button'; downS.textContent='↓'; downS.title='Move section down'; downS.className='btn btn-ghost'; downS.addEventListener('click', ()=>{ if (sidx>=menuData.length-1) return; [menuData[sidx+1], menuData[sidx]] = [menuData[sidx], menuData[sidx+1]]; render(); });
  const delS = document.createElement('button'); delS.type='button'; delS.textContent='Delete'; delS.className='btn btn-danger-muted'; delS.addEventListener('click', async ()=>{ if (!await showConfirm('Delete this section and its items?')) return; menuData.splice(sidx,1); render(); });
        hdrControls.appendChild(toggleBtn); hdrControls.appendChild(addItemBtn); hdrControls.appendChild(upS); hdrControls.appendChild(downS); hdrControls.appendChild(delS);
        header.appendChild(hdrControls);
        secWrap.appendChild(header);

        // items list
  const itemsWrap = document.createElement('div'); itemsWrap.className = 'menu-section-items';
        const items = Array.isArray(section.items) ? section.items : [];
        if (!items.length) {
          const hint = document.createElement('div'); hint.textContent = 'No items — use "Add Item"'; hint.className='small'; itemsWrap.appendChild(hint);
        }
        items.forEach((it, idx) => {
          const row = document.createElement('div'); row.className = 'menu-item-row';
          // if this matches the last added sentinel, highlight briefly
          try {
            if (lastAdded && lastAdded.sectionId === sectionId && lastAdded.index === idx) {
              row.classList.add('new-item-flash');
              // remove highlight after animation completes
              setTimeout(function(){ row.classList.remove('new-item-flash'); }, 1600);
              lastAdded = null;
            }
          } catch(e) { /* ignore */ }
          const leftCol = document.createElement('div');
            const titleIn = makeInput(it.title||'', 'e.g. 10x10 Storage Container'); titleIn.title = 'Unit title shown on the site'; titleIn.addEventListener('input', ()=> menuData[sidx].items[idx].title = titleIn.value);
            const shortIn = makeInput(it.short||'', 'e.g. Trailer-ready unit with ramp'); shortIn.title = 'Short subtitle or note shown under the title'; shortIn.classList.add('mt-03'); shortIn.addEventListener('input', ()=> menuData[sidx].items[idx].short = shortIn.value);
            const descIn = makeTextarea(it.description||'', 'Detailed description: suitable for furniture, vehicles, or commercial storage'); descIn.title = 'Long description shown when the item is expanded'; descIn.classList.add('mt-03'); descIn.addEventListener('input', ()=> menuData[sidx].items[idx].description = descIn.value);
            leftCol.appendChild(titleIn); leftCol.appendChild(shortIn); leftCol.appendChild(descIn);

            // Support multiple price entries per item: item.prices = [{ label, amount, note }]
            if (!Array.isArray(it.prices) && (it.price !== undefined || Array.isArray(it.quantities))) {
              // migrate legacy single price or quantities to prices array
              var migrated = [];
              if (it.price !== undefined) migrated.push({ label: '', amount: it.price, note: '' });
              // also migrate quantity-specific prices if present
              if (Array.isArray(it.quantities)) {
                it.quantities.forEach(function(q){ migrated.push({ label: q.label || q.value || '', amount: q.price || '', note: '' }); });
              }
              it.prices = migrated;
            }
            if (!Array.isArray(it.prices)) it.prices = [];

            // legacy scalar price input placeholder (may remain null)
            let priceIn = null;

            const priceContainer = document.createElement('div'); priceContainer.className = 'price-entries mt-03';
            const renderPriceEntries = function() {
              priceContainer.innerHTML = '';
              if (!it.prices.length) {
                const hint = document.createElement('div'); hint.className='small'; hint.textContent = 'No prices — add one below.'; priceContainer.appendChild(hint);
              }
              it.prices.forEach(function(p, pi){
                const prow = document.createElement('div'); prow.className = 'flex-row-sm mt-03 price-entry-row';
                const labelIn = makeInput(p.label || '', 'Mini description (e.g. 6ft, Monthly)'); labelIn.classList.add('field-input-flex'); labelIn.addEventListener('input', function(){ menuData[sidx].items[idx].prices[pi].label = labelIn.value; });
                const amountIn = makeInput(p.amount || '', 'Amount (e.g. 9.99)'); amountIn.type = 'number'; amountIn.step = '0.01'; amountIn.min = '0'; amountIn.classList.add('w-100'); amountIn.addEventListener('input', function(){ menuData[sidx].items[idx].prices[pi].amount = amountIn.value; });
                const noteIn = makeInput(p.note || '', 'Optional mini-note'); noteIn.classList.add('field-input'); noteIn.addEventListener('input', function(){ menuData[sidx].items[idx].prices[pi].note = noteIn.value; });
                const removeBtn = document.createElement('button'); removeBtn.type='button'; removeBtn.className='btn btn-danger-muted'; removeBtn.textContent='Remove'; removeBtn.addEventListener('click', async function(){ if (!await showConfirm('Remove this price entry?')) return; menuData[sidx].items[idx].prices.splice(pi,1); render(); });
                prow.appendChild(labelIn);
                prow.appendChild(amountIn);
                prow.appendChild(noteIn);
                prow.appendChild(removeBtn);
                priceContainer.appendChild(prow);
              });
              const addBtn = document.createElement('button'); addBtn.type='button'; addBtn.className='btn btn-ghost'; addBtn.textContent = 'Add price entry'; addBtn.addEventListener('click', function(){ menuData[sidx].items[idx].prices.push({ label:'', amount:'', note:'' }); render(); });
              priceContainer.appendChild(addBtn);
            };
            renderPriceEntries();
            leftCol.insertBefore(priceContainer, descIn);

            // quantity options: allow multiple quantity choices per item by default
            const allowQuantity = true;
            let qtyContainer = null;
            if (allowQuantity) {
              // Ensure backward compatibility: convert old single `quantity` value into `quantities` array
              if (!Array.isArray(it.quantities) && it.quantity !== undefined) {
                // preserve item-level price as default for the legacy quantity
                it.quantities = [ { label: '', value: it.quantity, price: it.price !== undefined ? it.price : '' } ];
              }
              if (!Array.isArray(it.quantities)) it.quantities = [];

              qtyContainer = document.createElement('div'); qtyContainer.className = 'qty-options mt-03';

              const createOptionRow = function(opt, optIndex) {
                const row = document.createElement('div'); row.className = 'flex-row-sm mt-03';
                const labelInput = makeInput(opt.label || '', 'Label (e.g. 6 pc, 12 pc)'); labelInput.classList.add('field-input-flex'); labelInput.addEventListener('input', ()=> { menuData[sidx].items[idx].quantities[optIndex].label = labelInput.value; });

                // value input: simple numeric/text input for unit size/quantity
                let valueInput = makeInput(opt.value||'', 'e.g. 1, 5x5');
                valueInput.classList.add('w-120');
                valueInput.addEventListener('input', ()=> { menuData[sidx].items[idx].quantities[optIndex].value = valueInput.value; });

                // price input for this quantity option
                const priceInput = makeInput(opt.price || '', 'Price (e.g. 6.00)');
                priceInput.classList.add('w-100');
                priceInput.title = 'Price specific to this quantity option';
                // ensure price input is numeric for units and pricing
                priceInput.type = 'number'; priceInput.step = '0.01'; priceInput.min = '0';
                priceInput.addEventListener('input', ()=>{ menuData[sidx].items[idx].quantities[optIndex].price = priceInput.value; });

                const removeBtn = document.createElement('button'); removeBtn.type='button'; removeBtn.className='btn btn-danger-muted'; removeBtn.textContent='Remove'; removeBtn.addEventListener('click', async ()=>{
                  if (!await showConfirm('Remove this quantity option?')) return;
                  menuData[sidx].items[idx].quantities.splice(optIndex,1);
                  render();
                });

                row.appendChild(labelInput);
                const valueWrap = document.createElement('div'); valueWrap.className = 'flex-row-sm'; valueWrap.appendChild(valueInput);
                row.appendChild(valueWrap);
                // price next to the value
                const priceWrap = document.createElement('div'); priceWrap.className = 'flex-row-sm'; priceWrap.appendChild(priceInput);
                row.appendChild(priceWrap);
                row.appendChild(removeBtn);
                return row;
              }

              const renderQtyOptions = function() {
                qtyContainer.innerHTML = '';
                const opts = menuData[sidx].items[idx].quantities || [];
                if (!opts.length) {
                  const hint = document.createElement('div'); hint.className='small'; hint.textContent = 'No quantity options — add one below.'; qtyContainer.appendChild(hint);
                }
                opts.forEach((o, oi) => {
                  qtyContainer.appendChild(createOptionRow(o, oi));
                });
                const addBtn = document.createElement('button'); addBtn.type='button'; addBtn.className='btn btn-ghost'; addBtn.textContent = 'Add quantity option'; addBtn.addEventListener('click', ()=>{
                  menuData[sidx].items[idx].quantities.push({ label: '', value: '', price: '' }); render();
                });
                qtyContainer.appendChild(addBtn);
              }

              // initial render for quantity options
              renderQtyOptions();

              // place qtyContainer next to price or before description
              if (priceIn) { leftCol.insertBefore(qtyContainer, descIn); }
              else { leftCol.insertBefore(qtyContainer, descIn); }
            }

          const rightCol = document.createElement('div'); rightCol.className = 'flex-col';
          const imgIn = makeInput(it.image||'', 'filename.jpg or https://...'); imgIn.title = 'Image filename within uploads/images or a full URL'; imgIn.setAttribute('data-field-type','image');
          // prefer gallery images when picking per-item images in the menu admin
          imgIn.dataset.context = 'gallery';
          const pick = document.createElement('button'); pick.type='button'; pick.textContent='Pick'; pick.className='btn btn-ghost'; pick.addEventListener('click', ()=> openImagePicker(imgIn));
          const imgRow = document.createElement('div'); imgRow.className = 'flex-row-sm'; imgRow.appendChild(imgIn); imgRow.appendChild(pick);
          const preview = document.createElement('img'); preview.className = 'preview-img'; if (imgIn.value) preview.src = (window.ADMIN_UPLOADS_BASE || '../uploads/images/') + imgIn.value;
          imgIn.addEventListener('input', ()=>{ menuData[sidx].items[idx].image = imgIn.value; if (imgIn.value) preview.src = (window.ADMIN_UPLOADS_BASE || '../uploads/images/') + imgIn.value; else preview.removeAttribute('src'); renderPreview(); });
          // collapsible wrapper for per-item preview (keeps item row compact)
          const previewWrap = document.createElement('div'); previewWrap.className = 'preview-collapsible-wrap';
          const previewToggle = document.createElement('button'); previewToggle.type='button'; previewToggle.className='btn btn-ghost small preview-toggle'; previewToggle.setAttribute('aria-pressed','false');
          previewToggle.innerHTML = '<span class="preview-label">Preview</span> <svg class="caret" width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
          const previewBody = document.createElement('div'); previewBody.className = 'preview-collapsible'; previewBody.appendChild(preview);
          previewToggle.addEventListener('click', function(){
            const isOpen = previewBody.classList.toggle('expanded');
            previewToggle.setAttribute('aria-pressed', isOpen ? 'true' : 'false');
            previewToggle.classList.toggle('open', isOpen);
          });
          // Also update preview when textual fields change
          titleIn.addEventListener('input', renderPreview); shortIn.addEventListener('input', renderPreview); if (priceIn) priceIn.addEventListener('input', renderPreview); descIn.addEventListener('input', renderPreview);

          const itemControls = document.createElement('div'); itemControls.className = 'flex-row-sm';
          const up = document.createElement('button'); up.type='button'; up.textContent='↑'; up.title='Move up'; up.className='btn btn-ghost'; up.addEventListener('click', ()=>{ if (idx<=0) return; [menuData[sidx].items[idx-1], menuData[sidx].items[idx]] = [menuData[sidx].items[idx], menuData[sidx].items[idx-1]]; render(); });
          const down = document.createElement('button'); down.type='button'; down.textContent='↓'; down.title='Move down'; down.className='btn btn-ghost'; down.addEventListener('click', ()=>{ if (idx>=menuData[sidx].items.length-1) return; [menuData[sidx].items[idx+1], menuData[sidx].items[idx]] = [menuData[sidx].items[idx], menuData[sidx].items[idx+1]]; render(); });
          const del = document.createElement('button'); del.type='button'; del.textContent='Delete'; del.className='btn btn-danger-muted'; del.addEventListener('click', async ()=>{ if (!await showConfirm('Delete this item?')) return; menuData[sidx].items.splice(idx,1); render(); });
          itemControls.appendChild(up); itemControls.appendChild(down); itemControls.appendChild(del);
          // ensure preview updates after delete
          del.addEventListener('click', ()=> setTimeout(renderPreview, 100));

          rightCol.appendChild(imgRow); rightCol.appendChild(previewToggle); rightCol.appendChild(previewBody); rightCol.appendChild(itemControls);

          row.appendChild(leftCol); row.appendChild(rightCol);
          itemsWrap.appendChild(row);
        });

        // honor persisted expanded state: if the user has never stored a
        // preference (no keys in localStorage), default to expanded so the
        // Units Management UI is visible and discoverable. If the user has
        // previously toggled sections, respect that stored state.
  // If localStorage contains expanded-section ids but none of them
  // correspond to current section ids (stale format or previous scheme),
  // treat sections as expanded by default to avoid hiding content.
  const storedHasMatch = Array.from(expandedSections).some(id => currentIds.has(id));
  const isExpanded = (expandedSections.size === 0) || (!storedHasMatch) || expandedSections.has(sectionId);
        if (!isExpanded) {
          itemsWrap.classList.remove('expanded');
          toggleBtn.setAttribute('aria-expanded','false');
        } else {
          itemsWrap.classList.add('expanded');
          toggleBtn.setAttribute('aria-expanded','true');
        }

        secWrap.appendChild(itemsWrap);
  const footer = document.createElement('div'); footer.className = 'footer-row';
  const saveSec = document.createElement('button'); saveSec.type='button'; saveSec.textContent='Save Sections'; saveSec.className='btn btn-primary'; saveSec.addEventListener('click', async ()=>{ await saveMenu(); renderPreview(); });
        footer.appendChild(saveSec); secWrap.appendChild(footer);

        listEl.appendChild(secWrap);
      });
      // update preview after rendering admin list
      renderPreview();
    }

    function renderPreview(){
      const area = document.getElementById('preview-area');
      if (!area) return;
      area.innerHTML = '';
      menuData.forEach((section)=>{
        const sec = document.createElement('div'); sec.className='preview-section';
        const title = document.createElement('div'); title.className='preview-title'; title.textContent = section.title || 'Section';
        sec.appendChild(title);
        const itemsWrap = document.createElement('div');
        // section-level details shown once for the whole section
        if (Array.isArray(section.details) && section.details.length) {
          const secDetails = document.createElement('div'); secDetails.className = 'preview-section-details';
          section.details.forEach(d=>{ const p = document.createElement('div'); p.textContent = d; secDetails.appendChild(p); });
          sec.appendChild(secDetails);
        }
        // If the section has a single image, show it once at the section level
        if (section.image) {
          const simg = document.createElement('img');
          simg.className = 'preview-section-image';
          simg.src = (window.ADMIN_UPLOADS_BASE || '../uploads/images/') + section.image;
          sec.appendChild(simg);
        }

        (section.items || []).forEach(it=>{
          const pi = document.createElement('div'); pi.className='preview-item';
          // only show per-item images when the section does not have a section-level image
          if (!section.image && it.image) { const im = document.createElement('img'); im.src = (window.ADMIN_UPLOADS_BASE || '../uploads/images/')+it.image; pi.appendChild(im); }
          const meta = document.createElement('div'); meta.className='preview-meta';
          const t = document.createElement('div'); t.textContent = it.title || ''; t.className = 'preview-title';
          const s = document.createElement('div'); s.className='small'; s.textContent = it.short || '';
          const p = document.createElement('div'); p.className='preview-price';
          // Render multiple price entries if present
          if (Array.isArray(it.prices) && it.prices.length) {
            const parts = it.prices.map(function(pe){
              if (!pe) return '';
              const label = pe.label ? String(pe.label).trim() : '';
              const amt = (pe.amount !== undefined && pe.amount !== '') ? ('$' + String(pe.amount)) : '';
              const note = pe.note ? String(pe.note).trim() : '';
              let seg = '';
              if (label) seg += label + (amt ? (': ' + amt) : '');
              else if (amt) seg += amt;
              if (note) seg += (seg ? ' — ' : '') + note;
              return seg;
            }).filter(Boolean);
            p.textContent = parts.join(' | ');
          } else {
            p.textContent = it.price ? ('$'+it.price) : '';
          }
          // show quantity(s) in preview for certain sections
          const q = document.createElement('div'); q.className = 'preview-qty';
          if (Array.isArray(it.quantities) && it.quantities.length) {
            // join labels/values/prices for preview
            const parts = it.quantities.map(function(o){
              if (!o) return '';
              const label = o.label ? o.label : '';
              const val = (o.value !== undefined && o.value !== '') ? (typeof o.value === 'number' ? String(o.value) : o.value) : '';
              const price = (o.price !== undefined && o.price !== '') ? ('$' + String(o.price)) : '';
              const seg = [label, val].filter(Boolean).join(': ');
              return seg + (price ? (' — ' + price) : '');
            }).filter(Boolean);
            q.textContent = parts.length ? ('Qty: ' + parts.join(' | ')) : '';
          } else {
            q.textContent = (it.quantity !== undefined && it.quantity !== '') ? ('Qty: ' + it.quantity) : '';
          }
          const d = document.createElement('div'); d.className = 'mt-03'; d.innerHTML = it.description ? it.description.replace(/\n/g,'<br>') : '';
          meta.appendChild(t); if (s.textContent) meta.appendChild(s); if (p.textContent) meta.appendChild(p); if (q.textContent) meta.appendChild(q); meta.appendChild(d);
          pi.appendChild(meta); itemsWrap.appendChild(pi);
        });
        sec.appendChild(itemsWrap); area.appendChild(sec);
      });
    }

    async function saveMenu(){
      const csrf = (document.querySelector('input[name="csrf_token"]')||{}).value || window.__csrfToken || '';
      try {
        // Validate and normalize prices client-side before sending
        for (let s = 0; s < menuData.length; s++) {
          const sec = menuData[s];
          const allowPrice = true;
          if (!Array.isArray(sec.items)) continue;
          // Generic client-side validation for quantities/prices. Server-side
          // `save-content.php` performs authoritative checks.
          for (let i = 0; i < sec.items.length; i++) {
            const it = sec.items[i];
            // Normalize legacy `quantity` -> `quantities` array
            if (!Array.isArray(it.quantities) && it.quantity !== undefined) {
              it.quantities = [ { label: '', value: it.quantity } ];
              delete it.quantity;
            }
            if (Array.isArray(it.quantities)) {
              for (let qi = 0; qi < it.quantities.length; qi++) {
                const qv = it.quantities[qi] && it.quantities[qi].value !== undefined ? String(it.quantities[qi].value).trim() : '';
                if (qv === '') { showToast('Quantity value required for option #' + (qi+1) + ' in: ' + (it.title || ''), 'error'); return; }
                const qn = Number(qv);
                if (!Number.isInteger(qn) || qn < 0) { showToast('Invalid quantity for option #' + (qi+1) + ' in: ' + (it.title || ''), 'error'); return; }
                it.quantities[qi].value = parseInt(qn, 10);
                // validate per-option price (if provided)
                const qpriceRaw = it.quantities[qi] && it.quantities[qi].price !== undefined ? String(it.quantities[qi].price).trim() : '';
                if (qpriceRaw === '') { showToast('Price required for quantity option #' + (qi+1) + ' in: ' + (it.title || ''), 'error'); return; }
                const qnum = Number(String(qpriceRaw).replace(/[^0-9.-]/g, ''));
                if (!isFinite(qnum) || qnum < 0) { showToast('Invalid price for quantity option #' + (qi+1) + ' in: ' + (it.title || ''), 'error'); return; }
                it.quantities[qi].price = qnum.toFixed(2);
              }
            }
            if (!allowPrice) {
              // ensure no price data is sent for this section
              delete it.price; delete it.prices;
              continue;
            }
            // Migrate legacy scalar price into prices array if needed
            if (!Array.isArray(it.prices) && it.price !== undefined) {
              it.prices = [ { label: '', amount: it.price, note: '' } ];
              delete it.price;
            }
            // If prices array present, validate each entry and normalize amount to 2 decimals
            if (Array.isArray(it.prices)) {
              for (let pi = 0; pi < it.prices.length; pi++) {
                const pe = it.prices[pi] || {};
                const rawAmt = (pe.amount !== undefined && pe.amount !== null) ? String(pe.amount).trim() : '';
                if (rawAmt === '') { showToast('Price amount required for entry #' + (pi+1) + ' in: ' + (it.title || ''), 'error'); return; }
                const num = Number(rawAmt.replace(/[^0-9.-]/g, ''));
                if (!isFinite(num) || num < 0) { showToast('Invalid price for entry #' + (pi+1) + ' in: ' + (it.title || ''), 'error'); return; }
                it.prices[pi].amount = num.toFixed(2);
                it.prices[pi].label = pe.label ? String(pe.label) : '';
                it.prices[pi].note = pe.note ? String(pe.note) : '';
              }
            }
          }
        }
        const body = { section: 'menu', content: menuData, csrf_token: csrf };
        const res = await fetch('save-content.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
        const j = await res.json();
        if (j && j.success) { window.__siteContent = window.__siteContent || {}; window.__siteContent.menu = JSON.parse(JSON.stringify(menuData)); showToast('Menu saved', 'success'); }
        else showToast('Save failed: '+(j && j.message ? j.message : 'unknown'),'error');
      } catch(err){ showToast('Save error: '+err.message,'error'); }
    }

    if (addBtn) addBtn.addEventListener('click', ()=>{ menuData.push({ title:'New Section', id:'section-'+Date.now(), items:[] }); render(); });
    render();

    // Visibility safeguard: some pages/CSS combinations can leave the
    // `#menu-list` visually collapsed. Ensure it has a sensible min-height
    // and show a helpful hint if rendering produced no visible children.
    try {
      if (listEl) {
        if (!listEl.style.minHeight) listEl.style.minHeight = '140px';
        // if render failed to populate children for any reason, show a hint
        if (!listEl.children || listEl.children.length === 0) {
          const fallback = document.createElement('div'); fallback.className = 'empty-note'; fallback.textContent = 'Menu editor ready — click "Add Unit Category" to create a section.';
          listEl.appendChild(fallback);
        }
      }
    } catch (e) { /* non-fatal */ }
  }

  // initialize menu admin if present
  initMenuAdmin();
  // profile menu toggle
  const profileBtn = document.getElementById('profile-btn');
  if (profileBtn) {
    // ARIA and keyboard support
    const menu = document.getElementById('profile-menu');
    profileBtn.setAttribute('aria-haspopup', 'true');
    profileBtn.setAttribute('aria-expanded', 'false');
    profileBtn.setAttribute('role', 'button');
    profileBtn.tabIndex = 0;
    if (menu) {
      menu.setAttribute('role', 'menu');
      menu.tabIndex = -1;
    }

  const openMenu = function(){ if (!menu) return; menu.classList.add('show-block'); profileBtn.setAttribute('aria-expanded','true'); menu.querySelectorAll('button,a').forEach(el=>el.tabIndex=0); menu.focus(); };
  const closeMenu = function(){ if (!menu) return; menu.classList.remove('show-block'); profileBtn.setAttribute('aria-expanded','false'); profileBtn.focus(); };

  profileBtn.addEventListener('click', (e)=>{ e.stopPropagation(); if (!menu) return; (menu.classList.contains('show-block')) ? closeMenu() : openMenu(); });
    profileBtn.addEventListener('keydown', (e)=>{ if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); profileBtn.click(); } if (e.key === 'ArrowDown') { e.preventDefault(); openMenu(); }});

    // click outside to close (only when menu is open) to avoid stealing focus when it's closed
    document.addEventListener('click', (e)=>{
      const m = document.getElementById('profile-menu');
      if (!m) return;
      // only act when menu is visible/open
      if (!m.classList.contains('show-block')) return;
      if (!profileBtn.contains(e.target) && !m.contains(e.target)) closeMenu();
    });
    // close on escape
    document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape') { const m = document.getElementById('profile-menu'); if (m && m.classList.contains('show-block')) closeMenu(); }});
  }
})();
