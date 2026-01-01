<?php
require 'cors.php';
header('Content-Type: application/json');
session_start();
require_once '../db.php';

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Fetch Watchlist
        $result = $conn->query("SELECT * FROM watchlist ORDER BY added_at DESC");
        $data = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode($data);
        break;

    case 'POST':
        // Add to Watchlist
        // Check if it's a JSON request or Form Data
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            // Fallback for form data
            $input = $_POST;
        }

        $plate = isset($input['plate_number']) ? strtoupper(trim($input['plate_number'])) : '';
        $reason = isset($input['reason']) ? trim($input['reason']) : '';
        $severity = isset($input['severity']) ? $input['severity'] : 'low';

        if (empty($plate) || empty($reason)) {
            http_response_code(400);
            echo json_encode(['error' => 'Plate number and reason are required']);
            exit;
        }

        try {
            $stmt = $conn->prepare("INSERT INTO watchlist (plate_number, reason, severity) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $plate, $reason, $severity);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => "Vehicle $plate added to watchlist"]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'DELETE':
        // Remove from Watchlist
        // Parse ID from URL query ?id=123 or from body
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if (!$id) {
             // Try body
             $input = json_decode(file_get_contents('php://input'), true);
             $id = isset($input['id']) ? (int)$input['id'] : 0;
        }

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid ID']);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM watchlist WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Vehicle removed']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
?>
