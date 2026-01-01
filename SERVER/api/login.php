<?php
require 'cors.php';
header('Content-Type: application/json');
session_start();
require_once '../db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = trim($input['password'] ?? '');

    if (empty($username) || empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'All fields are required.']);
        exit;
    }

    // 1. Check Admin Table
    $stmt = $conn->prepare("SELECT id, password, role, username FROM admin WHERE username = ? AND email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $user['role']; // 'admin' or 'superadmin'
            $_SESSION['full_name'] = $user['username']; // Admin uses username for display name
            
            echo json_encode(['success' => true, 'redirect' => '../index.html']);
            exit;
        }
    }

    // 2. Check Owners Table
    $stmt = $conn->prepare("SELECT id, password, full_name FROM owners WHERE username = ? AND email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $owner = $result->fetch_assoc();
        if (password_verify($password, $owner['password'])) {
            $_SESSION['user_id'] = $owner['id'];
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'owner';
            $_SESSION['full_name'] = $owner['full_name'];

            echo json_encode(['success' => true, 'redirect' => '../owners/dashboard.html']);
            exit;
        }
    }

    // Failed
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials. Please try again.']); // Keep generic for security
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
?>
