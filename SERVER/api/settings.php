<?php
require 'cors.php';
header('Content-Type: application/json');
session_start();
require_once '../db.php';

// Authentication Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $settings = [];
    $result = $conn->query("SELECT * FROM system_settings");
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    echo json_encode($settings);
    
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST; // Fallback

    $allowed_settings = ['site_name', 'admin_email', 'currency', 'fine_base_amount', 'maintenance_mode'];
    $error = false;

    // First handle maintenance mode explicit check since checkboxes might not be sent if unchecked
    // In JSON API, we expect all keys. If not present in JSON, assume unchecked? 
    // Safer: Front end should always send all keys.
    
    foreach ($allowed_settings as $key) {
        if (isset($input[$key])) {
            $value = $input[$key];
            if ($key === 'maintenance_mode') {
                $value = ($value === true || $value === '1' || $value === 'on') ? '1' : '0';
            }
            
            $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
            $clean_value = trim($value); // Simple trim
            $stmt->bind_param("ss", $clean_value, $key);
            
            if (!$stmt->execute()) {
                $error = true;
            }
        }
    }

    if ($error) {
        echo json_encode(['success' => false, 'error' => 'Some settings failed to update']);
    } else {
        echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
    }
}
?>
