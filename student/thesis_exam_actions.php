<?php
require_once __DIR__.'/dbconnect.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

function fail($msg, $code=400){
  http_response_code($code);
  echo json_encode(['success'=>false,'message'=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}

// Ενεργοποίησε exceptions
try {
  if ($pdo instanceof PDO) {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
  }
} catch (Throwable $e) {
  fail('Database connection error: ' . $e->getMessage(), 500);
}

if (empty($_SESSION['username'])) fail('Unauthorized', 401);

$role     = $_SESSION['role'] ?? '';
$username = $_SESSION['username'];

if ($role === 'secretary') fail('Μη εξουσιοδοτημένη πρόσβαση (Γραμματεία)', 403);

$reqThesisID = (int)($_POST['thesisID'] ?? 0);
if ($reqThesisID <= 0) fail('Λείπει thesisID');

// Φόρτωση Thesis + Student
try {
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
} catch (Throwable $e) {
  fail('Database query error: ' . $e->getMessage(), 500);
}

if (($T['th_status'] ?? '') !== 'EXAM') fail('Η πτυχιακή δεν είναι σε κατάσταση Υπό Εξέταση');

// Έλεγχος πρόσβασης
$canAccess = false;

if ($role === 'student' && $username === ($T['student_username'] ?? '')) {
  $canAccess = true;
}

if (!$canAccess && $role === 'teacher') {
  try {
    $qt = $pdo->prepare("SELECT id FROM teacher WHERE username = ? LIMIT 1");
    $qt->execute([$username]);
    $teacherId = (int)$qt->fetchColumn();

    if ($teacherId > 0) {
      if ((int)$T['supervisor'] === $teacherId) {
        $canAccess = true;
      } else {
        $qc = $pdo->prepare("
          SELECT 1 FROM committee
          WHERE thesisID = ? AND (member1 = ? OR member2 = ?)
          LIMIT 1
        ");
        $qc->execute([$reqThesisID, $teacherId, $teacherId]);
        if ($qc->fetchColumn()) $canAccess = true;
      }
    }
  } catch (Throwable $e) {
    fail('Access check error: ' . $e->getMessage(), 500);
  }
}

if (!$canAccess) fail('Δεν έχεις δικαίωμα πρόσβασης σε αυτή την πτυχιακή', 403);

// Διασφάλισε ότι υπάρχει γραμμή στο thesis_exam_meta
try {
  $pdo->beginTransaction();
  $chk = $pdo->prepare("SELECT thesisID FROM thesis_exam_meta WHERE thesisID=?");
  $chk->execute([$reqThesisID]);
  if (!$chk->fetchColumn()) {
    $ins = $pdo->prepare("
      INSERT INTO thesis_exam_meta (thesisID, created_at, updated_at)
      VALUES (?, NOW(), NOW())
    ");
    $ins->execute([$reqThesisID]);
  }
  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  fail('Meta preparation error: ' . $e->getMessage(), 500);
}

$action = $_POST['action'] ?? '';

try {
  // 1) Πρόχειρο & Σύνδεσμοι
  if ($action === 'save_draft') {
    
    // Links validation και processing
    $linksRaw = trim((string)($_POST['external_links'] ?? ''));
    $linksJson = '[]';
    
    if ($linksRaw !== '') {
      $linksArr = array_filter(array_map('trim', preg_split('/\R+/', $linksRaw)));
      
      $validLinks = [];
      foreach ($linksArr as $link) {
        if (filter_var($link, FILTER_VALIDATE_URL)) {
          $validLinks[] = $link;
        }
      }
      
      if (!empty($validLinks)) {
        $linksJson = json_encode(array_values($validLinks), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($linksJson === false) {
          fail('Σφάλμα επεξεργασίας συνδέσμων', 400);
        }
      }
    }

    $fileUploaded = false;
    $filePath = null;
    $fileSize = 0;

    // File upload handling - ΟΠΩΣ ΣΤΟΝ ΚΑΘΗΓΗΤΗ
    if (!empty($_FILES['draft_file']) && $_FILES['draft_file']['error'] === UPLOAD_ERR_OK) {
      $fileSize = (int)$_FILES['draft_file']['size'];
      $tmpName = $_FILES['draft_file']['tmp_name'];
      $originalName = $_FILES['draft_file']['name'];
      
      if ($fileSize === 0) {
        fail('Το αρχείο είναι κενό', 400);
      }
      
      // Δημιουργία φακέλου uploads/exam_drafts/
      $uploadDir = __DIR__ . '/uploads/exam_drafts/';
      if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0777, true)) {
          fail('Αδυναμία δημιουργίας φακέλου uploads', 500);
        }
      }
      
      // Δημιουργία μοναδικού ονόματος
      $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
      $fileName = uniqid('draft_' . $reqThesisID . '_', true) . '.' . $fileExtension;
      $fullPath = $uploadDir . $fileName;
      $relativePath = 'uploads/exam_drafts/' . $fileName;
      
      // Μετακίνηση αρχείου
      if (!@move_uploaded_file($tmpName, $fullPath)) {
        fail('Αποτυχία αποθήκευσης αρχείου', 500);
      }
      
      $filePath = $relativePath;
      $fileUploaded = true;
    }

    // Database update - ΑΠΟΘΗΚΗ PATH ΣΤΗ ΒΑΣΗ
    try {
      if ($fileUploaded && $filePath !== null) {
        // DELETE το παλιό αρχείο αν υπάρχει
        $oldFileQuery = $pdo->prepare("SELECT draft_file FROM thesis_exam_meta WHERE thesisID = ?");
        $oldFileQuery->execute([$reqThesisID]);
        $oldFile = $oldFileQuery->fetchColumn();
        
        if ($oldFile && file_exists(__DIR__ . '/' . $oldFile)) {
          @unlink(__DIR__ . '/' . $oldFile);
        }
        
        // UPDATE με νέο αρχείο
        $stmt = $pdo->prepare("
          UPDATE thesis_exam_meta
          SET draft_file = ?, external_links = ?, updated_at = NOW()
          WHERE thesisID = ?
        ");
        $result = $stmt->execute([$filePath, $linksJson, $reqThesisID]);
      } else {
        // UPDATE μόνο links
        $stmt = $pdo->prepare("
          UPDATE thesis_exam_meta
          SET external_links = ?, updated_at = NOW()
          WHERE thesisID = ?
        ");
        $result = $stmt->execute([$linksJson, $reqThesisID]);
      }
      
      if (!$result) {
        fail('Αποτυχία αποθήκευσης στη βάση δεδομένων', 500);
      }

      $message = 'Αποθηκεύτηκαν τα στοιχεία draft';
      if ($fileUploaded) {
        $message .= ' και το αρχείο (' . round($fileSize / 1024, 1) . ' KB)';
      }

      echo json_encode([
        'success' => true,
        'message' => $message,
        'file_uploaded' => $fileUploaded
      ], JSON_UNESCAPED_UNICODE);
      
    } catch (PDOException $e) {
      fail('Database error: ' . $e->getMessage(), 500);
    }
    
    exit;
  }

  // 2) Δήλωση εξέτασης
  if ($action === 'save_schedule') {
    $dt   = trim((string)($_POST['exam_datetime'] ?? ''));
    $room = trim((string)($_POST['exam_room'] ?? ''));
    $url  = trim((string)($_POST['exam_meeting_url'] ?? ''));

    $dtSQL = null;
    if ($dt !== '') {
      if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $dt)) {
        fail('Μη έγκυρη μορφή ημερομηνίας/ώρας', 400);
      }
      $dtSQL = str_replace('T', ' ', $dt) . ':00';
    }

    if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
      fail('Μη έγκυρος σύνδεσμος σύσκεψης', 400);
    }

    try {
      $q = $pdo->prepare("
        UPDATE thesis_exam_meta
        SET exam_datetime = ?, exam_room = ?, exam_meeting_url = ?, updated_at = NOW()
        WHERE thesisID = ?
      ");
      $q->execute([
        $dtSQL, 
        ($room !== '' ? $room : null), 
        ($url !== '' ? $url : null), 
        $reqThesisID
      ]);

      echo json_encode(['success'=>true,'message'=>'Αποθηκεύτηκε η δήλωση εξέτασης'], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
      fail('Database error: ' . $e->getMessage(), 500);
    }
    
    exit;
  }

  // 3) Μετά τη βαθμολόγηση
  if ($action === 'save_after') {
    try {
      $qg = $pdo->prepare("SELECT grading FROM thesis WHERE thesisID=?");
      $qg->execute([$reqThesisID]);
      $gradingFlag = (int)$qg->fetchColumn();
      
      if ($gradingFlag !== 1) {
        fail('Το βήμα αυτό ενεργοποιείται αφού ο επιβλέπων οριστικοποιήσει τη βαθμολόγηση', 403);
      }

      $report = trim((string)($_POST['report_url'] ?? ''));
      $repo   = trim((string)($_POST['repository_url'] ?? ''));

      if ($report !== '' && !filter_var($report, FILTER_VALIDATE_URL)) {
        fail('Μη έγκυρος σύνδεσμος πρακτικού', 400);
      }
      
      if ($repo !== '' && !filter_var($repo, FILTER_VALIDATE_URL)) {
        fail('Μη έγκυρος σύνδεσμος αποθετηρίου', 400);
      }

      $q = $pdo->prepare("
        UPDATE thesis_exam_meta
        SET report_url = ?, repository_url = ?, updated_at = NOW()
        WHERE thesisID = ?
      ");
      $q->execute([
        ($report !== '' ? $report : null), 
        ($repo !== '' ? $repo : null), 
        $reqThesisID
      ]);

      echo json_encode(['success'=>true,'message'=>'Αποθηκεύτηκαν οι σύνδεσμοι μετά τη βαθμολόγηση'], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
      fail('Database error: ' . $e->getMessage(), 500);
    }
    
    exit;
  }

  fail('Άγνωστη ενέργεια', 400);

} catch (Throwable $e) {
  error_log('thesis_exam_actions.php error: ' . $e->getMessage());
  fail('Σφάλμα διακομιστή: ' . $e->getMessage(), 500);
}
?>
