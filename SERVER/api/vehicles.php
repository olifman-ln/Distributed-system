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
        // Fetch specific vehicle for Edit
        if (isset($_GET['id'])) {
             $id = intval($_GET['id']);
             $stmt = $conn->prepare("SELECT * FROM vehicles WHERE id=?");
             $stmt->bind_param("i", $id);
             $stmt->execute();
             $result = $stmt->get_result();
             echo json_encode($result->fetch_assoc());
             break;
        }

        // Fetch Options (Types, Owners)
        if ($action === 'options') {
             $owners = $conn->query("SELECT id, full_name FROM owners ORDER BY full_name ASC")->fetch_all(MYSQLI_ASSOC);
             $types = $conn->query("SELECT id, type_name FROM vehicle_types ORDER BY type_name ASC")->fetch_all(MYSQLI_ASSOC);
             echo json_encode(['owners' => $owners, 'types' => $types]);
             break;
        }

        // Fetch All Vehicles
        $sql = "
            SELECT v.*, vt.type_name, o.full_name AS owner_name
            FROM vehicles v
            LEFT JOIN vehicle_types vt ON v.type_id = vt.id
            LEFT JOIN owners o ON v.owner_id = o.id
            ORDER BY v.registered_at DESC
        ";
        $result = $conn->query($sql);
        $vehicles = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode($vehicles);
        break;

    case 'POST':
        // Handle Add/Edit
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $plate_number = clean_input($_POST['plate_number']);
        $type_id = intval($_POST['type_id']);
        $model = clean_input($_POST['model']);
        $manufacture_year = intval($_POST['manufacture_year'] ?? 0);
        $color = clean_input($_POST['color']);
        $owner_id = intval($_POST['owner_id']);

        if(empty($plate_number) || empty($type_id) || empty($owner_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
           exit;
        }

        // Image Upload
        $vehicle_image = null;
        if (isset($_FILES['vehicle_image']) && $_FILES['vehicle_image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['vehicle_image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $newFilename = "vec_" . time() . "_" . preg_replace('/[^a-zA-Z0-9]/', '', $plate_number) . "." . $ext;
                $uploadDir = '../uploads/vehicles/'; // Relative to API folder
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Save path for DB (relative to root)
                $dbPath = "uploads/vehicles/" . $newFilename; 
                
                if (move_uploaded_file($_FILES['vehicle_image']['tmp_name'], $uploadDir . $newFilename)) {
                    $vehicle_image = $dbPath;
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to move vehicle image. Check folder permissions.']);
                    exit;
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid vehicle image type.']);
                exit;
            }
        } elseif (isset($_FILES['vehicle_image']) && $_FILES['vehicle_image']['error'] != 4) {
            http_response_code(400);
            echo json_encode(['error' => 'Vehicle image upload error code: ' . $_FILES['vehicle_image']['error']]);
            exit;
        }

        try {
            if ($id > 0) { // Update
                if ($vehicle_image) {
                    $stmt = $conn->prepare("UPDATE vehicles SET plate_number=?, type_id=?, model=?, manufacture_year=?, color=?, owner_id=?, vehicle_image=? WHERE id=?");
                    $stmt->bind_param("sisisisi", $plate_number, $type_id, $model, $manufacture_year, $color, $owner_id, $vehicle_image, $id);
                } else {
                    $stmt = $conn->prepare("UPDATE vehicles SET plate_number=?, type_id=?, model=?, manufacture_year=?, color=?, owner_id=? WHERE id=?");
                    $stmt->bind_param("sisisii", $plate_number, $type_id, $model, $manufacture_year, $color, $owner_id, $id);
                }
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Vehicle updated']);
                } else {
                    throw new Exception($stmt->error);
                }
            } else { // Insert
                // Check duplicate
                $check = $conn->prepare("SELECT id FROM vehicles WHERE plate_number = ?");
                $check->bind_param("s", $plate_number);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Plate number already exists']);
                    exit;
                }

                $stmt = $conn->prepare("INSERT INTO vehicles (plate_number, type_id, model, manufacture_year, color, owner_id, vehicle_image) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sisisis", $plate_number, $type_id, $model, $manufacture_year, $color, $owner_id, $vehicle_image);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Vehicle added']);
                } else {
                    throw new Exception($stmt->error);
                }
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if($id > 0) {
            $stmt = $conn->prepare("DELETE FROM vehicles WHERE id=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Vehicle deleted']);
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
}
?>
