<?php
require_once '../dbconnect.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

function ok(array $p = []) {
  echo json_encode(['success' => true] + $p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
function fail(string $m, int $c = 400) {
  http_response_code($c);
  echo json_encode(['success' => false, 'message' => $m], JSON_UNESCAPED_UNICODE);
  exit;
}

if (empty($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'student') fail('Unauthorized', 401);

// Thesis + status
$st = $pdo->prepare("
  SELECT s.studentID, s.thesisID, t.supervisor, t.th_status
  FROM student s
  JOIN thesis t ON t.thesisID = s.thesisID
  WHERE s.username=? LIMIT 1
");
$st->execute([$_SESSION['username']]);
$me = $st->fetch(PDO::FETCH_ASSOC);
if (!$me) ok(['view'=>'NO_THESIS','title'=>'Διαχείριση Διπλωματικής','html'=>'<p>Δεν έχει ανατεθεί πτυχιακή.</p>']);

$studentID    = (int)$me['studentID'];
$thesisID     = (int)$me['thesisID'];
$supervisorID = (int)$me['supervisor'];
$thStatus     = strtoupper((string)$me['th_status']);

// ASSIGNED
if ($thStatus === 'ASSIGNED') {
  $list = $pdo->query("SELECT username, CONCAT(t_fname,' ',t_lname) AS name, id FROM teacher ORDER BY t_lname, t_fname")->fetchAll(PDO::FETCH_ASSOC);
  $teachers = [];
  foreach ($list as $t) {
    if ($supervisorID && (int)$t['id'] === $supervisorID) continue;
    $teachers[] = ['val'=>$t['username'],'label'=>$t['username'].' — '.$t['name']];
  }
  // Invitations table as HTML fragment
  ob_start();
  $q = $pdo->prepare("
    SELECT ci.invitationID, ci.invitationDate, ci.response, ci.responseDate,
           t.username AS teacher_username, CONCAT(t.t_fname,' ',t.t_lname) AS teacher_name
    FROM committeeInvitations ci
    JOIN teacher t ON t.id = ci.receiverID
    WHERE ci.senderID = ?
    ORDER BY ci.invitationDate DESC, ci.invitationID DESC
  ");
  $q->execute([$studentID]);
  $invs = $q->fetchAll(PDO::FETCH_ASSOC);
  if (!$invs) echo '<p>Καμία πρόσκληση.</p>';
  else {
    echo '<table class="table"><thead><tr><th>Προς Διδάσκοντα</th><th>Ημερομηνία</th><th>Κατάσταση</th><th>Τελευταία ενέργεια</th></tr></thead><tbody>';
    foreach ($invs as $iv) {
      $status = is_null($iv['response']) ? 'Σε αναμονή' : ($iv['response'] ? 'Αποδοχή' : 'Απόρριψη');
      $last   = is_null($iv['response']) ? '-' : ($iv['responseDate'] ?: '-');
      echo '<tr><td>'.htmlspecialchars($iv['teacher_username'].' — '.$iv['teacher_name']).'</td><td>'.htmlspecialchars($iv['invitationDate']).'</td><td>'.htmlspecialchars($status).'</td><td>'.htmlspecialchars($last).'</td></tr>';
    }
    echo '</tbody></table>';
  }
  $inviteTable = ob_get_clean();
  ok([
    'view'       => 'ASSIGNED',
    'title'      => 'Διαχείριση Διπλωματικής — Υπό Ανάθεση',
    'note'       => 'Μετά από 2 αποδοχές (μαζί με τον επιβλέποντα => 3 συνολικά), θα γίνει Ενεργή.',
    'teachers'   => $teachers,
    'inviteTable'=> $inviteTable
  ]);
}

// ACTIVE
if ($thStatus === 'ACTIVE') {
  ok([
    'view'  => 'ACTIVE',
    'title' => 'Διαχείριση Διπλωματικής — Ενεργή',
    'html'  => '<div class="announcements" style="min-height:220px;"></div>'
  ]);
}

// EXAM
if ($thStatus === 'EXAM') {
  $m = $pdo->prepare("SELECT * FROM thesis_exam_meta WHERE thesisID=?");
  $m->execute([$thesisID]);
  $meta = $m->fetch(PDO::FETCH_ASSOC) ?: [];

  $hasFile = !empty($meta['draft_file']) && file_exists(__DIR__ . '/' . $meta['draft_file']);

  $gq = $pdo->prepare("
    SELECT SUM(CASE WHEN calc_grade IS NOT NULL THEN 1 ELSE 0 END) AS non_null_cnt,
           COUNT(*) AS total_cnt
    FROM grades
    WHERE thesisID = ?
  ");
  $gq->execute([$thesisID]);
  [$nonNullCnt, $totalCnt] = array_map('intval', $gq->fetch(PDO::FETCH_NUM));
  $after_enabled = ($totalCnt === 3 && $nonNullCnt === 3);

  ok([
    'view'  => 'EXAM',
    'title' => 'Διαχείριση Διπλωματικής — Υπό Εξέταση (EXAM)',
    'meta'  => [
      'thesisID'        => $thesisID,
      'external_links'  => isset($meta['external_links']) ? (json_decode($meta['external_links'], true) ?: []) : [],
      'draft_file'      => $meta['draft_file'] ?? null,
      'has_file'        => $hasFile,
      'exam_datetime'   => $meta['exam_datetime'] ?? '',
      'exam_room'       => $meta['exam_room'] ?? '',
      'exam_meeting_url'=> $meta['exam_meeting_url'] ?? '',
      'repository_url'  => $meta['repository_url'] ?? ''
    ],
    'after_enabled' => $after_enabled
  ]);
}

// DONE
if ($thStatus === 'DONE') {
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
  $th   = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

  $committee = array_values(array_filter([
    $th['supervisor_name'] ?? null,
    $th['member1_name'] ?? null,
    $th['member2_name'] ?? null
  ]));

  $days = null;
  if (!empty($th['assigned_date'])) {
    try {
      $today = new DateTimeImmutable('today');
      $ad    = new DateTimeImmutable($th['assigned_date']);
      $days  = (int)$today->diff($ad)->format('%a');
    } catch (Throwable $e) { $days = null; }
  }

  $m = $pdo->prepare("SELECT * FROM thesis_exam_meta WHERE thesisID=?");
  $m->execute([$thesisID]);
  $meta = $m->fetch(PDO::FETCH_ASSOC) ?: [];

  $gr = $pdo->prepare("SELECT AVG(grade) AS g FROM grades WHERE thesisID=?");
  $gr->execute([$thesisID]);
  $gRow = $gr->fetch(PDO::FETCH_ASSOC);
  $finalGrade = $gRow && $gRow['g'] !== null ? round((float)$gRow['g'], 2) : null;

  $examDate = ($meta['exam_datetime'] ?? null) ? date('d/m/Y H:i', strtotime($meta['exam_datetime'])) : '-';
  $examRoom = trim($meta['exam_room'] ?? '') !== '' ? $meta['exam_room'] : '-';
  $repoUrl  = trim($meta['repository_url'] ?? '') !== '' ? $meta['repository_url'] : null;

  $hist = $pdo->prepare("SELECT changeDate, changeTo FROM thesisStatusChanges WHERE thesisID=? ORDER BY changeDate ASC, id ASC");
  $hist->execute([$thesisID]);
  $history = $hist->fetchAll(PDO::FETCH_ASSOC);

  $hasDone = false; $lastDate = null;
  foreach ($history as $hrow) { if (strtoupper($hrow['changeTo'])==='DONE') $hasDone=true; $lastDate=$hrow['changeDate']; }
  if (!$hasDone) { $syntheticDate = $lastDate ?: date('Y-m-d'); $history[] = ['changeDate'=>$syntheticDate,'changeTo'=>'DONE']; }

  ok([
    'view'   => 'DONE',
    'title'  => 'Διαχείριση Διπλωματικής — Ολοκληρωμένη (DONE)',
    'thesis' => [
      'title'               => $th['title'] ?? '-',
      'description'         => $th['th_description'] ?? '-',
      'pdf'                 => $th['pdf_description'] ?? null,
      'status'              => $th['th_status'] ?? 'DONE',
      'committee'           => $committee,
      'days_since_assigned' => $days
    ],
    'exam' => [
      'date'        => $examDate,
      'room'        => $examRoom,
      'final_grade' => $finalGrade
    ],
    'repository_url' => $repoUrl,
    'history'        => array_map(function($r){
      return ['date'=>date('d/m/Y', strtotime($r['changeDate'])), 'status'=>strtoupper($r['changeTo'])];
    }, $history),
    'thesisID' => $thesisID
  ]);
}

// Fallback
ok(['view'=>'ACTIVE','title'=>'Διαχείριση Διπλωματικής — Ενεργή','html'=>'<div class="announcements" style="min-height:220px;"></div>']);
