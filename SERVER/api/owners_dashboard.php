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
$userName = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Owner');
$userRole = 'Vehicle Owner';

// Fetch Owner Profile Picture
$owner_profile_pic = '';
$stmt_pic = $conn->prepare("SELECT profile_picture FROM owners WHERE id = ?");
$stmt_pic->bind_param("i", $owner_id);
$stmt_pic->execute();
$owner_profile_pic = $stmt_pic->get_result()->fetch_assoc()['profile_picture'] ?? '';

try {
    // 1. Total Vehicles
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM vehicles WHERE owner_id = ?");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $totalVehicles = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // 2. Total Violations
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM violations v JOIN vehicles ve ON v.vehicle_id = ve.id WHERE ve.owner_id = ?");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $totalViolations = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // 3. Unpaid Violations
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS unpaid 
        FROM violations v 
        JOIN vehicles ve ON v.vehicle_id = ve.id 
        WHERE ve.owner_id = ? AND v.status != 'paid'
    ");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $unpaidViolations = $stmt->get_result()->fetch_assoc()['unpaid'] ?? 0;

    // 4. Total Accidents
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM incidents i JOIN vehicles ve ON i.vehicle_id = ve.id WHERE ve.owner_id = ?");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $totalAccidents = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // 5. Recent Violations
    $stmt = $conn->prepare("
        SELECT v.*, n.name AS node_name, ve.plate_number
        FROM violations v
        JOIN vehicles ve ON v.vehicle_id = ve.id
        LEFT JOIN nodes n ON v.node_id = n.id
        WHERE ve.owner_id = ?
        ORDER BY violation_time DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $recentViolations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 6. Recent Accidents
    $stmt = $conn->prepare("
        SELECT i.*, n.name AS node_name, ve.plate_number
        FROM incidents i
        JOIN vehicles ve ON i.vehicle_id = ve.id
        LEFT JOIN nodes n ON i.node_id = n.id
        WHERE ve.owner_id = ?
        ORDER BY reported_at DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $recentAccidents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 7. My Vehicles
    $stmt = $conn->prepare("
        SELECT v.*, vt.type_name
        FROM vehicles v
        LEFT JOIN vehicle_types vt ON v.type_id = vt.id
        WHERE v.owner_id = ?
        ORDER BY v.registered_at DESC
    ");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $myVehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 8. Pending Payments
    $stmt = $conn->prepare("
        SELECT p.*, 
               v.violation_type, 
               v.vehicle_id as v_vehicle_id,
               i.incident_type, 
               i.severity,
               i.vehicle_id as i_vehicle_id,
               COALESCE(vv.plate_number, vi.plate_number) as plate_number
        FROM payments p
        LEFT JOIN violations v ON p.violation_id = v.id
        LEFT JOIN incidents i ON p.incident_id = i.id
        LEFT JOIN vehicles vv ON v.vehicle_id = vv.id
        LEFT JOIN vehicles vi ON i.vehicle_id = vi.id
        WHERE p.owner_id = ? AND p.payment_status = 'pending'
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $pendingPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 9. Recent Notifications
    $stmt = $conn->prepare("
        SELECT * FROM notifications
        WHERE owner_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $recentNotifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 10. Total Pending Payments Amount
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total_pending
        FROM payments
        WHERE owner_id = ? AND payment_status = 'pending'
    ");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $totalPendingAmount = $stmt->get_result()->fetch_assoc()['total_pending'] ?? 0;

    echo json_encode([
        'user' => [
            'name' => $userName,
            'role' => $userRole,
            'avatar' => $owner_profile_pic
        ],
        'stats' => [
            'totalVehicles' => $totalVehicles,
            'totalViolations' => $totalViolations,
            'unpaidViolations' => $unpaidViolations,
            'totalAccidents' => $totalAccidents,
            'totalPendingAmount' => $totalPendingAmount
        ],
        'recentViolations' => $recentViolations,
        'recentAccidents' => $recentAccidents,
        'myVehicles' => $myVehicles,
        'pendingPayments' => $pendingPayments,
        'recentNotifications' => $recentNotifications
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server Error: ' . $e->getMessage()]);
}
?>
