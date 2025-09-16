<?php
require_once __DIR__.'/dbconnect.php';
session_start();
header('Content-Type: text/html; charset=utf-8');

/* Πρόσβαση */
if (empty($_SESSION['username'])) {
  http_response_code(401);
  echo 'Μη εξουσιοδοτημένη πρόσβαση';
  exit;
}
$thesisID = (int)($_GET['thesisID'] ?? 0);
if ($thesisID <= 0) {
  echo 'Λείπει thesisID';
  exit;
}

/* Helpers */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fullName($fname, $lname){
  $n = trim(($fname ?? '').' '.($lname ?? ''));
  return $n !== '' ? $n : '………………………………';
}
function dotted($value, $fallback='………………………………'){
  $v = trim((string)$value);
  return $v !== '' ? h($v) : $fallback;
}

/* Φόρτωση δεδομένων */
try {
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
    WHERE t.thesisID = ? LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([$thesisID]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) { echo 'Δεν βρέθηκαν στοιχεία για την πτυχιακή.'; exit; }

  // Υπολογισμός τελικού βαθμού: Μ.Ο. όλων των (calc_grade/3) για τη συγκεκριμένη thesis
  // Αντί για calc_grade/3 μπορείς να αλλάξεις σε (quality_grade + time_grade + presentation_grade)/3
  $finalGrade = null;
  $qg = $pdo->prepare("
    SELECT AVG(calc_grade/3) AS g
    FROM grades
    WHERE thesisID = ?
      AND calc_grade IS NOT NULL
  ");
  $qg->execute([$thesisID]);
  $gRow = $qg->fetch(PDO::FETCH_ASSOC);
  if ($gRow && $gRow['g'] !== null) {
    $finalGrade = round((float)$gRow['g'], 1); // προσαρμόζεις τα δεκαδικά αν θέλεις
  }
  $finalGradeText = $finalGrade !== null ? h($finalGrade) : '………………';

  // Συνάρτηση εύρεσης διδάσκοντα
  $teacherById = function($id) use ($pdo){
    if (!$id) return null;
    $q = $pdo->prepare("SELECT t_fname, t_lname FROM teacher WHERE id = ? LIMIT 1");
    $q->execute([$id]);
    $x = $q->fetch(PDO::FETCH_ASSOC);
    if (!$x) return null;
    return [
      'fname' => $x['t_fname'] ?? '',
      'lname' => $x['t_lname'] ?? '',
      'full'  => fullName($x['t_fname'] ?? '', $x['t_lname'] ?? '')
    ];
  };

  $studentFull = fullName($row['s_fname'] ?? null, $row['s_lname'] ?? null);
  $title       = dotted($row['title'] ?? '');

  // Εξεταστική πληροφορία
  $room    = dotted($row['exam_room'] ?? '');
  $dayDate = '……………………………';
  $dateStr = '……………………………';
  $timeStr = '………………';
  if (!empty($row['exam_datetime'])) {
    try {
      $dt = new DateTime($row['exam_datetime']);
      $dateStr = $dt->format('d/m/Y');
      $timeStr = $dt->format('H:i');
      $days = ['Κυρ','Δευ','Τρι','Τετ','Πεμ','Παρ','Σαβ'];
      $dayDate = $days[(int)$dt->format('w')] ?? '……';
    } catch (Throwable $e) {}
  }

  // Επιτροπή
  $sup = $teacherById((int)$row['supervisor']);
  $m1  = $teacherById((int)$row['member1']);
  $m2  = $teacherById((int)$row['member2']);

  // 1η λίστα: φυσική σειρά
  $membersOriginal = [];
  if ($sup) $membersOriginal[] = ['role'=>'Επιβλέπων','fname'=>$sup['fname'],'lname'=>$sup['lname'],'full'=>$sup['full']];
  if ($m1)  $membersOriginal[] = ['role'=>'Μέλος','fname'=>$m1['fname'],'lname'=>$m1['lname'],'full'=>$m1['full']];
  if ($m2)  $membersOriginal[] = ['role'=>'Μέλος','fname'=>$m2['fname'],'lname'=>$m2['lname'],'full'=>$m2['full']];

  // 2η λίστα: αλφαβητικά μόνο κατά όνομα
  $membersAlpha = $membersOriginal;
  usort($membersAlpha, function($a,$b){
    return strcasecmp($a['fname'], $b['fname']);
  });

  $supervisorFull = $sup['full'] ?? '………………………………';
  $gsText = (isset($row['gs_numb']) && $row['gs_numb'] !== null && $row['gs_numb'] !== '')
            ? h($row['gs_numb'])
            : '…………………';

} catch (Throwable $e) {
  http_response_code(500);
  echo 'Σφάλμα φόρτωσης δεδομένων.';
  exit;
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <title>Πρακτικό Εξέτασης Διπλωματικής Εργασίας</title>
  <link rel="stylesheet" href="praktiko_exam.css">
</head>
<body>
  <div class="header">
    <div class="university-name">ΠΡΑΚΤΙΚΟ ΣΥΝΕΔΡΙΑΣΗΣ</div>
    <div class="department-name">ΤΗΣ ΤΡΙΜΕΛΟΥΣ ΕΠΙΤΡΟΠΗΣ</div>
    <div class="document-subtitle2">ΓΙΑ ΤΗΝ ΠΑΡΟΥΣΙΑΣΗ ΚΑΙ ΚΡΙΣΗ ΤΗΣ ΔΙΠΛΩΜΑΤΙΚΗΣ ΕΡΓΑΣΙΑΣ</div>
    <div class="italic" style="margin-top:10px;">του/της φοιτητή/φοιτήτρια</div>
  </div>

  <div class="content">
    <p>Κ. <?php echo h($studentFull); ?></p>

    <p>
      Η συνεδρίαση πραγματοποιήθηκε στην αίθουσα <?php echo h($room); ?>
      στις <?php echo h($dateStr); ?> ημέρα <?php echo h($dayDate); ?> και ώρα <?php echo h($timeStr); ?>
    </p>

    <p>Στην συνεδρίαση είναι παρόντα τα μέλη της Τριμελούς Επιτροπής κ.κ.:</p>

    <!-- 1) Φυσική σειρά -->
    <ol>
      <?php foreach ($membersOriginal as $m): ?>
        <li><?php echo h($m['full']); ?>,</li>
      <?php endforeach; ?>
      <?php if (count($membersOriginal) < 3): ?>
        <?php for ($i=count($membersOriginal); $i<3; $i++): ?>
          <li>………………………………………………………………………………,</li>
        <?php endfor; ?>
      <?php endif; ?>
    </ol>

    <p>
      οι οποίοι ορίσθηκαν από την Συνέλευση του ΤΜΗΥΠ, στην συνεδρίαση της με αριθμό <?php echo $gsText; ?>
    </p>

    <p><strong>Ο/Η φοιτητής/φοιτήτρια</strong> κ. <?php echo h($studentFull); ?>
       ανέπτυξε το θέμα της Διπλωματικής του/της Εργασίας, με τίτλο
    </p>

    <p style="margin-left:20px;">
      «<?php echo h($title); ?><br>
      ………………………………………………………………………………………………………………………………»
    </p>

    <p>
      Στην συνέχεια υποβλήθηκαν ερωτήσεις στον υποψήφιο από τα μέλη της Τριμελούς Επιτροπής και τους άλλους
      παρευρισκόμενους, προκειμένου να διαμορφώσουν σαφή άποψη για το περιεχόμενο της εργασίας, για την
      επιστημονική συγκρότηση του μεταπτυχιακού φοιτητή.
    </p>

    <p>Μετά το τέλος της ανάπτυξης της εργασίας του και των ερωτήσεων, ο υποψήφιος αποχωρεί.</p>

    <p>
      Ο Επιβλέπων καθηγητής κ. <?php echo h($supervisorFull); ?>,
      προτείνει στα μέλη της Τριμελούς Επιτροπής, να ψηφίσουν για το αν εγκρίνεται η διπλωματική εργασία του
      <?php echo h($studentFull); ?>
    </p>

    <p>Τα μέλη της Τριμελούς Επιτροπής, ψηφίζουν κατ’ αλφαβητική σειρά:</p>

    <!-- 2) Αλφαβητική σειρά κατά όνομα -->
    <ol>
      <?php foreach ($membersAlpha as $m): ?>
        <li><?php echo h($m['full']); ?>,</li>
      <?php endforeach; ?>
      <?php if (count($membersAlpha) < 3): ?>
        <?php for ($i=count($membersAlpha); $i<3; $i++): ?>
          <li>………………………………………………………………………………,</li>
        <?php endfor; ?>
      <?php endif; ?>
    </ol>

    <p>
      υπέρ της εγκρίσεως της Διπλωματικής Εργασίας του φοιτητή
      <?php echo h($studentFull); ?> επειδή θεωρούν επιστημονικά επαρκή και το
      περιεχόμενο της ανταποκρίνεται στο θέμα που του δόθηκε.
    </p>

    <p>
      Μετά της έγκριση, ο εισηγητής κ. <?php echo h($supervisorFull); ?>,
      προτείνει στα μέλη της Τριμελούς Επιτροπής, να απονεμηθεί στο/στη φοιτητή/τρια
      κ. <?php echo h($studentFull); ?> ο βαθμός <?php echo $finalGradeText; ?>
    </p>

    <p>Τα μέλη της Τριμελούς Επιτροπής, απομένουν την παραπάνω βαθμολογία:</p>

    <table class="signature-table">
      <thead>
        <tr>
          <th>ΟΝΟΜΑΤΕΠΩΝΥΜΟ</th>
          <th>ΙΔΙΟΤΗΤΑ</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($membersAlpha as $m): ?>
          <tr><td><?php echo h($m['full']); ?></td><td><?php echo h($m['role']); ?></td></tr>
        <?php endforeach; ?>
        <?php if (count($membersAlpha) < 3): ?>
          <?php for ($i=count($membersAlpha); $i<3; $i++): ?>
            <tr><td></td><td></td></tr>
          <?php endfor; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <p>
      Μετά την έγκριση και την απονομή του βαθμού <?php echo $finalGradeText; ?>, η Τριμελής Επιτροπή, προτείνει να προχωρήσει
      στην διαδικασία για να ανακηρυχθεί τον κ. <?php echo h($studentFull); ?>, σε διπλωματούχο του Προγράμματος
      Σπουδών του «ΤΜΗΜΑΤΟΣ ΜΗΧΑΝΙΚΩΝ, ΗΛΕΚΤΡΟΝΙΚΩΝ ΥΠΟΛΟΓΙΣΤΩΝ ΚΑΙ ΠΛΗΡΟΦΟΡΙΚΗΣ ΠΑΝΕΠΙΣΤΗΜΙΟΥ ΠΑΤΡΩΝ»
      και να του απονέμει το Δίπλωμα Μηχανικού Η/Υ το οποίο αναγνωρίζεται ως Ενιαίος Τίτλος Σπουδών Μεταπτυχιακού Επιπέδου.
    </p>
  </div>
</body>
</html>
