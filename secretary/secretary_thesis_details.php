<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'secretary') {
  http_response_code(403);
  echo json_encode(['error' => 'Forbidden']); exit;
}

require_once '../dbconnect.php';

$thesisID = isset($_GET['thesisID']) ? (int)$_GET['thesisID'] : 0;
if ($thesisID <= 0) { http_response_code(400); echo json_encode(['error'=>'Invalid thesisID']); exit; }

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
WHERE t.thesisID = ?
LIMIT 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$thesisID]);
$th = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$th) { http_response_code(404); echo json_encode(['error'=>'Not found']); exit; }

$sqlC = "
SELECT
  CONCAT(t1.t_fname, ' ', t1.t_lname) AS supervisor_name,
  CONCAT(t2.t_fname, ' ', t2.t_lname) AS member1_name,
  CONCAT(t3.t_fname, ' ', t3.t_lname) AS member2_name
FROM committee c
LEFT JOIN teacher t1 ON t1.id = c.supervisor
LEFT JOIN teacher t2 ON t2.id = c.member1
LEFT JOIN teacher t3 ON t3.id = c.member2
WHERE c.thesisID = ?
";
$stmtC = $pdo->prepare($sqlC);
$stmtC->execute([$thesisID]);
$comm = $stmtC->fetch(PDO::FETCH_ASSOC);
$committee = [];
if ($comm) {
  if (!empty($comm['supervisor_name'])) $committee[] = 'Επιβλέπων: ' . $comm['supervisor_name'];
  if (!empty($comm['member1_name']))   $committee[] = 'Μέλος: ' . $comm['member1_name'];
  if (!empty($comm['member2_name']))   $committee[] = 'Μέλος: ' . $comm['member2_name'];
}

$assigned = $th['assigned_date_assigned'] ?: $th['assigned_date_active'];
$days = null;
if ($assigned) {
  try {
    $today = new DateTimeImmutable('today');
    $days = (int)$today->diff(new DateTimeImmutable($assigned))->format('%a');
  } catch (Throwable $e) {}
}

echo json_encode([
  'thesisID'              => (int)$th['thesisID'],
  'title'                 => $th['title'],
  'th_description'        => $th['th_description'],
  'th_status'             => $th['th_status'],
  'supervisor_name'       => $th['supervisor_name'] ?: null,
  'student_name'          => $th['student_name'] ?: null,
  'assigned_date'         => $assigned ?: null,
  'days_since_assignment' => $days,
  'committee'             => $committee
]);
?>