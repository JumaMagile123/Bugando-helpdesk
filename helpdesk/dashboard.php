<?php
/**
 * HELPDESK / ON-CALL DASHBOARD
 * Visible only to users with the helpdesk role.
 */
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/role_guidance.php';
require_role('helpdesk');
$guidance = role_guidance('helpdesk');
$new_requests = ticket_count($pdo, "status = 'pending'");
$assigned_today = ticket_count($pdo, "status = 'assigned' AND DATE(created_at) = CURDATE()");
$escalated = ticket_count($pdo, "status = 'escalated'");
$technicians = $pdo->query("SELECT id, full_name FROM users WHERE role = 'technician' AND status = 'active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$filter_status = $_GET['status'] ?? 'open';
$allowed_filters = ['open', 'pending', 'assigned', 'in_progress', 'escalated', 'resolved', 'closed'];
if (!in_array($filter_status, $allowed_filters, true)) { $filter_status = 'open'; }

// HelpDesk reviews tickets, sets category/priority, and assigns technicians.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ticket_id'])) {
    $ticket_id = (int) $_POST['ticket_id'];
    $action = $_POST['action'] ?? '';
    if ($action === 'review') {
        $category_id = (int) ($_POST['category_id'] ?? 0);
        $priority = $_POST['priority'] ?? 'medium';
        if ($category_id && in_array($priority, ['low', 'medium', 'high', 'critical'], true)) {
            $stmt = $pdo->prepare("UPDATE tickets SET category_id = ?, priority = ? WHERE id = ?");
            $stmt->execute([$category_id, $priority, $ticket_id]);
            $update = $pdo->prepare("INSERT INTO ticket_updates (ticket_id, updated_by, note, status) SELECT id, ?, 'HelpDesk reviewed category and priority', status FROM tickets WHERE id = ?");
            $update->execute([$_SESSION['user_id'], $ticket_id]);
            log_audit($pdo, $_SESSION['user_id'], 'TICKET_REVIEWED', 'Ticket ID: ' . $ticket_id);
        }
    } elseif ($action === 'assign') {
        $technician_id = (int) ($_POST['technician_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE tickets SET assigned_to = ?, status = 'assigned' WHERE id = ? AND status NOT IN ('resolved', 'closed')");
        $stmt->execute([$technician_id, $ticket_id]);
        if ($stmt->rowCount()) {
            $update = $pdo->prepare("INSERT INTO ticket_updates (ticket_id, updated_by, note, status) VALUES (?, ?, ?, 'assigned')");
            $update->execute([$ticket_id, $_SESSION['user_id'], 'HelpDesk assigned or reassigned the ticket.']);
            log_audit($pdo, $_SESSION['user_id'], 'TICKET_ASSIGNED', 'Ticket ID: ' . $ticket_id);
        }
    }
}

$where = $filter_status === 'open' ? "t.status NOT IN ('resolved', 'closed')" : "t.status = " . $pdo->quote($filter_status);
$incoming = $pdo->query("SELECT t.id, t.ticket_no, t.description, t.priority, t.status, t.category_id, t.assigned_to, t.created_at, u.full_name, d.name AS department_name
    FROM tickets t JOIN users u ON u.id = t.user_id LEFT JOIN departments d ON d.id = t.department_id
    WHERE $where ORDER BY FIELD(t.priority, 'critical', 'high', 'medium', 'low'), t.created_at ASC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HelpDesk Dashboard | Bugando ICT HelpDesk</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="container-fluid">
    <div class="row">

        <?php include '../includes/sidebar.php'; ?>

        <main class="col p-0">

            <div class="topbar d-flex justify-content-between align-items-center">
                <h5 class="mb-0">HelpDesk / On-Call Dashboard</h5>
                <div>
                    <span class="badge bg-info role-badge">HELPDESK</span>
                    <span class="ms-2 fw-semibold"><?= clean_input($_SESSION['full_name']) ?></span>
                </div>
            </div>

            <div class="p-4">
                <p class="text-muted">Receive, review, prioritize, and assign new ICT requests.</p>
                <div class="card panel-card mb-4 p-3"><h6><i class="bi bi-person-check me-2"></i><?= $guidance['title'] ?></h6><div class="row small"><div class="col-md-6"><strong>Anachoweza kufanya</strong><ul class="mb-0"><?php foreach ($guidance['can'] as $item): ?><li><?= clean_input($item) ?></li><?php endforeach; ?></ul></div><div class="col-md-6"><strong>Asichopaswa kufanya</strong><ul class="mb-0"><?php foreach ($guidance['cannot'] as $item): ?><li><?= clean_input($item) ?></li><?php endforeach; ?></ul></div></div></div>

                <div id="helpdesk-summary" class="row g-3">
                    <div class="col-md-4">
                        <div class="card stat-card p-3">
                            <div class="text-muted small">New / Unassigned</div>
                            <div class="fs-3 fw-bold text-danger"><?= $new_requests ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card p-3">
                            <div class="text-muted small">Assigned today</div>
                            <div class="fs-3 fw-bold text-primary"><?= $assigned_today ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card p-3">
                            <div class="text-muted small">Escalated</div>
                            <div class="fs-3 fw-bold text-warning"><?= $escalated ?></div>
                        </div>
                    </div>
                </div>

                <div class="card panel-card mt-4 p-4" id="incoming-requests"><div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0">Foleni ya maombi</h5><form method="GET"><select name="status" class="form-select form-select-sm" onchange="this.form.submit()"><option value="open" <?= $filter_status === 'open' ? 'selected' : '' ?>>Maombi yaliyo wazi</option><?php foreach (['pending', 'assigned', 'in_progress', 'escalated', 'resolved', 'closed'] as $status): ?><option value="<?= $status ?>" <?= $filter_status === $status ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $status)) ?></option><?php endforeach; ?></select></form></div>
                    <?php if (!$incoming): ?><div class="empty-state py-4"><i class="bi bi-check2-circle"></i><p>No requests match this queue.</p></div><?php else: ?><div class="table-responsive"><table class="table align-middle small"><thead><tr><th>Ticket / Requester</th><th>Issue</th><th>Review</th><th>Assignment</th></tr></thead><tbody><?php foreach ($incoming as $ticket): ?><tr><td class="fw-semibold text-primary"><?= clean_input($ticket['ticket_no']) ?><br><span class="text-dark fw-normal"><?= clean_input($ticket['full_name']) ?></span><br><span class="text-muted fw-normal"><?= clean_input($ticket['department_name'] ?? '') ?></span></td><td><?= clean_input(mb_strimwidth($ticket['description'], 0, 44, '...')) ?><br><span class="status-pill status-<?= clean_input($ticket['status']) ?>"><?= clean_input(str_replace('_', ' ', $ticket['status'])) ?></span></td><td><form method="POST" class="d-flex gap-1 mb-1"><input type="hidden" name="action" value="review"><input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>"><select name="category_id" class="form-select form-select-sm" required><option value="">Category</option><?php foreach ($categories as $category): ?><option value="<?= $category['id'] ?>" <?= (int) $ticket['category_id'] === (int) $category['id'] ? 'selected' : '' ?>><?= clean_input($category['name']) ?></option><?php endforeach; ?></select><select name="priority" class="form-select form-select-sm"><option value="low" <?= $ticket['priority'] === 'low' ? 'selected' : '' ?>>Low</option><option value="medium" <?= $ticket['priority'] === 'medium' ? 'selected' : '' ?>>Medium</option><option value="high" <?= $ticket['priority'] === 'high' ? 'selected' : '' ?>>High</option><option value="critical" <?= $ticket['priority'] === 'critical' ? 'selected' : '' ?>>Critical</option></select><button class="btn btn-sm btn-outline-primary" title="Save review"><i class="bi bi-check2"></i></button></form></td><td><form method="POST" class="d-flex gap-1"><input type="hidden" name="action" value="assign"><input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>"><select name="technician_id" class="form-select form-select-sm" required><option value="">Assign technician</option><?php foreach ($technicians as $technician): ?><option value="<?= $technician['id'] ?>" <?= (int) $ticket['assigned_to'] === (int) $technician['id'] ? 'selected' : '' ?>><?= clean_input($technician['full_name']) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-bmc" title="Assign or reassign"><i class="bi bi-person-check"></i></button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

</body>
</html>
