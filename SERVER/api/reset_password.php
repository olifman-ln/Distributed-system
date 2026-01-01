<?php
require 'cors.php';
header('Content-Type: application/json');
require '../db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = trim($input['token'] ?? '');
    $password = trim($input['password'] ?? '');
    
    if (!$token) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid Request']);
        exit;
    }

    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['error' => 'Password must be at least 6 characters long.']);
        exit;
    }

    // Validate Token
    $stmt = $conn->prepare("SELECT id, 'admin' as type FROM admin WHERE reset_token = ? AND reset_expires > NOW() UNION SELECT id, 'owners' as type FROM owners WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->bind_param("ss", $token, $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $table = ($user['type'] === 'admin') ? 'admin' : 'owners';
        
        $update = $conn->prepare("UPDATE $table SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $update->bind_param("si", $hashed, $user['id']);
        
        if ($update->execute()) {
            echo json_encode(['success' => true, 'message' => 'Password successfully reset!']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update password.']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or expired password reset link.']);
    }

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
?>
