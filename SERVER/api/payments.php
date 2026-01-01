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
    $payments = $conn->query("
        SELECT p.*, o.full_name as owner_name, v.plate_number
        FROM payments p
        JOIN owners o ON p.owner_id = o.id
        LEFT JOIN violations vi ON p.violation_id = vi.id
        LEFT JOIN vehicles v ON (vi.vehicle_id = v.id OR (p.payment_type='accident' AND p.violation_id IS NULL))
        ORDER BY p.created_at DESC
    ")->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['payments' => $payments]);

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'mark_paid') {
        $id = intval($input['id']);
        $stmt = $conn->prepare("UPDATE payments SET payment_status = 'paid', paid_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Update failed']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
}
?>
