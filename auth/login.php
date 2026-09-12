<?php
/**
 * ==========================================================
 * LOGIN PROCESS
 * Bugando Medical Centre - ICT HelpDesk System
 * ==========================================================
 * Receives the login form, verifies credentials, and redirects
 * the user to the dashboard that matches their role.
 */

require_once '../config/db.php';
require_once '../includes/functions.php';
start_session_safe();

// Only POST requests are accepted.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('index.php'));
    exit();
}

$username = clean_input($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Basic validation.
if ($username === '' || $password === '') {
    $_SESSION['login_error'] = 'Please enter your username and password.';
    header('Location: ' . app_url('index.php'));
    exit();
}

// Find the user by username.
$stmt = $pdo->prepare(
    "SELECT id, full_name, username, password_hash, role, status 
     FROM users WHERE username = ? LIMIT 1"
);
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Confirm that the account is active and the password is valid.
if ($user && $user['status'] === 'active' && password_verify($password, $user['password_hash'])) {

    // Login succeeded; create the session.
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role']      = $user['role'];

    // Record the login for accountability.
    log_audit($pdo, $user['id'], 'LOGIN', 'User logged in successfully');

    // Redirect each role to its dedicated dashboard.
    redirect_to_dashboard($user['role']);

} else {
    // Use a generic message so the system does not reveal which field failed.
    $_SESSION['login_error'] = 'The username or password is incorrect.';
    header('Location: ' . app_url('index.php'));
    exit();
}
