<?php
// student_manage_thesis.php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/dbconnect.php';

if (empty($_SESSION['username'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Not authenticated.'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  // Βρες φοιτητή + πτυχιακή
  $stmt = $pdo->prepare("SELECT id, studentID, thesisID FROM student WHERE username = ? LIMIT 1");
  $stmt->execute([$_SESSION['username']]);
  $student = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$student || !$student['thesisID']) {
    echo json_encode(['success' => false, 'message' => 'No thesis assigned.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $thesisID  = (int)$student['thesisID'];
  $studentID = (int)$student['studentID'];

  $action = $_POST['action'] ?? null;

  // ====== INVITE: INSERT στο committeeInvitations ======
  if ($action === 'invite') {
    // Μπορεί να έρθει teacherID ή teacherUsername από τη φόρμα
    $teacherID = isset($_POST['teacherID']) ? (int)$_POST['teacherID'] : 0;
    $teacherUsername = trim((string)($_POST['teacherUsername'] ?? ''));

    if ($teacherID <= 0 && $teacherUsername !== '') {
      $res = $pdo->prepare("SELECT id FROM teacher WHERE username = ? LIMIT 1");
      $res->execute([$teacherUsername]);
      $teacherID = (int)$res->fetchColumn();
    }

    if ($teacherID <= 0) {
      echo json_encode(['success' => false, 'message' => 'Μη έγκυρος διδάσκων.'], JSON_UNESCAPED_UNICODE);
      exit;
    }

    // Επιβεβαίωση ύπαρξης διδάσκοντα
    $st = $pdo->prepare("SELECT id FROM teacher WHERE id = ? LIMIT 1");
    $st->execute([$teacherID]);
    if (!$st->fetch()) {
      echo json_encode(['success' => false, 'message' => 'Διδάσκων δεν βρέθηκε.'], JSON_UNESCAPED_UNICODE);
      exit;
    }

    // Αποφυγή διπλής εκκρεμούς πρόσκλησης
    $chk = $pdo->prepare("
      SELECT 1
      FROM committeeInvitations
      WHERE senderID = ? AND receiverID = ? AND response IS NULL
      LIMIT 1
    ");
    $chk->execute([$studentID, $teacherID]);
    if ($chk->fetch()) {
      echo json_encode(['success' => false, 'message' => 'Υπάρχει ήδη πρόσκληση σε εκκρεμότητα.'], JSON_UNESCAPED_UNICODE);
      exit;
    }

    // Εισαγωγή πρόσκλησης: invitationDate=CURDATE(), response=NULL, responseDate=NULL
    $ins = $pdo->prepare("
      INSERT INTO committeeInvitations
        (senderID, receiverID, invitationDate, response, responseDate)
      VALUES
        (?, ?, CURDATE(), NULL, NULL)
    ");
    $ins->execute([$studentID, $teacherID]);

    echo json_encode(['success' => true, 'message' => 'Η πρόσκληση στάλθηκε.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // ====== SAVE DRAFT ======
  if ($action === 'save_draft') {
    $draftUrl = trim((string)($_POST['draftUrl'] ?? ''));
    if ($draftUrl !== '') {
      $note = "[DRAFT_URL] " . $draftUrl;
      $ins = $pdo->prepare("INSERT INTO teacherNotes (thesisID, teacherID, description) VALUES (?, 0, ?)");
      $ins->execute([$thesisID, $note]);
    }
    echo json_encode(['success' => true, 'message' => 'Αποθηκεύτηκε ο σύνδεσμος πρόχειρου.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // ====== SAVE EXAM ======
  if ($action === 'save_exam') {
    $date  = trim((string)($_POST['examDate'] ?? ''));
    $time  = trim((string)($_POST['examTime'] ?? ''));
    $place = trim((string)($_POST['examPlace'] ?? ''));
    $payload = "[EXAM_INFO] date={$date} time={$time} place={$place}";
    $ins = $pdo->prepare("INSERT INTO teacherNotes (thesisID, teacherID, description) VALUES (?, 0, ?)");
    $ins->execute([$thesisID, $payload]);

    echo json_encode(['success' => true, 'message' => 'Αποθηκεύτηκαν τα στοιχεία εξέτασης.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // ====== SAVE LIBRARY ======
  if ($action === 'save_library') {
    $libraryUrl = trim((string)($_POST['libraryUrl'] ?? ''));
    if ($libraryUrl !== '') {
      $payload = "[LIBRARY_LINK] " . $libraryUrl;
      $ins = $pdo->prepare("INSERT INTO teacherNotes (thesisID, teacherID, description) VALUES (?, 0, ?)");
      $ins->execute([$thesisID, $payload]);
    }
    echo json_encode(['success' => true, 'message' => 'Αποθηκεύτηκε ο σύνδεσμος βιβλιοθήκης.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  echo json_encode(['success' => false, 'message' => 'Άγνωστη ενέργεια.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Server error', 'detail' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
