<?php
require 'cors.php';
header('Content-Type: application/json');
session_start();

// Clear all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

echo json_encode(['success' => true, 'message' => 'Successfully logged out.']);
exit;
?>
