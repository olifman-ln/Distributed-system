<?php
require 'cors.php';
header('Content-Type: application/json');
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// User Info
$user = [
    'name' => htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Guest'),
    'role' => htmlspecialchars($_SESSION['role'] ?? ''),
    'avatar' => ''
];
$stmt_pic = $conn->prepare("SELECT profile_picture FROM admin WHERE id = ?");
$stmt_pic->bind_param("i", $_SESSION['user_id']);
$stmt_pic->execute();
$admin_data = $stmt_pic->get_result()->fetch_assoc();
$user['avatar'] = $admin_data['profile_picture'] ?? '';

// Stats
$today = date('Y-m-d');
$stats = [
    'totalNodes' => $conn->query("SELECT COUNT(*) FROM nodes")->fetch_row()[0],
    'onlineNodes' => $conn->query("SELECT COUNT(*) FROM nodes WHERE status = 'online'")->fetch_row()[0],
    'totalVehicles' => $conn->query("SELECT COUNT(*) FROM vehicles")->fetch_row()[0],
    'totalOwners' => $conn->query("SELECT COUNT(*) FROM owners")->fetch_row()[0],
    'todayIncidents' => $conn->query("SELECT COUNT(*) FROM incidents WHERE DATE(reported_at) = '$today'")->fetch_row()[0],
    'todayViolations' => $conn->query("SELECT COUNT(*) FROM violations WHERE DATE(violation_time) = '$today'")->fetch_row()[0],
];

// Recent Data
$recentVehicles = $conn->query("
    SELECT v.*, vt.type_name, o.full_name 
    FROM vehicles v
    LEFT JOIN vehicle_types vt ON v.type_id = vt.id
    LEFT JOIN owners o ON v.owner_id = o.id
    ORDER BY v.registered_at DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

$recentIncidents = $conn->query("
    SELECT i.*, n.name AS node_name
    FROM incidents i
    LEFT JOIN nodes n ON i.node_id = n.id
    ORDER BY reported_at DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

$recentViolations = $conn->query("
    SELECT v.*, n.name AS node_name, ve.plate_number as vehicle_number
    FROM violations v
    LEFT JOIN nodes n ON v.node_id = n.id
    LEFT JOIN vehicles ve ON v.vehicle_id = ve.id
    ORDER BY violation_time DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Charts
$vehiclesByType = $conn->query("
    SELECT vt.type_name, COUNT(v.id) as count
    FROM vehicles v
    RIGHT JOIN vehicle_types vt ON v.type_id = vt.id
    GROUP BY vt.id
    ORDER BY count DESC
")->fetch_all(MYSQLI_ASSOC);

// Mock Traffic Data for Chart (replace with real if table exists)
// Generating last 24h mock data for demo if real table 'traffic_data' logic isn't complex
$trafficData = [];
for ($i=0; $i<24; $i++) {
    $trafficData[] = rand(10, 100);
}

echo json_encode([
    'user' => $user,
    'stats' => $stats,
    'recentVehicles' => $recentVehicles,
    'recentIncidents' => $recentIncidents,
    'recentViolations' => $recentViolations,
    'vehiclesByType' => $vehiclesByType,
    'trafficVolume' => $trafficData
]);
?>
