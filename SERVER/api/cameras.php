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

function clean_input($data) {
    if (is_array($data)) return $data;
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        // Options (Nodes for dropdown)
        if ($action === 'options') {
            $nodes = $conn->query("SELECT id, name FROM nodes ORDER BY name")->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['nodes' => $nodes]);
            exit;
        }

        // List & Stats
        $sql = "
            SELECT c.*, n.name AS node_name
            FROM cameras c
            LEFT JOIN nodes n ON c.node_id = n.id
            ORDER BY c.created_at DESC
        ";
        $cameras = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

        $stats = [
            'total' => count($cameras),
            'active' => 0,
            'inactive' => 0
        ];

        foreach($cameras as $c) {
            if($c['status'] === 'active') $stats['active']++;
            else $stats['inactive']++;
        }

        echo json_encode(['cameras' => $cameras, 'stats' => $stats]);
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        
        $node_id = intval($input['node_id']);
        $camera_name = clean_input($input['camera_name']);
        $location = clean_input($input['location']);
        $direction = clean_input($input['direction']);
        $lane = clean_input($input['lane']);
        $ip = clean_input($input['ip_address']);
        $status = clean_input($input['status']);
        $id = isset($input['camera_id']) ? intval($input['camera_id']) : 0;

        try {
            if ($id > 0) {
                // Update
                $stmt = $conn->prepare("UPDATE cameras SET node_id=?, camera_name=?, location=?, direction=?, lane=?, ip_address=?, status=? WHERE id=?");
                $stmt->bind_param("issssssi", $node_id, $camera_name, $location, $direction, $lane, $ip, $status, $id);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Camera updated']);
                } else throw new Exception($stmt->error);
            } else {
                // Insert
                $stmt = $conn->prepare("INSERT INTO cameras (node_id, camera_name, location, direction, lane, ip_address, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issssss", $node_id, $camera_name, $location, $direction, $lane, $ip, $status);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Camera added']);
                } else throw new Exception($stmt->error);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id > 0) {
            if ($conn->query("DELETE FROM cameras WHERE id=$id")) {
                echo json_encode(['success' => true]);
            } else {
                 http_response_code(500); echo json_encode(['error' => 'Delete failed']);
            }
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}
?>
