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
        $search = $_GET['search'] ?? '';
        $sql = "SELECT * FROM owners";
        $params = [];
        $types = "";

        if ($search) {
            $sql .= " WHERE full_name LIKE ? OR phone LIKE ? OR license_number LIKE ?";
            $like = "%$search%";
            $params = [$like, $like, $like];
            $types = "sss";
        }
        $sql .= " ORDER BY created_at DESC";

        if ($params) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $owners = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } else {
            $owners = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
        }

        echo json_encode(['owners' => $owners]);
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);

        $id = isset($input['id']) ? intval($input['id']) : 0;
        $full_name = clean_input($input['full_name']);
        $phone = clean_input($input['phone']);
        $username = clean_input($input['username']);
        $email = clean_input($input['email']);
        $address = clean_input($input['address']);
        $license_number = clean_input($input['license_number']);
        $national_id = clean_input($input['national_id']);
        
        try {
            if ($id > 0) {
                // Update (No Password Update in original code either)
                $stmt = $conn->prepare("UPDATE owners SET full_name=?, phone=?, username=?, email=?, address=?, license_number=?, national_id=? WHERE id=?");
                $stmt->bind_param("sssssssi", $full_name, $phone, $username, $email, $address, $license_number, $national_id, $id);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Owner updated']);
                } else throw new Exception($stmt->error);
            } else {
                // Insert
                $password = password_hash($input['password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO owners (full_name, phone, username, email, password, address, license_number, national_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssss", $full_name, $phone, $username, $email, $password, $address, $license_number, $national_id);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Owner added']);
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
            if ($conn->query("DELETE FROM owners WHERE id=$id")) {
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
