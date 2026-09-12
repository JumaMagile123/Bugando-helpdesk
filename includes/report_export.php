<?php
/**
 * Report helper rahisi kwa Admin, HelpDesk na Technician.
 * Excel hutolewa kama HTML table inayofunguka moja kwa moja kwenye Excel.
 * PDF inatengenezwa hapa kwa PHP bila library kubwa ya ziada.
 */
function report_rows($pdo, $role, $user_id)
{
    $where = '';
    $params = [];
    if ($role === 'technician') {
        $where = 'WHERE t.assigned_to = ?';
        $params[] = $user_id;
    }

    $stmt = $pdo->prepare("SELECT t.ticket_no, u.full_name AS requester, COALESCE(d.name, 'Not specified') AS department_name, COALESCE(c.name, 'Not specified') AS category_name, t.location, t.priority, t.status, t.created_at, t.updated_at FROM tickets t JOIN users u ON u.id = t.user_id LEFT JOIN departments d ON d.id = t.department_id LEFT JOIN categories c ON c.id = t.category_id $where ORDER BY t.created_at DESC");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function report_ascii($value)
{
    return trim(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $value));
}

function download_excel($rows, $filename)
{
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    echo "<table border='1'><tr><th>Ticket</th><th>Requester</th><th>Department</th><th>Category</th><th>Location</th><th>Priority</th><th>Status</th><th>Created</th><th>Updated</th></tr>";
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $value) { echo '<td>' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</td>'; }
        echo '</tr>';
    }
    echo '</table>';
    exit();
}

function download_pdf($rows, $filename)
{
    $lines = ['Bugando Medical Centre - ICT HelpDesk Report', 'Generated: ' . date('Y-m-d H:i'), ''];
    foreach ($rows as $row) {
        $lines[] = report_ascii($row['ticket_no'] . ' | ' . $row['status'] . ' | ' . $row['priority'] . ' | ' . $row['department_name']);
    }
    $stream = "BT\n/F1 10 Tf\n50 790 Td\n";
    foreach ($lines as $index => $line) {
        if ($index > 0) { $stream .= "0 -16 Td\n"; }
        $stream .= '(' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], substr($line, 0, 110)) . ") Tj\n";
    }
    $stream .= "ET";
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream",
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $number => $object) { $offsets[] = strlen($pdf); $pdf .= ($number + 1) . " 0 obj\n" . $object . "\nendobj\n"; }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) { $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n"; }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    echo $pdf;
    exit();
}
