<?php
session_start();
require_once '../dbconnect.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM teacher WHERE username = ?");
$stmt->execute([$_SESSION['username']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$teacher) {
    header('Location: ../index.php');
    exit;
}

$teacherId = $teacher['id'];
$thesisID = $_GET['thesisID'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');

    $remove = $_POST['remove'] ?? null;
    $thesisRemoveID = trim($_POST['thesisID'] ?? '');

    if (!$thesisRemoveID) {
        echo json_encode(['success' => false, 'message' => 'Thesis ID is required']);
        exit;
    }

    if ($remove !== null) {
        $stmtCheck = $pdo->prepare("SELECT th_status FROM thesis WHERE thesisID = ?");
        $stmtCheck->execute([$thesisRemoveID]);
        $result = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        if ($result['th_status'] === 'ASSIGNED') {
            $pdo->beginTransaction();
            try {
                $stmt1 = $pdo->prepare("UPDATE thesis SET assigned = 0, th_status='NOT_ASSIGNED' WHERE thesisID = ?");
                $stmt2 = $pdo->prepare("UPDATE student SET thesisID = NULL WHERE thesisID = ?");
                $stmt3 = $pdo->prepare("INSERT INTO thesisStatusChanges (thesisID, changeDate, changeTo) VALUES (?, NOW(), 'NOT_ASSIGNED')");
                $stmt4 = $pdo->prepare("UPDATE committee SET member1 = NULL, member2 = NULL, m1_confirmation = NULL, m2_confirmation=NULL WHERE thesisID = ?");

                $stmt1->execute([$thesisRemoveID]);
                $stmt2->execute([$thesisRemoveID]);
                $stmt3->execute([$thesisRemoveID]);
                $stmt4->execute([$thesisRemoveID]);

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Επιτυχής αναίρεση.']);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Αποτυχία αναίρεσης: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Σφάλμα: μη έγκυρο ή τελικό θέμα.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Παρουσίασαν ελλείψεις δεδομένων.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');

    if (!$thesisID) {
        echo json_encode(['success' => false, 'message' => 'Thesis ID is required']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT supervisor, member1, member2 FROM committee WHERE thesisID = ?");
    $stmt->execute([$thesisID]);
    $committee = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($committee) {
        if (!empty($committee['member1'])) {
            $stmtM1 = $pdo->prepare("SELECT t_fname, t_lname FROM teacher WHERE id = ?");
            $stmtM1->execute([$committee['member1']]);
            $m1Name = $stmtM1->fetch(PDO::FETCH_ASSOC);
            $committee['member1Name'] = $m1Name ? ($m1Name['t_fname'] . ' ' . $m1Name['t_lname']) : 'Ν/Α.';
        } else {
            $committee['member1Name'] = 'Ν/Α.';
        }
        if (!empty($committee['member2'])) {
            $stmtM2 = $pdo->prepare("SELECT t_fname, t_lname FROM teacher WHERE id = ?");
            $stmtM2->execute([$committee['member2']]);
            $m2Name = $stmtM2->fetch(PDO::FETCH_ASSOC);
            $committee['member2Name'] = $m2Name ? ($m2Name['t_fname'] . ' ' . $m2Name['t_lname']) : 'Ν/Α.';
        } else {
            $committee['member2Name'] = 'Ν/Α.';
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM committeeinvitations WHERE senderID = ?");
    $stmt->execute([$teacherId]);
    $invitations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($invitations as &$invitation) {
        $stmt = $pdo->prepare("SELECT t_fname, t_lname FROM teacher WHERE id = ?");
        $stmt->execute([$invitation['receiverID']]);
        $name = $stmt->fetch(PDO::FETCH_ASSOC);
        $invitation['receiverName'] = $name ? ($name['t_fname'] . ' ' . $name['t_lname']) : 'Ανώνυμος';
    }

    echo json_encode([
        'success' => true,
        'committee' => $committee ?: [],
        'invitations' => $invitations ?: [],
        'teacherId' => $teacherId,
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8" />
    <link rel="stylesheet" href="../style.css" />
    <title>Ανατεθεμένο Θέμα</title>
</head>
<body>
<header>
    <div class="logo-title-row">
        <button class="back-btn" id="backBtn" aria-label="Back to dashboard">
            <img src="../logo2.jpg" alt="Logo" class="logo" />
        </button>
        <h1 class="site-title">Ανατεθεμένο Θέμα</h1>
    </div>
</header>
<main class="dashboard-main" role="main">
    <h2>Πληροφορίες Θέματος</h2>
    <table class="table" id="committeeTable" aria-describedby="committeeDescription">
        <thead>
            <tr>
                <th>Μέλος 1</th>
                <th>Μέλος 2</th>
            </tr>
        </thead>
        <tbody>
            <!-- AJAX content will go here -->
        </tbody>
    </table>
    <p id="committeeDescription" class="sr-only">Πίνακας με τα μέλη της επιτροπής</p>

    <h3>Προσκλήσεις</h3>
    <ul class="invitation-list" id="invitationList">
        <!-- AJAX content -->
    </ul>

    <form id="unassignForm" style="display:none;">
        <input type="hidden" name="thesisID" id="thesisID" value="<?= htmlspecialchars($thesisID, ENT_QUOTES) ?>" />
        <button type="submit" class="submit-btn">Αναίρεση Ανάθεσης</button>
    </form>
</main>
<footer class="footer" role="contentinfo">
    <p>© 2025 Thesis Management System</p>
</footer>
<script src="assignedthesis.js"></script>
</body>
</html>
