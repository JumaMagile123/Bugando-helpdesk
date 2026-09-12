<?php
/**
 * LOGOUT
 * Ends the session and returns the user to the public homepage.
 */
require_once '../includes/functions.php';
start_session_safe();

$user_id = $_SESSION['user_id'] ?? null;

// Clear the complete session before returning to the login homepage.
$_SESSION = [];
session_destroy();

header('Location: ' . app_url('index.php'));
exit();
