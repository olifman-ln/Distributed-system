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

// Initialize variables
$todayIncidents = 0;
$todayViolations = 0;
$onlineNodes = 0;
$totalNodes = 0;
$totalVehicles = 0;
$recentIncidents = [];
$recentViolations = [];
$trafficVolume = [];
$violationsByNode = [];

try {
    $today = date('Y-m-d');

    // Today's incidents
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM incidents WHERE DATE(reported_at) = ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $todayIncidents = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // Today's violations
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM violations WHERE DATE(violation_time) = ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $todayViolations = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // Nodes
    $totalNodes = $conn->query("SELECT COUNT(*) AS total FROM nodes")->fetch_assoc()['total'] ?? 0;
    $onlineNodes = $conn->query("SELECT COUNT(*) AS online FROM nodes WHERE status='online'")->fetch_assoc()['online'] ?? 0;

    // Vehicles
    $totalVehicles = $conn->query("SELECT COUNT(*) AS total FROM vehicles")->fetch_assoc()['total'] ?? 0;

    // Recent incidents
    $recentIncidents = $conn->query("
        SELECT i.*, n.name AS node_name 
        FROM incidents i 
        LEFT JOIN nodes n ON i.node_id = n.id 
        ORDER BY reported_at DESC LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);

    // Recent violations
    $recentViolations = $conn->query("
        SELECT v.*, n.name AS node_name 
        FROM violations v 
        LEFT JOIN nodes n ON v.node_id = n.id 
        ORDER BY violation_time DESC LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);

    // Traffic volume per hour
    // Initialize all 24 hours to 0
    $hourlyData = array_fill(0, 24, 0);
    
    $stmt = $conn->prepare("
        SELECT HOUR(violation_time) AS hour, COUNT(*) AS count
        FROM violations
        WHERE DATE(violation_time) = ?
        GROUP BY HOUR(violation_time)
    ");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $hourlyData[(int)$row['hour']] = (int)$row['count'];
    }

    // Violations by node
    $violationsByNode = $conn->query("
        SELECT n.name AS node_name, COUNT(v.id) AS count
        FROM violations v
        LEFT JOIN nodes n ON v.node_id = n.id
        GROUP BY v.node_id
    ")->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'stats' => [
            'todayIncidents' => $todayIncidents,
            'todayViolations' => $todayViolations,
            'onlineNodes' => $onlineNodes,
            'totalNodes' => $totalNodes,
            'totalVehicles' => $totalVehicles
        ],
        'charts' => [
            'trafficVolume' => $hourlyData,
            'violationsByNodeLabels' => array_column($violationsByNode, 'node_name'),
            'violationsByNodeData' => array_column($violationsByNode, 'count')
        ],
        'lists' => [
            'recentIncidents' => $recentIncidents,
            'recentViolations' => $recentViolations
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
