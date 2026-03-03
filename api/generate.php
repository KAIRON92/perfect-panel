<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

// Admin Password - CHANGE KARO!
define('ADMIN_SECRET', 'wisdom123');

// Supabase Config
define('SUPABASE_URL', getenv('SUPABASE_URL'));
define('SUPABASE_KEY', getenv('SUPABASE_KEY'));

// ----------------------------------------------------------------------
// FUNCTIONS
// ----------------------------------------------------------------------

function generateUniqueKey() {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $key = '';
    for ($i = 0; $i < 4; $i++) {
        for ($j = 0; $j < 4; $j++) {
            $key .= $characters[rand(0, strlen($characters) - 1)];
        }
        if ($i < 3) $key .= '-';
    }
    return $key;
}

function callSupabase($method, $endpoint, $data = null) {
    $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
    
    $ch = curl_init($url);
    $headers = [
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY,
        'Content-Type: application/json'
    ];
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'GET') {
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    } else if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } else if ($method === 'PATCH') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['response' => $response, 'code' => $httpCode];
}

function getAllKeys() {
    $result = callSupabase('GET', 'license_keys?select=*&order=created_at.desc');
    return json_decode($result['response'], true) ?? [];
}

// ----------------------------------------------------------------------
// HANDLE KEY GENERATION
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_secret'])) {
    if ($_POST['admin_secret'] !== ADMIN_SECRET) {
        $error = "❌ Unauthorized access!";
    } else {
        $plan = $_POST['plan'] ?? 'free';
        $days = intval($_POST['days'] ?? 30);
        
        // Generate unique key (check duplicate)
        do {
            $newKey = generateUniqueKey();
            $check = callSupabase('GET', "license_keys?license_key=eq.$newKey&select=license_key");
            $exists = !empty(json_decode($check['response'], true));
        } while ($exists);
        
        $expiry = date('Y-m-d H:i:s', strtotime("+$days days"));
        
        $data = [
            'license_key' => $newKey,
            'plan' => $plan,
            'expiry_date' => $expiry,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $result = callSupabase('POST', 'license_keys', $data);
        
        if ($result['code'] === 201) {
            $success = "✅ Key generated successfully!";
            $generatedKey = $newKey;
            $generatedExpiry = $expiry;
        } else {
            $error = "❌ Database error: " . $result['response'];
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Perfect Panel - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: #0f172a; color: #e2e8f0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        
        .header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            text-align: center;
        }
        .header h1 { font-size: 2.5em; margin-bottom: 10px; }
        .header p { opacity: 0.9; font-size: 1.1em; }
        
        .card {
            background: #1e293b;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            border: 1px solid #334155;
        }
        
        .card-title {
            font-size: 1.5em;
            margin-bottom: 25px;
            color: #a5b4fc;
            border-bottom: 2px solid #334155;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #cbd5e1;
            font-weight: 500;
        }
        
        input, select {
            width: 100%;
            padding: 12px 15px;
            background: #0f172a;
            border: 2px solid #334155;
            border-radius: 10px;
            font-size: 16px;
            color: #e2e8f0;
            transition: all 0.3s;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin: 20px 0;
            font-weight: 500;
        }
        
        .alert-success {
            background: #1e3a5f;
            border-left: 5px solid #3b82f6;
            color: #bfdbfe;
        }
        
        .alert-error {
            background: #4b2e2e;
            border-left: 5px solid #ef4444;
            color: #fecaca;
        }
        
        .key-display {
            background: #0f172a;
            border: 2px dashed #3b82f6;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: center;
        }
        
        .key-display .key {
            font-size: 32px;
            font-family: monospace;
            color: #3b82f6;
            font-weight: bold;
            letter-spacing: 2px;
        }
        
        .key-display .expiry {
            color: #94a3b8;
            margin-top: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            text-align: left;
            padding: 15px 10px;
            background: #0f172a;
            color: #94a3b8;
            font-weight: 600;
            font-size: 0.9em;
            text-transform: uppercase;
        }
        
        td {
            padding: 15px 10px;
            border-bottom: 1px solid #334155;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .status-active { background: #1e3a5f; color: #3b82f6; }
        .status-expired { background: #4b2e2e; color: #ef4444; }
        .status-banned { background: #4b2e2e; color: #ef4444; }
        
        .device-info {
            font-size: 0.9em;
            color: #94a3b8;
        }
        
        .badge {
            background: #334155;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #0f172a;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid #334155;
        }
        
        .stat-card .number {
            font-size: 2em;
            font-weight: bold;
            color: #a5b4fc;
        }
        
        .stat-card .label {
            color: #94a3b8;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>⚡ PERFECT PANEL ⚡</h1>
            <p>Complete License Management System | Device Lock | Expiry Tracking</p>
        </div>
        
        <?php
        // Get stats
        $allKeys = getAllKeys();
        $activeKeys = count(array_filter($allKeys, function($k) { 
            return $k['status'] === 'active' && strtotime($k['expiry_date']) > time();
        }));
        $totalLogins = array_sum(array_column($allKeys, 'total_logins'));
        $usedKeys = count(array_filter($allKeys, function($k) { return !empty($k['device_id']); }));
        ?>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo count($allKeys); ?></div>
                <div class="label">Total Keys</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $activeKeys; ?></div>
                <div class="label">Active Keys</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $usedKeys; ?></div>
                <div class="label">Registered Devices</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $totalLogins; ?></div>
                <div class="label">Total Logins</div>
            </div>
        </div>
        
        <!-- Key Generator Card -->
        <div class="card">
            <div class="card-title">🔑 Generate New License Key</div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <div class="key-display">
                    <div class="key"><?php echo $generatedKey; ?></div>
                    <div class="expiry">Expires: <?php echo date('d M Y H:i', strtotime($generatedExpiry)); ?></div>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Admin Password</label>
                    <input type="password" name="admin_secret" placeholder="Enter admin password" required>
                </div>
                
                <div class="form-group">
                    <label>Plan</label>
                    <select name="plan">
                        <option value="free">Free</option>
                        <option value="premium" selected>Premium</option>
                        <option value="vip">VIP</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Validity (Days)</label>
                    <input type="number" name="days" value="30" min="1" max="365" required>
                </div>
                
                <button type="submit">Generate Key</button>
            </form>
        </div>
        
        <!-- All Keys Card -->
        <div class="card">
            <div class="card-title">📋 All License Keys</div>
            
            <?php if (empty($allKeys)): ?>
                <p style="text-align: center; color: #94a3b8; padding: 40px;">No keys generated yet</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>License Key</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th>Device</th>
                            <th>Logins</th>
                            <th>Expiry</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allKeys as $key): 
                            $isExpired = strtotime($key['expiry_date']) < time();
                            $status = $isExpired ? 'expired' : ($key['status'] ?? 'active');
                            $statusClass = $status === 'active' ? 'status-active' : 'status-expired';
                        ?>
                        <tr>
                            <td><code><?php echo substr($key['license_key'], 0, 15); ?>...</code></td>
                            <td><span class="badge"><?php echo $key['plan']; ?></span></td>
                            <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $status; ?></span></td>
                            <td>
                                <?php if (!empty($key['device_name'])): ?>
                                    <div class="device-info">
                                        <?php echo $key['device_name']; ?><br>
                                        <small><?php echo $key['device_model'] ?? ''; ?></small>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #64748b;">Not used</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $key['total_logins'] ?? 0; ?></td>
                            <td><?php echo date('d M Y', strtotime($key['expiry_date'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>