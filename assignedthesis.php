<?php 
session_start();
require_once 'dbconnect.php';
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    header('Location: index.html');
    exit;
}
// Get current teacher id
$stmt = $pdo->prepare("SELECT id FROM teacher WHERE username = ?");
$stmt->execute([$_SESSION['username']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

// get thesisID from URL
$thesisID = isset($_GET['thesisID']) ? $_GET['thesisID'] : null;
if (!$thesisID) {
    http_response_code(400);
    echo "Thesis ID is required";
    exit;
}
// get the student from the thesisID
$stmt = $pdo->prepare("SELECT * FROM student WHERE thesisID = ?");
$stmt->execute([$thesisID]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);


// get committee members/invitations
$stmt = $pdo->prepare("SELECT supervisor, member1, member2 FROM committee WHERE thesisID = ?");
$stmt->execute([$thesisID]);
$committee = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare("SELECT * FROM committeeinvitations WHERE senderID = ?");
$stmt->execute([$student['id']]);
$invitations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// handle thesis unassignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 if (isset($_POST['remove'], $_POST['thesisID'])) {
        $thesisID = trim($_POST['thesisID']);
        $stmt = $pdo->prepare("SELECT finalized FROM thesis WHERE thesisID = ?");
        $stmt->execute([$thesisID]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && !$result['finalized']) {
            $stmt1 = $pdo->prepare("UPDATE thesis SET assigned = 0, th_status='NOT_ASSIGNED' WHERE thesisID = ?");
            $stmt2 = $pdo->prepare("UPDATE student SET thesisID = NULL WHERE thesisID = ?");
            $stmt3 = $pdo->prepare("INSERT INTO thesisStatusChanges (thesisID, changeDate, changeTo) VALUES (?, NOW(), 'NOT_ASSIGNED')");
            if ($stmt1->execute([$thesisID]) && $stmt2->execute([$thesisID]) && $stmt3->execute([$thesisID])) {
                echo "Success";
                //also need to remove members from committe
                $stmt = $pdo->prepare("UPDATE committee SET member1 = NULL, member2 = NULL, m1_confirmation = NULL, m2_confirmation=NULL WHERE thesisID = ?");
                $stmt->execute([$thesisID]);
            } else {
                http_response_code(500);
                echo "Failed to unassign thesis.";
            }
        } else {
            http_response_code(400);
            echo "Finalized or nonexistent thesis.";
        }
        exit;
    }
}
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
    <table class="table">
        <thead>
            <tr>
                <th>Committee Member 1</th>
                <th>Committee Member 2</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <?php if ($committee): ?>
                    <td><?= htmlspecialchars($committee['member1']) ?></td>
                    <td><?= htmlspecialchars($committee['member2']) ?></td>
                <?php else: ?>
                    <td colspan="2">No committee members assigned</td>
                <?php endif; ?>
            </tr>
        </tbody>
    </table>
    <h3>Invitations</h3>
    <?php if ($invitations): ?>
        <ul class="invitation-list">
            <?php foreach ($invitations as $invitation): 
                $stmt = $pdo->prepare("SELECT t_fname, t_lname FROM teacher where id = ?");
                $stmt -> execute([$invitation['receiverID']]);
                $name = $stmt->fetch(PDO::FETCH_ASSOC);
            ?>
               <li> <?= htmlspecialchars($name['t_fname'] . ' ' . $name['t_lname']) ?> - <?php if($invitation['response']): ?>
                    <?= htmlspecialchars($invitation['response'] . ' ' . $invitation['responseDate']) ?> 
                    <?php else : ?>
                    <p>Pending response</p>
                <?php endif; ?>
                </li>  
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No invitations sent.</p>
    <?php endif; ?>
    <?php if($teacher['id'] === $committee['supervisor']): ?>
        <form id="unassignForm" method="POST">
            <input type="hidden" name="thesisID" value="<?= htmlspecialchars($thesisID) ?>">
            <button class="submit-btn" type="submit" id="unassignBtn">Unassign Thesis</button>
        </form>
    <?php endif; ?>
</main>
<footer class="footer">
    <p>© 2025 Thesis Management System</p>
    <script src="assignedthesis.js"></script>
</body>