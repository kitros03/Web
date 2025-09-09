<?php
require_once __DIR__.'/dbconnect.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'student') {
  echo json_encode(['success'=>false,'message'=>'Not authenticated.']); exit;
}

$st = $pdo->prepare("SELECT studentID, thesisID FROM student WHERE username=? LIMIT 1");
$st->execute([$_SESSION['username']]);
$student = $st->fetch(PDO::FETCH_ASSOC);
if (!$student || !$student['thesisID']) {
  echo json_encode(['success'=>false,'message'=>'No thesis assigned.']); exit;
}
$studentID = (int)$student['studentID'];
$thesisID  = (int)$student['thesisID'];

$action = $_POST['action'] ?? 'list';

function listInvitations(PDO $pdo, int $studentID): array {
  $q = $pdo->prepare("
    SELECT ci.invitationID, ci.invitationDate, ci.response, ci.responseDate,
           t.username AS teacher_username, CONCAT(t.t_fname,' ',t.t_lname) AS teacher_name
    FROM committeeInvitations ci
    JOIN teacher t ON t.id = ci.receiverID
    WHERE ci.senderID = ?
    ORDER BY ci.invitationDate DESC, ci.invitationID DESC
  ");
  $q->execute([$studentID]);
  return $q->fetchAll(PDO::FETCH_ASSOC);
}

try {
  if ($action === 'invite') {
    $teacherUsername = trim((string)($_POST['teacherUsername'] ?? ''));
    if ($teacherUsername === '') {
      echo json_encode(['success'=>false,'message'=>'Δώσε username διδάσκοντα.']); exit;
    }
    $q = $pdo->prepare("SELECT id FROM teacher WHERE username=? LIMIT 1");
    $q->execute([$teacherUsername]);
    $teacherID = (int)$q->fetchColumn();
    if ($teacherID <= 0) {
      echo json_encode(['success'=>false,'message'=>'Διδάσκων δεν βρέθηκε.']); exit;
    }
    // αποφυγή διπλής εκκρεμούς
    $dup = $pdo->prepare("SELECT 1 FROM committeeInvitations WHERE senderID=? AND receiverID=? AND response IS NULL LIMIT 1");
    $dup->execute([$studentID, $teacherID]);
    if ($dup->fetch()) {
      echo json_encode(['success'=>false,'message'=>'Υπάρχει ήδη εκκρεμής πρόσκληση.']); exit;
    }
    $ins = $pdo->prepare("
      INSERT INTO committeeInvitations (senderID, receiverID, invitationDate, response, responseDate)
      VALUES (?, ?, CURDATE(), NULL, NULL)
    ");
    $ins->execute([$studentID, $teacherID]);
  }

  $rows = listInvitations($pdo, $studentID);
  echo json_encode(['success'=>true, 'invitations'=>$rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error','detail'=>$e->getMessage()]);
}
