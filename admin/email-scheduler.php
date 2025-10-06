<?php
require_once __DIR__ . '/config.php';
require_admin();
// Simple admin-protected UI for the Email Scheduler
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/partials/head.php'; ?>
    <title>Email Scheduler Admin</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
        /* lightweight admin UI styles (kept simple) */
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial;margin:0;background:#f5f7fa;color:#333}
        .container{max-width:1100px;margin:20px auto;padding:20px}
        .header{background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.06)}
        .tabs{display:flex;gap:8px;margin:16px 0}
        .tab{padding:8px 14px;border-radius:6px;background:#fff;border:none;cursor:pointer}
        .tab.active{background:#3498db;color:#fff}
        .tab-content{display:none;background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.06)}
        .tab-content.active{display:block}
        .campaign-card{border:1px solid #e6e6e6;padding:12px;border-radius:6px;background:#fafafa;margin-bottom:12px}
        .btn{padding:8px 12px;border-radius:6px;border:none;cursor:pointer}
        .btn-primary{background:#3498db;color:#fff}
    </style>
</head>
<body class="admin">
    <div class="container">
        <div class="header">
            <h1>Email Scheduler Admin</h1>
            <p>Manage scheduled campaigns and email configuration</p>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="switchTab('campaigns')">Campaigns</button>
            <button class="tab" onclick="switchTab('new-campaign')">New Campaign</button>
            <button class="tab" onclick="switchTab('config')">Email Config</button>
            <button class="tab" onclick="switchTab('logs')">Logs</button>
        </div>

        <div id="campaigns" class="tab-content active">
            <h2>Campaigns</h2>
            <div id="campaign-list">Loading...</div>
        </div>

        <div id="new-campaign" class="tab-content">
            <h2>Create Campaign</h2>
            <form id="campaign-form">
                <div><label>Name</label><input name="name" required></div>
                <div><label>Subject</label><input name="subject" required></div>
                <div><label>Body</label><textarea name="body" required></textarea></div>
                <div><label>Recipients (one per line)</label><textarea name="recipients" required></textarea></div>
                <div><label>Send Time</label><input type="time" name="send_time" value="09:00" required></div>
                <div><label><input type="checkbox" name="active" checked> Active</label></div>
                <button class="btn btn-primary" type="submit">Create</button>
            </form>
        </div>

        <div id="config" class="tab-content">
            <h2>Email Config</h2>
            <form id="config-form">
                <div><label>SMTP Server</label><input name="smtp_server" required></div>
                <div><label>SMTP Port</label><input name="smtp_port" required></div>
                <div><label>Email Address</label><input name="email_address" required></div>
                <div><label>Email Password</label><input type="password" name="email_password" required></div>
                <button class="btn btn-primary" type="submit">Save</button>
            </form>
        </div>

        <div id="logs" class="tab-content">
            <h2>Logs</h2>
            <div id="logs-list">Loading logs...</div>
        </div>
    </div>

    <script>
        const API_URL = 'api/email_api.php'; // admin-scoped API
        // expose CSRF token from server session
        const CSRF_TOKEN = '<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES); ?>';
        function switchTab(name){
            document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c=>c.classList.remove('active'));
            event.target.classList.add('active');
            document.getElementById(name).classList.add('active');
            if(name==='campaigns') loadCampaigns();
            if(name==='logs') loadLogs();
        }

        async function loadCampaigns(){
            try{
                const res=await fetch(`${API_URL}?action=campaigns`);
                const data=await res.json();
                const list=document.getElementById('campaign-list');
                if(!data.campaigns || data.campaigns.length===0){list.innerHTML='<p>No campaigns</p>';return}
                list.innerHTML=data.campaigns.map(c=>`<div class="campaign-card"><h3>${c.name}</h3><p>${c.subject}</p></div>`).join('');
            }catch(e){document.getElementById('campaign-list').innerHTML='<p>Error loading campaigns</p>'}
        }

        document.getElementById('campaign-form').addEventListener('submit', async (e)=>{
            e.preventDefault();
            const fd=new FormData(e.target);
            const recipients=fd.get('recipients').split('\n').map(r=>r.trim()).filter(r=>r);
            const data={name:fd.get('name'),subject:fd.get('subject'),body:fd.get('body'),recipients:recipients,send_days:['monday'],send_time:fd.get('send_time'),active:fd.get('active')?1:0};
            // attach CSRF token
            data.csrf_token = CSRF_TOKEN;
            await fetch(`${API_URL}?action=campaign`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
            e.target.reset(); loadCampaigns();
        });

        document.getElementById('config-form').addEventListener('submit', async (e)=>{
            e.preventDefault();
            const fd=new FormData(e.target);
            const data={smtp_server:fd.get('smtp_server'),smtp_port:fd.get('smtp_port'),email_address:fd.get('email_address'),email_password:fd.get('email_password')};
            data.csrf_token = CSRF_TOKEN;
            await fetch(`${API_URL}?action=config`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
            alert('Saved');
        });

        async function loadLogs(){
            try{const res=await fetch(`${API_URL}?action=logs`);const data=await res.json();const el=document.getElementById('logs-list');if(!data.logs||data.logs.length===0){el.innerHTML='<p>No logs</p>';return}el.innerHTML='<pre>'+JSON.stringify(data.logs, null, 2)+'</pre>'}catch(e){document.getElementById('logs-list').innerHTML='<p>Error loading logs</p>'}
        }

        loadCampaigns();
    </script>
</body>
</html>
