<?php
require_once __DIR__.'/dbconnect.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

function fail($msg, $code=400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$msg]); exit; }

// 1) Αυθεντικοποίηση
if (empty($_SESSION['username'])) fail('Unauthorized', 401);

$role     = $_SESSION['role'] ?? '';
$username = $_SESSION['username'];

// Γραμματεία δεν έχει πρόσβαση στις ενέργειες
if ($role === 'secretary') fail('Μη εξουσιοδοτημένη πρόσβαση (Γραμματεία)', 403);

// 2) thesisID από το POST
$reqThesisID = (int)($_POST['thesisID'] ?? 0);
if ($reqThesisID <= 0) fail('Λείπει thesisID');

// 3) Πληροφορίες πτυχιακής + χρήστη
$q = $pdo->prepare("
  SELECT t.thesisID, t.th_status, t.supervisor,
         s.studentID, s.username AS student_username
  FROM thesis t
  LEFT JOIN student s ON s.thesisID = t.thesisID
  WHERE t.thesisID = ? LIMIT 1
");
$q->execute([$reqThesisID]);
$T = $q->fetch(PDO::FETCH_ASSOC);
if (!$T) fail('Δεν βρέθηκε η πτυχιακή');

// Πρέπει να είναι σε EXAM
if (($T['th_status'] ?? '') !== 'EXAM') fail('Η πτυχιακή δεν είναι σε κατάσταση Υπό Εξέταση');

// 4) Έλεγχος πρόσβασης: φοιτητής της πτυχιακής ή επιβλέπων ή αποδεκτό μέλος επιτροπής
$canAccess = false;

// α) Φοιτητής
if ($role === 'student' && $username === ($T['student_username'] ?? '')) $canAccess = true;

// β) Επιβλέπων ή μέλος τριμελούς (ACCEPTED)
if (!$canAccess && $role === 'teacher') {
  $qt = $pdo->prepare("SELECT id FROM teacher WHERE username = ? LIMIT 1");
  $qt->execute([$username]);
  $teacherId = (int)$qt->fetchColumn();

  if ($teacherId > 0) {
    if ((int)$T['supervisor'] === $teacherId) {
      $canAccess = true;
    } else {
      $qc = $pdo->prepare("
        SELECT 1
        FROM committeeMembers
        WHERE thesisID = ? AND teacherID = ? AND status = 'ACCEPTED'
        LIMIT 1
      ");
      $qc->execute([$reqThesisID, $teacherId]);
      if ($qc->fetchColumn()) $canAccess = true;
    }
  }
}
if (!$canAccess) fail('Δεν έχεις δικαίωμα πρόσβασης σε αυτή την πτυχιακή', 403);

// 5) Εξασφάλισε ότι υπάρχει γραμμή στο thesis_exam_meta (transaction)
$pdo->beginTransaction();
try {
  $chk = $pdo->prepare("SELECT thesisID FROM thesis_exam_meta WHERE thesisID=?");
  $chk->execute([$reqThesisID]);
  if (!$chk->fetchColumn()) {
    $ins = $pdo->prepare("INSERT INTO thesis_exam_meta (thesisID, created_at, updated_at) VALUES (?, NOW(), NOW())");
    $ins->execute([$reqThesisID]);
  }
  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  fail('Αποτυχία προετοιμασίας meta', 500);
}

$action = $_POST['action'] ?? '';

try {
  // 6) Αποθήκευση Draft + Links
  if ($action === 'save_draft') {
    // Idempotent διασφάλιση ύπαρξης γραμμής (σε περίπτωση που παρακαμφθεί το παραπάνω)
    $pdo->prepare("
      INSERT IGNORE INTO thesis_exam_meta (thesisID, created_at, updated_at)
      VALUES (?, NOW(), NOW())
    ")->execute([$reqThesisID]);

    // Links -> JSON string (δουλεύει και αν η στήλη είναι TEXT)
    $linksRaw  = (string)($_POST['external_links'] ?? '');
    $linksArr  = array_filter(array_map('trim', preg_split('/\R+/', $linksRaw)));
    $linksJson = json_encode(array_values($linksArr), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

    // Προαιρετικό upload
    $draftPath = null;
    if (!empty($_FILES['draft_file']) && $_FILES['draft_file']['error'] === UPLOAD_ERR_OK && (int)$_FILES['draft_file']['size'] > 0) {
      $uploadDir = __DIR__.'/uploads/exam_drafts/';
      if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
      if (!is_dir($uploadDir) || !is_writable($uploadDir)) fail('Ο φάκελος upload δεν είναι εγγράψιμος', 500);

      $base = basename((string)$_FILES['draft_file']['name']);
      $safe = preg_replace('/[^A-Za-z0-9._-]/','_', $base);
      $name = uniqid('draft_', true).'_'.$safe;
      $full = $uploadDir.$name;

      if (!@move_uploaded_file($_FILES['draft_file']['tmp_name'], $full)) fail('Αποτυχία μεταφοράς αρχείου', 500);

      $draftPath = 'uploads/exam_drafts/'.$name;
    }

    if ($draftPath) {
      $q = $pdo->prepare("UPDATE thesis_exam_meta SET draft_file=?, external_links=?, updated_at=NOW() WHERE thesisID=?");
      $q->execute([$draftPath, $linksJson, $reqThesisID]);
    } else {
      $q = $pdo->prepare("UPDATE thesis_exam_meta SET external_links=?, updated_at=NOW() WHERE thesisID=?");
      $q->execute([$linksJson, $reqThesisID]);
    }

    echo json_encode(['success'=>true,'message'=>'Αποθηκεύτηκαν τα στοιχεία draft','reload'=> (bool)$draftPath]); exit;
  }

  // 7) Αποθήκευση Δήλωσης Εξέτασης
  if ($action === 'save_schedule') {
    $dt   = trim((string)($_POST['exam_datetime'] ?? ''));
    $room = trim((string)($_POST['exam_room'] ?? ''));
    $url  = trim((string)($_POST['exam_meeting_url'] ?? ''));

    if ($dt !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $dt)) {
      fail('Μη έγκυρη μορφή ημερομηνίας/ώρας');
    }
    $dtSQL = $dt ? (str_replace('T',' ', $dt).':00') : null;

    $q = $pdo->prepare("
      UPDATE thesis_exam_meta
      SET exam_datetime = ?, exam_room = ?, exam_meeting_url = ?, updated_at = NOW()
      WHERE thesisID = ?
    ");
    $q->execute([$dtSQL, ($room !== '' ? $room : null), ($url !== '' ? $url : null), $reqThesisID]);

    echo json_encode(['success'=>true,'message'=>'Αποθηκεύτηκε η δήλωση εξέτασης']); exit;
  }

  // 8) Αποθήκευση “Μετά τη βαθμολόγηση” (κλειδωμένο έως grading=1)
  if ($action === 'save_after') {
    $qg = $pdo->prepare("SELECT grading FROM thesis WHERE thesisID=?");
    $qg->execute([$reqThesisID]);
    $gradingFlag = (int)$qg->fetchColumn();
    if ($gradingFlag !== 1) fail('Το βήμα αυτό ενεργοποιείται αφού ο επιβλέπων οριστικοποιήσει τη βαθμολόγηση', 403);

    $report = trim((string)($_POST['report_url'] ?? ''));
    $repo   = trim((string)($_POST['repository_url'] ?? ''));

    $q = $pdo->prepare("
      UPDATE thesis_exam_meta
      SET report_url = ?, repository_url = ?, updated_at = NOW()
      WHERE thesisID = ?
    ");
    $q->execute([($report !== '' ? $report : null), ($repo !== '' ? $repo : null), $reqThesisID]);

    echo json_encode(['success'=>true,'message'=>'Αποθηκεύτηκαν οι σύνδεσμοι μετά τη βαθμολόγηση']); exit;
  }

  fail('Άγνωστη ενέργεια', 400);

} catch (Throwable $e) {
  http_response_code(500);
  // Αν θέλεις προσωρινά για debug: echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
  echo json_encode(['success'=>false,'message'=>'Σφάλμα διακομιστή']);
}
