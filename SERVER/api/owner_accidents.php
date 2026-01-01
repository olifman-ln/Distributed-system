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

// Fetch Accidents
$stmt = $conn->prepare("
    SELECT i.*, v.plate_number, n.name AS node_name
    FROM incidents i
    JOIN vehicles v ON i.vehicle_id = v.id
    LEFT JOIN nodes n ON i.node_id = n.id
    WHERE v.owner_id = ?
    ORDER BY i.reported_at DESC
");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$accidents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Check payment status for accidents
// We can't do a simple JOIN easily for 'latest payment' but we can iterate or use subquery
// Simple approach: Iterate and add payment status
foreach($accidents as &$incident) {
    $stmtP = $conn->prepare("SELECT payment_status FROM payments WHERE incident_id = ? ORDER BY id DESC LIMIT 1");
    $stmtP->bind_param("i", $incident['id']);
    $stmtP->execute();
    $p = $stmtP->get_result()->fetch_assoc();
    $incident['payment_status'] = $p['payment_status'] ?? 'none';
}

echo json_encode([
    'user' => [
        'name' => $userName,
        'role' => 'Vehicle Owner',
        'avatar' => $owner_profile_pic
    ],
    'stats' => ['unpaidViolations' => $unpaidViolations],
    'accidents' => $accidents
]);
?>
