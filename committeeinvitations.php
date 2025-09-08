<?php
// committeeinvitations.php (ή το αρχείο που δείχνει στον διδάσκοντα τις προσκλήσεις)
declare(strict_types=1);
session_start();
require_once __DIR__ . '/dbconnect.php';

if (!isset($_SESSION['username'])) {
  header('Location: index.html');
  exit;
}
header('Content-Type: text/html; charset=utf-8');

// Βρες id διδάσκοντα από username
$stmt = $pdo->prepare("SELECT id FROM teacher WHERE username = ? LIMIT 1");
$stmt->execute([$_SESSION['username']]);
$teacherID = (int)$stmt->fetchColumn();

// Φέρε εκκρεμείς προσκλήσεις προς τον συγκεκριμένο διδάσκοντα
// JOIN με student για thesisID, JOIN με thesis για τίτλο
$sql = "
  SELECT
    ci.invitationID,
    ci.invitationDate,
    st.studentID AS senderID,
    st.s_fname, st.s_lname,
    th.thesisID,
    th.title AS thesisTitle
  FROM committeeInvitations ci
  JOIN student st ON st.studentID = ci.senderID
  JOIN thesis  th ON th.thesisID  = st.thesisID
  WHERE ci.response IS NULL
    AND ci.receiverID = ?
  ORDER BY ci.invitationDate DESC, ci.invitationID DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$teacherID]);
$invitations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// AJAX accept/decline
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['response'], $_POST['invitationID'])) {
  header('Content-Type: application/json; charset=utf-8');

  $response     = $_POST['response']; // '1' αποδοχή, '0' απόρριψη
  $invitationID = (int)($_POST['invitationID'] ?? 0);

  if (!in_array($response, ['1','0'], true) || $invitationID <= 0) {
    echo json_encode(['success' => false, 'message' => 'Μη έγκυρη ενέργεια.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Βρες thesisID της πρόσκλησης μέσω JOIN στο student
  $q = $pdo->prepare("
    SELECT th.thesisID
    FROM committeeInvitations ci
    JOIN student st ON st.studentID = ci.senderID
    JOIN thesis  th ON th.thesisID  = st.thesisID
    WHERE ci.invitationID = ?
    LIMIT 1
  ");
  $q->execute([$invitationID]);
  $thesisID = (int)$q->fetchColumn();

  if ($thesisID <= 0) {
    echo json_encode(['success' => false, 'message' => 'Δεν βρέθηκε πτυχιακή για την πρόσκληση.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Έλεγχος πληρότητας επιτροπής
  $q = $pdo->prepare("SELECT member1, member2 FROM committee WHERE thesisID = ? LIMIT 1");
  $q->execute([$thesisID]);
  $committee = $q->fetch(PDO::FETCH_ASSOC);
  if ($committee && !empty($committee['member1']) && !empty($committee['member2']) && $response === '1') {
    echo json_encode(['success' => false, 'message' => 'Η επιτροπή είναι πλήρης.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Καταγραφή απάντησης πρόσκλησης
  $u1 = $pdo->prepare("UPDATE committeeInvitations SET response = ? WHERE invitationID = ?");
  $u2 = $pdo->prepare("UPDATE committeeInvitations SET responseDate = CURDATE() WHERE invitationID = ?");
  $ok = $u1->execute([(int)$response, $invitationID]) && $u2->execute([$invitationID]);

  if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'Αποτυχία καταγραφής απάντησης.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Αν αποδέχθηκε, πρόσθεσε τον διδάσκοντα στην επιτροπή (member1 ή member2) και μαρκάρισε επιβεβαίωση = 1
  if ($response === '1') {
    // Βρες τρέχουσα κατάσταση μέλους1
    $q = $pdo->prepare("SELECT member1 FROM committee WHERE thesisID = ? LIMIT 1");
    $q->execute([$thesisID]);
    $member1 = $q->fetchColumn();

    if ($member1) {
      // Γίνεται member2
      $upd = $pdo->prepare("UPDATE committee SET member2 = ?, m2_confirmation = 1 WHERE thesisID = ?");
      $upd->execute([$teacherID, $thesisID]);
    } else {
      // Δεν υπάρχει member1 → γίνε member1
      $upd = $pdo->prepare("UPDATE committee SET member1 = ?, m1_confirmation = 1 WHERE thesisID = ?");
      $upd->execute([$teacherID, $thesisID]);
    }
  }

  echo json_encode(['success' => true, 'message' => 'Η απάντηση καταγράφηκε.'], JSON_UNESCAPED_UNICODE);
  exit;
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <title>Committee Invitations</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
  <div class="logo-title-row">
    <button class="back-btn" id="backBtn">
      <img src="logo2.jpg" alt="Logo" class="logo" />
    </button>
    <h1 class="site-title">Committee Invitations</h1>
  </div>
</header>
<div class="dashboard-container">
  <main class="dashboard-main">
    <h2>Προσκλήσεις Επιτροπής</h2>
    <p>Αποδοχή ή απόρριψη προσκλήσεων τριμελούς.</p>

    <table class="table">
      <tr>
        <th>Θέμα Πτυχιακής</th>
        <th>Αποστολέας</th>
        <th>Ημερομηνία</th>
        <th>Ενέργειες</th>
      </tr>
      <?php if (!$invitations): ?>
        <tr><td colspan="4">Δεν βρέθηκαν προσκλήσεις.</td></tr>
      <?php else: foreach ($invitations as $inv): ?>
        <tr>
          <td><?= htmlspecialchars($inv['thesisTitle']) ?></td>
          <td><?= htmlspecialchars($inv['s_fname'].' '.$inv['s_lname']) ?></td>
          <td><?= htmlspecialchars($inv['invitationDate']) ?></td>
          <td>
            <form class="invitationForm" method="post">
              <input type="hidden" name="invitationID" value="<?= (int)$inv['invitationID'] ?>">
              <button class="submit-btn" type="submit" name="response" value="1">Αποδοχή</button>
              <button class="submit-btn" type="submit" name="response" value="0">Απόρριψη</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </table>
    <script src="committeeinvitations.js"></script>
  </main>
</div>
<footer class="footer"><p>&copy; 2025 Thesis Management System</p></footer>
</body>
</html>
