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
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Common User/Sidebar Data
$userName = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Owner');
$userRole = 'Vehicle Owner';
$stmt_pic = $conn->prepare("SELECT profile_picture FROM owners WHERE id = ?");
$stmt_pic->bind_param("i", $owner_id);
$stmt_pic->execute();
$owner_profile_pic = $stmt_pic->get_result()->fetch_assoc()['profile_picture'] ?? '';

// Unpaid Violations Count for Sidebar
$stmt = $conn->prepare("
    SELECT COUNT(*) AS unpaid 
    FROM violations v 
    JOIN vehicles ve ON v.vehicle_id = ve.id 
    WHERE ve.owner_id = ? AND v.status != 'paid'
");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$unpaidViolations = $stmt->get_result()->fetch_assoc()['unpaid'] ?? 0;

if ($method === 'GET') {
    $payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;
    
    if ($payment_id > 0) {
        // Fetch Single Payment (for receipt or method page)
         $stmt = $conn->prepare("
            SELECT p.*, 
                   v.violation_type, 
                   ve.plate_number AS vehicle_plate, 
                   i.incident_type, 
                   i.severity
            FROM payments p
            LEFT JOIN violations v ON p.violation_id = v.id
            LEFT JOIN incidents i ON (p.payment_type='accident' AND i.id = p.incident_id)
            LEFT JOIN vehicles ve ON (ve.id = v.vehicle_id OR ve.id = i.vehicle_id)
            WHERE p.id = ? AND p.owner_id = ?
        ");
        $stmt->bind_param("ii", $payment_id, $owner_id);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();

        if ($payment) {
            echo json_encode([
                'user' => [
                    'name' => $userName,
                    'role' => $userRole,
                    'avatar' => $owner_profile_pic
                ],
                'stats' => ['unpaidViolations' => $unpaidViolations],
                'payment' => $payment
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Payment not found']);
        }

    } else {
        // Fetch All Payments
        $payments_stmt = $conn->prepare("
        SELECT p.*, 
               v.violation_type, 
               ve.plate_number AS vehicle_plate, 
               i.incident_type, 
               i.severity
        FROM payments p
        LEFT JOIN violations v ON p.violation_id = v.id
        LEFT JOIN incidents i ON (p.payment_type='accident' AND i.id = p.incident_id)
        LEFT JOIN vehicles ve ON (ve.id = v.vehicle_id OR ve.id = i.vehicle_id)
        WHERE p.owner_id = ?
        ORDER BY p.created_at DESC
    ");
    $payments_stmt->bind_param("i", $owner_id);
    $payments_stmt->execute();
    $payments = $payments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'user' => [
            'name' => $userName,
            'role' => $userRole,
            'avatar' => $owner_profile_pic
        ],
        'stats' => ['unpaidViolations' => $unpaidViolations],
        'payments' => $payments
    ]);


    } elseif ($method === 'POST') {
    // Handle Pay Action
    $input = json_decode(file_get_contents('php://input'), true);
    $payment_id = isset($input['payment_id']) ? (int)$input['payment_id'] : 0;
    $pay_method = isset($input['payment_method']) ? clean_input($input['payment_method']) : 'cash';

    if ($payment_id > 0) {
        $stmt = $conn->prepare("UPDATE payments SET payment_status='paid', payment_method=?, paid_at=NOW() WHERE id=? AND owner_id=?");
        $stmt->bind_param("sii", $pay_method, $payment_id, $owner_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Payment successful']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Payment failed']);
        }
    } else {

        http_response_code(400);
        echo json_encode(['error' => 'Invalid Payment ID']);
    }
}
?>
