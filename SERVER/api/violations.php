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
        // Fetch Options (Vehicles, Cameras)
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

        // Fetch Violations + Stats
        // Build Filter Query
        $where = ["1=1"];
        $params = [];
        $types = "";

        if (!empty($_GET['type'])) {
            $where[] = "v.violation_type = ?";
            $params[] = $_GET['type']; $types .= "s";
        }
        if (!empty($_GET['status'])) {
            $where[] = "v.status = ?";
            $params[] = $_GET['status']; $types .= "s";
        }
        if (!empty($_GET['from'])) {
            $where[] = "DATE(v.violation_time) >= ?";
            $params[] = $_GET['from']; $types .= "s";
        }
        if (!empty($_GET['to'])) {
            $where[] = "DATE(v.violation_time) <= ?";
            $params[] = $_GET['to']; $types .= "s";
        }
        
        // Count Stats (Global, not filtered for now, or maybe filtered? Original code calculated on fetched limit 100 which is wrong for totals. Let's do real stats)
        $stats = [
            'total' => $conn->query("SELECT COUNT(*) FROM violations")->fetch_row()[0],
            'pending' => $conn->query("SELECT COUNT(*) FROM violations WHERE status='pending'")->fetch_row()[0],
            'confirmed' => $conn->query("SELECT COUNT(*) FROM violations WHERE status='confirmed'")->fetch_row()[0],
            'speeding' => $conn->query("SELECT COUNT(*) FROM violations WHERE violation_type='speeding'")->fetch_row()[0]
        ];

        // Fetch List
        $colName = 'camera_name'; // Reuse logic if possible or hardcode if known. Assuming reused logic above is for options only.
        // Let's re-run column check quickly or assume camera_name if likely.
        // To be safe, let's just use 'camera_name' as in original
        // Wait, original file did a check dynamically. I should do that too.
        $camCheck = $conn->query("SHOW COLUMNS FROM cameras");
        $camCol = 'camera_name';
        while($r = $camCheck->fetch_assoc()) {
             if(in_array(strtolower($r['Field']), ['camera_name','name'])) { $camCol = $r['Field']; break; }
        }

        $sql = "
            SELECT 
                v.*,
                ve.plate_number,
                o.full_name AS owner_name,
                c.$camCol AS camera_name,
                n.name AS node_name,
                TIMESTAMPDIFF(HOUR, v.violation_time, NOW()) as hours_ago
            FROM violations v
            LEFT JOIN vehicles ve ON v.vehicle_id = ve.id
            LEFT JOIN owners o ON ve.owner_id = o.id
            LEFT JOIN cameras c ON v.camera_id = c.id
            LEFT JOIN nodes n ON v.node_id = n.id
            WHERE " . implode(" AND ", $where) . "
            ORDER BY v.violation_time DESC
            LIMIT 100
        ";
        
        $stmt = $conn->prepare($sql);
        if(!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $violations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['stats' => $stats, 'violations' => $violations]);
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);

        // Confirm/Reject Actions
        if ($action === 'confirm' || $action === 'reject') {
            $v_id = intval($input['id']);
            if ($action === 'reject') {
                $stmt = $conn->prepare("UPDATE violations SET status = 'rejected' WHERE id = ?");
                $stmt->bind_param("i", $v_id);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Violation dismissed']);
                } else {
                    http_response_code(500); echo json_encode(['error' => 'Error dismissing']);
                }
            } elseif ($action === 'confirm') {
                try {
                    $v_stmt = $conn->prepare("SELECT v.*, ve.owner_id FROM violations v JOIN vehicles ve ON v.vehicle_id = ve.id WHERE v.id = ?");
                    $v_stmt->bind_param("i", $v_id);
                    $v_stmt->execute();
                    $v_data = $v_stmt->get_result()->fetch_assoc();

                    if (!$v_data) throw new Exception("Violation not found");
                    if ($v_data['status'] === 'confirmed') throw new Exception("Already confirmed");

                    $conn->begin_transaction();
                    
                    // Update status
                    $conn->query("UPDATE violations SET status='confirmed' WHERE id=$v_id");

                    // Calculate Fine
                    $s_res = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'fine_base_amount'");
                    $base_fine = $s_res->num_rows > 0 ? ($s_res->fetch_assoc()['setting_value']) : 500;
                    
                    $amount = $base_fine;
                    if ($v_data['violation_type'] === 'speeding' && $v_data['speed_actual'] > $v_data['speed_limit']) {
                        $over = $v_data['speed_actual'] - $v_data['speed_limit'];
                        $amount += ($over * 10);
                    }

                    // Create Payment
                    $pay_stmt = $conn->prepare("INSERT INTO payments (owner_id, violation_id, payment_type, amount, penalty_reason, payment_status) VALUES (?, ?, 'violation', ?, ?, 'pending')");
                    $reason = "Fine for " . str_replace('_', ' ', $v_data['violation_type']);
                    $pay_stmt->bind_param("iids", $v_data['owner_id'], $v_id, $amount, $reason);
                    $pay_stmt->execute();

                    // Notification
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (owner_id, title, message, reference_id) VALUES (?, ?, ?, ?)");
                    $title = "Violation Confirmed - Payment Required";
                    $msg = "Violation Confirmed. Type: " . $v_data['violation_type'] . ". Fine: " . $amount . " ETB.";
                    $notif_stmt->bind_param("issi", $v_data['owner_id'], $title, $msg, $v_id);
                    $notif_stmt->execute();

                    $conn->commit();
                    echo json_encode(['success' => true, 'message' => 'Violation confirmed and fine issued']);

                } catch (Exception $e) {
                    $conn->rollback();
                    http_response_code(500);
                    echo json_encode(['error' => $e->getMessage()]);
                }
            }
            exit;
        }

        // Add Violation
        $vehicle_id = intval($input['vehicle_id']);
        $camera_id = intval($input['camera_id']);
        $node_id = intval($input['node_id']);
        $type = clean_input($input['violation_type']);
        $speed_actual = !empty($input['speed_actual']) ? intval($input['speed_actual']) : null;
        $speed_limit = !empty($input['speed_limit']) ? intval($input['speed_limit']) : null;

        try {
            $stmt = $conn->prepare("INSERT INTO violations (vehicle_id, camera_id, node_id, violation_type, speed_actual, speed_limit) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiisii", $vehicle_id, $camera_id, $node_id, $type, $speed_actual, $speed_limit);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Violation recorded']);
            } else {
                throw new Exception($stmt->error);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}
?>
