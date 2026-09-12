<?php
/**
 * ADMIN DASHBOARD
 * Visible only to users with the admin role.
 */
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/role_guidance.php';
require_role('admin');
$guidance = role_guidance('admin');
$total_tickets = ticket_count($pdo);
$pending_tickets = ticket_count($pdo, "status = 'pending'");
$active_tickets = ticket_count($pdo, "status IN ('assigned', 'in_progress', 'escalated')");
$resolved_tickets = ticket_count($pdo, "status IN ('resolved', 'closed')");
$escalated_tickets = ticket_count($pdo, "status = 'escalated'");
$users = $pdo->query("SELECT u.id, u.full_name, u.username, u.role, u.status, d.name AS department_name FROM users u LEFT JOIN departments d ON d.id = u.department_id ORDER BY u.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$report_categories = $pdo->query("SELECT c.name, COUNT(t.id) AS total FROM categories c LEFT JOIN tickets t ON t.category_id = c.id GROUP BY c.id, c.name ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC);
$workload = $pdo->query("SELECT u.full_name, COUNT(t.id) AS total, SUM(t.status IN ('assigned', 'in_progress', 'escalated')) AS active FROM users u LEFT JOIN tickets t ON t.assigned_to = u.id WHERE u.role = 'technician' GROUP BY u.id, u.full_name ORDER BY active DESC, total DESC")->fetchAll(PDO::FETCH_ASSOC);
$audit = $pdo->query("SELECT a.action, a.details, a.created_at, u.full_name FROM audit_trail a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
$daily_report = $pdo->query("SELECT DATE(created_at) AS report_date, COUNT(*) AS total, SUM(status IN ('resolved', 'closed')) AS completed FROM tickets GROUP BY DATE(created_at) ORDER BY report_date DESC LIMIT 7")->fetchAll(PDO::FETCH_ASSOC);
$department_report = $pdo->query("SELECT COALESCE(d.name, 'Unassigned') AS department_name, COALESCE(t.location, 'Not specified') AS location, COUNT(t.id) AS total FROM tickets t LEFT JOIN departments d ON d.id = t.department_id GROUP BY d.name, t.location ORDER BY total DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
$performance_report = $pdo->query("SELECT u.full_name, COUNT(t.id) AS assigned, SUM(t.status IN ('resolved', 'closed')) AS resolved, ROUND(AVG(CASE WHEN t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at) END), 1) AS avg_resolution_hours FROM users u LEFT JOIN tickets t ON t.assigned_to = u.id WHERE u.role = 'technician' GROUP BY u.id, u.full_name ORDER BY resolved DESC")->fetchAll(PDO::FETCH_ASSOC);
$time_report = $pdo->query("SELECT ROUND(AVG(CASE WHEN assigned_to IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, updated_at) END), 1) AS avg_response_minutes, ROUND(AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) END), 1) AS avg_resolution_hours FROM tickets")->fetch(PDO::FETCH_ASSOC);

// Admin actions: user management and department creation.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_department') {
        $name = clean_input($_POST['name'] ?? '');
        if ($name !== '') { $stmt = $pdo->prepare("INSERT INTO departments (name) VALUES (?)"); $stmt->execute([$name]); log_audit($pdo, $_SESSION['user_id'], 'DEPARTMENT_CREATED', $name); }
    } elseif ($action === 'toggle_user') {
        $user_id = (int) ($_POST['user_id'] ?? 0);
        if ($user_id !== (int) $_SESSION['user_id']) { $stmt = $pdo->prepare("UPDATE users SET status = CASE WHEN status = 'active' THEN 'disabled' ELSE 'active' END WHERE id = ?"); $stmt->execute([$user_id]); log_audit($pdo, $_SESSION['user_id'], 'USER_STATUS_CHANGED', 'User ID: ' . $user_id); }
    } elseif ($action === 'add_user') {
        $full_name = clean_input($_POST['full_name'] ?? ''); $username = clean_input($_POST['username'] ?? ''); $email = clean_input($_POST['email'] ?? ''); $role = $_POST['role'] ?? 'staff'; $department_id = (int) ($_POST['department_id'] ?? 0); $password = $_POST['password'] ?? '';
        if ($full_name && $username && $password && in_array($role, ['admin', 'helpdesk', 'technician', 'staff'], true)) { $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, password_hash, role, department_id) VALUES (?, ?, ?, ?, ?, ?)"); $stmt->execute([$full_name, $username, $email ?: null, password_hash($password, PASSWORD_DEFAULT), $role, $department_id ?: null]); log_audit($pdo, $_SESSION['user_id'], 'USER_CREATED', $username); }
    }
    header('Location: ' . app_url('admin/dashboard.php'));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | Bugando ICT HelpDesk</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="container-fluid">
    <div class="row">

        <?php include '../includes/sidebar.php'; ?>

        <!-- Main content -->
        <main class="col p-0">

            <!-- Topbar -->
            <div class="topbar d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Admin Dashboard</h5>
                <div>
                    <span class="badge bg-primary role-badge">ADMIN</span>
                    <span class="ms-2 fw-semibold"><?= clean_input($_SESSION['full_name']) ?></span>
                </div>
            </div>

            <div class="p-4" id="system-summary">
                <p class="text-muted">Bugando Medical Centre system summary.</p>
                <div class="card panel-card mb-4 p-3"><h6><i class="bi bi-person-check me-2"></i><?= $guidance['title'] ?></h6><div class="row small"><div class="col-md-6"><strong>Anachoweza kufanya</strong><ul class="mb-0"><?php foreach ($guidance['can'] as $item): ?><li><?= clean_input($item) ?></li><?php endforeach; ?></ul></div><div class="col-md-6"><strong>Asichopaswa kufanya</strong><ul class="mb-0"><?php foreach ($guidance['cannot'] as $item): ?><li><?= clean_input($item) ?></li><?php endforeach; ?></ul></div></div></div>

                <!-- Summary cards are populated from live database data. -->
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="card stat-card p-3">
                            <div class="text-muted small">Total requests</div>
                            <div class="fs-3 fw-bold text-primary"><?= $total_tickets ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card p-3">
                            <div class="text-muted small">Pending</div>
                            <div class="fs-3 fw-bold text-warning"><?= $pending_tickets ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card p-3">
                            <div class="text-muted small">In progress</div>
                            <div class="fs-3 fw-bold text-info"><?= $active_tickets ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card p-3">
                            <div class="text-muted small">Resolved</div>
                            <div class="fs-3 fw-bold text-success"><?= $resolved_tickets ?></div>
                        </div>
                    </div>
                    <div class="col-md-3"><div class="card stat-card p-3"><div class="text-muted small">Escalated</div><div class="fs-3 fw-bold text-danger"><?= $escalated_tickets ?></div></div></div>
                </div>

                <div class="alert alert-info mt-4 small">
                    Role-based access keeps system oversight, request handling and assigned work separated by responsibility.
                </div>
                <div class="row g-4 mt-1">
                    <div class="col-lg-7" id="user-management"><div class="card panel-card p-4"><div class="d-flex justify-content-between align-items-center"><h5>User management</h5><button class="btn btn-sm btn-bmc" data-bs-toggle="collapse" data-bs-target="#add-user">Add user</button></div><div class="collapse mt-3" id="add-user"><form method="POST" class="row g-2"><input type="hidden" name="action" value="add_user"><div class="col-md-6"><input name="full_name" class="form-control form-control-sm" placeholder="Full name" required></div><div class="col-md-6"><input name="username" class="form-control form-control-sm" placeholder="Username" required></div><div class="col-md-5"><input name="email" type="email" class="form-control form-control-sm" placeholder="Email"></div><div class="col-md-3"><input name="password" type="password" class="form-control form-control-sm" placeholder="Password" required minlength="6"></div><div class="col-md-2"><select name="role" class="form-select form-select-sm"><option>staff</option><option>technician</option><option>helpdesk</option><option>admin</option></select></div><div class="col-md-2"><select name="department_id" class="form-select form-select-sm"><option value="">Department</option><?php foreach ($departments as $department): ?><option value="<?= $department['id'] ?>"><?= clean_input($department['name']) ?></option><?php endforeach; ?></select></div><div class="col-12"><button class="btn btn-sm btn-bmc">Create user</button></div></form></div><div class="table-responsive mt-3"><table class="table small align-middle"><thead><tr><th>Name</th><th>Role</th><th>Department</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($users as $user): ?><tr><td><?= clean_input($user['full_name']) ?><br><span class="text-muted">@<?= clean_input($user['username']) ?></span></td><td><?= clean_input($user['role']) ?></td><td><?= clean_input($user['department_name'] ?? 'None') ?></td><td><?= clean_input($user['status']) ?></td><td><?php if ((int) $user['id'] !== (int) $_SESSION['user_id']): ?><form method="POST"><input type="hidden" name="action" value="toggle_user"><input type="hidden" name="user_id" value="<?= $user['id'] ?>"><button class="btn btn-sm btn-outline-secondary">Toggle</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
                    <div class="col-lg-5" id="departments"><div class="card panel-card p-4"><h5>Departments</h5><form method="POST" class="input-group input-group-sm mb-3"><input type="hidden" name="action" value="add_department"><input name="name" class="form-control" placeholder="New department name" required><button class="btn btn-bmc">Add</button></form><?php foreach ($departments as $department): ?><span class="badge text-bg-light border me-1 mb-1"><?= clean_input($department['name']) ?></span><?php endforeach; ?></div><div class="card panel-card p-4 mt-4" id="reports"><h5>Operational reports</h5><div class="row g-2 mt-1"><div class="col-6"><div class="bg-light p-2 small"><span class="text-muted">Avg response</span><br><strong><?= $time_report['avg_response_minutes'] !== null ? clean_input($time_report['avg_response_minutes']) . ' min' : 'No data' ?></strong></div></div><div class="col-6"><div class="bg-light p-2 small"><span class="text-muted">Avg resolution</span><br><strong><?= $time_report['avg_resolution_hours'] !== null ? clean_input($time_report['avg_resolution_hours']) . ' hrs' : 'No data' ?></strong></div></div></div><h6 class="mt-4">Category report</h6><?php foreach ($report_categories as $report): ?><div class="d-flex justify-content-between small border-bottom py-2"><span><?= clean_input($report['name']) ?></span><strong><?= (int) $report['total'] ?></strong></div><?php endforeach; ?><h6 class="mt-4">Daily issues</h6><?php foreach ($daily_report as $report): ?><div class="d-flex justify-content-between small py-1"><span><?= clean_input($report['report_date']) ?></span><span><?= (int) $report['total'] ?> total / <?= (int) $report['completed'] ?> complete</span></div><?php endforeach; ?></div></div>
                    <div class="col-12"><div class="card panel-card p-4"><h5>Department, location and technician performance</h5><div class="row"><div class="col-lg-6"><h6 class="mt-2">Department / location</h6><div class="table-responsive"><table class="table small"><thead><tr><th>Department</th><th>Location</th><th>Total</th></tr></thead><tbody><?php foreach ($department_report as $report): ?><tr><td><?= clean_input($report['department_name']) ?></td><td><?= clean_input($report['location']) ?></td><td><?= (int) $report['total'] ?></td></tr><?php endforeach; ?></tbody></table></div></div><div class="col-lg-6"><h6 class="mt-2">Technician performance</h6><div class="table-responsive"><table class="table small"><thead><tr><th>Technician</th><th>Assigned</th><th>Resolved</th><th>Avg hours</th></tr></thead><tbody><?php foreach ($performance_report as $report): ?><tr><td><?= clean_input($report['full_name']) ?></td><td><?= (int) $report['assigned'] ?></td><td><?= (int) $report['resolved'] ?></td><td><?= $report['avg_resolution_hours'] !== null ? clean_input($report['avg_resolution_hours']) : '-' ?></td></tr><?php endforeach; ?></tbody></table></div></div></div></div></div>
                    <div class="col-12" id="audit-trail"><div class="card panel-card p-4"><h5>Recent audit trail</h5><div class="table-responsive"><table class="table small"><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th></tr></thead><tbody><?php foreach ($audit as $entry): ?><tr><td><?= date('d M Y H:i', strtotime($entry['created_at'])) ?></td><td><?= clean_input($entry['full_name'] ?? 'System') ?></td><td><?= clean_input($entry['action']) ?></td><td><?= clean_input($entry['details'] ?? '') ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
                </div>
            </div>
        </main>
    </div>
</div>

</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</html>
