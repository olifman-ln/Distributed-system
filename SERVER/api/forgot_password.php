<?php
require 'cors.php';
header('Content-Type: application/json');
require '../db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = trim($input['email'] ?? '');

    if (!$email) {
        http_response_code(400);
        echo json_encode(['error' => 'Please enter your email address.']);
        exit;
    }

    // Check Admin and Owners tables
    $stmt = $conn->prepare("SELECT id, 'admin' as type FROM admin WHERE email = ? UNION SELECT id, 'owners' as type FROM owners WHERE email = ?");
    $stmt->bind_param("ss", $email, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        $table = ($user['type'] === 'admin') ? 'admin' : 'owners'; // Safer than direct usage
        
        $update = $conn->prepare("UPDATE $table SET reset_token = ?, reset_expires = ? WHERE id = ?");
        $update->bind_param("ssi", $token, $expires, $user['id']);
        
        if ($update->execute()) {
            // SIMULATION FOR DEMO PURPOSES
            // In a real app, send email here.
            $simulationLink = "reset_password.html?token=" . $token;
            
            echo json_encode([
                'success' => true, 
                'message' => 'We have sent a password reset link to your email.',
                'debug_link' => $simulationLink // Sending this for the demo functionality
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Something went wrong. Please try again.']);
        }
    } else {
        // Security: Don't reveal if email exists, but mostly standard practice is generic message.
        // For this app's existing logic, we pretend success.
        echo json_encode([
            'success' => true, 
            'message' => 'If an account exists with this email, you will receive a reset link.'
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
?>
