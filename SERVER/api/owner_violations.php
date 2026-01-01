<?php
require 'cors.php';
header('Content-Type: application/json');
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$owner_id = $_SESSION['user_id'];

// Sidebar & User Data
$userName = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Owner');
$stmt_pic = $conn->prepare("SELECT profile_picture FROM owners WHERE id = ?");
$stmt_pic->bind_param("i", $owner_id);
$stmt_pic->execute();
$owner_profile_pic = $stmt_pic->get_result()->fetch_assoc()['profile_picture'] ?? '';

$stmt = $conn->prepare("
    SELECT COUNT(*) AS unpaid 
    FROM violations v 
    JOIN vehicles ve ON v.vehicle_id = ve.id 
    WHERE ve.owner_id = ? AND v.status != 'paid'
");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$unpaidViolations = $stmt->get_result()->fetch_assoc()['unpaid'] ?? 0;

function getFine($type) {
    return match ($type) {
        'speeding' => 2000,
        'red_light' => 3000,
        'lane_violation' => 1500,
        'illegal_parking' => 1000,
        'wrong_direction' => 4000,
        default => 0
    };
}

// Fetch Violations
$stmt = $conn->prepare("
    SELECT v.*, ve.plate_number, n.name AS node_name
    FROM violations v
    JOIN vehicles ve ON v.vehicle_id = ve.id
    LEFT JOIN nodes n ON v.node_id = n.id
    WHERE ve.owner_id = ?
    ORDER BY v.violation_time DESC
");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$violations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Augment with Fine amount if needed (though client can calculate, usually good to send)
foreach($violations as &$v) {
    $v['fine_amount'] = getFine($v['violation_type']);
}

echo json_encode([
    'user' => [
        'name' => $userName,
        'role' => 'Vehicle Owner',
        'avatar' => $owner_profile_pic
    ],
    'stats' => ['unpaidViolations' => $unpaidViolations],
    'violations' => $violations
]);
?>
