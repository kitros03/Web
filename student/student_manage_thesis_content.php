<?php
// student_manage_thesis_content.php — AJAX actions (PDO)
declare(strict_types=1);
session_start();
require_once '../dbconnect.php';

header('Content-Type: application/json; charset=utf-8');

function ok(array $p=[]): never { echo json_encode(['success'=>true]+$p, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function fail(string $m, int $c=400): never { http_response_code($c); echo json_encode(['success'=>false,'message'=>$m], JSON_UNESCAPED_UNICODE); exit; }

try {
  if (!isset($pdo) || !($pdo instanceof PDO)) fail('DB not ready', 500);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (Throwable $e) { fail('DB error: '.$e->getMessage(), 500); }

if (empty($_SESSION['username']) || (($_SESSION['role']??'')!=='student')) fail('Unauthorized', 401);

// Βρες φοιτητή + thesis
$st = $pdo->prepare("SELECT s.studentID, s.thesisID, t.th_status FROM student s JOIN thesis t ON t.thesisID=s.thesisID WHERE s.username=? LIMIT 1");
$st->execute([$_SESSION['username']]);
$me = $st->fetch(PDO::FETCH_ASSOC);
if (!$me) fail('Δεν έχει ανατεθεί πτυχιακή', 400);

$studentID = (int)$me['studentID'];
$thesisID  = (int)$me['thesisID'];
$thStatus  = strtoupper((string)$me['th_status']);

// Διασφάλιση meta row
$pdo->beginTransaction();
$chk = $pdo->prepare("SELECT 1 FROM thesis_exam_meta WHERE thesisID=?");
$chk->execute([$thesisID]);
if (!$chk->fetchColumn()) {
  $ins = $pdo->prepare("INSERT INTO thesis_exam_meta (thesisID, created_at, updated_at) VALUES (?, NOW(), NOW())");
  $ins->execute([$thesisID]);
}
$pdo->commit();

$action = $_POST['action'] ?? '';

// 1) Invite
if ($action === 'invite') {
  if ($thStatus !== 'ASSIGNED') fail('Η πρόσκληση επιτρέπεται μόνο σε κατάσταση Υπό Ανάθεση', 400);
  $teacherUsername = trim((string)($_POST['teacherUsername'] ?? ''));
  if ($teacherUsername==='') fail('Επέλεξε διδάσκοντα');

  $qt = $pdo->prepare("SELECT id FROM teacher WHERE username=? LIMIT 1");
  $qt->execute([$teacherUsername]);
  $tid = (int)$qt->fetchColumn();
  if ($tid<=0) fail('Διδάσκων δεν βρέθηκε');

  $qi = $pdo->prepare("INSERT INTO committeeInvitations (senderID, receiverID, invitationDate) VALUES (?, ?, CURDATE())");
  $qi->execute([$studentID, $tid]);

  ok(['message'=>'Η πρόσκληση στάλθηκε']);
}

// 2) Save draft
if ($action === 'save_draft') {
  if ($thStatus !== 'EXAM') fail('Το draft ενημερώνεται μόνο σε κατάσταση Υπό Εξέταση', 400);

  // Links
  $linksRaw = trim((string)($_POST['external_links'] ?? ''));
  $urls = [];
  if ($linksRaw!=='') {
    foreach (preg_split('/\R+/', $linksRaw) as $u) {
      $u = trim($u);
      if ($u!=='' && filter_var($u, FILTER_VALIDATE_URL)) $urls[] = $u;
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
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') fail('Επιτρέπεται μόνο PDF', 400);

    $dir = __DIR__.'/uploads/exam_drafts/';
    if (!is_dir($dir) && !@mkdir($dir, 0777, true)) fail('Αδυναμία δημιουργίας φακέλου uploads', 500);

    $newName = uniqid('draft_'.$thesisID.'_', true).'.pdf';
    $full = $dir.$newName;
    $rel  = 'uploads/exam_drafts/'.$newName;

    if (!move_uploaded_file($_FILES['draft_file']['tmp_name'], $full)) fail('Αποτυχία αποθήκευσης αρχείου', 500);

    // Διαγραφή παλιού
    $oldFile = $pdo->prepare("SELECT draft_file FROM thesis_exam_meta WHERE thesisID=?");
    $oldFile->execute([$thesisID]);
    $old = $oldFile->fetchColumn();
    if ($old && file_exists(__DIR__.'/'.$old)) @unlink(__DIR__.'/'.$old);

    $u = $pdo->prepare("UPDATE thesis_exam_meta SET draft_file=?, external_links=?, updated_at=NOW() WHERE thesisID=?");
    $u->execute([$rel, $linksJson, $thesisID]);

    $fileUploaded = true;
    $savedPath = $rel;
  } else {
    $u = $pdo->prepare("UPDATE thesis_exam_meta SET external_links=?, updated_at=NOW() WHERE thesisID=?");
    $u->execute([$linksJson, $thesisID]);
  }

  $msg = 'Αποθηκεύτηκαν τα στοιχεία draft';
  if ($fileUploaded) $msg .= ' και το αρχείο ('.round($bytes/1024,1).' KB)';
  ok(['message'=>$msg, 'file_uploaded'=>$fileUploaded, 'draft_file'=>$savedPath]);
}

// 3) Save schedule
if ($action === 'save_schedule') {
  if ($thStatus !== 'EXAM') fail('Η δήλωση εξέτασης γίνεται μόνο σε κατάσταση Υπό Εξέταση', 400);

  $dt  = trim((string)($_POST['exam_datetime'] ?? ''));
  $room= trim((string)($_POST['exam_room'] ?? ''));
  $url = trim((string)($_POST['exam_meeting_url'] ?? ''));

  $dtSQL = null;
  if ($dt!=='') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $dt)) fail('Μη έγκυρη μορφή ημερομηνίας/ώρας', 400);
    $dtSQL = str_replace('T',' ',$dt).':00';
  }
  if ($url!=='' && !filter_var($url, FILTER_VALIDATE_URL)) fail('Μη έγκυρος σύνδεσμος σύσκεψης', 400);

  $u = $pdo->prepare("UPDATE thesis_exam_meta SET exam_datetime=?, exam_room=?, exam_meeting_url=?, updated_at=NOW() WHERE thesisID=?");
  $u->execute([
    $dtSQL,
    ($room !== '' ? $room : null),
    ($url  !== '' ? $url  : null),
    $thesisID
  ]);
  ok(['message'=>'Αποθηκεύτηκε η δήλωση εξέτασης']);
}

// 4) Save after (repository)
if ($action === 'save_after') {
  // Προαπαιτούμενο grading=1
  $g = $pdo->prepare("SELECT grading FROM thesis WHERE thesisID=?");
  $g->execute([$thesisID]);
  $flag = (int)$g->fetchColumn();
  if ($flag !== 1) fail('Το βήμα αυτό ενεργοποιείται αφού ο επιβλέπων οριστικοποιήσει τη βαθμολόγηση', 403);

  $repo = trim((string)($_POST['repository_url'] ?? ''));
  if ($repo!=='' && !filter_var($repo, FILTER_VALIDATE_URL)) fail('Μη έγκυρος σύνδεσμος αποθετηρίου', 400);

  $u = $pdo->prepare("UPDATE thesis_exam_meta SET repository_url=?, updated_at=NOW() WHERE thesisID=?");
  $u->execute([($repo!==''?$repo:null), $thesisID]);

  ok(['message'=>'Αποθηκεύτηκε ο σύνδεσμος αποθετηρίου']);
}

fail('Άγνωστη ενέργεια', 400);
