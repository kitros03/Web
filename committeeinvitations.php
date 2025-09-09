<?php
require_once __DIR__.'/dbconnect.php';
session_start();

if (empty($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'teacher') {
  http_response_code(403); echo 'Forbidden'; exit;
}

$qt = $pdo->prepare("SELECT id FROM teacher WHERE username=? LIMIT 1");
$qt->execute([$_SESSION['username']]);
$teacherID = (int)$qt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json; charset=utf-8');
  $invID = (int)($_POST['invitationID'] ?? 0);
  $decision = $_POST['decision'] ?? null; // accept|reject
  if ($invID<=0 || !in_array($decision,['accept','reject'],true)) {
    echo json_encode(['success'=>false,'message'=>'Invalid input']); exit;
  }
  $resp = ($decision==='accept') ? 1 : 0;
  $upd = $pdo->prepare("UPDATE committeeInvitations SET response=?, responseDate=CURDATE() WHERE invitationID=? AND receiverID=?");
  $ok = $upd->execute([$resp, $invID, $teacherID]);
  if (!$ok || !$upd->rowCount()) { echo json_encode(['success'=>false,'message'=>'Invitation not found']); exit; }

  // thesis από το invitation
  $ql = $pdo->prepare("SELECT s.thesisID FROM committeeInvitations ci JOIN student s ON s.studentID = ci.senderID WHERE ci.invitationID=?");
  $ql->execute([$invID]);
  $thesisID = (int)$ql->fetchColumn();

  // Αν Accept => πρόσθεσε ως member1/member2 αν λείπει
  if ($resp === 1 && $thesisID > 0) {
    $qC = $pdo->prepare("SELECT supervisor, member1, member2 FROM committee WHERE thesisID=? LIMIT 1");
    $qC->execute([$thesisID]);
    if ($comm = $qC->fetch(PDO::FETCH_ASSOC)) {
      $already = in_array($teacherID, array_map('intval', [
        (int)$comm['supervisor'], (int)$comm['member1'], (int)$comm['member2']
      ]), true);
      if (!$already) {
        if (empty($comm['member1'])) {
          $pdo->prepare("UPDATE committee SET member1=? WHERE thesisID=?")->execute([$teacherID, $thesisID]);
        } elseif (empty($comm['member2'])) {
          $pdo->prepare("UPDATE committee SET member2=? WHERE thesisID=?")->execute([$teacherID, $thesisID]);
        }
      }
    }
  }

  // Έλεγχος τριάδας και προαγωγή με διαγραφή pending
  $promoted = false;
  if ($thesisID > 0) {
    $stq = $pdo->prepare("SELECT th_status FROM thesis WHERE thesisID=?");
    $stq->execute([$thesisID]);
    $curr = (string)$stq->fetchColumn();

    $cq = $pdo->prepare("SELECT supervisor, member1, member2 FROM committee WHERE thesisID=?");
    $cq->execute([$thesisID]);
    $c = $cq->fetch(PDO::FETCH_ASSOC) ?: [];

    $total = 0;
    if (!empty($c['supervisor'])) $total++;
    if (!empty($c['member1']))   $total++;
    if (!empty($c['member2']))   $total++;

    if ($curr === 'ASSIGNED' && $total >= 3) {
      $pdo->prepare("UPDATE thesis SET th_status='EXAM' WHERE thesisID=?")->execute([$thesisID]);
      $pdo->prepare("INSERT INTO thesisStatusChanges (thesisID, changeDate, changeTo) VALUES (?, CURDATE(), 'EXAM')")
          ->execute([$thesisID]);
      // Διαγραφή ΟΛΩΝ των pending invitations
      $pdo->prepare("
        DELETE ci FROM committeeInvitations ci
        JOIN student s ON s.studentID = ci.senderID
        WHERE s.thesisID=? AND ci.response IS NULL
      ")->execute([$thesisID]);
      $promoted = true;
    }
  }

  echo json_encode(['success'=>true,'promoted'=>$promoted]); exit;
}

// Προβολή λίστας για τον καθηγητή
$stmt = $pdo->prepare("
 SELECT ci.invitationID, ci.invitationDate,
        CONCAT(st.s_fname,' ',st.s_lname) AS senderName,
        th.title AS thesisTitle
 FROM committeeInvitations ci
 JOIN student st ON st.studentID = ci.senderID
 LEFT JOIN thesis th ON th.thesisID = st.thesisID
 WHERE ci.receiverID = ?
 ORDER BY ci.invitationDate DESC, ci.invitationID DESC
");
$stmt->execute([$teacherID]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="stylesheet" href="style.css">
  <title>Committee Invitations</title>
</head>
<body>
  <header>
    <div class="logo-title-row">
      <button class="back-btn" id="backBtn" onclick="location.href='teacherdashboard.php'">
        <img src="logo2.jpg" alt="Logo" class="logo" />
      </button>
      <h1 class="site-title">Committee Invitations</h1>
    </div>
  </header>

  <div class="dashboard-main">
    <div class="announcements">
      <h2>Προσκλήσεις Επιτροπής</h2>
      <p>Αποδοχή ή απόρριψη προσκλήσεων τριμελούς.</p>

      <table class="table" id="invTable">
        <thead>
          <tr>
            <th>Θέμα Πτυχιακής</th>
            <th>Αποστολέας</th>
            <th>Ημερομηνία</th>
            <th>Ενέργειες</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="4">Δεν βρέθηκαν προσκλήσεις.</td></tr>
          <?php else: foreach ($rows as $inv): ?>
            <tr data-inv-id="<?= (int)$inv['invitationID'] ?>">
              <td><?= htmlspecialchars($inv['thesisTitle'] ?? '-') ?></td>
              <td><?= htmlspecialchars($inv['senderName'] ?? '-') ?></td>
              <td><?= htmlspecialchars($inv['invitationDate'] ?? '-') ?></td>
              <td>
                <button class="submit-btn accept-btn" data-id="<?= (int)$inv['invitationID'] ?>">Αποδοχή</button>
                <button class="delete-btn reject-btn" data-id="<?= (int)$inv['invitationID'] ?>">Απόρριψη</button>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <footer class="footer">
    <p>&copy; 2025 Thesis Management System</p>
  </footer>

  <script>
  // Αισιόδοξη αφαίρεση γραμμής
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.accept-btn, .reject-btn');
    if (!btn) return;
    const row = btn.closest('tr');
    const id = btn.dataset.id;
    const decision = btn.classList.contains('accept-btn') ? 'accept' : 'reject';
    const prevDisplay = row.style.display;
    row.style.display = 'none';
    const fd = new FormData();
    fd.append('invitationID', id);
    fd.append('decision', decision);
    try {
      const resp = await fetch('committeeinvitations.php', { method:'POST', credentials:'same-origin', body: fd });
      const data = await resp.json();
      if (!data || !data.success) {
        row.style.display = prevDisplay || '';
        alert((data && data.message) ? data.message : 'Σφάλμα ενημέρωσης.');
      } else {
        row.remove();
        const tbody = document.querySelector('#invTable tbody');
        if (tbody && !tbody.querySelector('tr')) {
          const tr = document.createElement('tr');
          tr.innerHTML = '<td colspan="4">Δεν βρέθηκαν προσκλήσεις.</td>';
          tbody.appendChild(tr);
        }
      }
    } catch (err) {
      row.style.display = prevDisplay || '';
      alert('Σφάλμα δικτύου.');
    }
  });
  </script>
</body>
</html>
