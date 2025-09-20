<?php
// manage_thesis_content.php — JSON endpoint (PDO)
declare(strict_types=1);
session_start();
require_once '../dbconnect.php'; // παρέχει $pdo (PDO)

header('Content-Type: application/json; charset=utf-8');

function ok(array $p=[]): never { echo json_encode(['success'=>true]+$p, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function fail(string $m, int $c=400): never { http_response_code($c); echo json_encode(['success'=>false,'message'=>$m], JSON_UNESCAPED_UNICODE); exit; }

try {
  if (!isset($pdo) || !($pdo instanceof PDO)) fail('DB not ready', 500);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (Throwable $e) { fail('DB error: '.$e->getMessage(), 500); }

if (empty($_SESSION['username']) || (($_SESSION['role']??'')!=='student')) fail('Unauthorized', 401);

// Βρες thesis του φοιτητή
$st = $pdo->prepare("
  SELECT s.studentID, s.thesisID, t.supervisor, t.th_status
  FROM student s
  JOIN thesis t ON t.thesisID = s.thesisID
  WHERE s.username = ? LIMIT 1
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

if ($thStatus === 'ASSIGNED') {
  // Διδάσκοντες
  $teachers = [];
  foreach ($pdo->query("SELECT id, username, CONCAT(t_fname,' ',t_lname) AS nm FROM teacher ORDER BY t_lname, t_fname") as $t) {
    $teachers[] = ['val'=>$t['username'], 'label'=>$t['username'].' — '.$t['nm']];
  }
  // Προσκλήσεις
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
  if (!$rows) {
    echo '<p>Καμία πρόσκληση.</p>';
  } else {
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

if ($thStatus === 'ACTIVE') {
  ok([
    'view'  => 'ACTIVE',
    'title' => 'Διαχείριση Διπλωματικής — Ενεργή',
    'html'  => '<div class="announcements" style="min-height:220px;"></div>',
    'thesisID' => $thesisID
  ]);
}

if ($thStatus === 'EXAM') {
  // Meta
  $m = $pdo->prepare("SELECT draft_file, external_links, exam_datetime, exam_room, exam_meeting_url, repository_url FROM thesis_exam_meta WHERE thesisID=?");
  $m->execute([$thesisID]);
  $meta = $m->fetch(PDO::FETCH_ASSOC) ?: [];

  $hasFile = (!empty($meta['draft_file']) && file_exists(__DIR__.'/'.$meta['draft_file']));
  // 3/3 βαθμολογίες
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

// Default
ok([
  'view'  => 'ACTIVE',
  'title' => 'Διαχείριση Διπλωματικής — Ενεργή',
  'html'  => '<div class="announcements" style="min-height:220px;"></div>',
  'thesisID' => $thesisID
]);
