<?php
/**
 * ==========================================================
 * HELPER FUNCTIONS
 * Shared helper functions used throughout the application.
 * ==========================================================
 */

/**
 * Start a session when one has not already been started.
 */
function start_session_safe()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Translate legacy visible labels while older templates are being migrated.
    static $english_ui_enabled = false;
    if (!$english_ui_enabled) {
        ob_start(static function ($output) {
            return strtr($output, [
                'Rudi dashboard' => 'Back to dashboard',
                'Chagua idara' => 'Select department',
                'Chagua category' => 'Select category',
                'Hakuna taarifa kwa sasa.' => 'No records found.',
                'Jumla ya tickets' => 'Total tickets',
                'Aliyeripoti / Idara' => 'Requester / Department',
                'Tatizo' => 'Issue',
                'Hali' => 'Status',
                'Tarehe' => 'Date',
                'Idara' => 'Department',
                'Aina ya tatizo' => 'Issue type',
                'Kipaumbele' => 'Priority',
                'Chini' => 'Low',
                'Wastani' => 'Medium',
                'Juu' => 'High',
                'Muhimu sana' => 'Critical',
                'Eneo' => 'Location',
                'Maelezo ya tatizo' => 'Issue description',
                'Tuma ombi' => 'Submit request',
                'Pakua PDF' => 'Download PDF',
                'Hakuna tickets za kuripoti.' => 'No tickets to report.',
                'Foleni ya maombi' => 'Request queue',
                'Foleni ya kazi' => 'Work queue',
                'Kazi nilizopangiwa' => 'Assigned jobs',
                'Hakuna kazi mpya ulizopangiwa.' => 'No new jobs assigned.',
                'Wasilisha tatizo la ICT' => 'Submit ICT issue',
                'Wasilisha tatizo la ICT na fuatilia majibu ya timu ya msaada.' => 'Submit an ICT issue and track responses from the support team.',
                'Muhtasari' => 'Summary',
                'Maelezo ya ombi' => 'Request details',
                'Historia ya maendeleo' => 'Progress history',
                'Haijawekwa' => 'Not specified',
                'Halijawekwa' => 'Not specified',
                'Bado hujawasilisha ombi.' => 'You have not submitted a request yet.',
                'Tengeneza account' => 'Create an account',
                'Ingia' => 'Sign in',
                'Pakua report ya tickets zinazoruhusiwa kwa role yako.' => 'Download the ticket report available to your role.',
                'Maelezo mazuri humsaidia technician kuanza haraka.' => 'A clear description helps the technician start quickly.',
                'Eleza tatizo kwa ufupi...' => 'Briefly describe the issue...',
                'Bado hujawasilisha ombi.' => 'You have not submitted a request yet.',
                'Ombi limetumwa. Namba ya ticket ni' => 'Request submitted. Ticket number:',
                'Asante. Maoni yako yamehifadhiwa na ticket imefungwa.' => 'Thank you. Your feedback was saved and the ticket was closed.',
                'Bora sana' => 'Excellent',
                'Nzuri' => 'Good',
                'Haijakidhi' => 'Unsatisfactory',
                'Mbaya' => 'Poor',
                'Anachoweza kufanya' => 'Can do',
                'Asichopaswa kufanya' => 'Cannot do',
                'Zinazoshughulikiwa' => 'In progress',
                'Nilizotatua' => 'Resolved by me',
                'Aliyeripoti / Tatizo' => 'Requester / Issue',
                'Maendeleo' => 'Progress',
                'Pandisha ngazi' => 'Escalate',
                'Andika maendeleo au utatuzi' => 'Enter progress or resolution notes',
                'Inaendelea' => 'In progress',
                'Imetatuliwa' => 'Resolved',
                'Hifadhi' => 'Save',
                'Pandisha kwa' => 'Escalate to',
                'Sababu ya kupandisha ngazi' => 'Reason for escalation',
                'Pandisha' => 'Escalate',
                'Thibitisha utatuzi' => 'Confirm resolution',
                'Chagua' => 'Select',
                'Maoni' => 'Feedback',
                'Angalia' => 'View',
                'Tarehe' => 'Date',
            ]);
        });
        $english_ui_enabled = true;
    }
}

/**
 * Escape user input for safe HTML output.
 */
function clean_input($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/** Build URLs that also work when the project is inside an XAMPP subfolder. */
function app_url($path = '')
{
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    $base = preg_replace('#/(auth|admin|helpdesk|technician|staff|includes)$#', '', $base);
    return $base . '/' . ltrim($path, '/');
}

/**
 * Require an authenticated session.
 */
function require_login()
{
    start_session_safe();
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . app_url('index.php'));
        exit();
    }
}

/**
 * Require a specific role for the current page.
 *
 * @param string $required_role  'admin' | 'helpdesk' | 'technician'
 */
function require_role($required_role)
{
    require_login();
    if ($_SESSION['role'] !== $required_role) {
        // Return users to the dashboard that belongs to their role.
        redirect_to_dashboard($_SESSION['role']);
        exit();
    }
}

/**
 * Redirect a user to the dashboard for their role.
 */
function redirect_to_dashboard($role)
{
    switch ($role) {
        case 'admin':
            header('Location: ' . app_url('admin/dashboard.php'));
            break;
        case 'helpdesk':
            header('Location: ' . app_url('helpdesk/dashboard.php'));
            break;
        case 'technician':
            header('Location: ' . app_url('technician/dashboard.php'));
            break;
        case 'staff':
            header('Location: ' . app_url('staff/dashboard.php'));
            break;
        default:
            header('Location: ' . app_url('index.php'));
    }
    exit();
}

/**
 * Record an action in the audit trail.
 */
function log_audit($pdo, $user_id, $action, $details = '')
{
    $stmt = $pdo->prepare(
        "INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)"
    );
    $stmt->execute([$user_id, $action, $details]);
}

/** Return a ticket count without repeating dashboard SQL. */
function ticket_count($pdo, $where = '', $params = [])
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets" . ($where ? " WHERE $where" : ''));
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/** Convert stored status values into readable English labels. */
function sw_status($status)
{
    $labels = [
        'pending' => 'Pending',
        'assigned' => 'Assigned',
        'in_progress' => 'In progress',
        'escalated' => 'Escalated',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];
    return $labels[$status] ?? $status;
}

/** Convert stored priority values into readable English labels. */
function sw_priority($priority)
{
    $labels = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'];
    return $labels[$priority] ?? $priority;
}
