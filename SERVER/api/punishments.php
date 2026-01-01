<?php
require 'cors.php';
header('Content-Type: application/json');
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Get options for issuing punishment
    $owners = $conn->query("SELECT id, full_name, username FROM owners ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
    $violations = $conn->query("
        SELECT v.id, ve.plate_number, v.violation_type
        FROM violations v
        JOIN vehicles ve ON v.vehicle_id = ve.id
        WHERE v.status IN ('confirmed','pending')
    ")->fetch_all(MYSQLI_ASSOC);
    $accidents = $conn->query("
        SELECT i.id, ve.plate_number, i.severity
        FROM incidents i
        JOIN vehicles ve ON i.vehicle_id = ve.id
    ")->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'owners' => $owners,
        'violations' => $violations,
        'accidents' => $accidents
    ]);

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $owner_id = intval($input['owner_id'] ?? 0);
    $type = $input['type'] ?? ''; // violation | accident
    $reference_id = intval($input['reference_id'] ?? 0);
    $amount = floatval($input['amount'] ?? 0);
    $message = trim($input['message'] ?? '');

    if (!$owner_id || !$type || !$reference_id || $amount <= 0 || !$message) {
        http_response_code(400);
        echo json_encode(['error' => 'All fields are required and amount must be positive.']);
        exit;
    }

    $conn->begin_transaction();
    try {
        // 1. Create Payment
        $violation_id = ($type === 'violation') ? $reference_id : NULL;
        $stmt = $conn->prepare("
            INSERT INTO payments (violation_id, owner_id, amount, payment_type, payment_status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param("iids", $violation_id, $owner_id, $amount, $type);
        $stmt->execute();
        $payment_id = $conn->insert_id;

        // 2. Create Notification
        $title = ucfirst($type) . " Punishment Issued";
        $stmt2 = $conn->prepare("
            INSERT INTO notifications (owner_id, title, message, reference_id)
            VALUES (?, ?, ?, ?)
        ");
        $stmt2->bind_param("issi", $owner_id, $title, $message, $reference_id);
        $stmt2->execute();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Punishment issued successfully.']);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Transaction failed: ' . $e->getMessage()]);
    }
}
?>
