<?php
require_once '../dbconnect.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'student') {
  echo json_encode(['success'=>false,'message'=>'Not authenticated.']); exit;
}

$sql = "
SELECT t.thesisID, t.title, t.th_description, t.pdf_description, t.th_status,
       CONCAT(sup.t_fname,' ',sup.t_lname) AS supervisor_name,
       CONCAT(m1.t_fname,' ',m1.t_lname)   AS member1_name,
       CONCAT(m2.t_fname,' ',m2.t_lname)   AS member2_name,
       (SELECT MIN(changeDate) FROM thesisStatusChanges
         WHERE thesisID=t.thesisID AND changeTo='ASSIGNED') AS assigned_date
FROM thesis t
JOIN student st ON st.thesisID = t.thesisID
LEFT JOIN committee c ON c.thesisID = t.thesisID
LEFT JOIN teacher sup ON sup.id = c.supervisor
LEFT JOIN teacher m1  ON m1.id  = c.member1
LEFT JOIN teacher m2  ON m2.id  = c.member2
WHERE st.username = ?
LIMIT 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['username']]);
$th = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$th) { echo json_encode(['success'=>false,'message'=>'No thesis assigned.']); exit; }

// Επιβλέπων
$supervisor_name = $th['supervisor_name'] ?? null;

// Επιτροπή: πρώτος ο επιβλέπων
$committee = [];
if (!empty($supervisor_name))       $committee[] = $supervisor_name;
if (!empty($th['member1_name']))    $committee[] = $th['member1_name'];
if (!empty($th['member2_name']))    $committee[] = $th['member2_name'];


$currStatus = $th['th_status'] ?? 'NOT_ASSIGNED';
if ($currStatus === 'ASSIGNED' && count($committee) >= 3) {
  $pdo->prepare("UPDATE thesis SET th_status='ACTIVE' WHERE thesisID=?")->execute([(int)$th['thesisID']]);
  $pdo->prepare("INSERT INTO thesisStatusChanges (thesisID, changeDate, changeTo) VALUES (?, CURDATE(), 'ACTIVE')")
      ->execute([(int)$th['thesisID']]);
  $pdo->prepare("
    DELETE ci FROM committeeInvitations ci
    JOIN student s ON s.studentID = ci.senderID
    WHERE s.thesisID=? AND ci.response IS NULL
  ")->execute([(int)$th['thesisID']]);
  $currStatus = 'ACTIVE';
}

// Ημέρες από ανάθεση
$days = null;
if (!empty($th['assigned_date'])) {
  try {
    $today = new DateTimeImmutable('today');
    $ad = new DateTimeImmutable($th['assigned_date']);
    $days = (int)$today->diff($ad)->format('%a');
  } catch (Throwable $e) {
    $days = null;
  }
}

echo json_encode([
  'success' => true,
  'title'   => $th['title'] ?? null,
  'desc'    => $th['th_description'] ?? null,
  'file'    => $th['pdf_description'] ?? null,
  'status'  => $currStatus,
  'supervisor_name' => $supervisor_name,
  'committee' => $committee,
  'days_since_assignment' => $days
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
