<?php
require_once __DIR__ . '/config.php';
require_admin();
// Simple admin-protected UI for the Email Scheduler
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Email Scheduler Admin</title>
    <!-- use admin brand styles; head partial provides CSS/JS includes -->
    <?php require_once __DIR__ . '/partials/head.php'; ?>
</head>
<body class="admin">
    <div class="page-wrap">
        <div class="admin-card">
            <div class="admin-card-header header-row">
                <div class="header-left">
                    <div class="header-brand">
                        <span class="logo-inline"><img class="logo-img" src="/uploads/images/favicon-set/favicon-32x32.png" alt="logo"></span>
                        <h1 class="admin-card-title m-0">Email Scheduler Admin</h1>
                    </div>
                    <p class="muted mb-05">Manage scheduled campaigns and email configuration</p>
                </div>
                <div class="top-actions">
                    <a href="/admin/index.php" class="btn btn-ghost">Back to dashboard</a>
                </div>
            </div>

        <div class="admin-card-body">
            <div class="top-actions">
                <div class="tabs">
                    <button class="btn btn-ghost tab active" data-target="campaigns">Campaigns</button>
                    <button class="btn btn-ghost tab" data-target="new-campaign">New Campaign</button>
                    <button class="btn btn-ghost tab" data-target="config">Email Config</button>
                    <button class="btn btn-ghost tab" data-target="logs">Logs</button>
                </div>
            </div>

            <div id="campaigns" class="tab-content active">
                <h2>Campaigns</h2>
                <div id="campaign-list" class="section-wrap">
                    <div id="toast-container"></div>
                    <div class="search-form mt-06">
                        <input id="filter-text" class="search-input" placeholder="Search campaigns by name or subject">
                        <select id="filter-status" class="min-w-140">
                            <option value="all">All statuses</option>
                            <option value="1">Active</option>
                            <option value="0">Paused</option>
                        </select>
                        <label class="perpage-label">Per page
                            <select id="per-page" class="ml-025">
                                <option>5</option>
                                <option selected>10</option>
                                <option>25</option>
                            </select>
                        </label>
                    </div>

                    <table class="admin-table" id="campaigns-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Subject</th>
                                <th>Recipients</th>
                                <th>Send Time</th>
                                <th>Days</th>
                                <th>Created</th>
                                <th>Status</th>
                                <th>Suppliers</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="8">Loading...</td></tr>
                        </tbody>
                    </table>

                    <div class="pagination-wrap mt-06">
                        <div id="pagination-controls" class="pagination-wrap"></div>
                    </div>
                </div>
            </div>

            <div id="new-campaign" class="tab-content">
                <h2>Create Campaign</h2>
                <form id="campaign-form" class="content-editor">
                    <div class="form-row">
                            <label for="fld-name">Name</label>
                            <input id="fld-name" class="" name="name" required>
                            <div class="field-error" id="err-name" aria-live="polite"></div>
                        </div>
                    <div class="form-row">
                            <label for="fld-subject">Subject</label>
                            <input id="fld-subject" name="subject" required>
                            <div class="field-error" id="err-subject" aria-live="polite"></div>
                    </div>
                    <div class="form-row">
                            <label for="fld-body">Body</label>
                            <textarea id="fld-body" name="body" required></textarea>
                            <div class="field-error" id="err-body" aria-live="polite"></div>
                    </div>
                    <div class="form-row">
                            <label for="fld-recipients">Recipients (one per line)</label>
                            <textarea id="fld-recipients" name="recipients" required></textarea>
                            <div class="field-error" id="err-recipients" aria-live="polite"></div>
                    </div>
                    <div class="form-row">
                        <label>Send Time</label>
                        <input type="time" name="send_time" value="09:00" required>
                    </div>
                    <div class="form-row">
                        <label><input type="checkbox" name="active" checked> Active</label>
                    </div>
                    <div class="form-actions">
                        <input type="hidden" id="editing-id" value="">
                        <button class="btn btn-primary" id="campaign-submit" type="submit">Create</button>
                        <button class="btn btn-ghost hidden" id="campaign-cancel" type="button">Cancel</button>
                    </div>
                    <div class="card-spaced hidden" id="supplier-editor">
                        <h3>Suppliers</h3>
                        <div id="supplier-list" class="section-wrap">No suppliers</div>
                        <form id="supplier-form" class="smtp-form mt-06">
                            <input type="hidden" id="supplier-campaign-id" value="">
                            <input type="hidden" id="supplier-editing-id" value="">
                            <div class="form-row smtp-label"><label for="supplier-name">Name</label><input id="supplier-name" name="name"></div>
                            <div class="form-row smtp-label"><label for="supplier-url">URL</label><input id="supplier-url" name="url"></div>
                            <div class="form-row smtp-label">
                                <label>Selectors</label>
                                <div id="selector-rows" class="flex-col">
                                    <div class="selector-row"><input class="selector-key" placeholder="key"><input class="selector-val" placeholder="CSS selector"></div>
                                </div>
                                <div class="flex-row-sm mt-04">
                                    <button type="button" class="btn btn-ghost" id="add-selector-row">Add selector</button>
                                    <button type="button" class="btn btn-ghost" id="preview-selectors">Preview</button>
                                    <label class="ml-08"><input type="checkbox" id="force-preview"> Force fresh</label>
                                </div>
                                <div id="preview-results" class="mt-06 hidden preview-box"></div>
                            </div>
                            <div class="form-actions">
                                <button class="btn btn-primary" id="add-supplier" type="button">Add Supplier</button>
                                <button class="btn btn-primary hidden" id="update-supplier" type="button">Update Supplier</button>
                                <button class="btn btn-ghost hidden" id="cancel-supplier" type="button">Cancel</button>
                            </div>
                        </form>
                    </div>
                </form>
            </div>

            <div id="config" class="tab-content">
                <h2>Email Config</h2>
                <form id="config-form" class="smtp-form section-wrap">
                    <div class="form-row smtp-label">
                        <label>SMTP Server</label>
                        <input name="smtp_server" required>
                    </div>
                    <div class="form-row smtp-label">
                        <label>SMTP Port</label>
                        <input name="smtp_port" required>
                    </div>
                    <div class="form-row smtp-label">
                        <label>Email Address</label>
                        <input name="email_address" required>
                    </div>
                    <div class="form-row smtp-label">
                        <label>Email Password</label>
                        <input type="password" name="email_password" required>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Save</button>
                    </div>
                </form>
            </div>

            <div id="logs" class="tab-content">
                <h2>Logs</h2>
                <div id="logs-list" class="section-wrap">Loading logs...</div>
            </div>
        </div>
    </div>

    <!-- Confirmation modal -->
    <div id="modal-backdrop" class="modal-backdrop hidden">
            <div class="app-modal-dialog" role="dialog" aria-modal="true" id="confirm-modal">
            <div id="modal-header"><h3 id="modal-title">Confirm</h3><button id="modal-close" class="app-modal-close">×</button></div>
            <div class="app-modal-body" id="modal-body">Are you sure?</div>
            <div class="modal-actions">
                <button id="modal-cancel" class="btn btn-ghost">Cancel</button>
                <button id="modal-confirm" class="btn btn-primary">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        const API_URL = 'api/email_api.php'; // admin-scoped API
    // expose CSRF token from server session
    const CSRF_TOKEN = '<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES); ?>';
        function switchTab(target){
            // target may be a string id or a button element
            let name = typeof target === 'string' ? target : (target.dataset && target.dataset.target ? target.dataset.target : null);
            if(!name) return;
            document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c=>c.classList.remove('active'));
            // mark the clicked tab active
            const btn = document.querySelector(`.tab[data-target="${name}"]`);
            if(btn) btn.classList.add('active');
            const content = document.getElementById(name);
            if(content) content.classList.add('active');
            if(name==='campaigns') loadCampaigns();
            if(name==='logs') loadLogs();
        }

        // attach tab button handlers
        document.querySelectorAll('.tab').forEach(b=>b.addEventListener('click', (e)=>{ switchTab(e.currentTarget); }));

        function showToast(message, type=''){
            const tcont=document.getElementById('toast-container');
            const div=document.createElement('div'); div.className='toast '+(type||''); div.textContent=message;
            tcont.appendChild(div);
            setTimeout(()=>{div.remove()},4000);
        }

        let CURRENT_PAGE = 1;

        async function loadCampaigns(page = 1){
            CURRENT_PAGE = page;
            const q = document.getElementById('filter-text').value.trim();
            const status = document.getElementById('filter-status').value;
            const perPage = parseInt(document.getElementById('per-page').value,10)||10;
            const params = new URLSearchParams({ action: 'campaigns', page: page, per_page: perPage });
            if(q) params.set('q', q);
            if(status && status !== 'all') params.set('status', status);
            try{
                const res = await fetch(`${API_URL}?${params.toString()}`);
                const data = await res.json();
                const items = data.campaigns || [];
                const total = data.total || 0;
                renderTableRows(items);
                const pages = Math.max(1, Math.ceil(total / perPage));
                renderPagination(pages, page);
            }catch(e){
                const cl = document.getElementById('campaign-list'); if (cl) { cl.textContent = ''; const p = document.createElement('p'); p.textContent = 'Error loading campaigns'; cl.appendChild(p); }
            }
        }

        function renderTableRows(items){
            const tbody=document.querySelector('#campaigns-table tbody');
            if(!items || items.length===0){ tbody.textContent = ''; const tr = document.createElement('tr'); const td = document.createElement('td'); td.colSpan = 8; td.textContent = 'No campaigns'; tr.appendChild(td); tbody.appendChild(tr); return }
            tbody.textContent = '';
            items.forEach(function(c){
                const recipientsCount = (c.recipients||[]).length;
                const days = (c.send_days||[]).join(', ');
                const tr = document.createElement('tr'); tr.dataset.id = c.id;
                function td(txt, cls){ const t = document.createElement('td'); if (cls) t.className = cls; t.textContent = txt; return t; }
                tr.appendChild(td(c.name || ''));
                tr.appendChild(td(c.subject || ''));
                tr.appendChild(td(String(recipientsCount)));
                tr.appendChild(td(c.send_time || '-'));
                tr.appendChild(td(days));
                tr.appendChild(td(c.created_at || '-'));
                tr.appendChild(td(String(c.suppliers_count || 0)));
                tr.appendChild(td(c.active ? 'Active' : 'Paused'));
                const actions = document.createElement('td');
                ['view-suppliers','edit','send','delete'].forEach(function(a){ const btn = document.createElement('button'); btn.className = a==='delete' ? 'btn btn-danger-muted' : 'btn btn-ghost'; btn.dataset.action = a; btn.textContent = a==='view-suppliers' ? 'View Suppliers' : (a==='edit' ? 'Edit' : (a==='send' ? 'Send' : 'Delete')); actions.appendChild(btn); });
                tr.appendChild(actions);
                tbody.appendChild(tr);
            });
            // rebind actions
            document.querySelectorAll('#campaigns-table [data-action]').forEach(b=>b.addEventListener('click', async (ev)=>{
                const btn=ev.currentTarget; const row=btn.closest('tr'); const id=row.dataset.id; const action=btn.dataset.action;
                if(action==='view-suppliers') return showSuppliersModal(id);
                if(action==='edit') return editCampaign(id);
                if(action==='delete') return showConfirm('Delete campaign?', async ()=>{
                    const r=await fetch(`${API_URL}?action=campaign&id=${encodeURIComponent(id)}`,{method:'DELETE',headers:{'X-CSRF-Token':CSRF_TOKEN,'Content-Type':'application/json'}});
                    const d=await r.json(); if(d.success){ showToast('Deleted'); await refreshCampaigns(); } else showToast(d.error||'Failed','error');
                });
                if(action==='send') return showConfirm('Send campaign now?', async ()=>{
                    const r=await fetch(`${API_URL}?action=send`,{method:'POST',headers:{'X-CSRF-Token':CSRF_TOKEN,'Content-Type':'application/json'},body:JSON.stringify({campaign_id:id})});
                    const d=await r.json(); if(d.success) showToast('Send queued'); else showToast(d.error||'Failed','error');
                });
            }));
        }

        function renderPagination(pages, current){
            const pc=document.getElementById('pagination-controls'); if (pc) { pc.textContent = ''; for(let i=1;i<=pages;i++){ const btn=document.createElement('button'); btn.className='btn btn-ghost'; btn.textContent=i; if(i===current) btn.classList.add('current-page'); btn.addEventListener('click',()=>{ loadCampaigns(i); }); pc.appendChild(btn); } }
        }

        // wire filter controls once and load initial page
        document.getElementById('filter-text').addEventListener('input', ()=>{ loadCampaigns(1); });
        document.getElementById('filter-status').addEventListener('change', ()=>{ loadCampaigns(1); });
        document.getElementById('per-page').addEventListener('change', ()=>{ loadCampaigns(1); });

        async function initCampaigns(){ await loadCampaigns(1); }

        async function refreshCampaigns(){ await loadCampaigns(CURRENT_PAGE); }

        function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[c])) }

        async function editCampaign(id){
            const res=await fetch(`${API_URL}?action=campaign&id=${encodeURIComponent(id)}`);
            const data=await res.json(); if(!data.campaign) { showToast('Failed to load campaign','error'); return }
                const c=data.campaign;
            document.getElementById('fld-name').value=c.name||'';
            document.getElementById('fld-subject').value=c.subject||'';
            document.getElementById('fld-body').value=c.body||'';
            document.getElementById('fld-recipients').value=(c.recipients||[]).join('\n');
            document.getElementById('editing-id').value=c.id;
            document.getElementById('campaign-submit').textContent='Update';
            document.getElementById('campaign-cancel').classList.add('show-inline-block');
            // populate suppliers
            const supList = document.getElementById('supplier-list');
            if(c.suppliers && c.suppliers.length>0){
                supList.textContent = '';
                c.suppliers.forEach(function(s){
                    const row = document.createElement('div'); row.className = 'space-between-row'; row.dataset.sid = s.id;
                    const left = document.createElement('div'); const strong = document.createElement('strong'); strong.textContent = s.name || ''; left.appendChild(strong); const urlDiv = document.createElement('div'); urlDiv.className = 'muted-text small'; urlDiv.textContent = s.url || ''; left.appendChild(urlDiv);
                    const right = document.createElement('div'); const editBtn = document.createElement('button'); editBtn.className='btn btn-ghost'; editBtn.dataset.sid = s.id; editBtn.dataset.action = 'edit-supplier'; editBtn.textContent = 'Edit'; const delBtn = document.createElement('button'); delBtn.className='btn btn-danger-muted'; delBtn.dataset.sid = s.id; delBtn.dataset.action = 'del-supplier'; delBtn.textContent = 'Delete'; right.appendChild(editBtn); right.appendChild(document.createTextNode(' ')); right.appendChild(delBtn);
                    row.appendChild(left); row.appendChild(right); supList.appendChild(row);
                });
            } else { supList.textContent = ''; const p = document.createElement('p'); p.className = 'muted'; p.textContent = 'No suppliers'; supList.appendChild(p); }
            document.getElementById('supplier-campaign-id').value = c.id;
            document.getElementById('supplier-editor').classList.add('show-block');
            switchTab('new-campaign');
            // wire supplier delete buttons
            document.querySelectorAll('[data-action="del-supplier"]').forEach(b=>b.addEventListener('click', async (ev)=>{
                const sid = ev.currentTarget.dataset.sid;
                if(!sid) return;
                await showConfirm('Delete supplier?', async ()=>{
                    const r=await fetch(`${API_URL}?action=supplier&id=${encodeURIComponent(sid)}`,{method:'DELETE',headers:{'X-CSRF-Token':CSRF_TOKEN}});
                    const d=await r.json(); if(d.success) showToast('Supplier deleted'); else showToast(d.error||'Failed','error');
                    // refresh campaign suppliers
                    editCampaign(document.getElementById('editing-id').value);
                });
            }));
            // wire supplier edit buttons
            document.querySelectorAll('[data-action="edit-supplier"]').forEach(b=>b.addEventListener('click', async (ev)=>{
                const sid = ev.currentTarget.dataset.sid; if(!sid) return;
                // find supplier in current campaign
                const sup = (c.suppliers||[]).find(s=>String(s.id)===String(sid)); if(!sup) return;
                document.getElementById('supplier-editing-id').value = sup.id;
                document.getElementById('supplier-name').value = sup.name || '';
                document.getElementById('supplier-url').value = sup.url || '';
                document.getElementById('supplier-selectors').value = JSON.stringify(sup.selectors || {});
                document.getElementById('add-supplier').classList.add('hidden');
                document.getElementById('update-supplier').classList.add('show-inline-block');
                document.getElementById('cancel-supplier').classList.add('show-inline-block');
            }));
        }

        // Confirmation modal helpers
        function showConfirm(message, onConfirm){
            return new Promise((resolve)=>{
                const titleEl = document.getElementById('modal-title');
                const bodyEl = document.getElementById('modal-body');
                const backdrop=document.getElementById('modal-backdrop');
                const confirmBtn = document.getElementById('modal-confirm');
                const cancelBtn = document.getElementById('modal-cancel');
                const closeBtn = document.getElementById('modal-close');
                titleEl.textContent='Confirm';
                bodyEl.textContent=message;
                backdrop.classList.add('open');
                const prevActive = document.activeElement;
                // hide main content from assistive tech while modal open
                const mainContent = document.querySelector('body > .container');
                if(mainContent) mainContent.setAttribute('aria-hidden','true');

                // find all focusable elements inside modal
                const focusableSelector = 'a[href], area[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])';
                const modal = document.getElementById('confirm-modal');
                const focusables = Array.from(modal.querySelectorAll(focusableSelector));
                const firstFocusable = focusables[0] || confirmBtn;
                const lastFocusable = focusables[focusables.length-1] || closeBtn;
                firstFocusable.focus();

                const cleanup=()=>{
                    backdrop.classList.remove('open');
                    confirmBtn.onclick=null; cancelBtn.onclick=null; closeBtn.onclick=null;
                    if(mainContent) mainContent.removeAttribute('aria-hidden');
                    if(prevActive && prevActive.focus) prevActive.focus();
                    document.removeEventListener('keydown', keyHandler);
                };

                confirmBtn.onclick = async ()=>{ cleanup(); try{ await onConfirm(); resolve(true); } catch(e){ resolve(false); } };
                cancelBtn.onclick = ()=>{ cleanup(); resolve(false); };
                closeBtn.onclick = ()=>{ cleanup(); resolve(false); };

                function keyHandler(e){
                    if(e.key === 'Escape'){ cleanup(); resolve(false); return; }
                    if(e.key !== 'Tab') return;
                    // manage Tab focus
                    const idx = focusables.indexOf(document.activeElement);
                    if(e.shiftKey){
                        if(document.activeElement === firstFocusable){ lastFocusable.focus(); e.preventDefault(); }
                    } else {
                        if(document.activeElement === lastFocusable){ firstFocusable.focus(); e.preventDefault(); }
                    }
                }
                document.addEventListener('keydown', keyHandler);
            });
        }

                // show suppliers in modal for campaign id
                async function showSuppliersModal(campaignId){
                    try{
                        const res = await fetch(`${API_URL}?action=campaign&id=${encodeURIComponent(campaignId)}`);
                        const data = await res.json(); if(!data.campaign) { showToast('Failed to load suppliers','error'); return }
                        const c = data.campaign;
                        const body = document.getElementById('modal-body');
                        body.textContent = '';
                        if(!c.suppliers || c.suppliers.length===0){ const p = document.createElement('p'); p.className = 'muted'; p.textContent = 'No suppliers for this campaign'; body.appendChild(p); }
                        else {
                            const container = document.createElement('div');
                            c.suppliers.forEach(function(s){
                                const row = document.createElement('div'); row.className = 'space-between-row';
                                const left = document.createElement('div'); const strong = document.createElement('strong'); strong.textContent = s.name || ''; left.appendChild(strong); const urlDiv = document.createElement('div'); urlDiv.className = 'muted-text small'; urlDiv.textContent = s.url || ''; left.appendChild(urlDiv);
                                const right = document.createElement('div'); const delBtn = document.createElement('button'); delBtn.className = 'btn btn-danger-muted'; delBtn.dataset.sid = s.id; delBtn.dataset.action = 'del-supplier-modal'; delBtn.textContent = 'Delete'; right.appendChild(delBtn);
                                row.appendChild(left); row.appendChild(right); container.appendChild(row);
                            });
                            body.appendChild(container);
                            // wire delete buttons
                            body.querySelectorAll('[data-action="del-supplier-modal"]').forEach(b=>b.addEventListener('click', async (ev)=>{
                                const sid = ev.currentTarget.dataset.sid; if(!sid) return;
                                await showConfirm('Delete supplier?', async ()=>{
                                    const r=await fetch(`${API_URL}?action=supplier&id=${encodeURIComponent(sid)}`,{method:'DELETE',headers:{'X-CSRF-Token':CSRF_TOKEN}});
                                    const d=await r.json(); if(d.success){ showToast('Supplier deleted'); showSuppliersModal(campaignId); } else showToast(d.error||'Failed','error');
                                });
                            }));
                        }
                        document.getElementById('modal-title').textContent = 'Suppliers for: ' + (c.name||'');
                        // open modal
                        const backdrop = document.getElementById('modal-backdrop'); backdrop.classList.add('open');
                        // focus handling via showConfirm's trap is not used here; attach simple close
                        document.getElementById('modal-confirm').classList.add('hidden');
                        document.getElementById('modal-cancel').textContent='Close';
                        document.getElementById('modal-cancel').onclick = ()=>{ backdrop.classList.remove('open'); document.getElementById('modal-confirm').classList.remove('hidden'); document.getElementById('modal-cancel').textContent='Cancel'; };
                    }catch(e){ showToast('Failed to load suppliers','error'); }
                }

        document.getElementById('campaign-cancel').addEventListener('click', (e)=>{
            document.getElementById('campaign-form').reset(); document.getElementById('editing-id').value=''; document.getElementById('campaign-submit').textContent='Create'; document.getElementById('campaign-cancel').classList.remove('show-inline-block');
        });

        function validateCampaignData(data){
            const errors={};
            if(!data.name || data.name.trim().length<2) errors.name='Name is required (min 2 chars)';
            if(!data.subject || data.subject.trim().length<3) errors.subject='Subject is required';
            if(!data.body || data.body.trim().length<3) errors.body='Body is required';
            if(!Array.isArray(data.recipients) || data.recipients.length===0) errors.recipients='At least one recipient required';
            return errors;
        }

        document.getElementById('campaign-form').addEventListener('submit', async (e)=>{
            e.preventDefault();
            const fd=new FormData(e.target);
            const recipients=fd.get('recipients').split('\n').map(r=>r.trim()).filter(r=>r);
            const payload={name:fd.get('name'),subject:fd.get('subject'),body:fd.get('body'),recipients:recipients,send_days:['monday'],send_time:fd.get('send_time'),active:fd.get('active')?1:0};
            // client-side validation
            const errs=validateCampaignData(payload);
            ['name','subject','body','recipients'].forEach(f=>{ const el=document.getElementById('err-'+f); if(el) el.textContent=(errs[f]||''); });
            if(Object.keys(errs).length>0) { showToast('Fix validation errors','error'); return }

            const editingId = document.getElementById('editing-id').value;
            if(editingId){
                const r=await fetch(`${API_URL}?action=campaign&id=${encodeURIComponent(editingId)}`,{method:'PUT',headers:{'X-CSRF-Token':CSRF_TOKEN,'Content-Type':'application/json'},body:JSON.stringify(payload)});
                const d=await r.json(); if(d.success){ showToast('Updated'); document.getElementById('campaign-form').reset(); document.getElementById('editing-id').value=''; document.getElementById('campaign-submit').textContent='Create'; document.getElementById('campaign-cancel').classList.remove('show-inline-block'); loadCampaigns(); } else showToast(d.error||'Failed','error');
            } else {
                const r=await fetch(`${API_URL}?action=campaign`,{method:'POST',headers:{'X-CSRF-Token':CSRF_TOKEN,'Content-Type':'application/json'},body:JSON.stringify(payload)});
                const d=await r.json(); if(d.success){ showToast('Created'); e.target.reset(); loadCampaigns(); } else showToast(d.error||'Failed','error');
            }
        });

        async function postTempSuppliers(campaignId){
            for(const s of TEMP_SUPPLIERS){
                try{
                    const r = await fetch(`${API_URL}?action=supplier`,{method:'POST',headers:{'X-CSRF-Token':CSRF_TOKEN,'Content-Type':'application/json'},body:JSON.stringify({campaign_id:campaignId,name:s.name,url:s.url,selectors:s.selectors})});
                    const d = await r.json(); if(!d.success) showToast('Failed to save a supplier','error');
                }catch(e){ showToast('Failed to save a supplier','error'); }
            }
            // if succeeded, clear temp suppliers and refresh
            TEMP_SUPPLIERS.length = 0; renderTempSuppliers(); editCampaign(campaignId);
        }

        // add supplier
        document.getElementById('add-supplier').addEventListener('click', async ()=>{
            // For New Campaign flow, if supplier-campaign-id is empty, add to TEMP_SUPPLIERS
            const cid = document.getElementById('supplier-campaign-id').value;
            const name = document.getElementById('supplier-name').value.trim();
            const url = document.getElementById('supplier-url').value.trim();
            // collect selector rows
            const selectorRows = Array.from(document.querySelectorAll('#selector-rows .selector-row'));
            const selectors = {};
            selectorRows.forEach(row=>{
                const k = row.querySelector('.selector-key').value.trim();
                const v = row.querySelector('.selector-val').value.trim();
                if(k && v) selectors[k]=v;
            });
            if(!name || !url){ showToast('Supplier name and URL required','error'); return }
            if(Object.keys(selectors).length===0){ showToast('Add at least one selector','error'); return }

            if(!cid){
                // temp flow
                TEMP_SUPPLIERS.push({name:name,url:url,selectors:selectors});
                renderTempSuppliers();
                document.getElementById('supplier-name').value=''; document.getElementById('supplier-url').value=''; document.querySelectorAll('#selector-rows .selector-row input').forEach(i=>i.value='');
                showToast('Supplier added (temporary)');
                return;
            }

            // persisted flow (editing existing campaign)
            const r = await fetch(`${API_URL}?action=supplier`,{method:'POST',headers:{'X-CSRF-Token':CSRF_TOKEN,'Content-Type':'application/json'},body:JSON.stringify({campaign_id:cid,name:name,url:url,selectors:selectors})});
            const d = await r.json(); if(d.success){ showToast('Supplier added'); document.getElementById('supplier-name').value=''; document.getElementById('supplier-url').value=''; document.querySelectorAll('#selector-rows .selector-row input').forEach(i=>i.value=''); editCampaign(cid); } else showToast(d.error||'Failed','error');
        });

        // temp suppliers for New Campaign flow
        const TEMP_SUPPLIERS = [];
        function renderTempSuppliers(){
            const list = document.getElementById('supplier-list');
            if(TEMP_SUPPLIERS.length===0) { list.textContent = ''; const p = document.createElement('p'); p.className = 'muted'; p.textContent = 'No suppliers'; list.appendChild(p); return }
            list.textContent = '';
            TEMP_SUPPLIERS.forEach(function(s, idx){ const row = document.createElement('div'); row.className = 'space-between-row'; row.dataset['tempIdx'] = idx; const left = document.createElement('div'); const strong = document.createElement('strong'); strong.textContent = s.name || ''; left.appendChild(strong); const urlDiv = document.createElement('div'); urlDiv.className = 'muted-text small'; urlDiv.textContent = s.url || ''; left.appendChild(urlDiv); const right = document.createElement('div'); const del = document.createElement('button'); del.className='btn btn-danger-muted'; del.dataset.tempAction='del-temp'; del.dataset.idx = idx; del.textContent='Delete'; right.appendChild(del); row.appendChild(left); row.appendChild(right); list.appendChild(row); });
            document.querySelectorAll('[data-temp-action="del-temp"]').forEach(b=>b.addEventListener('click', (ev)=>{
                const i = parseInt(ev.currentTarget.dataset.idx,10); if(isNaN(i)) return; TEMP_SUPPLIERS.splice(i,1); renderTempSuppliers();
            }));
        }

        // update supplier
        document.getElementById('update-supplier').addEventListener('click', async ()=>{
            const sid = document.getElementById('supplier-editing-id').value;
            const cid = document.getElementById('supplier-campaign-id').value;
            const name = document.getElementById('supplier-name').value.trim();
            const url = document.getElementById('supplier-url').value.trim();
            let selectors = document.getElementById('supplier-selectors').value.trim();
            try{ selectors = JSON.parse(selectors); } catch(e){ showToast('Selectors must be valid JSON','error'); return }
            if(!sid) { showToast('No supplier selected','error'); return }
            const r = await fetch(`${API_URL}?action=supplier&id=${encodeURIComponent(sid)}`,{method:'PUT',headers:{'X-CSRF-Token':CSRF_TOKEN,'Content-Type':'application/json'},body:JSON.stringify({id:sid,name:name,url:url,selectors:selectors})});
            const d = await r.json(); if(d.success){ showToast('Supplier updated'); document.getElementById('supplier-name').value=''; document.getElementById('supplier-url').value=''; document.getElementById('supplier-selectors').value=''; document.getElementById('supplier-editing-id').value=''; document.getElementById('add-supplier').classList.remove('hidden'); document.getElementById('update-supplier').classList.remove('show-inline-block'); document.getElementById('cancel-supplier').classList.remove('show-inline-block'); editCampaign(cid); } else showToast(d.error||'Failed','error');
        });

        document.getElementById('cancel-supplier').addEventListener('click', ()=>{
            document.getElementById('supplier-name').value=''; document.getElementById('supplier-url').value=''; document.getElementById('supplier-selectors').value=''; document.getElementById('supplier-editing-id').value=''; document.getElementById('add-supplier').classList.remove('hidden'); document.getElementById('update-supplier').classList.remove('show-inline-block'); document.getElementById('cancel-supplier').classList.remove('show-inline-block');
        });

        // add selector row
        document.getElementById('add-selector-row').addEventListener('click', ()=>{
            const wrap = document.getElementById('selector-rows');
            const div = document.createElement('div'); div.className='selector-row flex-row-sm';
            div.textContent = '';
            var k = document.createElement('input'); k.className = 'selector-key'; k.placeholder = 'key';
            var v = document.createElement('input'); v.className = 'selector-val'; v.placeholder = 'CSS selector';
            div.appendChild(k); div.appendChild(v);
            wrap.appendChild(div);
        });

        // Preview selectors against a URL (uses test-scrape API). If supplier id is present, pass it to allow cache reads/writes.
        document.getElementById('preview-selectors').addEventListener('click', async ()=>{
            const url = document.getElementById('supplier-url').value.trim();
            if(!url){ showToast('Enter supplier URL to preview','error'); return }
            const selectorRows = Array.from(document.querySelectorAll('#selector-rows .selector-row'));
            const selectors = {};
            selectorRows.forEach(row=>{
                const k = row.querySelector('.selector-key').value.trim();
                const v = row.querySelector('.selector-val').value.trim();
                if(k && v) selectors[k]=v;
            });
            if(Object.keys(selectors).length===0){ showToast('Add at least one selector to preview','error'); return }
            const previewEl = document.getElementById('preview-results'); previewEl.classList.remove('hidden'); previewEl.textContent='Loading...';
            const supplierId = document.getElementById('supplier-editing-id').value || null;
            const force = document.getElementById('force-preview').checked ? 1 : 0;
            try{
                const r = await fetch(`${API_URL}?action=test-scrape`,{method:'POST',headers:{'X-CSRF-Token':CSRF_TOKEN,'Content-Type':'application/json'},body:JSON.stringify({url:url,selectors:selectors,supplier_id:supplierId,force:force})});
                const d = await r.json();
                    if(!d.success){ previewEl.textContent = 'Preview failed: '+(d.message||d.error||'Unknown'); return }
                    previewEl.textContent = '';
                    const hdr = document.createElement('div'); hdr.appendChild(document.createElement('strong')).textContent = 'Preview results';
                    previewEl.appendChild(hdr);
                    const wrap = document.createElement('div'); wrap.className = 'mt-04';
                    for(const k of Object.keys(d.data||{})){
                        const row = document.createElement('div'); const strong = document.createElement('strong'); strong.textContent = k + ': '; row.appendChild(strong); row.appendChild(document.createTextNode(String(d.data[k]))); wrap.appendChild(row);
                    }
                    previewEl.appendChild(wrap);
            }catch(e){ previewEl.textContent = 'Preview failed'; }
        });

        document.getElementById('config-form').addEventListener('submit', async (e)=>{
            e.preventDefault();
            const fd=new FormData(e.target);
            const data={smtp_server:fd.get('smtp_server'),smtp_port:fd.get('smtp_port'),email_address:fd.get('email_address'),email_password:fd.get('email_password')};
            const r=await fetch(`${API_URL}?action=config`,{method:'POST',headers:{'X-CSRF-Token':CSRF_TOKEN,'Content-Type':'application/json'},body:JSON.stringify(data)});
            const d=await r.json(); if(d.success) showToast('Saved'); else showToast(d.error||'Failed','error');
        });

        async function loadLogs(){
            try{const res=await fetch(`${API_URL}?action=logs`);const data=await res.json();const el=document.getElementById('logs-list');el.textContent='';if(!data.logs||data.logs.length===0){const p=document.createElement('p');p.textContent='No logs';el.appendChild(p);return}const pre=document.createElement('pre');pre.textContent=JSON.stringify(data.logs, null, 2);el.appendChild(pre);}catch(e){const el=document.getElementById('logs-list');el.textContent='';const p=document.createElement('p');p.textContent='Error loading logs';el.appendChild(p);}
        }

        // initialize the campaign list and wire controls
        initCampaigns();
    </script>
</body>
</html>
