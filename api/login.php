<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Supabase Config
define('SUPABASE_URL', getenv('SUPABASE_URL'));
define('SUPABASE_KEY', getenv('SUPABASE_KEY'));

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

// ----------------------------------------------------------------------
// MAIN LOGIN HANDLER
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $license_key = $_POST['license_key'] ?? '';
    $device_id = $_POST['device_id'] ?? '';
    $device_name = $_POST['device_name'] ?? 'Unknown Device';
    $device_model = $_POST['device_model'] ?? '';
    $device_brand = $_POST['device_brand'] ?? '';
    $device_os = $_POST['device_os'] ?? 'Android';
    $ip_address = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (empty($license_key) || empty($device_id)) {
        echo json_encode(['status' => 'error', 'message' => 'License key and device ID required']);
        exit;
    }
    
    // Get key from database
    $result = callSupabase('GET', "license_keys?license_key=eq.$license_key&select=*");
    
    if ($result['code'] !== 200) {
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
        exit;
    }
    
    $keys = json_decode($result['response'], true);
    
    if (empty($keys)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid license key']);
        exit;
    }
    
    $keyData = $keys[0];
    
    // Check if banned
    if ($keyData['status'] === 'banned') {
        echo json_encode(['status' => 'error', 'message' => 'This key has been banned']);
        exit;
    }
    
    // Check expiry
    if (strtotime($keyData['expiry_date']) < time()) {
        // Auto-update status to expired
        callSupabase('PATCH', "license_keys?license_key=eq.$license_key", ['status' => 'expired']);
        echo json_encode(['status' => 'error', 'message' => 'License key expired']);
        exit;
    }
    
    // Check device lock
    if (empty($keyData['device_id'])) {
        // FIRST LOGIN - register device
        $updateData = [
            'device_id' => $device_id,
            'device_name' => $device_name,
            'device_model' => $device_model,
            'device_brand' => $device_brand,
            'device_os' => $device_os,
            'ip_address' => $ip_address,
            'first_login' => date('Y-m-d H:i:s'),
            'last_login' => date('Y-m-d H:i:s'),
            'total_logins' => 1
        ];
        
        callSupabase('PATCH', "license_keys?license_key=eq.$license_key", $updateData);
        
        // Log login
        callSupabase('POST', 'login_history', [
            'license_key' => $license_key,
            'device_id' => $device_id,
            'device_name' => $device_name,
            'device_model' => $device_model,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'success' => true
        ]);
        
        // Calculate days left
        $expiry = new DateTime($keyData['expiry_date']);
        $now = new DateTime();
        $daysLeft = $now->diff($expiry)->days;
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Device registered successfully!',
            'first_login' => true,
            'plan' => $keyData['plan'],
            'expiry_date' => $keyData['expiry_date'],
            'days_left' => $daysLeft
        ]);
        
    } else if ($keyData['device_id'] === $device_id) {
        // SAME DEVICE - allow login
        $newCount = ($keyData['total_logins'] ?? 0) + 1;
        
        callSupabase('PATCH', "license_keys?license_key=eq.$license_key", [
            'last_login' => date('Y-m-d H:i:s'),
            'total_logins' => $newCount,
            'ip_address' => $ip_address
        ]);
        
        // Log login
        callSupabase('POST', 'login_history', [
            'license_key' => $license_key,
            'device_id' => $device_id,
            'device_name' => $device_name,
            'device_model' => $device_model,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'success' => true
        ]);
        
        // Calculate days left
        $expiry = new DateTime($keyData['expiry_date']);
        $now = new DateTime();
        $daysLeft = $now->diff($expiry)->days;
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful!',
            'first_login' => false,
            'plan' => $keyData['plan'],
            'expiry_date' => $keyData['expiry_date'],
            'days_left' => $daysLeft,
            'total_logins' => $newCount
        ]);
        
    } else {
        // DIFFERENT DEVICE - BLOCK
        echo json_encode([
            'status' => 'device_mismatch',
            'message' => 'This key is already registered to another device!',
            'registered_device' => $keyData['device_name']
        ]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
?>