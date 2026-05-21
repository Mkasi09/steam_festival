<?php
require_once __DIR__ . '/config.php';

function cleanFileName(string $name): string
{
    $value = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $name);
    return trim($value ?? 'export', '_') ?: 'export';
}

function xlsCell(mixed $value): string
{
    $text = (string) ($value ?? '');
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function xlsTable(array $headers, array $rows): string
{
    $html = '<table class="data-table"><thead><tr>';
    foreach ($headers as $header) {
        $html .= '<th>' . xlsCell($header) . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    if (!$rows) {
        $html .= '<tr><td colspan="' . count($headers) . '" class="muted">No records found</td></tr>';
    }

    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $value) {
            $html .= '<td>' . xlsCell($value) . '</td>';
        }
        $html .= '</tr>';
    }

    return $html . '</tbody></table>';
}

function outXls(string $filename, string $body): never
{
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";
    echo '<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #18211f; }
        .title { background: #0f766e; color: #ffffff; font-size: 20px; font-weight: 700; }
        .subtitle { color: #65706b; font-weight: 700; }
        .section-title { background: #e7f5f2; color: #115e59; font-size: 15px; font-weight: 700; }
        table { border-collapse: collapse; margin-bottom: 18px; }
        th, td { border: 1px solid #b8c9c1; padding: 8px 10px; vertical-align: top; mso-number-format: "\@"; }
        th { background: #115e59; color: #ffffff; font-weight: 700; text-align: left; }
        .summary-box th { background: #cce5e0; color: #18211f; }
        .summary-box td { background: #f7fbfa; font-size: 16px; font-weight: 700; text-align: center; }
        .label { background: #f7fbfa; color: #65706b; font-weight: 700; }
        .muted { color: #65706b; font-style: italic; }
    </style>
</head>
<body>' . $body . '</body></html>';
    exit;
}

$pdo = db();
$type = $_GET['type'] ?? 'overall';
$schoolId = (int) ($_GET['school_id'] ?? 0);

if ($type === 'school' && $schoolId > 0) {
    $schoolStmt = $pdo->prepare(
        'SELECT s.id, s.name, s.emis_number, d.name AS district, c.name AS circuit, s.contact_person, s.phone, s.email, s.address
         FROM schools s
         LEFT JOIN districts d ON d.id = s.district_id
         LEFT JOIN circuits c ON c.id = s.circuit_id
         WHERE s.id = ?'
    );
    $schoolStmt->execute([$schoolId]);
    $school = $schoolStmt->fetch();

    if (!$school) {
        flash('School not found for export.', 'error');
        redirect('admin.php');
    }

    $statsStmt = $pdo->prepare(
        'SELECT
            COUNT(*) AS learner_count,
            SUM(CASE WHEN gender = "Female" THEN 1 ELSE 0 END) AS female_count,
            SUM(CASE WHEN gender = "Male" THEN 1 ELSE 0 END) AS male_count,
            SUM(CASE WHEN gender = "Other" THEN 1 ELSE 0 END) AS other_count
         FROM learners
         WHERE school_id = ?'
    );
    $statsStmt->execute([$schoolId]);
    $stats = $statsStmt->fetch();

    $learnersStmt = $pdo->prepare(
        'SELECT first_name, last_name, race, grade, gender, phone, email
         FROM learners
         WHERE school_id = ?
         ORDER BY last_name, first_name'
    );
    $learnersStmt->execute([$schoolId]);
    $learners = $learnersStmt->fetchAll();

    $learnerRows = [];
    foreach ($learners as $learner) {
        $learnerRows[] = [
            $learner['first_name'],
            $learner['last_name'],
            $learner['race'],
            $learner['grade'],
            $learner['gender'],
            formatPhone($learner['phone']),
            $learner['email'],
        ];
    }

    $body = '
        <table>
            <tr><td class="title" colspan="8">STEAM Festival School Participation Export</td></tr>
            <tr><td class="subtitle" colspan="8">Generated for: ' . xlsCell($school['name']) . '</td></tr>
        </table>
        <table class="summary-box">
            <tr>
                <th>Total Learners</th>
                <th>Female</th>
                <th>Male</th>
                <th>Other</th>
            </tr>
            <tr>
                <td>' . xlsCell((string) ($stats['learner_count'] ?? 0)) . '</td>
                <td>' . xlsCell((string) ($stats['female_count'] ?? 0)) . '</td>
                <td>' . xlsCell((string) ($stats['male_count'] ?? 0)) . '</td>
                <td>' . xlsCell((string) ($stats['other_count'] ?? 0)) . '</td>
            </tr>
        </table>
        <table>
            <tr><td class="section-title" colspan="8">School Details</td></tr>
            <tr>
                <td class="label">School</td><td>' . xlsCell($school['name']) . '</td>
                <td class="label">EMIS</td><td>' . xlsCell($school['emis_number']) . '</td>
                <td class="label">District</td><td>' . xlsCell($school['district']) . '</td>
                <td class="label">Circuit</td><td>' . xlsCell($school['circuit']) . '</td>
            </tr>
            <tr>
                <td class="label">Contact</td><td>' . xlsCell($school['contact_person']) . '</td>
                <td class="label">Phone</td><td>' . xlsCell(formatPhone($school['phone'])) . '</td>
                <td class="label">Email</td><td>' . xlsCell($school['email']) . '</td>
                <td class="label">Address</td><td>' . xlsCell($school['address']) . '</td>
            </tr>
        </table>
        <table>
            <tr><td class="section-title" colspan="7">Learner Entries</td></tr>
        </table>' .
        xlsTable(['First Name', 'Last Name', 'Race', 'Grade', 'Gender', 'Phone', 'Email'], $learnerRows);

    outXls('school_participation_' . cleanFileName((string) $school['name']), $body);
}

$overallStats = $pdo->query(
    'SELECT
        (SELECT COUNT(DISTINCT school_id) FROM learners) AS school_count,
        (SELECT COUNT(*) FROM learners) AS learner_count,
        (SELECT SUM(CASE WHEN gender = "Female" THEN 1 ELSE 0 END) FROM learners) AS female_count,
        (SELECT SUM(CASE WHEN gender = "Male" THEN 1 ELSE 0 END) FROM learners) AS male_count,
        (SELECT SUM(CASE WHEN gender = "Other" THEN 1 ELSE 0 END) FROM learners) AS other_count'
)->fetch();

$districtStats = $pdo->query(
    'SELECT d.name AS district, COUNT(DISTINCT s.id) AS schools, COUNT(l.id) AS learners
     FROM districts d
     LEFT JOIN schools s ON s.district_id = d.id
     JOIN learners l ON l.school_id = s.id
     GROUP BY d.id, d.name
     ORDER BY d.name'
)->fetchAll();

$participants = $pdo->query(
    'SELECT s.name AS school, s.emis_number, d.name AS district, c.name AS circuit, s.address,
            l.first_name, l.last_name, l.race, l.grade, l.gender, l.phone, l.email
     FROM schools s
     LEFT JOIN districts d ON d.id = s.district_id
     LEFT JOIN circuits c ON c.id = s.circuit_id
     JOIN learners l ON l.school_id = s.id
     ORDER BY s.name, l.last_name, l.first_name'
)->fetchAll();

$districtRows = [];
foreach ($districtStats as $district) {
    $districtRows[] = [$district['district'], (string) $district['schools'], (string) $district['learners']];
}

$participantRows = [];
foreach ($participants as $row) {
    $participantRows[] = [
        $row['school'],
        $row['emis_number'],
        $row['district'],
        $row['circuit'],
        $row['address'],
        $row['first_name'],
        $row['last_name'],
        $row['race'],
        $row['grade'],
        $row['gender'],
        formatPhone($row['phone']),
        $row['email'],
    ];
}

$body = '
    <table>
        <tr><td class="title" colspan="12">STEAM Festival Overall Participation Export</td></tr>
        <tr><td class="subtitle" colspan="12">Participation report with each field separated into its own Excel column.</td></tr>
    </table>
    <table class="summary-box">
        <tr>
            <th>Participating Schools</th>
            <th>Learner Entries</th>
            <th>Female</th>
            <th>Male</th>
            <th>Other</th>
        </tr>
        <tr>
            <td>' . xlsCell((string) ($overallStats['school_count'] ?? 0)) . '</td>
            <td>' . xlsCell((string) ($overallStats['learner_count'] ?? 0)) . '</td>
            <td>' . xlsCell((string) ($overallStats['female_count'] ?? 0)) . '</td>
            <td>' . xlsCell((string) ($overallStats['male_count'] ?? 0)) . '</td>
            <td>' . xlsCell((string) ($overallStats['other_count'] ?? 0)) . '</td>
        </tr>
    </table>
    <table>
        <tr><td class="section-title" colspan="3">District Summary</td></tr>
    </table>' .
    xlsTable(['District', 'Participating Schools', 'Learner Entries'], $districtRows) .
    '<table>
        <tr><td class="section-title" colspan="12">Participation Detail</td></tr>
    </table>' .
    xlsTable(
        ['School', 'EMIS', 'District', 'Circuit', 'Address', 'First Name', 'Last Name', 'Race', 'Grade', 'Gender', 'Phone', 'Email'],
        $participantRows
    );

outXls('overall_participation_export', $body);
