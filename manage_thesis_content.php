<?php
// manage_thesis_content.php (PARTIAL — χωρίς <html> <body>)
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
require_once __DIR__ . '/dbconnect.php';

if (empty($_SESSION['username'])) {
  echo '<section class="card empty-state"><h2 class="card-title">Δεν έχει γίνει σύνδεση</h2><p class="muted">Συνδέσου για να διαχειριστείς την πτυχιακή.</p></section>';
  return;
}

// 1) Thesis του φοιτητή
$sql = "
  SELECT t.thesisID, t.title, t.th_status
  FROM student st
  JOIN thesis  t ON t.thesisID = st.thesisID
  WHERE st.username = ?
  LIMIT 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['username']]);
$thesis = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$thesis) {
  echo '<section class="card empty-state"><h2 class="card-title">Δεν έχει ανατεθεί πτυχιακή</h2><p class="muted">Αφού ανατεθεί, θα εμφανιστούν εδώ οι επιλογές διαχείρισης.</p></section>';
  return;
}

$thesisID = (int)$thesis['thesisID'];
$status   = (string)($thesis['th_status'] ?? '');

// 2) Committee row (για αποκλεισμούς στο select)
$stmt = $pdo->prepare("SELECT supervisor, member1, member2 FROM committee WHERE thesisID=? LIMIT 1");
$stmt->execute([$thesisID]);
$comm = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['supervisor'=>null,'member1'=>null,'member2'=>null];

// 3) ReceiverIDs από εκκρεμείς/προηγούμενες προσκλήσεις για να μη διπλοπροκληθούν
$sqlInvRx = "
  SELECT ci.receiverID
  FROM committeeInvitations ci
  JOIN student st ON st.studentID = ci.senderID
  WHERE st.thesisID = ?
";
$stmtInvRx = $pdo->prepare($sqlInvRx);
$stmtInvRx->execute([$thesisID]);
$invReceiverIds = $stmtInvRx->fetchAll(PDO::FETCH_COLUMN);

// 4) Λίστα διδασκόντων (usernames) με αποκλεισμούς supervisor/μέλη/ήδη προσκεκλημένους
$exclude = array_unique(array_values(array_filter([
  $comm['supervisor'] ?? null,
  $comm['member1']    ?? null,
  $comm['member2']    ?? null,
  ...($invReceiverIds ?: [])
], static fn($v) => !is_null($v))));

if (!empty($exclude)) {
  $in   = implode(',', array_fill(0, count($exclude), '?'));
  $sqlT = "SELECT id, username, t_fname, t_lname FROM teacher WHERE id NOT IN ($in) ORDER BY username ASC";
  $stmtT = $pdo->prepare($sqlT);
  $stmtT->execute($exclude);
} else {
  $stmtT = $pdo->query("SELECT id, username, t_fname, t_lname FROM teacher ORDER BY username ASC");
}
$teachers = $stmtT->fetchAll(PDO::FETCH_ASSOC);

// 5) Flags προβολής ενοτήτων
$showCommittee = ($status === 'ASSIGNED'); // Υπό ανάθεση
$showExam      = ($status === 'EXAM');     // Υπό εξέταση
$showLibrary   = ($status === 'DONE');     // Περατωμένη
?>

<section class="card">
  <h2 class="card-title">Διαχείριση Διπλωματικής</h2>
  <ul class="meta-list">
    <li><span class="meta-label">Θέμα</span><span class="meta-value"><?= htmlspecialchars($thesis['title'] ?? '') ?></span></li>
    <li><span class="meta-label">Κατάσταση</span><span class="badge badge-status"><?= htmlspecialchars($status) ?></span></li>
  </ul>
</section>

<?php if ($showCommittee): ?>
  <?php
  // Πίνακας προσκλήσεων (JOIN: invitation → student → thesis για τίτλο)
  $sqlInvList = "
    SELECT 
      ci.invitationID,
      ci.invitationDate,
      ci.response,
      ci.responseDate,
      tr.username AS teacher_username,
      CONCAT(tr.t_fname, ' ', tr.t_lname) AS teacher_name
    FROM committeeInvitations ci
    JOIN student st ON st.studentID = ci.senderID
    JOIN teacher tr ON tr.id        = ci.receiverID
    WHERE st.thesisID = ?
    ORDER BY ci.invitationDate DESC, ci.invitationID DESC
  ";
  $stmtInvList = $pdo->prepare($sqlInvList);
  $stmtInvList->execute([$thesisID]);
  $inviteRows = $stmtInvList->fetchAll(PDO::FETCH_ASSOC);
  ?>
  <section class="card">
    <h3 class="card-title">Προσθήκη Μελών Επιτροπής</h3>
    <form id="inviteForm" class="stack" method="post" action="student_manage_thesis.php">
      <input type="hidden" name="action" value="invite">
      <!-- Στέλνουμε username ώστε το backend να το επιλύσει σε id -->
      <label class="form-label">Διδάσκων (username)</label>
      <select class="input" name="teacherUsername" id="teacherUsername" required>
        <option value="" selected disabled>Επίλεξε διδάσκοντα</option>
        <?php foreach ($teachers as $t): ?>
          <option value="<?= htmlspecialchars($t['username']) ?>">
            <?= htmlspecialchars($t['username']) ?> — <?= htmlspecialchars($t['t_fname'].' '.$t['t_lname']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary" type="submit">Αποστολή Πρόσκλησης</button>
    </form>

    <div class="spacer"></div>
    <h4>Προσκλήσεις Επιτροπής</h4>
    <?php if (!$inviteRows): ?>
      <p class="muted">Καμία πρόσκληση.</p>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th>Προς Διδάσκοντα</th>
            <th>Ημερομηνία</th>
            <th>Κατάσταση</th>
            <th>Τελευταία ενέργεια</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($inviteRows as $iv): 
            $statusText = is_null($iv['response']) ? 'Σε αναμονή' : ($iv['response'] ? 'Αποδοχή' : 'Απόρριψη');
            $lastDate   = $iv['responseDate'] ?: $iv['invitationDate'];
          ?>
            <tr>
              <td><?= htmlspecialchars($iv['teacher_username'].' — '.$iv['teacher_name']) ?></td>
              <td><?= htmlspecialchars($iv['invitationDate']) ?></td>
              <td><?= htmlspecialchars($statusText) ?></td>
              <td><?= htmlspecialchars($lastDate) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php if ($showExam): ?>
  <section class="card">
    <h3 class="card-title">Υλικό & Εξέταση</h3>
    <form id="draftForm" class="stack" method="post" action="student_manage_thesis.php">
      <input type="hidden" name="action" value="save_draft">
      <input type="hidden" name="thesisID" value="<?= htmlspecialchars((string)$thesisID) ?>">
      <label class="form-label">Σύνδεσμος πρόχειρου (π.χ. Drive)</label>
      <input class="input" type="url" name="draftUrl" placeholder="https://...">
      <button class="btn btn-primary" type="submit">Αποθήκευση</button>
    </form>

    <form id="examForm" class="stack" method="post" action="student_manage_thesis.php">
      <input type="hidden" name="action" value="save_exam">
      <input type="hidden" name="thesisID" value="<?= htmlspecialchars((string)$thesisID) ?>">
      <label class="form-label">Ημερομηνία</label>
      <input class="input" type="date" name="examDate">
      <label class="form-label">Ώρα</label>
      <input class="input" type="time" name="examTime">
      <label class="form-label">Χώρος/Σύνδεσμος</label>
      <input class="input" type="text" name="examPlace" placeholder="Αίθουσα ή URL">
      <button class="btn btn-primary" type="submit">Αποθήκευση</button>
    </form>
  </section>
<?php endif; ?>

<?php if ($showLibrary): ?>
  <section class="card">
    <h3 class="card-title">Σύνδεσμος Τελικού Κειμένου (Βιβλιοθήκη)</h3>
    <form id="libraryForm" class="stack" method="post" action="student_manage_thesis.php">
      <input type="hidden" name="action" value="save_library">
      <input type="hidden" name="thesisID" value="<?= htmlspecialchars((string)$thesisID) ?>">
      <label class="form-label">URL Βιβλιοθήκης</label>
      <input class="input" type="url" name="libraryUrl" placeholder="https://...">
      <button class="btn btn-primary" type="submit">Αποθήκευση</button>
    </form>
  </section>
<?php endif; ?>
