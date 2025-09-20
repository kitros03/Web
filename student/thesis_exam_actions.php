<?php

session_start();
require_once '../dbconnect.php'; 

header('Content-Type: application/json; charset=utf-8');

function ok($p=[]) { echo json_encode(['success'=>true]+$p, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function fail($m, $code=400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$m], JSON_UNESCAPED_UNICODE); exit; }


if (empty($_SESSION['username'])) fail('Unauthorized', 401);
$role = $_SESSION['role'] ?? '';
$username = $_SESSION['username'];
if ($role === 'secretary') fail('Μη εξουσιοδοτημένη πρόσβαση (Γραμματεία)', 403);


if (!isset($connection) || mysqli_connect_errno()) fail('DB error: '.mysqli_connect_error(), 500);


$thesisID = (int)($_POST['thesisID'] ?? 0);
if ($thesisID <= 0) fail('Λείπει thesisID');

$action = $_POST['action'] ?? '';


$sql = "SELECT t.thesisID, t.th_status, t.supervisor, s.studentID, s.username AS student_username
        FROM thesis t LEFT JOIN student s ON s.thesisID=t.thesisID
        WHERE t.thesisID=? LIMIT 1";
$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_bind_param($stmt, "i", $thesisID);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$T = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);
if (!$T) fail('Δεν βρέθηκε η πτυχιακή', 404);
if (($T['th_status'] ?? '') !== 'EXAM') fail('Η πτυχιακή δεν είναι σε κατάσταση Υπό Εξέταση', 400);


$can = false;
if ($role === 'student' && $username === ($T['student_username'] ?? '')) $can = true;
if (!$can && $role === 'teacher') {
  $stmt = mysqli_prepare($connection, "SELECT id FROM teacher WHERE username=? LIMIT 1");
  mysqli_stmt_bind_param($stmt, "s", $username);
  mysqli_stmt_execute($stmt);
  $rr = mysqli_stmt_get_result($stmt);
  $teacherId = ($rr && ($r = mysqli_fetch_row($rr))) ? (int)$r[0] : 0;
  mysqli_stmt_close($stmt);
  if ($teacherId > 0) {
    if ((int)$T['supervisor'] === $teacherId) $can = true;
    if (!$can) {
      $stmt = mysqli_prepare($connection, "SELECT 1 FROM committee WHERE thesisID=? AND (member1=? OR member2=?) LIMIT 1");
      mysqli_stmt_bind_param($stmt, "iii", $thesisID, $teacherId, $teacherId);
      mysqli_stmt_execute($stmt);
      $cr = mysqli_stmt_get_result($stmt);
      $can = (bool)($cr && mysqli_fetch_row($cr));
      mysqli_stmt_close($stmt);
    }
  }
}
if (!$can) fail('Δεν έχεις δικαίωμα πρόσβασης σε αυτή την πτυχιακή', 403);


$stmt = mysqli_prepare($connection, "SELECT thesisID FROM thesis_exam_meta WHERE thesisID=?");
mysqli_stmt_bind_param($stmt, "i", $thesisID);
mysqli_stmt_execute($stmt);
$er = mysqli_stmt_get_result($stmt);
$hasMeta = (bool)($er && mysqli_fetch_row($er));
mysqli_stmt_close($stmt);
if (!$hasMeta) {
  $stmt = mysqli_prepare($connection, "INSERT INTO thesis_exam_meta (thesisID, created_at, updated_at) VALUES (?, NOW(), NOW())");
  mysqli_stmt_bind_param($stmt, "i", $thesisID);
  if (!mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); fail('Meta preparation error: '.mysqli_error($connection), 500); }
  mysqli_stmt_close($stmt);
}

if ($action === 'save_draft') {
  
  $linksRaw = trim((string)($_POST['external_links'] ?? ''));
  $urls = [];
  if ($linksRaw !== '') {
    $lines = preg_split('/\R+/', $linksRaw);
    foreach ($lines as $u) {
      $u = trim($u);
      if ($u !== '' && filter_var($u, FILTER_VALIDATE_URL)) $urls[] = $u;
    }
  }
  $linksJson = json_encode(array_values($urls), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

  $fileUploaded = false;
  $savedPath = null;
  $bytes = 0;

  if (!empty($_FILES['draft_file']) && $_FILES['draft_file']['error'] === UPLOAD_ERR_OK) {
    $bytes = (int)$_FILES['draft_file']['size'];
    if ($bytes === 0) fail('Το αρχείο είναι κενό', 400);
    $name = $_FILES['draft_file']['name'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') fail('Επιτρέπεται μόνο PDF', 400);

    $dir = __DIR__.'/uploads/exam_drafts/';
    if (!is_dir($dir) && !@mkdir($dir, 0777, true)) fail('Αδυναμία δημιουργίας φακέλου uploads', 500);

    $newName = uniqid('draft_'.$thesisID.'_', true).'.pdf';
    $full = $dir.$newName;
    $rel  = 'uploads/exam_drafts/'.$newName;

    if (!move_uploaded_file($_FILES['draft_file']['tmp_name'], $full)) fail('Αποτυχία αποθήκευσης αρχείου', 500);

    // Διαγραφή παλιού αρχείου
    $stmt = mysqli_prepare($connection, "SELECT draft_file FROM thesis_exam_meta WHERE thesisID=?");
    mysqli_stmt_bind_param($stmt, "i", $thesisID);
    mysqli_stmt_execute($stmt);
    $gr = mysqli_stmt_get_result($stmt);
    $old = ($gr && ($r = mysqli_fetch_row($gr))) ? $r[0] : null;
    mysqli_stmt_close($stmt);
    if ($old && file_exists(__DIR__.'/'.$old)) @unlink(__DIR__.'/'.$old);

    
    $stmt = mysqli_prepare($connection, "UPDATE thesis_exam_meta SET draft_file=?, external_links=?, updated_at=NOW() WHERE thesisID=?");
    mysqli_stmt_bind_param($stmt, "ssi", $rel, $linksJson, $thesisID);
    if (!mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); fail('Αποτυχία αποθήκευσης στη βάση', 500); }
    mysqli_stmt_close($stmt);

    $fileUploaded = true;
    $savedPath = $rel;
  } else {
    
    $stmt = mysqli_prepare($connection, "UPDATE thesis_exam_meta SET external_links=?, updated_at=NOW() WHERE thesisID=?");
    mysqli_stmt_bind_param($stmt, "si", $linksJson, $thesisID);
    if (!mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); fail('Αποτυχία αποθήκευσης συνδέσμων', 500); }
    mysqli_stmt_close($stmt);
  }

  $msg = 'Αποθηκεύτηκαν τα στοιχεία draft';
  if ($fileUploaded) $msg .= ' και το αρχείο ('.round($bytes/1024,1).' KB)';
  ok(['message'=>$msg, 'file_uploaded'=>$fileUploaded, 'draft_file'=>$savedPath]);
}

if ($action === 'save_schedule') {
  $dt  = trim((string)($_POST['exam_datetime'] ?? ''));
  $room= trim((string)($_POST['exam_room'] ?? ''));
  $url = trim((string)($_POST['exam_meeting_url'] ?? ''));

  $dtSQL = null;
  if ($dt !== '') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $dt)) fail('Μη έγκυρη μορφή ημερομηνίας/ώρας', 400);
    $dtSQL = str_replace('T',' ',$dt).':00';
  }
  if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) fail('Μη έγκυρος σύνδεσμος σύσκεψης', 400);

  $roomOrNull = ($room !== '' ? $room : null);
  $urlOrNull  = ($url  !== '' ? $url  : null);

  $stmt = mysqli_prepare($connection, "UPDATE thesis_exam_meta
    SET exam_datetime=?, exam_room=?, exam_meeting_url=?, updated_at=NOW()
    WHERE thesisID=?");
  mysqli_stmt_bind_param($stmt, "sssi", $dtSQL, $roomOrNull, $urlOrNull, $thesisID);
  if (!mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); fail('Database error', 500); }
  mysqli_stmt_close($stmt);
  ok(['message'=>'Αποθηκεύτηκε η δήλωση εξέτασης']);
}

if ($action === 'save_after') {
  // Ενεργοποιείται όταν thesis.grading = 1
  $stmt = mysqli_prepare($connection, "SELECT grade FROM grades WHERE thesisID=?");
  mysqli_stmt_bind_param($stmt, "i", $thesisID);
  mysqli_stmt_execute($stmt);
  $gr = mysqli_stmt_get_result($stmt);
  $flag = ($gr && ($r = mysqli_fetch_row($gr))) ? (int)$r[0] : 0;
  mysqli_stmt_close($stmt);
  if ($flag !== 1) fail('Το βήμα αυτό ενεργοποιείται αφού ο επιβλέπων οριστικοποιήσει τη βαθμολόγηση', 403);

  $report = trim((string)($_POST['report_url'] ?? ''));
  $repo   = trim((string)($_POST['repository_url'] ?? ''));

  if ($report !== '' && !filter_var($report, FILTER_VALIDATE_URL)) fail('Μη έγκυρος σύνδεσμος πρακτικού', 400);
  if ($repo   !== '' && !filter_var($repo, FILTER_VALIDATE_URL)) fail('Μη έγκυρος σύνδεσμος αποθετηρίου', 400);

  $reportOrNull = ($report !== '' ? $report : null);
  $repoOrNull   = ($repo   !== '' ? $repo   : null);

  $stmt = mysqli_prepare($connection, "UPDATE thesis_exam_meta SET report_url=?, repository_url=?, updated_at=NOW() WHERE thesisID=?");
  mysqli_stmt_bind_param($stmt, "ssi", $reportOrNull, $repoOrNull, $thesisID);
  if (!mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); fail('Database error', 500); }
  mysqli_stmt_close($stmt);
  ok(['message'=>'Αποθηκεύτηκαν οι σύνδεσμοι μετά τη βαθμολόγηση']);
}

fail('Άγνωστη ενέργεια', 400);
