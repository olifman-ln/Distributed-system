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
        // Options
        if ($action === 'options') {
            $vehicles = $conn->query("SELECT ve.id, ve.plate_number, o.full_name FROM vehicles ve JOIN owners o ON ve.owner_id = o.id ORDER BY ve.plate_number")->fetch_all(MYSQLI_ASSOC);
            
             // Camera Check
            $colName = 'camera_name';
            $check = $conn->query("SHOW COLUMNS FROM cameras");
            while($r = $check->fetch_assoc()) {
                if(in_array(strtolower($r['Field']), ['title','name','camera_name'])) { $colName = $r['Field']; break; }
            }
            $cameras = $conn->query("SELECT c.id, c.$colName as name, n.id as node_id, n.name as node_name FROM cameras c JOIN nodes n ON c.node_id = n.id WHERE c.status = 'active'")->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['vehicles' => $vehicles, 'cameras' => $cameras]);
            exit;
        }

        // List & Stats
        $where = ["1=1"];
        $params = [];
        $types = "";

        if(!empty($_GET['severity'])) { $where[] = "severity = ?"; $params[] = $_GET['severity']; $types .= "s"; }
        if(!empty($_GET['type'])) { $where[] = "incident_type = ?"; $params[] = $_GET['type']; $types .= "s"; }
        
        $sql = "
            SELECT i.*, ve.plate_number, o.full_name AS owner_name, 
                   c.camera_name, n.name AS node_name,
                   TIMESTAMPDIFF(HOUR, i.reported_at, NOW()) as hours_ago
            FROM incidents i
            LEFT JOIN vehicles ve ON i.vehicle_id = ve.id
            LEFT JOIN owners o ON ve.owner_id = o.id
            LEFT JOIN cameras c ON i.camera_id = c.id
            LEFT JOIN nodes n ON i.node_id = n.id
            WHERE " . implode(" AND ", $where) . "
            ORDER BY i.reported_at DESC LIMIT 100
        ";

       
        $colName = 'camera_name';
        $check = $conn->query("SHOW COLUMNS FROM cameras");
        while($r = $check->fetch_assoc()) { if(in_array(strtolower($r['Field']), ['title','name','camera_name'])) { $colName = $r['Field']; break; } }
        
        $sql = str_replace('c.camera_name', "c.$colName as camera_name", $sql);

        $stmt = $conn->prepare($sql);
        if(!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $incidents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Stats
        $stats = [
            'total' => $conn->query("SELECT COUNT(*) FROM incidents")->fetch_row()[0],
            'low' => $conn->query("SELECT COUNT(*) FROM incidents WHERE severity='low'")->fetch_row()[0],
            'moderate' => $conn->query("SELECT COUNT(*) FROM incidents WHERE severity='moderate'")->fetch_row()[0],
            'high' => $conn->query("SELECT COUNT(*) FROM incidents WHERE severity='high'")->fetch_row()[0]
        ];

        echo json_encode(['incidents' => $incidents, 'stats' => $stats]);
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        
        $vehicle_id = intval($input['vehicle_id']);
        $camera_id = intval($input['camera_id']);
        $node_id = intval($input['node_id']);
        $incident_type = clean_input($input['type']);
        $description = clean_input($input['description']);
        $severity = clean_input($input['severity']);
        $id = isset($input['incident_id']) ? intval($input['incident_id']) : 0;

        try {
            if ($id > 0) { // Update
                $stmt = $conn->prepare("UPDATE incidents SET vehicle_id=?, camera_id=?, node_id=?, incident_type=?, description=?, severity=? WHERE id=?");
                $stmt->bind_param("iiisssi", $vehicle_id, $camera_id, $node_id, $incident_type, $description, $severity, $id);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Accident updated']);
                } else throw new Exception($stmt->error);
            } else { // Insert
                $conn->begin_transaction();

                $stmt = $conn->prepare("INSERT INTO incidents (vehicle_id, camera_id, node_id, incident_type, description, severity) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iiisss", $vehicle_id, $camera_id, $node_id, $incident_type, $description, $severity);
                $stmt->execute();
                $incident_id = $conn->insert_id;

                // Owner Lookup
                $owner_res = $conn->query("SELECT owner_id FROM vehicles WHERE id=$vehicle_id");
                $owner_data = $owner_res->fetch_assoc();

                if ($owner_data && $owner_data['owner_id']) {
                    $oid = $owner_data['owner_id'];
                    $fine = 500;
                    if ($severity == 'critical') $fine = 5000;
                    elseif ($severity == 'high') $fine = 3000;
                    elseif ($severity == 'medium') $fine = 1500;
                    elseif ($severity == 'low') $fine = 800;

                    $pay = $conn->prepare("INSERT INTO payments (owner_id, incident_id, payment_type, amount, penalty_reason, payment_status) VALUES (?, ?, 'accident', ?, ?, 'pending')");
                    $reason = "Fine for $incident_type ($severity severity)";
                    $pay->bind_param("iids", $oid, $incident_id, $fine, $reason);
                    $pay->execute();

                    $notif = $conn->prepare("INSERT INTO notifications (owner_id, title, message, reference_id) VALUES (?, 'Accident Report - Payment Required', ?, ?)");
                    $msg = "Accident reported. Severity: $severity. Fine: $fine ETB.";
                    $notif->bind_param("isi", $oid, $msg, $incident_id);
                    $notif->execute();
                }

                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Accident reported and processed']);
            }
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id > 0) {
            if ($conn->query("DELETE FROM incidents WHERE id=$id")) {
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
