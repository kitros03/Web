<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username']) || !isset($_SESSION['type']) || $_SESSION['type'] !== 'secretary') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/dbconnect.php'; // PDO: $pdo

// Λίστα ΔΕ σε ACTIVE ή EXAM
// Υπολογισμός ημερών από την επίσημη ανάθεση: από την πρώτη ημερομηνία changeTo='ASSIGNED'.
// Αν δεν υπάρχει, από την πρώτη ημερομηνία changeTo='ACTIVE'. Αν δεν υπάρχει ούτε αυτό, null.

$sql = "
SELECT
  t.thesisID,
  t.title,
  t.th_description,
  t.th_status,
  CONCAT(te.t_fname, ' ', te.t_lname) AS supervisor_name,
  CONCAT(s.s_fname, ' ', s.s_lname)   AS student_name,
  (
    SELECT MIN(changeDate)
    FROM thesisStatusChanges sc
    WHERE sc.thesisID = t.thesisID AND sc.changeTo = 'ASSIGNED'
  ) AS assigned_date_assigned,
  (
    SELECT MIN(changeDate)
    FROM thesisStatusChanges sc
    WHERE sc.thesisID = t.thesisID AND sc.changeTo = 'ACTIVE'
  ) AS assigned_date_active
FROM thesis t
LEFT JOIN teacher te ON te.id = t.supervisor
LEFT JOIN student s  ON s.thesisID = t.thesisID
WHERE t.th_status IN ('ACTIVE', 'EXAM')
ORDER BY t.thesisID DESC
";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$out = [];
$today = new DateTimeImmutable('today');

foreach ($rows as $r) {
    $assigned = $r['assigned_date_assigned'] ?: $r['assigned_date_active']; // string ή null
    $days = null;
    if ($assigned) {
        try {
            $ad = new DateTimeImmutable($assigned);
            $days = (int)$today->diff($ad)->format('%a');
        } catch (Throwable $e) { $days = null; }
    }
    $out[] = [
        'thesisID'             => (int)$r['thesisID'],
        'title'                => $r['title'],
        'th_description'       => $r['th_description'],
        'th_status'            => $r['th_status'],
        'supervisor_name'      => $r['supervisor_name'] ?: null,
        'student_name'         => $r['student_name'] ?: null,
        'days_since_assignment'=> $days
    ];
}

echo json_encode($out);
