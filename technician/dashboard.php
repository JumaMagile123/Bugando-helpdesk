<?php
/**
 * TECHNICIAN / OFFICER DASHBOARD
 * Visible only to users with the technician role.
 */
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/role_guidance.php';
require_role('technician');
$guidance = role_guidance('technician');
$assigned_to_me = ticket_count($pdo, 'assigned_to = ? AND status NOT IN (\'resolved\', \'closed\')', [$_SESSION['user_id']]);
$in_progress = ticket_count($pdo, 'assigned_to = ? AND status = \'in_progress\'', [$_SESSION['user_id']]);
$resolved_by_me = ticket_count($pdo, 'assigned_to = ? AND status IN (\'resolved\', \'closed\')', [$_SESSION['user_id']]);
$escalated_by_me = ticket_count($pdo, 'assigned_to = ? AND status = \'escalated\'', [$_SESSION['user_id']]);
$supervisors = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('admin', 'helpdesk') AND status = 'active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

// Technicians record progress, resolve tickets, or escalate them.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ticket_id'])) {
    $ticket_id = (int) $_POST['ticket_id'];
    $action = $_POST['action'] ?? '';
    $note = trim($_POST['note'] ?? '');
    $status = $action === 'accept' ? 'in_progress' : ($_POST['status'] ?? '');
    if (in_array($status, ['in_progress', 'resolved'], true) && ($action === 'accept' || $note !== '')) {
        $stmt = $pdo->prepare("UPDATE tickets SET status = ?, resolved_at = CASE WHEN ? = 'resolved' THEN NOW() ELSE NULL END WHERE id = ? AND assigned_to = ?");
        $stmt->execute([$status, $status, $ticket_id, $_SESSION['user_id']]);
        if ($stmt->rowCount()) {
            $update = $pdo->prepare("INSERT INTO ticket_updates (ticket_id, updated_by, note, status) VALUES (?, ?, ?, ?)");
            $update->execute([$ticket_id, $_SESSION['user_id'], $note ?: 'Technician accepted the assigned job.', $status]);
            log_audit($pdo, $_SESSION['user_id'], 'TICKET_STATUS_UPDATED', 'Ticket ID: ' . $ticket_id . ', status: ' . $status);
        }
    } elseif ($action === 'escalate' && $note !== '') {
        $escalated_to = (int) ($_POST['escalated_to'] ?? 0);
        $stmt = $pdo->prepare("UPDATE tickets SET status = 'escalated' WHERE id = ? AND assigned_to = ? AND status NOT IN ('resolved', 'closed')");
        $stmt->execute([$ticket_id, $_SESSION['user_id']]);
        if ($stmt->rowCount()) {
            $escalation = $pdo->prepare("INSERT INTO escalations (ticket_id, escalated_by, escalated_to, reason) VALUES (?, ?, ?, ?)");
            $escalation->execute([$ticket_id, $_SESSION['user_id'], $escalated_to ?: null, $note]);
            $update = $pdo->prepare("INSERT INTO ticket_updates (ticket_id, updated_by, note, status) VALUES (?, ?, ?, 'escalated')");
            $update->execute([$ticket_id, $_SESSION['user_id'], $note]);
            log_audit($pdo, $_SESSION['user_id'], 'TICKET_ESCALATED', 'Ticket ID: ' . $ticket_id);
        }
    }
}

$my_jobs_stmt = $pdo->prepare("SELECT t.id, t.ticket_no, t.description, t.priority, t.status, t.created_at, u.full_name, d.name AS department_name
    FROM tickets t JOIN users u ON u.id = t.user_id LEFT JOIN departments d ON d.id = t.department_id
    WHERE t.assigned_to = ? AND t.status NOT IN ('resolved', 'closed') ORDER BY t.created_at ASC");
$my_jobs_stmt->execute([$_SESSION['user_id']]);
$my_jobs = $my_jobs_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Technician Dashboard | Bugando ICT HelpDesk</title>
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
                <h5 class="mb-0">Technician Workspace</h5>
                <div>
                    <span class="badge bg-success role-badge">TECHNICIAN</span>
                    <span class="ms-2 fw-semibold"><?= clean_input($_SESSION['full_name']) ?></span>
                </div>
            </div>

            <div class="p-4">
                <p class="text-muted">Accept work, record progress, resolve issues, or escalate challenges.</p>
                <div class="card panel-card mb-4 p-3"><h6><i class="bi bi-person-check me-2"></i><?= $guidance['title'] ?></h6><div class="row small"><div class="col-md-6"><strong>Anachoweza kufanya</strong><ul class="mb-0"><?php foreach ($guidance['can'] as $item): ?><li><?= clean_input($item) ?></li><?php endforeach; ?></ul></div><div class="col-md-6"><strong>Asichopaswa kufanya</strong><ul class="mb-0"><?php foreach ($guidance['cannot'] as $item): ?><li><?= clean_input($item) ?></li><?php endforeach; ?></ul></div></div></div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="card stat-card p-3">
                            <div class="text-muted small">Assigned to me</div>
                            <div class="fs-3 fw-bold text-primary"><?= $assigned_to_me ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card p-3">
                            <div class="text-muted small">In progress</div>
                            <div class="fs-3 fw-bold text-info"><?= $in_progress ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card p-3">
                            <div class="text-muted small">Resolved by me</div>
                            <div class="fs-3 fw-bold text-success"><?= $resolved_by_me ?></div>
                        </div>
                    </div>
                </div>

                <div class="card panel-card mt-4 p-4" id="assigned-jobs"><div class="d-flex justify-content-between align-items-center mb-2"><h5 class="mb-0">Assigned jobs</h5><span class="small text-muted">Work queue</span></div>
                    <?php if (!$my_jobs): ?><div class="empty-state py-4"><i class="bi bi-check2-circle"></i><p>Hakuna kazi mpya ulizopangiwa.</p></div><?php else: ?><div class="table-responsive"><table class="table align-middle small"><thead><tr><th>Ticket</th><th>Aliyeripoti / Tatizo</th><th>Maendeleo</th><th>Pandisha ngazi</th></tr></thead><tbody><?php foreach ($my_jobs as $job): ?><tr><td class="fw-semibold text-primary"><?= clean_input($job['ticket_no']) ?><br><span class="text-muted fw-normal"><?= clean_input($job['department_name'] ?? '') ?></span><br><span class="status-pill status-<?= clean_input($job['status']) ?>"><?= clean_input(sw_status($job['status'])) ?></span></td><td><?= clean_input($job['full_name']) ?><br><?= clean_input(mb_strimwidth($job['description'], 0, 48, '...')) ?></td><td><form method="POST"><input type="hidden" name="ticket_id" value="<?= $job['id'] ?>"><input type="hidden" name="action" value="status"><textarea name="note" class="form-control form-control-sm mb-1" rows="2" placeholder="Andika maendeleo au utatuzi" required></textarea><div class="d-flex gap-1"><select name="status" class="form-select form-select-sm"><option value="in_progress">Inaendelea</option><option value="resolved">Imetatuliwa</option></select><button class="btn btn-sm btn-bmc">Hifadhi</button></div></form></td><td><form method="POST"><input type="hidden" name="ticket_id" value="<?= $job['id'] ?>"><input type="hidden" name="action" value="escalate"><select name="escalated_to" class="form-select form-select-sm mb-1"><option value="">Pandisha kwa</option><?php foreach ($supervisors as $supervisor): ?><option value="<?= $supervisor['id'] ?>"><?= clean_input($supervisor['full_name']) ?></option><?php endforeach; ?></select><textarea name="note" class="form-control form-control-sm mb-1" rows="2" placeholder="Sababu ya kupandisha ngazi" required></textarea><button class="btn btn-sm btn-outline-danger">Pandisha</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

</body>
</html>
