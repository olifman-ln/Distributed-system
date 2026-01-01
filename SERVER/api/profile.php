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

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Helper for cleaning input
function clean($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

if ($method === 'GET') {
    // Fetch User Data
    $stmt = $conn->prepare("SELECT id, username, email, role, profile_picture FROM admin WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    echo json_encode($user);

} elseif ($method === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_info') {
        // Update Info & Avatar
        $username = clean($_POST['username']);
        $email = clean($_POST['email']);
        
        // Check uniqueness
        $check = $conn->prepare("SELECT id FROM admin WHERE (username = ? OR email = ?) AND id != ?");
        $check->bind_param("ssi", $username, $email, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'error' => 'Username or Email already taken']);
            exit;
        }

        // Handle Avatar Upload
        $avatarPath = '';
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_picture']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {
                $newFilename = "admin_" . $user_id . "_" . time() . "." . $ext;
                $uploadDir = dirname(__DIR__) . '/uploads/avatars/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadDir . $newFilename)) {
                    $avatarPath = "uploads/avatars/" . $newFilename; // Path for DB
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file. Check folder permissions.']);
                    exit;
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid file type.']);
                exit;
            }
        } elseif (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] != 4) {
             echo json_encode(['success' => false, 'error' => 'Upload error code: ' . $_FILES['profile_picture']['error']]);
             exit;
        }

        // Update Query
        if ($avatarPath) {
            $update = $conn->prepare("UPDATE admin SET username = ?, email = ?, profile_picture = ? WHERE id = ?");
            $update->bind_param("sssi", $username, $email, $avatarPath, $user_id);
        } else {
            $update = $conn->prepare("UPDATE admin SET username = ?, email = ? WHERE id = ?");
            $update->bind_param("ssi", $username, $email, $user_id);
        }

        if ($update->execute()) {
            $_SESSION['username'] = $username;
            echo json_encode(['success' => true, 'message' => 'Profile updated']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database update failed']);
        }

    } elseif ($action === 'change_password') {
        // Change Password
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        // Get current hash
        $stmt = $conn->prepare("SELECT password FROM admin WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stored_hash = $stmt->get_result()->fetch_assoc()['password'];

        if (!password_verify($current, $stored_hash)) {
            echo json_encode(['success' => false, 'error' => 'Current password incorrect']);
        } elseif ($new !== $confirm) {
            echo json_encode(['success' => false, 'error' => 'New passwords do not match']);
        } elseif (strlen($new) < 6) {
            echo json_encode(['success' => false, 'error' => 'Password too short (min 6 chars)']);
        } else {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
            $update->bind_param("si", $hashed, $user_id);
            if ($update->execute()) {
                echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update password']);
            }
        }
    }
}
?>
