<?php
/** Report and download page for the authorized roles. */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/report_export.php';
require_role($report_role);
$rows = report_rows($pdo, $report_role, $_SESSION['user_id']);
if (($_GET['format'] ?? '') === 'excel') { download_excel($rows, 'bugando-ict-report'); }
if (($_GET['format'] ?? '') === 'pdf') { download_pdf($rows, 'bugando-ict-report'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Reports | Bugando ICT HelpDesk</title><meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"><link href="<?= app_url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body><div class="container-fluid"><div class="row"><?php include __DIR__ . '/sidebar.php'; ?><main class="col p-0"><div class="topbar d-flex justify-content-between align-items-center"><h5 class="mb-0">Ripoti za ICT</h5><span class="badge bg-primary role-badge"><?= strtoupper($report_role) ?></span></div><div class="p-4"><p class="text-muted">Pakua report ya tickets zinazoruhusiwa kwa role yako.</p><div class="card panel-card p-4"><div class="d-flex gap-2 mb-3"><a href="?format=excel" class="btn btn-success"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Pakua Excel</a><a href="?format=pdf" class="btn btn-danger"><i class="bi bi-file-earmark-pdf me-2"></i>Pakua PDF</a></div><?php if (!$rows): ?><div class="empty-state"><i class="bi bi-inbox"></i><p>Hakuna tickets za kuripoti.</p></div><?php else: ?><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Ticket</th><th>Aliyeripoti</th><th>Idara</th><th>Category</th><th>Priority</th><th>Hali</th><th>Tarehe</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?= clean_input($row['ticket_no']) ?></td><td><?= clean_input($row['requester']) ?></td><td><?= clean_input($row['department_name']) ?></td><td><?= clean_input($row['category_name']) ?></td><td><?= clean_input(sw_priority($row['priority'])) ?></td><td><span class="status-pill status-<?= clean_input($row['status']) ?>"><?= clean_input(sw_status($row['status'])) ?></span></td><td><?= date('d M Y', strtotime($row['created_at'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></div></main></div></div></body></html>
