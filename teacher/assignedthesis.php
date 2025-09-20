<?php
session_start();
require_once 'dbconnect.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    header('Location: index.html');
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM teacher WHERE username = ?");
$stmt->execute([$_SESSION['username']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$teacher) {
    header('Location: index.html');
    exit;
}

$teacherId = $teacher['id'];
$thesisID = $_GET['thesisID'] ?? null;
if (!$thesisID) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Thesis ID is required']);
    } else {
        echo "Thesis ID is required";
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    if (isset($_POST['remove'], $_POST['thesisID'])) {
        $thesisRemoveID = trim($_POST['thesisID']);
        $stmtCheck = $pdo->prepare("SELECT finalized FROM thesis WHERE thesisID = ?");
        $stmtCheck->execute([$thesisRemoveID]);
        $result = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        if ($result && !$result['finalized']) {
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
                echo json_encode(['success' => true, 'message' => 'Thesis unassigned successfully.']);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Failed to unassign thesis: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Finalized or nonexistent thesis.']);
        }
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');

    // Committee members
    $stmt = $pdo->prepare("SELECT supervisor, member1, member2 FROM committee WHERE thesisID = ?");
    $stmt->execute([$thesisID]);
    $committee = $stmt->fetch(PDO::FETCH_ASSOC);

    // Invitations
    $stmt = $pdo->prepare("SELECT * FROM committeeinvitations WHERE senderID = ?");
    $stmt->execute([$teacherId]);
    $invitations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get teacher names for invitations
    foreach ($invitations as &$invitation) {
        $stmt = $pdo->prepare("SELECT t_fname, t_lname FROM teacher WHERE id = ?");
        $stmt->execute([$invitation['receiverID']]);
        $name = $stmt->fetch(PDO::FETCH_ASSOC);
        $invitation['receiverName'] = $name['t_fname'] . ' ' . $name['t_lname'];
    }

    echo json_encode([
        'success' => true,
        'committee' => $committee ?: [],
        'invitations' => $invitations ?: [],
        'teacherId' => $teacherId,
    ]);
    exit;
}

header('Location: index.html');
exit;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Assigned Thesis</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <div class="logo-title-row">
        <button class="back-btn" id="backBtn">
            <img src="logo2.jpg" alt="Logo" class="logo" />
        </button>
        <h1 class="site-title">Assigned Thesis</h1>
    </div>
</header>
<main class="dashboard-main">
    <h2>Thesis Details</h2>
    <table class="table" id="committeeTable">
        <thead>
            <tr>
                <th>Committee Member 1</th>
                <th>Committee Member 2</th>
            </tr>
        </thead>
        <tbody>
            <!-- Filled by JS -->
        </tbody>
    </table>
    <h3>Invitations</h3>
    <ul class="invitation-list" id="invitationList">
        <!-- Filled by JS -->
    </ul>
    <form id="unassignForm" style="display:none;">
        <input type="hidden" name="thesisID" id="thesisID" value="<?= htmlspecialchars($thesisID) ?>" />
        <button class="submit-btn" type="submit" id="unassignBtn">Unassign Thesis</button>
    </form>
</main>
<footer class="footer">
    <p>© 2025 Thesis Management System</p>
</footer>
<script src="assignedthesis.js"></script>
</body>
</html>
