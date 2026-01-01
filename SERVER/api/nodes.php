<?php
require 'cors.php';
header('Content-Type: application/json');
session_start();
require_once '../db.php';

// Auth Check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

function clean_input($data) {
    if (is_array($data)) return $data;
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        // Fetch Nodes
        $sql = "SELECT * FROM nodes ORDER BY id DESC";
        $result = $conn->query($sql);
        $nodes = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode($nodes);
        break;

    case 'POST':
        // Add or Update Node
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;

        $name = clean_input($input['name'] ?? '');
        $location = clean_input($input['location'] ?? '');
        $status = clean_input($input['status'] ?? 'offline');
        $lat = !empty($input['lat']) ? floatval($input['lat']) : null;
        $lng = !empty($input['lng']) ? floatval($input['lng']) : null;
        $id = isset($input['id']) ? intval($input['id']) : 0;
        
        // Validation
        if (empty($name) || empty($location)) {
            http_response_code(400);
            echo json_encode(['error' => 'Name and Location are required']);
            exit;
        }

        try {
            if ($id > 0) {
                // UPDATE
                $stmt = $conn->prepare("UPDATE nodes SET name=?, location=?, status=?, lat=?, lng=? WHERE id=?");
                $stmt->bind_param("sssddi", $name, $location, $status, $lat, $lng, $id);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Node updated successfully']);
                } else {
                     throw new Exception($stmt->error);
                }
            } else {
                // INSERT
                $apiKey = bin2hex(random_bytes(16)); // 32 chars
                $stmt = $conn->prepare("INSERT INTO nodes (name, location, status, lat, lng, api_key) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssdds", $name, $location, $status, $lat, $lng, $apiKey);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Node added successfully', 'id' => $stmt->insert_id, 'api_key' => $apiKey]);
                } else {
                    throw new Exception($stmt->error);
                }
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
        }
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id <= 0) {
            $input = json_decode(file_get_contents('php://input'), true);
            $id = isset($input['id']) ? intval($input['id']) : 0;
        }

        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM nodes WHERE id=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Node deleted successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Delete failed']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid ID']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
?>
