<?php
declare(strict_types=1);
session_start();
require_once '../dbconnect.php';
header('Content-Type: application/json; charset=utf-8');

function ok(array $p=[]): never { echo json_encode(['success'=>true]+$p, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function fail(string $m,int $c=400): never { http_response_code($c); echo json_encode(['success'=>false,'message'=>$m], JSON_UNESCAPED_UNICODE); exit; }

try {
  if (!isset($pdo) || !($pdo instanceof PDO)) fail('DB not ready', 500);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (Throwable $e) { fail('DB error: '.$e->getMessage(), 500); }

if (empty($_SESSION['username']) || (($_SESSION['role']??'')!=='student')) fail('Unauthorized', 401);

// Thesis του φοιτητή
$st = $pdo->prepare("
  SELECT s.studentID, s.thesisID, t.supervisor, t.th_status
  FROM student s
  JOIN thesis t ON t.thesisID = s.thesisID
  WHERE s.username=? LIMIT 1
");
$st->execute([$_SESSION['username']]);
$me = $st->fetch(PDO::FETCH_ASSOC);

if (!$me) ok([
  'view'  => 'NO_THESIS',
  'title' => 'Διαχείριση Διπλωματικής',
  'html'  => '<section class="card"><p class="muted">Δεν έχει ανατεθεί πτυχιακή.</p></section>'
]);

$studentID    = (int)$me['studentID'];
$thesisID     = (int)$me['thesisID'];
$supervisorID = (int)$me['supervisor'];
$thStatus     = strtoupper((string)$me['th_status']);

// ASSIGNED
if ($thStatus === 'ASSIGNED') {
  $teachers = [];
  foreach ($pdo->query("SELECT id, username, CONCAT(t_fname,' ',t_lname) AS nm FROM teacher ORDER BY t_lname, t_fname") as $t) {
    $teachers[] = ['val'=>$t['username'], 'label'=>$t['username'].' — '.$t['nm']];
  }

  $q = $pdo->prepare("
    SELECT ci.invitationID, ci.invitationDate, ci.response, ci.responseDate,
           t.username AS teacher_username, CONCAT(t.t_fname,' ',t.t_lname) AS teacher_name
    FROM committeeInvitations ci
    JOIN teacher t ON t.id = ci.receiverID
    WHERE ci.senderID = ?
    ORDER BY ci.invitationDate DESC, ci.invitationID DESC
  ");
  $q->execute([$studentID]);
  $rows = $q->fetchAll(PDO::FETCH_ASSOC);
  ob_start();
  if (!$rows) echo '<p>Καμία πρόσκληση.</p>';
  else {
    echo '<table class="table"><thead><tr><th>Προς Διδάσκοντα</th><th>Ημερομηνία</th><th>Κατάσταση</th><th>Τελευταία ενέργεια</th></tr></thead><tbody>';
    foreach ($rows as $iv) {
      $status = is_null($iv['response']) ? 'Σε αναμονή' : ($iv['response'] ? 'Αποδοχή' : 'Απόρριψη');
      $last   = is_null($iv['response']) ? '-' : ($iv['responseDate'] ?: '-');
      echo '<tr><td>'.htmlspecialchars($iv['teacher_username'].' — '.$iv['teacher_name']).'</td><td>'.htmlspecialchars($iv['invitationDate']).'</td><td>'.htmlspecialchars($status).'</td><td>'.htmlspecialchars($last).'</td></tr>';
    }
    echo '</tbody></table>';
  }
  $inviteTable = ob_get_clean();

  ok([
    'view'        => 'ASSIGNED',
    'title'       => 'Διαχείριση Διπλωματικής — Υπό Ανάθεση',
    'note'        => 'Μετά από 2 αποδοχές (μαζί με τον επιβλέποντα => 3 συνολικά), θα γίνει Ενεργή.',
    'teachers'    => $teachers,
    'inviteTable' => $inviteTable,
    'thesisID'    => $thesisID
  ]);
}

// ACTIVE
if ($thStatus === 'ACTIVE') {
  ok([
    'view'  => 'ACTIVE',
    'title' => 'Διαχείριση Διπλωματικής — Ενεργή',
    'html'  => '<div class="announcements" style="min-height:220px;"></div>',
    'thesisID' => $thesisID
  ]);
}

// EXAM
if ($thStatus === 'EXAM') {
  $m = $pdo->prepare("SELECT draft_file, external_links, exam_datetime, exam_room, exam_meeting_url, repository_url FROM thesis_exam_meta WHERE thesisID=?");
  $m->execute([$thesisID]);
  $meta = $m->fetch(PDO::FETCH_ASSOC) ?: [];

  $hasFile = (!empty($meta['draft_file']) && file_exists(__DIR__.'/'.$meta['draft_file']));
  $gq = $pdo->prepare("SELECT SUM(CASE WHEN calc_grade IS NOT NULL THEN 1 ELSE 0 END) AS nn, COUNT(*) AS tt FROM grades WHERE thesisID=?");
  $gq->execute([$thesisID]);
  $gr = $gq->fetch(PDO::FETCH_ASSOC) ?: ['nn'=>0,'tt'=>0];
  $after_enabled = ((int)$gr['tt']===3 && (int)$gr['nn']===3);

  ok([
    'view'  => 'EXAM',
    'title' => 'Διαχείριση Διπλωματικής — Υπό Εξέταση',
    'meta'  => [
      'thesisID'         => $thesisID,
      'draft_file'       => $meta['draft_file'] ?? null,
      'has_file'         => $hasFile,
      'external_links'   => ($meta['external_links'] ? (json_decode($meta['external_links'], true) ?: []) : []),
      'exam_datetime'    => $meta['exam_datetime'] ?? '',
      'exam_room'        => $meta['exam_room'] ?? '',
      'exam_meeting_url' => $meta['exam_meeting_url'] ?? '',
      'repository_url'   => $meta['repository_url'] ?? ''
    ],
    'after_enabled' => $after_enabled
  ]);
}

// DONE 
if ($thStatus === 'DONE') {
  // Θέμα
  $stmt = $pdo->prepare("SELECT t.thesisID, t.title, t.th_description, t.pdf_description, t.th_status FROM thesis t WHERE t.thesisID=? LIMIT 1");
  $stmt->execute([$thesisID]);
  $th = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

  // Επιτροπή
  $c = $pdo->prepare("SELECT c.supervisor, c.member1, c.member2 FROM committee c WHERE c.thesisID=? LIMIT 1");
  $c->execute([$thesisID]);
  $crow = $c->fetch(PDO::FETCH_ASSOC) ?: ['supervisor'=>null,'member1'=>null,'member2'=>null];

  $nameById = function($id) use ($pdo){
    if (!$id) return null;
    $q = $pdo->prepare("SELECT CONCAT(t_fname,' ',t_lname) AS nm FROM teacher WHERE id=?");
    $q->execute([$id]);
    $nm = $q->fetchColumn();
    return $nm ?: null;
  };
  $committee = array_values(array_filter([
    $nameById((int)$crow['supervisor']),
    $nameById((int)$crow['member1']),
    $nameById((int)$crow['member2']),
  ]));

  
  $m = $pdo->prepare("SELECT exam_datetime, exam_room, exam_meeting_url, repository_url FROM thesis_exam_meta WHERE thesisID=?");
  $m->execute([$thesisID]);
  $meta = $m->fetch(PDO::FETCH_ASSOC) ?: [];

  // Ημερομηνία & Ώρα  και Αίθουσα
  $examDate = ($meta['exam_datetime'] ?? null) ? date('d/m/Y H:i', strtotime($meta['exam_datetime'])) : '-';
  $examRoom = trim($meta['exam_room'] ?? '') !== '' ? $meta['exam_room'] : '-';

  // Τελικός βαθμός 
  $gr = $pdo->prepare("SELECT AVG(grade) AS g FROM grades WHERE thesisID=?");
  $gr->execute([$thesisID]);
  $gRow = $gr->fetch(PDO::FETCH_ASSOC);
  $finalGrade = ($gRow && $gRow['g'] !== null) ? round((float)$gRow['g'], 2) : null;

  // Ιστορικό
  $hist = $pdo->prepare("SELECT changeDate, changeTo FROM thesisStatusChanges WHERE thesisID=? ORDER BY changeDate ASC, id ASC");
  $hist->execute([$thesisID]);
  $history = $hist->fetchAll(PDO::FETCH_ASSOC);

  $hasDone = false; $lastDate = null;
  foreach ($history as $r) { if (strtoupper((string)$r['changeTo'])==='DONE') $hasDone=true; $lastDate=$r['changeDate']; }
  if (!$hasDone) { $syntheticDate = $lastDate ?: date('Y-m-d'); $history[] = ['changeDate'=>$syntheticDate,'changeTo'=>'DONE']; }

  ok([
    'view'  => 'DONE',
    'title' => 'Διαχείριση Διπλωματικής — Περατωμένη',
    'thesis'=> [
      'title'       => $th['title'] ?? '-',
      'description' => $th['th_description'] ?? '-',
      'pdf'         => $th['pdf_description'] ?? null,
      'status'      => $th['th_status'] ?? 'DONE',
      'committee'   => $committee,
      'final_grade' => $finalGrade
    ],
    'exam' => [
      'datetime' => $examDate,
      'room'     => $examRoom,
      'url'      => $meta['exam_meeting_url'] ?? null
    ],
    'repository_url' => ($meta['repository_url'] ?? null) ?: null,
    'history' => array_map(function($r){
      return ['date'=>date('d/m/Y', strtotime($r['changeDate'])), 'status'=>strtoupper((string)$r['changeTo'])];
    }, $history),
    'thesisID' => $thesisID
  ]);
}

// Fallback
ok([
  'view'  => 'ACTIVE',
  'title' => 'Διαχείριση Διπλωματικής — Ενεργή',
  'html'  => '<div class="announcements" style="min-height:220px;"></div>',
  'thesisID' => $thesisID
]);
