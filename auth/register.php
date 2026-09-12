<?php
/**
 * ==========================================================
 * CREATE ACCOUNT - for Bugando staff
 * Bugando Medical Centre - ICT HelpDesk System
 * ==========================================================
 * Staff can create an account, sign in, and report ICT service issues.
 *
 * Admin, HelpDesk, and Technician accounts are created by an Admin.
 * Public registration assigns the staff role.
 */

require_once '../config/db.php';
require_once '../includes/functions.php';
start_session_safe();

// An authenticated user does not need to create another account.
if (isset($_SESSION['user_id'])) {
    redirect_to_dashboard($_SESSION['role']);
}

$error   = '';
$success = '';

// Load departments for the registration form.
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name     = clean_input($_POST['full_name'] ?? '');
    $username      = clean_input($_POST['username'] ?? '');
    $email         = clean_input($_POST['email'] ?? '');
    $department_id = clean_input($_POST['department_id'] ?? '');
    $password      = $_POST['password'] ?? '';
    $confirm       = $_POST['confirm_password'] ?? '';

    // Validate submitted account details.
    if ($full_name === '' || $username === '' || $password === '') {
        $error = 'Please enter your name, username, and password.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must contain at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Password and confirmation do not match.';
    } else {
        // Prevent duplicate usernames.
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);

        if ($check->fetch()) {
            $error = 'That username is already in use. Please choose another.';
        } else {
            // Store the password as a secure one-way hash.
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Public registration only allows the staff role.
            $stmt = $pdo->prepare(
                "INSERT INTO users (full_name, username, email, password_hash, role, department_id)
                 VALUES (?, ?, ?, ?, 'staff', ?)"
            );
            $stmt->execute([
                $full_name,
                $username,
                $email ?: null,
                $password_hash,
                $department_id ?: null,
            ]);

            $newUserId = $pdo->lastInsertId();
            log_audit($pdo, $newUserId, 'ACCOUNT_CREATED', 'Staff self-registered');

            $success = 'Your account has been created. You can now sign in.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create an Account | Bugando ICT HelpDesk</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="login-wrapper">
    <div class="login-card" style="max-width: 460px;">

        <img src="../assets/img/bugando-logo.svg" alt="Bugando Medical Centre Logo" class="login-logo">
        <div class="login-title">Create an Account</div>
        <div class="login-subtitle">For Bugando Medical Centre staff reporting ICT issues</div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><?= clean_input($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success py-2 small">
                <?= clean_input($success) ?>
                <a href="../index.php" class="fw-semibold">Nenda Login &rarr;</a>
            </div>
        <?php else: ?>

        <form action="register.php" method="POST" autocomplete="off">
            <div class="mb-3">
                <label class="form-label small fw-semibold">Full name</label>
                <input type="text" name="full_name" class="form-control"
                       value="<?= clean_input($_POST['full_name'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Username</label>
                <input type="text" name="username" class="form-control"
                       value="<?= clean_input($_POST['username'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Email (optional)</label>
                <input type="email" name="email" class="form-control"
                       value="<?= clean_input($_POST['email'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Department</label>
                <select name="department_id" class="form-select">
                    <option value="">-- Select department --</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>"><?= clean_input($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Password</label>
                <input type="password" name="password" class="form-control" required minlength="6">
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" required minlength="6">
            </div>

            <button type="submit" class="btn btn-bmc w-100 py-2">Create account</button>
        </form>

        <?php endif; ?>

        <p class="text-center text-muted small mt-3 mb-0">
            Already have an account? <a href="../index.php">Sign in</a>
        </p>
    </div>
</div>

</body>
</html>