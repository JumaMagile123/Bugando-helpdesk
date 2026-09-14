<?php
/**
 * STAFF DASHBOARD
 * Staff can submit and track their ICT requests.
 */
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/role_guidance.php';
require_role('staff');
$guidance = role_guidance('staff');

$error = '';
$success = '';
$selected_ticket = (int) ($_GET['ticket'] ?? 0);
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Handle staff actions: submitting tickets and feedback.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $ticket_id = (int) ($_POST['ticket_id'] ?? 0);

    if ($action === 'feedback') {
        $rating = (int) ($_POST['feedback_rating'] ?? 0);
        $feedback = trim($_POST['feedback'] ?? '');
        if ($rating < 1 || $rating > 5 || $feedback === '') {
            $error = 'Please provide a rating and comment.';
        } else {
            $stmt = $pdo->prepare("UPDATE tickets SET feedback = ?, feedback_rating = ? WHERE id = ? AND user_id = ? AND status = 'resolved'");
            $stmt->execute([$feedback, $rating, $ticket_id, $_SESSION['user_id']]);
            if ($stmt->rowCount()) {
                $close = $pdo->prepare("UPDATE tickets SET status = 'closed', closed_at = NOW() WHERE id = ? AND user_id = ?");
                $close->execute([$ticket_id, $_SESSION['user_id']]);
                $update = $pdo->prepare("INSERT INTO ticket_updates (ticket_id, updated_by, note, status) VALUES (?, ?, ?, 'closed')");
                $update->execute([$ticket_id, $_SESSION['user_id'], 'Requester provided feedback and closed the ticket.']);
                log_audit($pdo, $_SESSION['user_id'], 'TICKET_CLOSED', 'Ticket ID: ' . $ticket_id);
                $success = 'Thank you. Your feedback was saved and the ticket was closed.';
            }
        }
    } elseif ($action === 'submit') {
    $department_id = (int) ($_POST['department_id'] ?? 0);
    $category_id = (int) ($_POST['category_id'] ?? 0);
    $location = clean_input($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $allowed_priorities = ['low', 'medium', 'high', 'critical'];

    if (!$department_id || !$category_id || $description === '') {
        $error = 'Please select a department and category, then describe the issue.';
    } elseif (!in_array($priority, $allowed_priorities, true)) {
        $error = 'The selected priority is invalid.';
    } else {
        $ticket_no = 'BMC-' . date('Y') . '-' . str_pad((string) (ticket_count($pdo) + 1), 4, '0', STR_PAD_LEFT);
        // Load this staff member's tickets for tracking.
        $stmt = $pdo->prepare(
            "INSERT INTO tickets (ticket_no, user_id, department_id, category_id, location, description, priority)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$ticket_no, $_SESSION['user_id'], $department_id, $category_id, $location ?: null, $description, $priority]);
        log_audit($pdo, $_SESSION['user_id'], 'TICKET_CREATED', $ticket_no);
        $success = "Your request was submitted. The ticket number is $ticket_no.";
    }
    }
}

$stmt = $pdo->prepare(
    "SELECT t.id, t.ticket_no, t.description, t.priority, t.status, t.created_at, c.name AS category_name
     FROM tickets t LEFT JOIN categories c ON c.id = t.category_id
     WHERE t.user_id = ? ORDER BY t.created_at DESC LIMIT 6"
);
$stmt->execute([$_SESSION['user_id']]);
$my_tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = ticket_count($pdo, 'user_id = ?', [$_SESSION['user_id']]);
$open = ticket_count($pdo, 'user_id = ? AND status NOT IN (\'resolved\', \'closed\')', [$_SESSION['user_id']]);
$resolved = ticket_count($pdo, 'user_id = ? AND status IN (\'resolved\', \'closed\')', [$_SESSION['user_id']]);

$detail = null;
$updates = [];
if ($selected_ticket) {
    $detail_stmt = $pdo->prepare("SELECT t.*, c.name AS category_name, d.name AS department_name FROM tickets t LEFT JOIN categories c ON c.id = t.category_id LEFT JOIN departments d ON d.id = t.department_id WHERE t.id = ? AND t.user_id = ?");
    $detail_stmt->execute([$selected_ticket, $_SESSION['user_id']]);
    $detail = $detail_stmt->fetch(PDO::FETCH_ASSOC);
    if ($detail) {
        $updates_stmt = $pdo->prepare("SELECT tu.*, u.full_name FROM ticket_updates tu JOIN users u ON u.id = tu.updated_by WHERE tu.ticket_id = ? ORDER BY tu.created_at DESC");
        $updates_stmt->execute([$selected_ticket]);
        $updates = $updates_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Dashboard | Bugando ICT HelpDesk</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="container-fluid"><div class="row">
    <?php include '../includes/sidebar.php'; ?>
    <main class="col p-0">
        <div class="topbar d-flex justify-content-between align-items-center">
            <div><span class="text-muted small">Staff workspace</span><h5 class="mb-0">My HelpDesk</h5></div>
            <div><span class="badge bg-secondary role-badge">STAFF</span><span class="ms-2 fw-semibold"><?= clean_input($_SESSION['full_name']) ?></span></div>
        </div>
        <div class="p-4 dashboard-content">
            <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= clean_input($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success py-2 small"><?= clean_input($success) ?></div><?php endif; ?>
            <?php if ($detail): ?><div class="card panel-card mb-4 p-4"><div class="d-flex justify-content-between"><div><span class="text-primary fw-semibold"><?= clean_input($detail['ticket_no']) ?></span><h5 class="mb-1 mt-1">Maelezo ya ombi</h5><p class="text-muted small mb-0"><?= clean_input($detail['description']) ?></p></div><span class="status-pill status-<?= clean_input($detail['status']) ?>"><?= clean_input(sw_status($detail['status'])) ?></span></div><hr><div class="row small"><div class="col-md-4"><strong>Idara</strong><br><?= clean_input($detail['department_name'] ?? 'Haijawekwa') ?></div><div class="col-md-4"><strong>Aina ya tatizo</strong><br><?= clean_input($detail['category_name'] ?? 'Haijawekwa') ?></div><div class="col-md-4"><strong>Eneo</strong><br><?= clean_input($detail['location'] ?? 'Halijawekwa') ?></div></div><h6 class="mt-4">Historia ya maendeleo</h6><?php foreach ($updates as $update): ?><div class="border-start border-3 border-primary ps-3 mb-3 small"><strong><?= clean_input(sw_status($update['status'])) ?></strong> na <?= clean_input($update['full_name']) ?><br><span class="text-muted"><?= clean_input($update['note'] ?? '') ?> · <?= date('d M Y H:i', strtotime($update['created_at'])) ?></span></div><?php endforeach; ?><?php if ($detail['status'] === 'resolved'): ?><form method="POST" class="border-top pt-3 mt-3"><input type="hidden" name="action" value="feedback"><input type="hidden" name="ticket_id" value="<?= $detail['id'] ?>"><h6>Thibitisha utatuzi</h6><div class="row g-2"><div class="col-md-3"><label class="form-label small">Rating</label><select name="feedback_rating" class="form-select" required><option value="">Chagua</option><option value="5">5 - Bora sana</option><option value="4">4 - Nzuri</option><option value="3">3 - Wastani</option><option value="2">2 - Haijakidhi</option><option value="1">1 - Mbaya</option></select></div><div class="col-md-9"><label class="form-label small">Maoni</label><input name="feedback" class="form-control" required placeholder="Eleza kuhusu utatuzi wa tatizo"></div></div><button class="btn btn-bmc btn-sm mt-3">Tuma maoni na funga</button></form><?php endif; ?><a href="<?= app_url('staff/dashboard.php#my-requests') ?>" class="btn btn-link btn-sm px-0 mt-2">Rudi kwenye maombi</a></div><?php elseif ($selected_ticket): ?><div class="alert alert-warning">Ombi hilo halikupatikana.</div><?php endif; ?>
            <div class="d-flex justify-content-between align-items-end mb-3"><div><p class="text-muted mb-1">Submit an ICT issue and track responses from the support team.</p><h4 class="mb-0">Summary</h4></div></div>
            <div class="card panel-card mb-4 p-3"><h6><i class="bi bi-person-check me-2"></i><?= $guidance['title'] ?></h6><div class="row small"><div class="col-md-6"><strong>Can do</strong><ul class="mb-0"><?php foreach ($guidance['can'] as $item): ?><li><?= clean_input($item) ?></li><?php endforeach; ?></ul></div><div class="col-md-6"><strong>Cannot do</strong><ul class="mb-0"><?php foreach ($guidance['cannot'] as $item): ?><li><?= clean_input($item) ?></li><?php endforeach; ?></ul></div></div></div>
            <div class="row g-3 mb-4">
                <div class="col-md-4"><div class="card stat-card p-3"><span class="text-muted small">My requests</span><strong class="fs-3 text-primary"><?= $total ?></strong></div></div>
                <div class="col-md-4"><div class="card stat-card p-3"><span class="text-muted small">Open requests</span><strong class="fs-3 text-warning"><?= $open ?></strong></div></div>
                <div class="col-md-4"><div class="card stat-card p-3"><span class="text-muted small">Resolved</span><strong class="fs-3 text-success"><?= $resolved ?></strong></div></div>
            </div>
            <div class="row g-4">
                <div class="col-lg-5" id="submit-issue"><div class="card panel-card p-4"><h5 class="mb-1">Wasilisha tatizo la ICT</h5><p class="text-muted small">Maelezo mazuri humsaidia technician kuanza haraka.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="submit">
                        <div class="mb-3"><label class="form-label small fw-semibold">Idara</label><select name="department_id" class="form-select" required><option value="">Chagua idara</option><?php foreach ($departments as $department): ?><option value="<?= $department['id'] ?>"><?= clean_input($department['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="row g-2"><div class="col-7"><label class="form-label small fw-semibold">Aina ya tatizo</label><select name="category_id" class="form-select" required><option value="">Chagua category</option><?php foreach ($categories as $category): ?><option value="<?= $category['id'] ?>"><?= clean_input($category['name']) ?></option><?php endforeach; ?></select></div><div class="col-5"><label class="form-label small fw-semibold">Kipaumbele</label><select name="priority" class="form-select"><option value="low">Chini</option><option value="medium" selected>Wastani</option><option value="high">Juu</option><option value="critical">Muhimu sana</option></select></div></div>
                        <div class="mb-3 mt-3"><label class="form-label small fw-semibold">Eneo</label><input name="location" class="form-control" placeholder="Mfano: Ward 3, Records"></div>
                        <div class="mb-3"><label class="form-label small fw-semibold">Maelezo ya tatizo</label><textarea name="description" class="form-control" rows="4" required placeholder="Eleza tatizo kwa ufupi..."></textarea></div>
                        <button class="btn btn-bmc w-100"><i class="bi bi-send me-2"></i>Tuma ombi</button>
                    </form>
                </div></div>
                <div class="col-lg-7" id="my-requests"><div class="card panel-card p-4"><div class="d-flex justify-content-between"><h5>Recent requests</h5><span class="small text-muted">Last 6</span></div>
                    <?php if (!$my_tickets): ?><div class="empty-state"><i class="bi bi-inbox"></i><p>Bado hujawasilisha ombi.</p></div><?php else: ?><div class="table-responsive"><table class="table align-middle small"><thead><tr><th>Ticket</th><th>Tatizo</th><th>Hali</th><th>Tarehe</th><th></th></tr></thead><tbody><?php foreach ($my_tickets as $ticket): ?><tr><td class="fw-semibold text-primary"><?= clean_input($ticket['ticket_no']) ?></td><td><?= clean_input($ticket['category_name'] ?? 'Jumla') ?></td><td><span class="status-pill status-<?= clean_input($ticket['status']) ?>"><?= clean_input(sw_status($ticket['status'])) ?></span></td><td><?= date('d M Y', strtotime($ticket['created_at'])) ?></td><td><a class="btn btn-sm btn-outline-primary" href="<?= app_url('staff/dashboard.php?ticket=' . $ticket['id']) ?>">Angalia</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
                </div></div>
            </div>
        </div>
    </main>
</div></div>
</body>
</html>
