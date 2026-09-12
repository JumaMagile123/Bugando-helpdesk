<?php
/**
 * Shared page for smaller modules.
 * Each page sets its role and module before including this file.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_role($module_role);

$module_titles = [
    'users' => ['User Management', 'View users, roles, and account status.'],
    'tickets' => ['All Tickets', 'View the history of all ICT requests.'],
    'departments' => ['Departments', 'View the departments used by the system.'],
    'incoming' => ['Incoming Requests', 'View new tickets that have not been assigned.'],
    'assign' => ['Assign Technician', 'View tickets that need a technician.'],
    'queue' => ['Ticket Queue', 'Track tickets by their current status.'],
    'jobs' => ['Assigned Jobs', 'View your current work as a technician.'],
    'progress' => ['Progress Notes', 'Angalia tickets zako zinazoendelea kushughulikiwa.'],
    'escalations' => ['Escalations', 'Angalia tickets zilizopandishwa ngazi.'],
    'submit' => ['Submit ICT Issue', 'Use the form to submit a new request.'],
    'requests' => ['My Requests', 'View and track your ICT requests.'],
];
[$title, $description] = $module_titles[$module_name] ?? ['Module', 'Bugando ICT HelpDesk system module.'];

$rows = [];
$submit_error = '';
$submit_success = '';
if ($module_name === 'users') {
    $rows = $pdo->query("SELECT u.full_name, u.username, u.role, u.status, COALESCE(d.name, 'Not specified') AS department_name FROM users u LEFT JOIN departments d ON d.id = u.department_id ORDER BY u.full_name")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($module_name === 'departments') {
    $rows = $pdo->query("SELECT d.name, COUNT(t.id) AS total FROM departments d LEFT JOIN tickets t ON t.department_id = d.id GROUP BY d.id, d.name ORDER BY d.name")->fetchAll(PDO::FETCH_ASSOC);
} elseif (in_array($module_name, ['incoming', 'assign', 'queue', 'tickets'], true)) {
    $condition = $module_name === 'incoming' ? "WHERE t.status = 'pending'" : ($module_name === 'assign' ? "WHERE t.status IN ('pending', 'assigned')" : ($module_name === 'queue' ? "WHERE t.status NOT IN ('resolved', 'closed')" : ''));
    $rows = $pdo->query("SELECT t.ticket_no, t.description, t.priority, t.status, t.created_at, u.full_name, COALESCE(d.name, 'Not specified') AS department_name FROM tickets t JOIN users u ON u.id = t.user_id LEFT JOIN departments d ON d.id = t.department_id $condition ORDER BY t.created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
} elseif (in_array($module_name, ['jobs', 'progress', 'escalations'], true)) {
    $condition = $module_name === 'progress' ? "AND t.status = 'in_progress'" : ($module_name === 'escalations' ? "AND t.status = 'escalated'" : "AND t.status NOT IN ('resolved', 'closed')");
    $stmt = $pdo->prepare("SELECT t.ticket_no, t.description, t.priority, t.status, t.created_at, COALESCE(d.name, 'Not specified') AS department_name FROM tickets t LEFT JOIN departments d ON d.id = t.department_id WHERE t.assigned_to = ? $condition ORDER BY t.created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($module_name === 'requests') {
    $stmt = $pdo->prepare("SELECT t.ticket_no, t.description, t.priority, t.status, t.created_at, COALESCE(d.name, 'Not specified') AS department_name FROM tickets t LEFT JOIN departments d ON d.id = t.department_id WHERE t.user_id = ? ORDER BY t.created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($module_name === 'submit') {
    $departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $department_id = (int) ($_POST['department_id'] ?? 0);
        $category_id = (int) ($_POST['category_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        if (!$department_id || !$category_id || $description === '') {
            $submit_error = 'Please select a department and category, then describe the issue.';
        } elseif (!in_array($priority, ['low', 'medium', 'high', 'critical'], true)) {
            $submit_error = 'Invalid priority.';
        } else {
            $ticket_no = 'BMC-' . date('Y') . '-' . str_pad((string) (ticket_count($pdo) + 1), 4, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("INSERT INTO tickets (ticket_no, user_id, department_id, category_id, location, description, priority) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$ticket_no, $_SESSION['user_id'], $department_id, $category_id, clean_input($_POST['location'] ?? '') ?: null, $description, $priority]);
            log_audit($pdo, $_SESSION['user_id'], 'TICKET_CREATED', $ticket_no);
            $submit_success = "Request submitted. Your ticket number is $ticket_no.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= clean_input($title) ?> | Bugando ICT HelpDesk</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= app_url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body>
<div class="container-fluid"><div class="row">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="col p-0"><div class="topbar d-flex justify-content-between align-items-center"><div><span class="text-muted small">Bugando ICT HelpDesk</span><h5 class="mb-0"><?= clean_input($title) ?></h5></div><span class="badge bg-primary role-badge"><?= strtoupper(clean_input($module_role)) ?></span></div>
    <div class="p-4"><p class="text-muted"><?= clean_input($description) ?></p><div class="card panel-card p-4"><div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0"><?= clean_input($title) ?></h5><a href="<?= app_url($module_role . '/dashboard.php') ?>" class="btn btn-sm btn-outline-primary">Rudi dashboard</a></div>
        <?php if ($module_name === 'submit'): ?><?php if ($submit_error): ?><div class="alert alert-danger py-2 small"><?= clean_input($submit_error) ?></div><?php endif; ?><?php if ($submit_success): ?><div class="alert alert-success py-2 small"><?= clean_input($submit_success) ?></div><?php endif; ?><form method="POST" class="row g-3"><div class="col-md-6"><label class="form-label small fw-semibold">Idara</label><select name="department_id" class="form-select" required><option value="">Chagua idara</option><?php foreach ($departments as $department): ?><option value="<?= $department['id'] ?>"><?= clean_input($department['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label small fw-semibold">Aina ya tatizo</label><select name="category_id" class="form-select" required><option value="">Chagua category</option><?php foreach ($categories as $category): ?><option value="<?= $category['id'] ?>"><?= clean_input($category['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label small fw-semibold">Eneo</label><input name="location" class="form-control" placeholder="Mfano: Records"></div><div class="col-md-4"><label class="form-label small fw-semibold">Kipaumbele</label><select name="priority" class="form-select"><option value="low">Chini</option><option value="medium" selected>Wastani</option><option value="high">Juu</option><option value="critical">Muhimu sana</option></select></div><div class="col-12"><label class="form-label small fw-semibold">Maelezo ya tatizo</label><textarea name="description" class="form-control" rows="5" required></textarea></div><div class="col-12"><button class="btn btn-bmc"><i class="bi bi-send me-2"></i>Tuma ombi</button></div></form>
        <?php elseif (!$rows): ?><div class="empty-state"><i class="bi bi-inbox"></i><p>Hakuna taarifa kwa sasa.</p></div><?php elseif ($module_name === 'users'): ?><div class="table-responsive"><table class="table align-middle small"><thead><tr><th>Jina</th><th>Username</th><th>Role</th><th>Idara</th><th>Status</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?= clean_input($row['full_name']) ?></td><td><?= clean_input($row['username']) ?></td><td><?= clean_input($row['role']) ?></td><td><?= clean_input($row['department_name']) ?></td><td><?= clean_input($row['status']) ?></td></tr><?php endforeach; ?></tbody></table></div>
        <?php elseif ($module_name === 'departments'): ?><div class="table-responsive"><table class="table align-middle small"><thead><tr><th>Idara</th><th>Jumla ya tickets</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?= clean_input($row['name']) ?></td><td><?= (int) $row['total'] ?></td></tr><?php endforeach; ?></tbody></table></div>
        <?php else: ?><div class="table-responsive"><table class="table align-middle small"><thead><tr><th>Ticket</th><th>Aliyeripoti / Idara</th><th>Tatizo</th><th>Priority</th><th>Hali</th><th>Tarehe</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td class="fw-semibold text-primary"><?= clean_input($row['ticket_no']) ?></td><td><?= clean_input($row['full_name'] ?? '') ?><br><?= clean_input($row['department_name']) ?></td><td><?= clean_input(mb_strimwidth($row['description'], 0, 50, '...')) ?></td><td><?= clean_input(sw_priority($row['priority'])) ?></td><td><span class="status-pill status-<?= clean_input($row['status']) ?>"><?= clean_input(sw_status($row['status'])) ?></span></td><td><?= date('d M Y', strtotime($row['created_at'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div></div></main>
</div></div>
</body></html>
