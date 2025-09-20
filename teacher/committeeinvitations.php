<?php
session_start();

require_once '../dbconnect.php';
if (empty($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$qt = $pdo->prepare("SELECT id FROM teacher WHERE username=? LIMIT 1");
$qt->execute([$_SESSION['username']]);
$teacherID = (int)$qt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    header('Content-Type: application/json; charset=utf-8');
    
    $invID = isset($_POST['invitationID']) ? (int)$_POST['invitationID'] : 0;
    $decision = $_POST['decision'] ?? null; // "accept" or "reject"

    if ($invID <= 0 || !in_array($decision, ['accept', 'reject'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }

    $resp = ($decision === 'accept') ? 1 : 0;
    $upd = $pdo->prepare("UPDATE committeeInvitations SET response=?, responseDate=CURDATE() WHERE invitationID=? AND receiverID=?");
    $ok = $upd->execute([$resp, $invID, $teacherID]);

    if (!$ok || !$upd->rowCount()) {
        echo json_encode(['success' => false, 'message' => 'Invitation not found']);
        exit;
    }

    // Find thesisID from invitation
    $ql = $pdo->prepare("SELECT s.thesisID FROM committeeInvitations ci JOIN student s ON s.studentID = ci.senderID WHERE ci.invitationID=?");
    $ql->execute([$invID]);
    $thesisID = (int)$ql->fetchColumn();

    // If accepted and thesis exists, add teacher as committee member if not already present
    if ($resp === 1 && $thesisID > 0) {
        $qC = $pdo->prepare("SELECT supervisor, member1, member2 FROM committee WHERE thesisID=?");
        $qC->execute([$thesisID]);
        $comm = $qC->fetch(PDO::FETCH_ASSOC);

        $members = array_filter([
            isset($comm['supervisor']) ? (int)$comm['supervisor'] : null,
            isset($comm['member1']) ? (int)$comm['member1'] : null,
            isset($comm['member2']) ? (int)$comm['member2'] : null
        ]);

        if (!in_array($teacherID, $members, true)) {
            if (empty($comm['member1'])) {
                $pdo->prepare("UPDATE committee SET member1=? WHERE thesisID=?")->execute([$teacherID, $thesisID]);
            } elseif (empty($comm['member2'])) {
                $pdo->prepare("UPDATE committee SET member2=? WHERE thesisID=?")->execute([$teacherID, $thesisID]);
            }
        }
    }

    // Check if committee is complete to update thesis status
    $promoted = false;
    if ($thesisID > 0) {
        $stq = $pdo->prepare("SELECT th_status FROM thesis WHERE thesisID=?");
        $stq->execute([$thesisID]);
        $curr = (string)$stq->fetchColumn();

        $cq = $pdo->prepare("SELECT supervisor, member1, member2 FROM committee WHERE thesisID=?");
        $cq->execute([$thesisID]);
        $c = $cq->fetch(PDO::FETCH_ASSOC) ?: [];

        $countMembers = 0;
        if (!empty($c['supervisor'])) $countMembers++;
        if (!empty($c['member1'])) $countMembers++;
        if (!empty($c['member2'])) $countMembers++;

        if ($curr === 'ASSIGNED' && $countMembers >= 3) {
            $pdo->prepare("UPDATE thesis SET th_status='ACTIVE' WHERE thesisID=?")->execute([$thesisID]);
            $pdo->prepare("INSERT INTO thesisStatusChanges (thesisID, changeDate, changeTo) VALUES (?, CURDATE(), 'ACTIVE')")->execute([$thesisID]);
            $pdo->prepare("
                DELETE ci FROM committeeInvitations ci
                JOIN student s ON s.studentID = ci.senderID
                WHERE s.thesisID=? AND ci.response IS NULL
            ")->execute([$thesisID]);
            $promoted = true;
        }
    }

    echo json_encode(['success' => true, 'promoted' => $promoted]);
    exit;
}

// AJAX GET to list pending invitations
if ($_SERVER['REQUEST_METHOD'] === 'GET' &&
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {

    header('Content-Type: application/json; charset=utf-8');

    $stmt = $pdo->prepare("
        SELECT ci.invitationID, ci.invitationDate,
               CONCAT(st.s_fname, ' ', st.s_lname) AS senderName,
               th.title AS thesisTitle,
               ci.response, ci.responseDate
        FROM committeeInvitations ci
        JOIN student st ON st.studentID = ci.senderID
        LEFT JOIN thesis th ON th.thesisID = st.thesisID
        WHERE ci.receiverID = ? AND ci.response IS NULL
        ORDER BY ci.invitationDate DESC, ci.invitationID DESC
    ");
    $stmt->execute([$teacherID]);
    $invitations = $stmt->fetchAll();

    echo json_encode($invitations);
    exit;
}

http_response_code(405);
echo 'Method not allowed';
?>

<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <title>Committee Invitations</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
  <header>
  <div class="logo-title-row">
        <button class="back-btn" id="backBtn">
            <img src="../logo2.jpg" alt="Logo" class="logo" />
        </button>
        <h1 class="site-title">Committee Invitations</h1>
    </div>
  </header>
  <main class="dashboard-main">
    <div class="announcements">
      <h2>Προσκλήσεις Επιτροπής</h2>
      <p>Αποδοχή ή απόρριψη προσκλήσεων τριμελούς.</p>
      <table class="table" id="invitationTable">
      <thead>
      <tr><th>Θέμα Πτυχιακής</th><th>Αποστολέας</th><th>Ημερομηνία</th><th>Ενέργειες</th></tr>
      </thead>
      <tbody id="invitationBody">
      <tr><td colspan="4">Φόρτωση...</td></tr>
      </tbody>
      </table>
    </div>
  </main>
  <footer class="footer">
  <p>© 2025</p>
  </footer>
    <script src="committeeinvitations.js"></script></script>
</body>
</html>
