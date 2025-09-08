<?php
// thesis_details.php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/dbconnect.php';

// Απαίτηση σύνδεσης
if (empty($_SESSION['username'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Not authenticated.'], JSON_UNESCAPED_UNICODE);
  exit;
}

// 1) Βρες την πτυχιακή του φοιτητή (student.username -> student.thesisID -> thesis)
$sql = "
  SELECT 
    t.thesisID,
    t.title,
    t.th_description,
    t.pdf_description,
    t.th_status
  FROM student st
  JOIN thesis t ON t.thesisID = st.thesisID
  WHERE st.username = ?
  LIMIT 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['username']]);
$thesis = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$thesis) {
  echo json_encode(['success' => false, 'message' => 'Δεν βρέθηκαν στοιχεία.'], JSON_UNESCAPED_UNICODE);
  exit;
}

$thesisID = (int)$thesis['thesisID'];

// 2) Επιτροπή: επιβλέπων + μέχρι 2 μέλη
$committee = [];
$sqlC = "
  SELECT 
    CONCAT(sup.t_fname, ' ', sup.t_lname) AS supervisor_name,
    CONCAT(m1.t_fname,  ' ', m1.t_lname)  AS member1_name,
    CONCAT(m2.t_fname,  ' ', m2.t_lname)  AS member2_name
  FROM committee c
  LEFT JOIN teacher sup ON sup.id = c.supervisor
  LEFT JOIN teacher m1  ON m1.id = c.member1
  LEFT JOIN teacher m2  ON m2.id = c.member2
  WHERE c.thesisID = ?
  LIMIT 1
";
$stmtC = $pdo->prepare($sqlC);
$stmtC->execute([$thesisID]);
if ($row = $stmtC->fetch(PDO::FETCH_ASSOC)) {
  if (!empty($row['supervisor_name'])) $committee[] = $row['supervisor_name'];
  if (!empty($row['member1_name']))   $committee[] = $row['member1_name'];
  if (!empty($row['member2_name']))   $committee[] = $row['member2_name'];
}

// 3) Ημέρες από επίσημη ανάθεση (πρώτη φορά που έγινε ASSIGNED)
$sqlA = "
  SELECT MIN(changeDate) AS assigned_date
  FROM thesisStatusChanges
  WHERE thesisID = ? AND changeTo = 'ASSIGNED'
";
$stmtA = $pdo->prepare($sqlA);
$stmtA->execute([$thesisID]);
$assigned = $stmtA->fetchColumn();

$days_since_assignment = null;
if ($assigned) {
  $assignedDate = new DateTime($assigned);
  $now = new DateTime('today');
  $interval = $assignedDate->diff($now);
  $days_since_assignment = (int)$interval->format('%a');
}


