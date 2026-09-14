<?php
/**
 * Role guidance shown on each dashboard.
 * These responsibilities follow the Bugando ICT HelpDesk concept note.
 */
function role_guidance($role)
{
    $guidance = [
        'staff' => [
            'title' => 'Staff responsibilities',
            'can' => ['Create an account and sign in', 'Submit an ICT issue', 'Track ticket status and progress', 'Provide additional details and feedback after resolution'],
            'cannot' => ['Assign tickets to technicians', 'Change another user\'s ticket priority or status', 'Access other staff members\' information'],
        ],
        'helpdesk' => [
            'title' => 'HelpDesk / On-Call responsibilities',
            'can' => ['Receive and triage new requests', 'Review categories and set priority', 'Assign or transfer tickets to technicians', 'Monitor the queue and escalations'],
            'cannot' => ['Change system accounts', 'Close tickets without the resolution process', 'Access clinical or patient-care workflows'],
        ],
        'technician' => [
            'title' => 'Technician / Officer responsibilities',
            'can' => ['View assigned work', 'Accept and start work', 'Add progress notes and resolution details', 'Escalate complex issues to HelpDesk/Admin'],
            'cannot' => ['Take a ticket that is not assigned to them', 'Assign a ticket to another technician', 'Delete the history or audit trail'],
        ],
        'admin' => [
            'title' => 'Administrator responsibilities',
            'can' => ['Manage users, roles, and departments', 'View tickets and management reports', 'Monitor workload, response time, and resolution time', 'Review the audit trail for accountability'],
            'cannot' => ['Handle hospital clinical workflows', 'Delete the audit trail during normal use', 'Grant access without the correct role and responsibility'],
        ],
    ];

    return $guidance[$role] ?? null;
}
?>
