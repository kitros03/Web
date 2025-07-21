<?php
session_start();
require 'dbconnect.php';
if (!isset($_SESSION['username'])) {
    header('Location: index.html');
    exit;
}
header('Content-Type: text/html; charset=utf-8');
$stmt = $pdo->prepare("SELECT teacherID FROM teacher WHERE username = ?");
$stmt->execute([$_SESSION['username']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch invitations
$stmt = $pdo->prepare("SELECT invitationID, thesisID, senderID, invitationDate FROM committeeinvitations WHERE response IS NULL AND receiverID = ?");
$stmt->execute([$teacher['teacherID']]);
$invitations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all extra info up front
function getSenderName($pdo, $senderID) {
    $stmt = $pdo->prepare("SELECT s_fname, s_lname FROM student WHERE studentID = ?");
    $stmt->execute([$senderID]);
    $sender = $stmt->fetch(PDO::FETCH_ASSOC);
    return $sender ? $sender['s_fname'] . ' ' . $sender['s_lname'] : 'Unknown Student';
}
function getThesisTitle($pdo, $thesisID) {
    $stmt = $pdo->prepare("SELECT title FROM thesis WHERE thesisID = ?");
    $stmt->execute([$thesisID]);
    $thesis = $stmt->fetch(PDO::FETCH_ASSOC);
    return $thesis ? $thesis['title'] : 'Unknown Thesis';
}

// Handle accept/decline POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['response'], $_POST['invitationID'])) {
    $response = $_POST['response'];
    $invitationID = $_POST['invitationID'];
    if ($response === '1' || $response === '0') {
        $stmt = $pdo->prepare("UPDATE committeeinvitations SET response = ? WHERE invitationID = ?");
        $stmt2 = $pdo->prepare("UPDATE committeeinvitations SET responseDate = NOW() WHERE invitationID = ?");
        
     //need to check if there is space in committee
        $stmt3 = $pdo->prepare("SELECT member1, member2 FROM committee WHERE thesisID = (SELECT thesisID FROM committeeinvitations WHERE invitationID = ?)");
        $stmt3->execute([$invitationID]);
        $committee = $stmt3->fetch(PDO::FETCH_ASSOC);
        if($committee["member1"] && $committee["member2"]) {
            echo json_encode(['success' => false, 'message' => 'Committee is full.']);
            exit;
        }
        if ($stmt->execute([$response, $invitationID]) && $stmt2->execute([$invitationID])) {
            echo json_encode(['success' => true, 'message' => 'Response recorded successfully.']);
            // If accepted, we also need to update the committee table
            // Check if the committee is full
            if ($stmt3->rowCount() === 0) {
                echo json_encode(['success' => false, 'message' => 'No committee found for this invitation.']);
                exit;
            }
            // need to find currect user`s userID
            $stmt3 = $pdo->prepare("SELECT teacherID FROM teacher WHERE username = ?");
            $stmt3->execute([$_SESSION['username']]);
            $teacherID = $stmt3->fetchColumn();
            if (!$teacherID) {
                echo json_encode(['success' => false, 'message' => 'User not found.']);
                exit;
            }
            // need to add to committee if accepted
            if ($response === '1') {
                //first need to check if there is a member1
                $stmt3 = $pdo->prepare("SELECT member1 FROM committee WHERE thesisID = (SELECT thesisID FROM committeeinvitations WHERE invitationID = ?)");
                $stmt3->execute([$invitationID]);
                $member1 = $stmt3->fetchColumn();
                if ($member1) {
                    // if member1 exists, add as member2
                    $stmt3 = $pdo->prepare("UPDATE committee SET member2 = ?, m2_confirmation = 0 WHERE thesisID = (SELECT thesisID FROM committeeinvitations WHERE invitationID = ?)");
                    $stmt3->execute([$_SESSION['username'], $invitationID]);
                } else {
                    // if no member1, add as member1
                    $stmt3 = $pdo->prepare("UPDATE committee SET member1 = ?, m1_confirmation = 0 WHERE thesisID = (SELECT thesisID FROM committeeinvitations WHERE invitationID = ?)");
                    $stmt3->execute([$teacherID, $invitationID]);
                }  
                exit;            
        }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to record response.']);
            exit;
        }
        
    }
    exit; // Exit after processing the POST request
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
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
            <h2>Committee Invitations</h2>
            <p>Here you can accept or decline committee invitations.</p>
            <table class="table">
                <tr>
                    <th>Thesis Title</th>
                    <th>Sender Name</th>
                    <th>Invitation Date</th>
                    <th>Actions</th>
                </tr>
                <?php if (empty($invitations)): ?>
                    <tr><td colspan="4">No committee invitations found.</td></tr>
                <?php else: ?>
                    <?php foreach ($invitations as $invitation): ?>
                        <tr>
                            <td><?= htmlspecialchars(getThesisTitle($pdo, $invitation['thesisID'])) ?></td>
                            <td><?= htmlspecialchars(getSenderName($pdo, $invitation['senderID'])) ?></td>
                            <td><?= htmlspecialchars($invitation['invitationDate']) ?></td>
                            <td>
                                <form class="invitationForm" method="post">
                                    <input type="hidden" name="invitationID" value="<?= htmlspecialchars($invitation['invitationID']) ?>">
                                    <button class="submit-btn" type="submit" name="response" value="1">Accept</button>
                                    <button class="submit-btn" type="submit" name="response" value="0">Decline</button>
                                    <div class="result"></div> 
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
            <script src="committeeinvitations.js"></script>
        </main>
    </div>
    <footer class="footer">
        <p>&copy; 2025 Thesis Management System</p>
</body>
</html>
