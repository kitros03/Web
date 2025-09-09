<?php
require_once __DIR__.'/dbconnect.php';
session_start();
header('Content-Type: text/html; charset=utf-8');

if (empty($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'student') {
  echo '<h3>Διαχείριση Διπλωματικής</h3><p>Συνδέσου για να διαχειριστείς την πτυχιακή.</p>';
  exit;
}

// Student + Thesis
$st = $pdo->prepare("SELECT s.studentID, s.thesisID, t.supervisor
                     FROM student s
                     LEFT JOIN thesis t ON t.thesisID = s.thesisID
                     WHERE s.username=? LIMIT 1");
$st->execute([$_SESSION['username']]);
$me = $st->fetch(PDO::FETCH_ASSOC);

if (!$me || !$me['thesisID']) {
  echo '<h3>Διαχείριση Διπλωματικής</h3>';
  echo '<p>Δεν έχει ανατεθεί πτυχιακή.</p>';
  exit;
}
$studentID    = (int)$me['studentID'];
$thesisID     = (int)$me['thesisID'];
$supervisorID = (int)($me['supervisor'] ?? 0);

// Τρέχουσα κατάσταση
$st2 = $pdo->prepare("SELECT th_status FROM thesis WHERE thesisID=?");
$st2->execute([$thesisID]);
$thStatus = (string)$st2->fetchColumn();

// ========= View όταν είναι Υπό Εξέταση (EXAM) =========
if ($thStatus === 'ACTIVE') {
  echo '<h3>Διαχείριση Διπλωματικής — Ενεργή </h3>';
  echo '<p>Η πτυχιακή είναι σε κατάσταση <strong>Ενεργή</strong>. Οι προσκλήσεις έχουν ολοκληρωθεί. Ανέμενε από καθηγητή να αλλάξει την κατάσταση</p>';
  // Κενό panel, ίδιο layout
  echo '<div class="announcements" style="min-height:220px;"></div>';
  exit;
}

// ========= View όταν είναι Υπό Ανάθεση (ASSIGNED ή οτιδήποτε πλην EXAM) =========
echo '<h3>Διαχείριση Διπλωματικής — Υπό Ανάθεση</h3>';

// Λίστα διδασκόντων για αποστολή πρόσκλησης
$list = $pdo->query("SELECT username, CONCAT(t_fname,' ',t_lname) AS name, id
                     FROM teacher
                     ORDER BY t_lname, t_fname")->fetchAll(PDO::FETCH_ASSOC);

// Τρέχουσες προσκλήσεις (για τον πίνακα)
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

<!-- Φόρμα αποστολής πρόσκλησης (όπως στη φωτογραφία/παλιό μενού) -->
<form id="inviteForm" class="form" method="post">
  <input type="hidden" name="action" value="invite">
  <label for="teacherUsername">Διδάσκων (username)</label>
  <select id="teacherUsername" name="teacherUsername" required>
    <option value="">Επέλεξε διδάσκοντα</option>
    <?php foreach ($list as $t): ?>
      <?php if ($supervisorID && (int)$t['id'] === $supervisorID) continue; ?>
      <option value="<?= htmlspecialchars($t['username']) ?>">
        <?= htmlspecialchars($t['username'].' — '.$t['name']) ?>
      </option>
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
        <tr>
          <th>Προς Διδάσκοντα</th>
          <th>Ημερομηνία</th>
          <th>Κατάσταση</th>
          <th>Τελευταία ενέργεια</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($invs as $iv): ?>
          <tr>
            <td><?= htmlspecialchars($iv['teacher_username'].' — '.$iv['teacher_name']) ?></td>
            <td><?= htmlspecialchars($iv['invitationDate']) ?></td>
            <td><?= htmlspecialchars(statusText($iv['response'])) ?></td>
            <td><?= htmlspecialchars(lastDateText($iv['responseDate'], $iv['response'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
