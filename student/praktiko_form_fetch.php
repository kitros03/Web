<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

// Πρόσβαση
if (empty($_SESSION['username'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Μη εξουσιοδοτημένη πρόσβαση'], JSON_UNESCAPED_UNICODE);
  exit;
}

// Είσοδος
$thesisID = (int)($_GET['thesisID'] ?? $_POST['thesisID'] ?? 0);
if ($thesisID <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'Λείπει thesisID'], JSON_UNESCAPED_UNICODE);
  exit;
}

// MySQLi τοπική σύνδεση (δεν αλλάζει το δικό σου dbconnect)
$host = 'localhost';
$db   = 'projectweb';
$user = 'root';
$pass = '';
$port = 3306;

$mysqli = @new mysqli($host, $user, $pass, $db, $port);
if ($mysqli->connect_errno) {
  http_response_code(503);
  echo json_encode(['error' => 'Προσωρινή αδυναμία σύνδεσης στη βάση.'], JSON_UNESCAPED_UNICODE);
  exit;
}
$mysqli->set_charset('utf8mb4');

// Helpers
function fullName($f,$l){ $n=trim(($f??'').' '.($l??'')); return $n!==''?$n:'………………………………'; }
function dotted($v,$fb='………………………………'){ $s=trim((string)$v); return $s!==''?$s:$fb; }

$thesisIDEsc = (int)$thesisID;

try {
  // Κύρια φόρτωση
  $sql = "
    SELECT 
      t.thesisID, t.title, t.supervisor, t.gs_numb,
      s.s_fname, s.s_lname,
      tem.exam_datetime, tem.exam_room,
      c.member1, c.member2
    FROM thesis t
    LEFT JOIN student s ON s.thesisID = t.thesisID
    LEFT JOIN thesis_exam_meta tem ON tem.thesisID = t.thesisID
    LEFT JOIN committee c ON c.thesisID = t.thesisID
    WHERE t.thesisID = {$thesisIDEsc}
    LIMIT 1";
  $res = $mysqli->query($sql);
  if ($res === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Αποτυχία ανάκτησης δεδομένων πτυχιακής.'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $row = $res->fetch_assoc();
  $res->free();

  if (!$row) {
    http_response_code(404);
    echo json_encode(['error'=>'Δεν βρέθηκαν στοιχεία για την πτυχιακή.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Τελικός βαθμός από grades.grade (μέσος όρος, 1 δεκαδικό)
  $resG = $mysqli->query("SELECT AVG(grade) AS avg_grade FROM grades WHERE thesisID = {$thesisIDEsc}");
  $avgGrade = null;
  if ($resG !== false) {
    $g = $resG->fetch_assoc();
    if ($g && $g['avg_grade'] !== null) $avgGrade = round((float)$g['avg_grade'], 1);
    $resG->free();
  }
  $finalGradeText = $avgGrade !== null ? number_format($avgGrade, 1, '.', '') : '………………';

  // Διδάσκοντες
  $teacherById = function(int $id) use ($mysqli){
    if ($id<=0) return null;
    $r = $mysqli->query("SELECT t_fname, t_lname FROM teacher WHERE id = {$id} LIMIT 1");
    if ($r === false) return null;
    $x = $r->fetch_assoc();
    $r->free();
    if (!$x) return null;
    return [
      'fname'=>$x['t_fname']??'',
      'lname'=>$x['t_lname']??'',
      'full'=>trim(($x['t_fname']??'').' '.($x['t_lname']??''))
    ];
  };

  // Συναρμολόγηση
  $studentFull = fullName($row['s_fname'] ?? null, $row['s_lname'] ?? null);
  $title       = dotted($row['title'] ?? '');
  $room        = dotted($row['exam_room'] ?? '');

  $dayDate='……………………………'; $dateStr='……………………………'; $timeStr='………………';
  if (!empty($row['exam_datetime'])) {
    try {
      $dt = new DateTime($row['exam_datetime']);
      $dateStr = $dt->format('d/m/Y');
      $timeStr = $dt->format('H:i');
      $days = ['Κυρ','Δευ','Τρι','Τετ','Πεμ','Παρ','Σαβ'];
      $dayDate = $days[(int)$dt->format('w')] ?? '……';
    } catch (Throwable $e) {}
  }

  $sup = $teacherById((int)$row['supervisor']);
  $m1  = $teacherById((int)$row['member1']);
  $m2  = $teacherById((int)$row['member2']);

  $membersOriginal = [];
  if ($sup) $membersOriginal[] = ['role'=>'Επιβλέπων','fname'=>$sup['fname']??'','lname'=>$sup['lname']??'','full'=>$sup['full']??''];
  if ($m1)  $membersOriginal[] = ['role'=>'Μέλος','fname'=>$m1['fname']??'','lname'=>$m1['lname']??'','full'=>$m1['full']??''];
  if ($m2)  $membersOriginal[] = ['role'=>'Μέλος','fname'=>$m2['fname']??'','lname'=>$m2['lname']??'','full'=>$m2['full']??''];

  $membersAlpha = $membersOriginal;
  usort($membersAlpha, function($a,$b){ return strcasecmp($a['fname'],$b['fname']); });

  $supervisorFull = $sup['full'] ?? '………………………………';
  $gsText = (isset($row['gs_numb']) && $row['gs_numb']!=='') ? (string)$row['gs_numb'] : '…………………';

  echo json_encode([
    'studentFull'     => $studentFull,
    'title'           => $title,
    'room'            => $room,
    'dayDate'         => $dayDate,
    'dateStr'         => $dateStr,
    'timeStr'         => $timeStr,
    'membersOriginal' => $membersOriginal,
    'membersAlpha'    => $membersAlpha,
    'supervisorFull'  => $supervisorFull,
    'gsText'          => $gsText,
    'finalGradeText'  => $finalGradeText
  ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  error_log('praktiko_form_fetch error: '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Προέκυψε σφάλμα κατά τη φόρτωση δεδομένων.'], JSON_UNESCAPED_UNICODE);
} finally {
  if (isset($mysqli) && $mysqli instanceof mysqli) $mysqli->close();
}
