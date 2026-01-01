<?php
require 'cors.php';
header('Content-Type: application/json');
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$owner_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Sidebar & Context Data
$stmt = $conn->prepare("
    SELECT COUNT(*) AS unpaid 
    FROM violations v 
    JOIN vehicles ve ON v.vehicle_id = ve.id 
    WHERE ve.owner_id = ? AND v.status != 'paid'
");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$unpaidViolations = $stmt->get_result()->fetch_assoc()['unpaid'] ?? 0;

if ($method === 'GET') {
    // Fetch Profile
    $stmt = $conn->prepare("SELECT id, username, full_name, email, phone, address, profile_picture FROM owners WHERE id = ?");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $owner = $stmt->get_result()->fetch_assoc();

    echo json_encode([
        'user' => [
            'name' => $owner['full_name'],
            'role' => 'Vehicle Owner',
            'avatar' => $owner['profile_picture']
        ],
        'stats' => ['unpaidViolations' => $unpaidViolations],
        'profile' => $owner
    ]);

} elseif ($method === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        
        // Handle Avatar
        $avatarPath = null;
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_picture']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {
                $newFilename = "owner_" . $owner_id . "_" . time() . "." . $ext;
                $uploadDir = dirname(__DIR__) . '/uploads/avatars/';
                if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
                
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadDir . $newFilename)) {
                    $avatarPath = "uploads/avatars/" . $newFilename;
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

        if ($avatarPath) {
            $stmt = $conn->prepare("UPDATE owners SET full_name=?, phone=?, email=?, address=?, profile_picture=? WHERE id=?");
            $stmt->bind_param("sssssi", $full_name, $phone, $email, $address, $avatarPath, $owner_id);
        } else {
            $stmt = $conn->prepare("UPDATE owners SET full_name=?, phone=?, email=?, address=? WHERE id=?");
            $stmt->bind_param("ssssi", $full_name, $phone, $email, $address, $owner_id);
        }

        if ($stmt->execute()) {
            $_SESSION['full_name'] = $full_name; // update session
            echo json_encode(['success' => true, 'message' => 'Profile updated']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database update failed']);
        }

    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        $stmt = $conn->prepare("SELECT password FROM owners WHERE id = ?");
        $stmt->bind_param("i", $owner_id);
        $stmt->execute();
        $stored_hash = $stmt->get_result()->fetch_assoc()['password'];

        if (!password_verify($current, $stored_hash)) {
            echo json_encode(['success' => false, 'error' => 'Current password incorrect']);
        } elseif ($new !== $confirm) {
            echo json_encode(['success' => false, 'error' => 'New passwords do not match']);
        } else {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE owners SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $owner_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Password changed']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to change password']);
            }
        }
    }
}
?>
