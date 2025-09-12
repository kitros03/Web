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

/*
  STATUS MAP
  - ASSIGNED  -> Dashboard Προσκλήσεων
  - ACTIVE    -> Dashboard Ενεργή
  - EXAM      -> Dashboard Υπό Εξέταση (3 bullets)
*/

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

  // Ξεκλείδωμα 3ου βήματος όταν thesis.grading = 1 (εναλλακτικά, COUNT(*) από grades)
  $gt = $pdo->prepare("SELECT grading FROM thesis WHERE thesisID=?");
  $gt->execute([$thesisID]);
  $gradingFlag = (int)$gt->fetchColumn();
  $after_enabled = ($gradingFlag === 1);

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
        <?php if (!empty($meta['draft_file'])): ?>
          <p class="small mt-8">Τρέχον αρχείο: <a class="mono" target="_blank" href="<?php echo h($meta['draft_file']); ?>">Άνοιγμα</a></p>
        <?php endif; ?>
      </form>
    </section>

    <section class="card">
      <h4>2) Δήλωση εξέτασης</h4>
      <form id="examScheduleForm">
        <input type="hidden" name="action" value="save_schedule">
        <input type="hidden" name="thesisID" value="<?php echo $thesisID; ?>">
        <label>Ημερομηνία & Ώρα:</label>
        <input class="w-100" type="datetime-local" name="exam_datetime" value="<?php echo h($meta['exam_datetime'] ?? ''); ?>">
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
        <label>Σύνδεσμος πρακτικού (HTML/PDF):</label>
        <input class="w-100" type="url" name="report_url" value="<?php echo h($meta['report_url'] ?? ''); ?>" <?php echo $after_enabled ? '' : 'disabled'; ?>>
        <label class="mt-8">Σύνδεσμος αποθετηρίου (Νημερτής):</label>
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

  <script>
  async function postForm(url, form) {
    const r = await fetch(url, { method:'POST', body: form, credentials:'same-origin' });
    return r.json();
  }
  document.getElementById('examDraftForm')?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const fd = new FormData(e.target);
    const res = await postForm('thesis_exam_actions.php', fd);
    alert(res.message || (res.success ? 'Αποθηκεύτηκε' : 'Σφάλμα'));
    if (res.success && res.reload) location.reload();
  });
  document.getElementById('examScheduleForm')?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const fd = new FormData(e.target);
    const res = await postForm('thesis_exam_actions.php', fd);
    alert(res.message || (res.success ? 'Αποθηκεύτηκε' : 'Σφάλμα'));
  });
  document.getElementById('examAfterForm')?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const fd = new FormData(e.target);
    const res = await postForm('thesis_exam_actions.php', fd);
    alert(res.message || (res.success ? 'Αποθηκεύτηκε' : 'Σφάλμα'));
  });
  </script>
  <?php
  exit;
}

// Fallback
echo '<h3>Διαχείριση Διπλωματικής — Ενεργή</h3>';
echo '<p>Δεν απαιτούνται ενέργειες αυτή τη στιγμή.</p>';
echo '<div class="announcements" style="min-height:220px;"></div>';
