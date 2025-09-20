<?php 
session_start();
require_once 'dbconnect.php';

// Redirect unauthorized users
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    } else {
        header('Location: index.html');
        exit;
    }
}

// Get thesis ID
$thesisID = $_GET['thesisID'] ?? $_POST['thesisID'] ?? null;
if (!$thesisID) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Thesis ID is required']);
        exit;
    } else {
        echo 'Thesis ID is required.';
        exit;
    }
}

// Get teacher ID from session
$stmt = $pdo->prepare("SELECT id FROM teacher WHERE username = ?");
$stmt->execute([$_SESSION['username']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$teacher) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    } else {
        echo 'Unauthorized';
        exit;
    }
}

// AJAX POST handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['description'])) {
        $desc = trim($_POST['description']);
        if ($desc === '') {
            echo json_encode(['success' => false, 'message' => 'Description is required.']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO teachernotes (thesisID, teacherID, description) VALUES (?, ?, ?)");
        if ($stmt->execute([$thesisID, $teacher['id'], $desc])) {
            echo json_encode(['success' => true, 'message' => 'Note added successfully.']);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding note.']);
            exit;
        }
    } elseif (isset($_POST['start_examination'])) {
        $stmt1 = $pdo->prepare("UPDATE thesis SET th_status = 'EXAM' WHERE thesisID = ?");
        $stmt2 = $pdo->prepare("INSERT INTO thesisStatusChanges (thesisID, changeDate, changeTo) VALUES (?, NOW(), 'EXAM')");
        $stmt3 = $pdo->prepare("INSERT INTO thesis_exam_meta (thesisID) VALUES (?)");

        if ($stmt1->execute([$thesisID]) && $stmt2->execute([$thesisID]) && $stmt3->execute([$thesisID])) {
            echo json_encode(['success' => true, 'message' => 'Examination started successfully.']);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Error starting examination.']);
            exit;
        }
    } elseif (isset($_POST['remove'])) {
        // Placeholder for unassign implementation
        echo json_encode(['success' => false, 'message' => 'Unassign not implemented yet.']);
        exit;
    }
}

// Fetch data for normal GET load
$stmt = $pdo->prepare("SELECT * FROM thesis WHERE thesisID = ?");
$stmt->execute([$thesisID]);
$thesis = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT supervisor, member1, member2 FROM committee WHERE thesisID = ?");
$stmt->execute([$thesisID]);
$committeeID = $stmt->fetch(PDO::FETCH_ASSOC);

function getTeacherName($pdo, $id) {
    if (!$id) return null;
    $stmt = $pdo->prepare("SELECT CONCAT(t_fname,' ',t_lname) FROM teacher WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetchColumn();
}

$supervisorName = getTeacherName($pdo, $committeeID['supervisor'] ?? null);
$member1Name = getTeacherName($pdo, $committeeID['member1'] ?? null);
$member2Name = getTeacherName($pdo, $committeeID['member2'] ?? null);

$stmt = $pdo->prepare("SELECT * FROM teachernotes WHERE thesisID = ? AND teacherID = ?");
$stmt->execute([$thesisID, $teacher['id']]);
$teachernotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT changeDate FROM thesisStatusChanges WHERE thesisID = ? ORDER BY changeDate DESC LIMIT 1");
$stmt->execute([$thesisID]);
$changeDate = $stmt->fetch(PDO::FETCH_ASSOC);

$activeDate = new DateTime($changeDate['changeDate'] ?? 'now');
$activeDate->modify('+2 years');
$currentDate = new DateTime();

?>

<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8" />
<link rel="stylesheet" href="style.css" />
<title>Active Thesis</title>
</head>
<body>
<header>
    <div class="logo-title-row">
        <button class="back-btn" id="backBtn"><img src="logo2.jpg" class="logo" alt="Logo" /></button>
        <h1 class="site-title">Active Thesis</h1>
    </div>
</header>
<main class="dashboard-main">
    <h2>My Notes</h2>
    <button class="submit-btn" id="addNotesBtn">Add Note</button>
    <div id="popupWindow1" class="popup-window" style="display:none;">
        <div class="popup-content">
            <h3>Add Note</h3>
            <form id="addNoteForm" data-thesis-id="<?= htmlspecialchars($thesisID) ?>">
                <input type="hidden" name="thesisID" value="<?= htmlspecialchars($thesisID) ?>">
                <label for="description">Description:</label>
                <textarea id="description" name="description" required></textarea>
                <button type="submit">Save Note</button>
            </form>
            <button id="closePopupBtn1" class="close-popup-btn" aria-label="Close">&times;</button>
        </div>
    </div>   
    <button class="submit-btn" id="viewNotesBtn">View Notes</button>
    <div id="popupWindow2" class="popup-window" style="display:none;">
        <div class="popup-content" id="notesListContent">
            <?php if ($teachernotes): ?>
                <h3>Notes</h3>
                <ul class="notes-list">
                    <?php foreach ($teachernotes as $note): ?>
                        <li><strong><?= htmlspecialchars($note['description']) ?></strong></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No notes found.</p>
            <?php endif; ?>
        </div>
        <button id="closePopupBtn2" class="close-popup-btn" aria-label="Close">&times;</button>
    </div>    
    <?php if ($committeeID['supervisor'] === $teacher['id']): ?>
        <h2>Unassign Thesis</h2>
        <?php if ($currentDate > $activeDate): ?>
            <p>The thesis has been active for more than 2 years, you can now unassign it.</p>
            <form id="unassignForm" method="post">
                <input type="hidden" name="thesisID" value="<?= htmlspecialchars($thesisID) ?>">
                <button type="submit" class="unassign-btn">Unassign Thesis</button>
            </form>
        <?php else: ?>
            <p>The thesis has been active for less than 2 years, you cannot unassign it yet.</p>
        <?php endif; ?>
        <h2>Start Examination</h2>
        <?php if ($thesis['gs_numb']): ?>
            <form id="startExamForm" method="post" data-thesis-id="<?= htmlspecialchars($thesisID) ?>">
                <input type="hidden" name="thesisID" value="<?= htmlspecialchars($thesisID) ?>">
                <button type="submit" class="submit-btn">Start Examination</button>
            </form>
        <?php else: ?>
            <p>You cannot start the examination until the secretary has provided the number of the GS.</p>
        <?php endif; ?>
    <?php else: ?>
        <p>You are not authorized for further actions.</p>
    <?php endif; ?>
    <script src="activethesis.js"></script>
</main> 
<footer>
    <p class="footer">&copy; 2025 Thesis Management System</p>
</footer>
</body>
</html>
