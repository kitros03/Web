<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'secretary') {
  http_response_code(403);
  echo json_encode(['error' => 'Forbidden']); exit;
}

require_once __DIR__ . '/dbconnect.php'; // $pdo

// Επιστρέφουμε ΔΕ με ACTIVE, EXAM ή DONE
$sql = "
WITH latest_change AS (
  SELECT sc.thesisID, sc.changeTo AS latest_status, sc.changeDate
  FROM thesisStatusChanges sc
  JOIN (
    SELECT thesisID, MAX(changeDate) AS maxd
    FROM thesisStatusChanges
    GROUP BY thesisID
  ) x ON x.thesisID = sc.thesisID AND x.maxd = sc.changeDate
)
SELECT
  t.thesisID,
  t.title,
  t.th_description,
  COALESCE(t.th_status, lc.latest_status) AS th_status,
  CONCAT(te.t_fname, ' ', te.t_lname) AS supervisor_name,
  CONCAT(s.s_fname, ' ', s.s_lname)   AS student_name,
  (
    SELECT MIN(changeDate)
    FROM thesisStatusChanges scA
    WHERE scA.thesisID = t.thesisID AND scA.changeTo = 'ASSIGNED'
  ) AS assigned_date_assigned,
  (
    SELECT MIN(changeDate)
    FROM thesisStatusChanges scB
    WHERE scB.thesisID = t.thesisID AND scB.changeTo = 'ACTIVE'
  ) AS assigned_date_active
FROM thesis t
LEFT JOIN latest_change lc ON lc.thesisID = t.thesisID
LEFT JOIN teacher te ON te.id = t.supervisor
LEFT JOIN student s  ON s.thesisID = t.thesisID
WHERE (t.th_status IN ('ACTIVE','EXAM','DONE') OR lc.latest_status IN ('ACTIVE','EXAM','DONE'))
ORDER BY t.thesisID DESC
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$today = new DateTimeImmutable('today');
$out = [];
foreach ($rows as $r) {
  $assigned = $r['assigned_date_assigned'] ?: $r['assigned_date_active'];
  $days = null;
  if ($assigned) {
    try { $days = (int)$today->diff(new DateTimeImmutable($assigned))->format('%a'); } catch (Throwable $e) {}
  }
  $out[] = [
    'thesisID' => (int)$r['thesisID'],
    'title'    => $r['title'],
    'th_description' => $r['th_description'],
    'th_status'=> $r['th_status'],
    'supervisor_name' => $r['supervisor_name'] ?: null,
    'student_name'    => $r['student_name'] ?: null,
    'days_since_assignment' => $days
  ];
}
echo json_encode($out);
