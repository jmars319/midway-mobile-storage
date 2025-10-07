<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Scheduler Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 10px 20px;
            background: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .tab:hover {
            background: #e8f4f8;
        }
        
        .tab.active {
            background: #3498db;
            color: white;
        }
        
        .tab-content {
            display: none;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="time"],
        textarea,
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .checkbox-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .checkbox-group label {
            display: flex;
            align-items: center;
            font-weight: normal;
        }
        
        .checkbox-group input {
            margin-right: 5px;
            width: auto;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .campaign-list {
            display: grid;
            gap: 15px;
        }
        
        .campaign-card {
            border: 1px solid #e0e0e0;
            padding: 15px;
            border-radius: 6px;
            background: #fafafa;
        }
        
        .campaign-card h3 {
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .campaign-meta {
            font-size: 13px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .campaign-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .alert {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .supplier-list {
            margin-top: 15px;
        }
        
        .supplier-item {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .hidden {
            display: none;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 Email Scheduler Admin</h1>
            <p>Manage your automated email campaigns</p>
        </div>
        
        <div class="tabs">
            <button class="tab active" onclick="switchTab('campaigns')">Campaigns</button>
            <button class="tab" onclick="switchTab('new-campaign')">New Campaign</button>
            <button class="tab" onclick="switchTab('config')">Email Config</button>
            <button class="tab" onclick="switchTab('logs')">Email Logs</button>
        </div>
        
        <!-- Campaigns Tab -->
        <div id="campaigns" class="tab-content active">
            <h2>Your Campaigns</h2>
            <div id="campaign-list" class="campaign-list">
                <p>Loading campaigns...</p>
            </div>
        </div>
        
        <!-- New Campaign Tab -->
        <div id="new-campaign" class="tab-content">
            <h2>Create New Campaign</h2>
            <div id="campaign-alert"></div>
            <form id="campaign-form">
                <div class="form-group">
                    <label>Campaign Name</label>
                    <input type="text" name="name" required>
                </div>
                
                <div class="form-group">
                    <label>Email Subject</label>
                    <input type="text" name="subject" required>
                </div>
                
                <div class="form-group">
                    <label>Email Body</label>
                    <textarea name="body" required></textarea>
                </div>
                
                <div class="form-group">
                    <label>Recipients (one per line)</label>
                    <textarea name="recipients" placeholder="email1@example.com&#10;email2@example.com" required></textarea>
                </div>
                
                <div class="form-group">
                    <label>Send on Days</label>
                    <div class="checkbox-group">
                        <label><input type="checkbox" name="days" value="monday"> Monday</label>
                        <label><input type="checkbox" name="days" value="tuesday"> Tuesday</label>
                        <label><input type="checkbox" name="days" value="wednesday"> Wednesday</label>
                        <label><input type="checkbox" name="days" value="thursday"> Thursday</label>
                        <label><input type="checkbox" name="days" value="friday"> Friday</label>
                        <label><input type="checkbox" name="days" value="saturday"> Saturday</label>
                        <label><input type="checkbox" name="days" value="sunday"> Sunday</label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Send Time</label>
                    <input type="time" name="send_time" value="09:00" required>
                </div>
                
                <div class="form-group">
                    <label><input type="checkbox" name="active" checked> Active</label>
                </div>
                
                <button type="submit" class="btn btn-primary">Create Campaign</button>
            </form>
        </div>
        
        <!-- Config Tab -->
        <div id="config" class="tab-content">
            <h2>Email Configuration</h2>
            <div id="config-alert"></div>
            <form id="config-form">
                <div class="form-group">
                    <label>SMTP Server</label>
                    <input type="text" name="smtp_server" placeholder="smtp.gmail.com" required>
                </div>
                
                <div class="form-group">
                    <label>SMTP Port</label>
                    <input type="text" name="smtp_port" placeholder="587" required>
                </div>
                
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email_address" required>
                </div>
                
                <div class="form-group">
                    <label>Email Password (or App Password)</label>
                    <input type="password" name="email_password" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Save Configuration</button>
            </form>
        </div>
        
        <!-- Logs Tab -->
        <div id="logs" class="tab-content">
            <h2>Email Logs</h2>
            <div id="logs-list">
                <p>Loading logs...</p>
            </div>
        </div>
    </div>
    
    <!-- Edit Campaign Modal -->
    <div id="edit-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Campaign</h2>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div id="edit-campaign-content"></div>
        </div>
    </div>
    
    <script>
        const API_URL = 'api/email_api.php'; // Adjust this path to match your setup
        
        // Tab switching
        function switchTab(tabName) {
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(tabName).classList.add('active');
            
            if (tabName === 'campaigns') loadCampaigns();
            if (tabName === 'logs') loadLogs();
            if (tabName === 'config') loadConfig();
        }
        
        // Load campaigns
        async function loadCampaigns() {
            try {
                const response = await fetch(`${API_URL}?action=campaigns`);
                const data = await response.json();
                
                const listEl = document.getElementById('campaign-list');
                if (data.campaigns.length === 0) {
                    listEl.innerHTML = '<p>No campaigns yet. Create your first one!</p>';
                    return;
                }
                
                listEl.innerHTML = data.campaigns.map(campaign => `
                    <div class="campaign-card">
                        <h3>${campaign.name}</h3>
                        <span class="status-badge ${campaign.active ? 'status-active' : 'status-inactive'}">
                            ${campaign.active ? 'Active' : 'Inactive'}
                        </span>
                        <div class="campaign-meta">
                            <p><strong>Subject:</strong> ${campaign.subject}</p>
                            <p><strong>Days:</strong> ${campaign.send_days.join(', ')}</p>
                            <p><strong>Time:</strong> ${campaign.send_time}</p>
                            <p><strong>Recipients:</strong> ${campaign.recipients.length}</p>
                        </div>
                        <div class="campaign-actions">
                            <button class="btn btn-success" onclick="sendNow(${campaign.id})">Send Now</button>
                            <button class="btn btn-primary" onclick="editCampaign(${campaign.id})">Edit</button>
                            <button class="btn btn-secondary" onclick="viewSuppliers(${campaign.id})">Suppliers</button>
                            <button class="btn btn-danger" onclick="deleteCampaign(${campaign.id})">Delete</button>
                        </div>
                    </div>
                `).join('');
            } catch (error) {
                console.error('Error loading campaigns:', error);
                document.getElementById('campaign-list').innerHTML = '<p>Error loading campaigns</p>';
            }
        }
        
        // Create campaign
        document.getElementById('campaign-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            
            const days = [];
            document.querySelectorAll('input[name="days"]:checked').forEach(cb => {
                days.push(cb.value);
            });
            
            const recipients = formData.get('recipients').split('\n').map(r => r.trim()).filter(r => r);
            
            const data = {
                name: formData.get('name'),
                subject: formData.get('subject'),
                body: formData.get('body'),
                recipients: recipients,
                send_days: days,
                send_time: formData.get('send_time'),
                active: formData.get('active') ? 1 : 0
            };
            
            try {
                const response = await fetch(`${API_URL}?action=campaign`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('campaign-alert', 'Campaign created successfully!', 'success');
                    e.target.reset();
                    loadCampaigns();
                } else {
                    showAlert('campaign-alert', 'Error creating campaign', 'error');
                }
            } catch (error) {
                showAlert('campaign-alert', 'Error: ' + error.message, 'error');
            }
        });
        
        // Send campaign now
        async function sendNow(campaignId) {
            if (!confirm('Send this campaign now?')) return;
            
            try {
                const response = await fetch(`${API_URL}?action=send`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ campaign_id: campaignId })
                });
                
                const result = await response.json();
                alert(result.success ? 'Email sent!' : 'Failed to send email');
            } catch (error) {
                alert('Error sending email: ' + error.message);
            }
        }
        
        // Delete campaign
        async function deleteCampaign(campaignId) {
            if (!confirm('Delete this campaign?')) return;
            
            try {
                const response = await fetch(`${API_URL}?action=campaign&id=${campaignId}`, {
                    method: 'DELETE'
                });
                
                const result = await response.json();
                if (result.success) {
                    loadCampaigns();
                }
            } catch (error) {
                alert('Error deleting campaign: ' + error.message);
            }
        }
        
        // Edit campaign (simplified - opens modal)
        async function editCampaign(campaignId) {
            try {
                const response = await fetch(`${API_URL}?action=campaign&id=${campaignId}`);
                const data = await response.json();
                const campaign = data.campaign;
                
                document.getElementById('edit-campaign-content').innerHTML = `
                    <form id="edit-form">
                        <input type="hidden" name="id" value="${campaign.id}">
                        <div class="form-group">
                            <label>Campaign Name</label>
                            <input type="text" name="name" value="${campaign.name}" required>
                        </div>
                        <div class="form-group">
                            <label>Subject</label>
                            <input type="text" name="subject" value="${campaign.subject}" required>
                        </div>
                        <div class="form-group">
                            <label>Body</label>
                            <textarea name="body" required>${campaign.body}</textarea>
                        </div>
                        <div class="form-group">
                            <label><input type="checkbox" name="active" ${campaign.active ? 'checked' : ''}> Active</label>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                `;
                
                document.getElementById('edit-modal').classList.add('active');
                
                document.getElementById('edit-form').addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const formData = new FormData(e.target);
                    const updateData = {
                        name: formData.get('name'),
                        subject: formData.get('subject'),
                        body: formData.get('body'),
                        recipients: campaign.recipients,
                        send_days: campaign.send_days,
                        send_time: campaign.send_time,
                        active: formData.get('active') ? 1 : 0
                    };
                    
                    const response = await fetch(`${API_URL}?action=campaign&id=${campaign.id}`, {
                        method: 'PUT',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(updateData)
                    });
                    
                    const result = await response.json();
                    if (result.success) {
                        closeModal();
                        loadCampaigns();
                    }
                });
                
            } catch (error) {
                alert('Error loading campaign: ' + error.message);
            }
        }
        
        // View suppliers
        function viewSuppliers(campaignId) {
            alert('Supplier management coming soon! For now, add suppliers via the API.');
        }
        
        // Close modal
        function closeModal() {
            document.getElementById('edit-modal').classList.remove('active');
        }
        
        // Load email config
        async function loadConfig() {
            try {
                const response = await fetch(`${API_URL}?action=config`);
                const data = await response.json();
                
                if (data.config) {
                    document.querySelector('[name="smtp_server"]').value = data.config.smtp_server || '';
                    document.querySelector('[name="smtp_port"]').value = data.config.smtp_port || '';
                    document.querySelector('[name="email_address"]').value = data.config.email_address || '';
                }
            } catch (error) {
                console.error('Error loading config:', error);
            }
        }
        
        // Save email config
        document.getElementById('config-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            
            const data = {
                smtp_server: formData.get('smtp_server'),
                smtp_port: formData.get('smtp_port'),
                email_address: formData.get('email_address'),
                email_password: formData.get('email_password')
            };
            
            try {
                const response = await fetch(`${API_URL}?action=config`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                if (result.success) {
                    showAlert('config-alert', 'Configuration saved!', 'success');
                }
            } catch (error) {
                showAlert('config-alert', 'Error: ' + error.message, 'error');
            }
        });
        
        // Load logs
        async function loadLogs() {
            try {
                const response = await fetch(`${API_URL}?action=logs`);
                const data = await response.json();
                
                const logsEl = document.getElementById('logs-list');
                if (data.logs.length === 0) {
                    logsEl.innerHTML = '<p>No email logs yet.</p>';
                    return;
                }
                
                logsEl.innerHTML = '<table class="doc-table">' +
                    '<tr class="doc-tr doc-tr--header"><th class="doc-th">Date</th><th class="doc-th">Campaign ID</th><th class="doc-th">Status</th><th class="doc-th">Message</th></tr>' +
                    data.logs.map(log => `
                        <tr class="doc-tr">
                            <td class="doc-td">${log.sent_at}</td>
                            <td class="doc-td">${log.campaign_id}</td>
                            <td class="doc-td"><span class="status-badge ${log.status === 'success' ? 'status-active' : 'status-inactive'}">${log.status}</span></td>
                            <td class="doc-td">${log.message}</td>
                        </tr>
                    `).join('') +
                    '</table>';
            } catch (error) {
                document.getElementById('logs-list').innerHTML = '<p>Error loading logs</p>';
            }
        }
        
        // Show alert
        function showAlert(elementId, message, type) {
            const alertEl = document.getElementById(elementId);
            alertEl.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            setTimeout(() => alertEl.innerHTML = '', 5000);
        }
        
        // Initialize
        loadCampaigns();
    </script>
</body>
</html>