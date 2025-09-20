<?php
require_once '../dbconnect.php';
session_start();
header('Content-Type: text/html; charset=utf-8');

// Μόνο φοιτητές (student view)
if (empty($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'student') {
  echo '<h3>Διαχείριση Διπλωματικής</h3><p>Συνδέσου για να διαχειριστείς την πτυχιακή.</p>';
  exit;
}

// Student + Thesis + Status
$st = $pdo->prepare("SELECT s.studentID, s.thesisID, t.supervisor, t.th_status
                     FROM student s
                     JOIN thesis t ON t.thesisID = s.thesisID
                     WHERE s.username=? LIMIT 1");
$st->execute([$_SESSION['username']]);
$me = $st->fetch(PDO::FETCH_ASSOC);

if (!$me) {
  echo '<h3>Διαχείριση Διπλωματικής</h3><p>Δεν έχει ανατεθεί πτυχιακή.</p>';
  exit;
}

$studentID    = (int)$me['studentID'];
$thesisID     = (int)$me['thesisID'];
$supervisorID = (int)$me['supervisor'];
$thStatus     = (string)$me['th_status'];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ===== ASSIGNED =====
if ($thStatus === 'ASSIGNED') {
  echo '<h3>Διαχείριση Διπλωματικής — Υπό Ανάθεση</h3>';
  echo '<p>Αποστολή προσκλήσεων προς μέλη τριμελούς. Μετά από 2 αποδοχές (μαζί με τον επιβλέποντα => 3 συνολικά), η κατάσταση θα γίνει <strong>Ενεργή</strong>.</p>';

  $list = $pdo->query("SELECT username, CONCAT(t_fname,' ',t_lname) AS name, id
                       FROM teacher ORDER BY t_lname, t_fname")->fetchAll(PDO::FETCH_ASSOC);

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

  function statusText($r){ return is_null($r) ? 'Σε αναμονή' : ($r ? 'Αποδοχή' : 'Απόρριψη'); }
  function lastDateText($d,$r){ return is_null($r) ? '-' : ($d ?: '-'); }
  ?>
  <form id="inviteForm" class="form" method="post">
    <input type="hidden" name="action" value="invite">
    <label for="teacherUsername">Διδάσκων (username)</label>
    <select id="teacherUsername" name="teacherUsername" required>
      <option value="">Επέλεξε διδάσκοντα</option>
      <?php foreach ($list as $t): ?>
        <?php if ($supervisorID && (int)$t['id'] === $supervisorID) continue; ?>
        <option value="<?= h($t['username']) ?>"><?= h($t['username'].' — '.$t['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="submit-btn">Αποστολή Πρόσκλησης</button>
  </form>

  <h3>Προσκλήσεις Επιτροπής</h3>
  <div id="inviteTable">
    <?php if (!$invs): ?>
      <p>Καμία πρόσκληση.</p>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr><th>Προς Διδάσκοντα</th><th>Ημερομηνία</th><th>Κατάσταση</th><th>Τελευταία ενέργεια</th></tr>
        </thead>
        <tbody>
          <?php foreach ($invs as $iv): ?>
            <tr>
              <td><?= h($iv['teacher_username'].' — '.$iv['teacher_name']) ?></td>
              <td><?= h($iv['invitationDate']) ?></td>
              <td><?= h(statusText($iv['response'])) ?></td>
              <td><?= h(lastDateText($iv['responseDate'], $iv['response'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <?php
  exit;
}

// ===== ACTIVE =====
if ($thStatus === 'ACTIVE') {
  echo '<h3>Διαχείριση Διπλωματικής — Ενεργή</h3>';
  echo '<p>Η πτυχιακή είναι σε κατάσταση <strong>Ενεργή</strong>. Αναμένεται ο επιβλέπων/τριμελής να θέσουν την κατάσταση <strong>Υπό Εξέταση (EXAM)</strong>.</p>';
  echo '<div class="announcements" style="min-height:220px;"></div>';
  exit;
}

// ===== EXAM =====
if ($thStatus === 'EXAM') {
  // Φόρτωση meta
  $m = $pdo->prepare("SELECT * FROM thesis_exam_meta WHERE thesisID=?");
  $m->execute([$thesisID]);
  $meta = $m->fetch(PDO::FETCH_ASSOC) ?: [];

  // Έλεγχος αρχείου (file path)
  $hasFile = !empty($meta['draft_file']) && file_exists(__DIR__ . '/' . $meta['draft_file']);

  // Ξεκλείδωμα 3ου βήματος: ακριβώς 3 εγγραφές grades και όλες με calc_grade NOT NULL
  $gq = $pdo->prepare("
    SELECT SUM(CASE WHEN calc_grade IS NOT NULL THEN 1 ELSE 0 END) AS non_null_cnt,
           COUNT(*) AS total_cnt
    FROM grades
    WHERE thesisID = ?
  ");
  $gq->execute([$thesisID]);
  list($nonNullCnt, $totalCnt) = array_map('intval', $gq->fetch(PDO::FETCH_NUM));
  $after_enabled = ($totalCnt === 3 && $nonNullCnt === 3);

  echo '<h3>Διαχείριση Διπλωματικής — Υπό Εξέταση (EXAM)</h3>';
  echo '<p>Η πτυχιακή είναι σε κατάσταση <strong>Υπό Εξέταση</strong>.</p>';
  ?>
  <style>
    .grid-2 { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
    .card { background:#d1d1d1; padding:16px; border-radius:6px; }
    .w-100 { width:100%; }
    .mt-8{margin-top:8px;} .mt-12{margin-top:12px;} .mt-16{margin-top:16px;}
    .small{font-size:13px;} .mono{font-family:ui-monospace,Menlo,monospace;}
    .disabled-block { opacity:.6; pointer-events:none; }
    .preview-btn { background:#4CAF50; color:white; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; }
    .preview-btn:hover { background:#45a049; }
    .preview-btn:disabled { background:#cccccc; cursor:not-allowed; }
  </style>

  <div class="grid-2 mt-16">
    <section class="card">
      <h4>1) Πρόχειρο κείμενο & σύνδεσμοι</h4>
      <p class="small">Ανέβασε αρχείο και πρόσθεσε συνδέσμους (Drive, YouTube κ.λπ.).</p>
      <form id="examDraftForm" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_draft">
        <input type="hidden" name="thesisID" value="<?php echo $thesisID; ?>">
        <label>Πρόχειρο αρχείο:</label>
        <input class="w-100" type="file" name="draft_file" accept=".pdf,.doc,.docx,.ppt,.pptx">
        <label class="mt-8">Σύνδεσμοι (ένας ανά γραμμή):</label>
        <textarea class="w-100" name="external_links" rows="4" placeholder="https://drive.google.com/...&#10;https://youtube.com/..."><?php
          echo h(isset($meta['external_links']) ? implode("\n", json_decode($meta['external_links'], true) ?: []) : '');
        ?></textarea>
        <button class="submit-btn mt-12" type="submit">Αποθήκευση</button>
        <?php if ($hasFile): ?>
          <p class="small mt-8">Αρχείο: <a class="mono" target="_blank" href="<?php echo h($meta['draft_file']); ?>"><?php 
            echo h(basename($meta['draft_file'])); 
          ?></a>
          <?php 
          $filePath = __DIR__ . '/' . $meta['draft_file'];
          if (file_exists($filePath)) {
            echo '(' . round(filesize($filePath) / 1024, 1) . ' KB)';
          }
          ?>
          </p>
        <?php endif; ?>
      </form>
    </section>

    <section class="card">
      <h4>2) Δήλωση εξέτασης</h4>
      <form id="examScheduleForm">
        <input type="hidden" name="action" value="save_schedule">
        <input type="hidden" name="thesisID" value="<?php echo $thesisID; ?>">
        <label>Ημερομηνία & Ώρα:</label>
        <input class="w-100" type="datetime-local" name="exam_datetime" value="<?php 
          $dt = $meta['exam_datetime'] ?? '';
          if ($dt && $dt !== '0000-00-00 00:00:00') {
            echo h(date('Y-m-d\TH:i', strtotime($dt)));
          }
        ?>">
        <label class="mt-8">Αίθουσα (προαιρετικό):</label>
        <input class="w-100" type="text" name="exam_room" value="<?php echo h($meta['exam_room'] ?? ''); ?>">
        <label class="mt-8">Σύνδεσμος σύσκεψης (προαιρετικό):</label>
        <input class="w-100" type="url" name="exam_meeting_url" value="<?php echo h($meta['exam_meeting_url'] ?? ''); ?>">
        <button class="submit-btn mt-12" type="submit">Αποθήκευση</button>
      </form>
    </section>

    <section class="card <?php echo $after_enabled ? '' : 'disabled-block'; ?>">
      <h4>3) Μετά τη βαθμολόγηση</h4>
      <?php if (!$after_enabled): ?>
        <p class="small">Το βήμα αυτό ενεργοποιείται αφού ο επιβλέπων οριστικοποιήσει τη βαθμολόγηση.</p>
      <?php endif; ?>
      <form id="examAfterForm">
        <input type="hidden" name="action" value="save_after">
        <input type="hidden" name="thesisID" value="<?php echo $thesisID; ?>">
        <div style="display: flex; align-items: center; gap: 8px;">
          <label style="margin: 0;">Πρακτικό εξέτασης:</label>
          <button type="button" class="preview-btn" id="reportPreviewBtn" <?php echo $after_enabled ? '' : 'disabled'; ?> onclick="window.open('praktiko_form.php?thesisID=<?php echo $thesisID; ?>', '_blank', 'width=800,height=900,scrollbars=yes')">Προβολή</button>
        </div>
        <label class="mt-16">Σύνδεσμος αποθετηρίου (Νημερτής):</label>
        <input class="w-100" type="url" name="repository_url" value="<?php echo h($meta['repository_url'] ?? ''); ?>" <?php echo $after_enabled ? '' : 'disabled'; ?>>
        <button class="submit-btn mt-12" type="submit" <?php echo $after_enabled ? '' : 'disabled'; ?>>Αποθήκευση</button>
      </form>
    </section>

    <section class="card">
      <h4>Χρήσιμες πληροφορίες</h4>
      <ul class="small">
        <li>Οι ενέργειες είναι ορατές/διαθέσιμες στον/στη φοιτητή/τρια και στα μέλη της τριμελούς επιτροπής.</li>
        <li>Το 3ο βήμα ενεργοποιείται αυτόματα μόλις οριστικοποιηθεί η βαθμολόγηση.</li>
      </ul>
    </section>
  </div>
  <?php
  exit;
}

// ===== DONE =====
if ($thStatus === 'DONE') {
  // Στοιχεία "Προβολής Θέματος"
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
  $th = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

  $supervisor_name = $th['supervisor_name'] ?? null;
  $committee = [];
  if (!empty($supervisor_name))    $committee[] = $supervisor_name;
  if (!empty($th['member1_name'])) $committee[] = $th['member1_name'];
  if (!empty($th['member2_name'])) $committee[] = $th['member2_name'];

  // Ημέρες από ανάθεση
  $days = null;
  if (!empty($th['assigned_date'])) {
    try {
      $today = new DateTimeImmutable('today');
      $ad = new DateTimeImmutable($th['assigned_date']);
      $days = (int)$today->diff($ad)->format('%a');
    } catch (Throwable $e) { $days = null; }
  }

  // Μεταδεδομένα εξέτασης + βαθμός
  $m = $pdo->prepare("SELECT * FROM thesis_exam_meta WHERE thesisID=?");
  $m->execute([$thesisID]);
  $meta = $m->fetch(PDO::FETCH_ASSOC) ?: [];

  $gr = $pdo->prepare("SELECT AVG(grade) AS g FROM grades WHERE thesisID=?");
  $gr->execute([$thesisID]);
  $gRow = $gr->fetch(PDO::FETCH_ASSOC);
  $finalGrade = $gRow && $gRow['g'] !== null ? round((float)$gRow['g'], 2) : null;

  $examDate  = ($meta['exam_datetime'] ?? null) ? date('d/m/Y H:i', strtotime($meta['exam_datetime'])) : '-';
  $examRoom  = trim($meta['exam_room'] ?? '') !== '' ? $meta['exam_room'] : '-';
  $repoUrl   = trim($meta['repository_url'] ?? '') !== '' ? $meta['repository_url'] : null;

  // Ιστορικό κατάστασης + έλεγχος DONE
  $hist = $pdo->prepare("SELECT changeDate, changeTo FROM thesisStatusChanges WHERE thesisID=? ORDER BY changeDate ASC, id ASC");
  $hist->execute([$thesisID]);
  $history = $hist->fetchAll(PDO::FETCH_ASSOC);

  $hasDone = false;
  $lastDate = null;
  foreach ($history as $hrow) {
    if (strtoupper($hrow['changeTo']) === 'DONE') $hasDone = true;
    $lastDate = $hrow['changeDate'];
  }
  if (!$hasDone) {
    // Αν δεν υπάρχει DONE καταχωρημένο, το εμφανίζουμε για λόγους πληρότητας
    $syntheticDate = $lastDate ?: date('Y-m-d');
    $history[] = ['changeDate' => $syntheticDate, 'changeTo' => 'DONE'];
  }

  echo '<h3>Διαχείριση Διπλωματικής — Ολοκληρωμένη (DONE)</h3>';
  echo '<p>Η πτυχιακή έχει ολοκληρωθεί. Ακολουθεί σύνοψη και ιστορικό.</p>';
  ?>
  <style>
    .done-grid { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
    .card { background:#d1d1d1; padding:16px; border-radius:6px; }
    .small{font-size:13px;}
    .btn { background:#4CAF50; color:#fff; border:0; border-radius:4px; padding:8px 14px; cursor:pointer; }
    .btn:hover { background:#3e9444; }
    .muted { color:#333; }
    .kv { margin: 6px 0; }
    .kv b { display:inline-block; min-width:120px; }
    .ul { margin:6px 0 0 18px; }
  </style>

  <div class="done-grid">
    <!-- Αριστερά: Προβολή Θέματος -->
    <section class="card">
      <h4>Προβολή Θέματος</h4>
      <div class="kv"><b>Θέμα:</b> <?php echo h($th['title'] ?? '-'); ?></div>
      <div class="kv"><b>Περιγραφή:</b> <?php echo h($th['th_description'] ?? '-'); ?></div>
      <div class="kv">
        <b>Συνημμένο αρχείο:</b>
        <?php if (!empty($th['pdf_description'])): ?>
          <a href="<?php echo h($th['pdf_description']); ?>" target="_blank">Προβολή ή Λήψη</a>
        <?php else: ?>
          -
        <?php endif; ?>
      </div>
      <div class="kv"><b>Κατάσταση:</b> <?php echo h($th['th_status'] ?? 'DONE'); ?></div>
      <div class="kv"><b>Επιτροπή:</b></div>
      <ul class="ul">
        <?php if ($committee): ?>
          <?php foreach ($committee as $cm): ?>
            <li><?php echo h($cm); ?></li>
          <?php endforeach; ?>
        <?php else: ?>
          <li>-</li>
        <?php endif; ?>
      </ul>
      <div class="kv"><b>Χρόνος από ανάθεση:</b> <?php echo $days !== null ? h($days) : '-'; ?></div>
    </section>

    <!-- Δεξιά: Στοιχεία εξέτασης -->
    <section class="card">
      <h4>Στοιχεία Εξέτασης</h4>
      <div class="kv"><b>Ημ/νία & ώρα:</b> <?php echo h($examDate); ?></div>
      <div class="kv"><b>Αίθουσα:</b> <?php echo h($examRoom); ?></div>
      <div class="kv"><b>Τελικός βαθμός:</b> <?php echo $finalGrade !== null ? h($finalGrade) : '-'; ?></div>
      <div class="kv" style="margin-top:10px;">
        <button class="btn" type="button" onclick="window.open('praktiko_form.php?thesisID=<?php echo $thesisID; ?>','_blank','width=800,height=900,scrollbars=yes')">Πρακτικό</button>
      </div>
    </section>

    <!-- Ιστορικό Κατάστασης -->
    <section class="card" style="grid-column:1 / -1;">
      <h4>Ιστορικό κατάστασης</h4>
      <?php if ($history): ?>
        <ul class="small">
          <?php foreach ($history as $hrow): ?>
            <li><?php echo h(date('d/m/Y', strtotime($hrow['changeDate'])) . ' — ' . strtoupper($hrow['changeTo'])); ?></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="small">Δεν βρέθηκε ιστορικό αλλαγών.</p>
      <?php endif; ?>
    </section>

    <!-- Αποθετήριο -->
    <section class="card" style="grid-column:1 / -1;">
      <h4>Αποθετήριο</h4>
      <?php if ($repoUrl): ?>
        <p class="small">Σύνδεσμος: <a target="_blank" href="<?php echo h($repoUrl); ?>"><?php echo h($repoUrl); ?></a></p>
      <?php else: ?>
        <p class="small">Δεν έχει δηλωθεί σύνδεσμος αποθετηρίου.</p>
      <?php endif; ?>
    </section>
  </div>
  <?php
  exit;
}

// ===== Fallback =====
echo '<h3>Διαχείριση Διπλωματικής — Ενεργή</h3>';
echo '<p>Δεν απαιτούνται ενέργειες αυτή τη στιγμή.</p>';
echo '<div class="announcements" style="min-height:220px;"></div>';
?>
