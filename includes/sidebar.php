<?php
/**
 * ==========================================================
 * SIDEBAR (reusable)
 * The menu changes according to $_SESSION['role'].
 * Included by each role dashboard.
 * ==========================================================
 */

$current_role = $_SESSION['role'] ?? '';

// Role-based menus.
$menus = [
    'admin' => [
        ['icon' => 'speedometer2', 'label' => 'Dashboard', 'link' => 'admin/dashboard.php'],
        ['icon' => 'people',       'label' => 'User Management', 'link' => 'admin/users.php'],
        ['icon' => 'ticket-perforated', 'label' => 'All Tickets', 'link' => 'admin/tickets.php'],
        ['icon' => 'diagram-3',    'label' => 'Departments', 'link' => 'admin/departments.php'],
        ['icon' => 'bar-chart',    'label' => 'Reports and Audit', 'link' => 'admin/reports.php'],
    ],
    'helpdesk' => [
        ['icon' => 'speedometer2', 'label' => 'Dashboard', 'link' => 'helpdesk/dashboard.php'],
        ['icon' => 'inbox',        'label' => 'Incoming Requests', 'link' => 'helpdesk/incoming.php'],
        ['icon' => 'person-check', 'label' => 'Assign Technician', 'link' => 'helpdesk/assign.php'],
        ['icon' => 'clock-history','label' => 'Ticket Queue', 'link' => 'helpdesk/queue.php'],
        ['icon' => 'file-earmark-bar-graph', 'label' => 'Download Reports', 'link' => 'helpdesk/reports.php'],
    ],
    'technician' => [
        ['icon' => 'speedometer2', 'label' => 'Dashboard', 'link' => 'technician/dashboard.php'],
        ['icon' => 'tools',        'label' => 'Assigned Jobs', 'link' => 'technician/jobs.php'],
        ['icon' => 'chat-left-text', 'label' => 'Progress Notes', 'link' => 'technician/progress.php'],
        ['icon' => 'exclamation-triangle', 'label' => 'Escalations', 'link' => 'technician/escalations.php'],
        ['icon' => 'file-earmark-bar-graph', 'label' => 'Download Reports', 'link' => 'technician/reports.php'],
    ],
    'staff' => [
        ['icon' => 'speedometer2', 'label' => 'My Dashboard', 'link' => 'staff/dashboard.php'],
        ['icon' => 'plus-circle', 'label' => 'Submit ICT Issue', 'link' => 'staff/submit.php'],
        ['icon' => 'clock-history', 'label' => 'My Requests', 'link' => 'staff/requests.php'],
    ],
];

$items = $menus[$current_role] ?? [];
?>
<div class="col-auto col-md-3 col-xl-2 px-0 sidebar">
    <div class="text-center mb-4">
        <img src="<?= app_url('assets/img/bugando-logo.svg') ?>" alt="BMC Logo" width="48" height="48">
        <div class="fw-bold mt-1 small text-white">BMC ICT HelpDesk</div>
    </div>

    <ul class="nav nav-pills flex-column px-2">
        <?php foreach ($items as $item): ?>
            <li class="nav-item">
                <a href="<?= app_url($item['link']) ?>" class="nav-link">
                    <i class="bi bi-<?= $item['icon'] ?> me-2"></i><?= $item['label'] ?>
                </a>
            </li>
        <?php endforeach; ?>

        <li class="nav-item mt-4">
            <a href="<?= app_url('auth/logout.php') ?>" class="nav-link text-danger-emphasis">
                <i class="bi bi-box-arrow-right me-2"></i>Log out
            </a>
        </li>
    </ul>
</div>
