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

// get thesis ID from GET or POST (POST for AJAX form submissions)
$thesisID = $_GET['thesisID'] ?? $_POST['thesisID'] ?? null;
if (!$thesisID) {
    http_response_code(400);
    echo "Thesis ID is required";
    exit;
}

// Fetch thesis details
$stmt = $pdo->prepare("SELECT * FROM thesis WHERE thesisID = ?");
$stmt->execute([$thesisID]);
$thesis = $stmt->fetch(PDO::FETCH_ASSOC);

// get the committee members
$stmt = $pdo->prepare("SELECT supervisor, member1, member2 FROM committee WHERE thesisID = ?");
$stmt->execute([$thesisID]);
$committeeID = $stmt->fetch(PDO::FETCH_ASSOC);

// get the names of committee members
$stmt = $pdo->prepare("SELECT t_fname, t_lname FROM teacher WHERE id = ?");
$stmt->execute([$committeeID['supervisor']]);
$supervisor = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare("SELECT t_fname, t_lname FROM teacher WHERE id = ?");
$stmt->execute([$committeeID['member1']]);
$member1 = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare("SELECT t_fname, t_lname FROM teacher WHERE id = ?");
$stmt->execute([$committeeID['member2']]);
$member2 = $stmt->fetch(PDO::FETCH_ASSOC);

// get all notes for this teacher and thesis
$stmt = $pdo->prepare("SELECT * FROM teachernotes WHERE thesisID = ? AND teacherID = ?");
$stmt->execute([$thesisID, $teacher['id']]);
$teachernotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// need to get the date of the active thesis status, thesis is currenty active so last changeTo should be 'ACTIVE'
$stmt = $pdo->prepare("SELECT changeDate FROM thesisStatusChanges WHERE thesisID = ? ORDER BY changeDate DESC LIMIT 1");
$stmt->execute([$thesisID]);
$changeDate = $stmt->fetch(PDO::FETCH_ASSOC);

// now i need to add 2 years to the changeDate and compare it with the current date
$activeDate = new DateTime($changeDate['changeDate']);
$activeDate->modify('+2 years');
$currentDate = new DateTime();

// handle form submissions for adding notes and starting examination
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['description'])) {
        $description = trim($_POST['description']);
        if ($description === '') {
            echo json_encode(['success' => false, 'message' => 'Description is required.']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO teachernotes (thesisID, teacherID, description) VALUES (?, ?, ?)");
        if ($stmt->execute([$thesisID, $teacher['id'], $description])) {
            echo json_encode(['success' => true, 'message' => 'Note added successfully.']);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding note.']);
            exit;
        }
    } elseif (isset($_POST['start_examination'])) {
        $stmt = $pdo->prepare("UPDATE thesis SET th_status = 'EXAM' WHERE thesisID = ?");
        if (!$stmt->execute([$thesisID])) {
            echo json_encode(['success' => false, 'message' => 'Error starting examination.']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO thesisStatusChanges (thesisID, changeDate, changeTo) VALUES (?, NOW(), 'EXAM')");
        if (!$stmt->execute([$thesisID])) {
            echo json_encode(['success' => false, 'message' => 'Error logging examination start.']);
            exit;
        }
        echo json_encode(['success' => true, 'message' => 'Examination started successfully.']);
        exit;
    }
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
        <h1 class="site-title">Active Thesis</h1>
    </div>
</header>
<main class="dashboard-main">
    <h2>My Notes</h2>
    <button class="submit-btn" id="addNotesBtn">Add Note</button>
    <div id="popupWindow1" class="popup-window" style="display:none;">
        <div class="popup-content">
            <h3>Add Note</h3>
            <form id="addNoteForm">
                <label for="description">Description:</label>
                <textarea id="description" name="description" required></textarea>
                <button type="submit">Save Note</button>
            </form>
            <button id="closePopupBtn1" class="close-popup-btn" aria-label="Close">&times;</button>
        </div>
    </div>   
    <button class="submit-btn" id="viewNotesBtn">View Notes</button>
    <div id="popupWindow2" class="popup-window" style="display:none;">
        <div class="popup-content">
            <?php if (isset($teachernotes)): ?>
                <h3>Notes</h3>
                <ul class="notes-list">
                <?php foreach ($teachernotes as $note): ?>
                    <li>
                        <strong><?= htmlspecialchars($note['description']) ?></strong>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No notes found.</p>
            <?php endif; ?>
            <button id="closePopupBtn2" class="close-popup-btn" aria-label="Close">&times;</button>
        </div>
    </div>    
    <?php if($committeeID['supervisor'] === $teacher['id']):?>
        <h2>Unassign Thesis</h2>
        <?php if($currentDate > $activeDate): ?>
            <p> The thesis has been active for more than 2 years, you can now unassign it.</p>
            <form id="unassignForm" method="post">
                <input type="hidden" name="thesisID" value="<?= htmlspecialchars($thesisID) ?>">
                <button type="submit" class="unassign-btn">Unassign Thesis</button>
            </form>
        <?php else: ?>
            <p> The thesis has been active for less than 2 years, you cannot unassign it yet.</p>
        <?php endif; ?>
        <h2> Start examination</h2>
        <?php if($thesis['gs_numb']): ?>
            <form id="startExamForm" method="post" data-thesis-id="<?= htmlspecialchars($thesisID) ?>">
                <input type="hidden" name="thesisID" value="<?= htmlspecialchars($thesisID) ?>">
                <button type="submit" class="submit-btn">Start Examination</button>
            </form>
        <?php else: ?>
            <p> You cannot start the examination until the secretary has provided the number of the GS.</p>
        <?php endif; ?>
    <?php else: ?>
        <p>You are not authorized for further actions.</p>
    <?php endif; ?>
    <script src="activethesis.js"></script>
    </main> 
<footer>
        <p class="footer">© 2025 Thesis Management System</p>
</footer>
</body>
</html>