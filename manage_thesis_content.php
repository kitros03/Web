<?php
require_once __DIR__.'/dbconnect.php';
session_start();
header('Content-Type: text/html; charset=utf-8');

// Μόνο φοιτητές βλέπουν αυτό το panel (student view)
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

if ($thStatus === 'ASSIGNED') {
  echo '<h3>Διαχείριση Διπλωματικής — Υπό Ανάθεση</h3>';
  echo '<p>Αποστολή προσκλήσεων προς μέλη τριμελούς. Μετά από 2 αποδοχές (μαζί με τον επιβλέποντα =&gt; 3 συνολικά), η κατάσταση θα γίνει <strong>Ενεργή</strong>.</p>';

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

if ($thStatus === 'ACTIVE') {
  echo '<h3>Διαχείριση Διπλωματικής — Ενεργή</h3>';
  echo '<p>Η πτυχιακή είναι σε κατάσταση <strong>Ενεργή</strong>. Αναμένεται ο επιβλέπων/τριμελής να θέσουν την κατάσταση <strong>Υπό Εξέταση (EXAM)</strong>.</p>';
  echo '<div class="announcements" style="min-height:220px;"></div>';
  exit;
}

// ============ EXAM: 3 βήματα ============
if ($thStatus === 'EXAM') {
  // Φόρτωση meta
  $m = $pdo->prepare("SELECT * FROM thesis_exam_meta WHERE thesisID=?");
  $m->execute([$thesisID]);
  $meta = $m->fetch(PDO::FETCH_ASSOC) ?: [];

  // Έλεγχος αν υπάρχει αρχείο (τώρα είναι file path, όχι BLOB)
  $hasFile = !empty($meta['draft_file']) && file_exists(__DIR__ . '/' . $meta['draft_file']);

  // Ξεκλείδωμα 3ου βήματος όταν thesis.grading = 1
// Ξεκλείδωμα 3ου βήματος όταν ΥΠΑΡΧΟΥΝ 3 εγγραφές grades για τη thesis
// και όλες έχουν μη-NULL calc_grade
$gq = $pdo->prepare("
  SELECT 
    SUM(CASE WHEN calc_grade IS NOT NULL THEN 1 ELSE 0 END) AS non_null_cnt,
    COUNT(*) AS total_cnt
  FROM grades
  WHERE thesisID = ?
");
$gq->execute([$thesisID]);
list($nonNullCnt, $totalCnt) = array_map('intval', $gq->fetch(PDO::FETCH_NUM));

// Προϋπόθεση: ακριβώς 3 εγγραφές και ΚΑΜΙΑ calc_grade NULL
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
        <textarea class="w-100" name="external_links" rows="4" placeholder="https://drive.google.com/...
https://youtube.com/..."><?php
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
        <li>Το 3ο βήμα ενεργοποιείται αυτόματα μόλις οριστικοποιηθεί η βαθμολόγηση από τον επιβλέποντα.</li>
      </ul>
    </section>
  </div>
  <?php
  exit;
}

// Fallback
echo '<h3>Διαχείριση Διπλωματικής — Ενεργή</h3>';
echo '<p>Δεν απαιτούνται ενέργειες αυτή τη στιγμή.</p>';
echo '<div class="announcements" style="min-height:220px;"></div>';
?>
