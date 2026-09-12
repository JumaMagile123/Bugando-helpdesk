<?php
/**
 * ==========================================================
 * HOMEPAGE YA UMMA NA LOGIN
 * Bugando Medical Centre - ICT Service Request & HelpDesk System
 * ==========================================================
 * Kila mtumiaji huanzia hapa. Baada ya login, mtumiaji hupelekwa
 * kwenye dashboard inayolingana na role yake.
 */

require_once 'includes/functions.php';
start_session_safe();

// Mpeleke mtumiaji aliyeingia kwenye dashboard yake.
if (isset($_SESSION['user_id'])) {
    redirect_to_dashboard($_SESSION['role']);
}

// Onyesha ujumbe wa kosa la login kama upo.
$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | Bugando ICT HelpDesk</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="home-page">

<div class="login-wrapper">
    <div class="home-shell">
        <section class="intro-panel">
            <div class="brand-row">
                <img src="assets/img/bugando-logo.svg" alt="Bugando Medical Centre Logo" class="brand-logo">
                <div>
                    <div class="eyebrow">BUGANDO MEDICAL CENTRE</div>
                    <div class="brand-name">ICT HelpDesk</div>
                </div>
            </div>
            <div class="intro-copy">
                <span class="section-kicker">SERVICE REQUEST SYSTEM</span>
                <h1>Fast ICT support for every department.</h1>
                <p>A simple way to submit, assign, and track ICT service requests at Bugando Medical Centre.</p>
            </div>
            <div class="feature-grid">
                <div><i class="bi bi-ticket-perforated"></i><strong>Submit an issue</strong><span>Report a service problem</span></div>
                <div><i class="bi bi-diagram-3"></i><strong>Right assignment</strong><span>Route it to the right specialist</span></div>
                <div><i class="bi bi-graph-up-arrow"></i><strong>Track progress</strong><span>Follow every step</span></div>
            </div>
            <div class="audience-note"><i class="bi bi-shield-check"></i> Role-based access, accountability, and service tracking.</div>
        </section>

        <div class="login-card">

        <!-- Bugando logo -->
        <div class="login-title">Welcome back</div>
        <div class="login-subtitle">Sign in to the ICT service request system</div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><?= clean_input($error) ?></div>
        <?php endif; ?>

        <form action="auth/login.php" method="POST" autocomplete="off">
            <div class="mb-3">
                <label class="form-label small fw-semibold">Username</label>
                <input type="text" name="username" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-bmc w-100 py-2"><i class="bi bi-box-arrow-in-right me-2"></i>Sign in</button>
        </form>

        <p class="text-center small mt-3 mb-1">
            Do not have an account? <a href="auth/register.php" class="fw-semibold">Create an account</a>
        </p>

        <p class="text-center text-muted small mb-0">
            &copy; <?= date('Y') ?> Bugando Medical Centre &mdash; ICT Department
        </p>
    </div>
    </div>
</div>

</body>
</html>