<?php 
session_start();
require_once 'dbconnect.php';

// Auth check
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    } else {
        header('Location: index.php');
    }
    exit;
}

// Get teacher ID
$stmt = $pdo->prepare("SELECT id FROM teacher WHERE username = ?");
$stmt->execute([$_SESSION['username']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$teacher) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    } else {
        echo "Unauthorized access.";
    }
    exit;
}

// Get thesis ID
$thesisID = $_GET['thesisID'] ?? $_POST['thesisID'] ?? null;
if (!$thesisID) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Thesis ID missing']);
    } else {
        echo "Thesis ID missing.";
    }
    exit;
}


// POST AJAX for adding notes, exam, unassign
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    header('Content-Type: application/json');

    if (isset($_POST['description'])) {
        $desc = trim($_POST['description']);
        if ($desc === '') {
            echo json_encode(['success' => false, 'message' => 'Description required']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO teachernotes (thesisID, teacherID, description) VALUES (?, ?, ?)");
        $res = $stmt->execute([$thesisID, $teacher['id'], $desc]);
        echo json_encode(['success' => $res, 'message' => $res ? 'Note added.' : 'Failed to add note.']);
        exit;
    }

    if (isset($_POST['start_examination'])) {
        $pdo->beginTransaction();
        try {
            $stmt1 = $pdo->prepare("UPDATE thesis SET th_status = 'EXAM' WHERE thesisID = ?");
            $stmt1->execute([$thesisID]);

            $stmt2 = $pdo->prepare("INSERT INTO thesisStatusChanges (thesisID, changeDate, changeTo) VALUES (?, NOW(), 'EXAM')");
            $stmt2->execute([$thesisID]);

            $stmt3 = $pdo->prepare("INSERT INTO thesis_exam_meta (thesisID) VALUES (?)");
            $stmt3->execute([$thesisID]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Examination started successfully']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to start examination: ' . $e->getMessage()]);
        }
        exit;
    }

    if (isset($_POST['remove'])) {
        $pdo->beginTransaction();
        try {
            $stmt1 = $pdo->prepare("UPDATE thesis SET th_status = 'NOT_ASSIGNED' WHERE thesisID = ?");
            $stmt1->execute([$thesisID]);

            $stmt2 = $pdo->prepare("UPDATE student SET thesisID = NULL WHERE thesisID = ?");
            $stmt2->execute([$thesisID]);

            $stmt3 = $pdo->prepare("UPDATE thesisStatusChanges SET changeDate = NOW(), changeTo = 'NOT_ASSIGNED' WHERE thesisID = ?");
            $stmt3->execute([$thesisID]);

            $stmt4 = $pdo->prepare("UPDATE committee SET member1 = NULL, member2 = NULL, m1_confirmation = NULL, m2_confirmation = NULL WHERE thesisID = ?");
            $stmt4->execute([$thesisID]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Thesis unassigned successfully']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to unassign thesis: ' . $e->getMessage()]);
        }
        exit;
    }
}

// GET AJAX to fetch data for display
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    header('Content-Type: application/json');

    $stmt = $pdo->prepare("SELECT * FROM thesis WHERE thesisID = ?");
    $stmt->execute([$thesisID]);
    $thesis = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT supervisor, member1, member2 FROM committee WHERE thesisID = ?");
    $stmt->execute([$thesisID]);
    $committee = $stmt->fetch(PDO::FETCH_ASSOC);

    function getTeacherName($pdo, $id) {
        if (!$id) return null;
        $stmt = $pdo->prepare("SELECT CONCAT(t_fname, ' ', t_lname) FROM teacher WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn();
    }

    $committee['supervisorName'] = getTeacherName($pdo, $committee['supervisor'] ?? null);
    $committee['member1Name'] = getTeacherName($pdo, $committee['member1'] ?? null);
    $committee['member2Name'] = getTeacherName($pdo, $committee['member2'] ?? null);

    $stmt = $pdo->prepare("SELECT * FROM teachernotes WHERE thesisID = ? AND teacherID = ?");
    $stmt->execute([$thesisID, $teacher['id']]);
    $teachernotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT changeDate FROM thesisStatusChanges WHERE thesisID = ? ORDER BY changeDate DESC LIMIT 1");
    $stmt->execute([$thesisID]);
    $changeDateRaw = $stmt->fetchColumn();

    $timezone = new DateTimeZone('Europe/Athens');
    try {
        $activeDate = new DateTime($changeDateRaw, $timezone);
    } catch (Exception $ex) {
        $activeDate = new DateTime('now', $timezone);
    }
    $activeDate->modify('+2 years');
    $currentDate = new DateTime('now', $timezone);

    $canUnassign = false;
    if (isset($committee['supervisor']) && $committee['supervisor'] == $teacher['id']) {
        $canUnassign = ($currentDate > $activeDate);
    }

    echo json_encode([
        'success' => true,
        'thesis' => $thesis,
        'committee' => $committee,
        'teachernotes' => $teachernotes,
        'canUnassign' => $canUnassign,
        'hasGsNumb' => !empty($thesis['gs_numb']),
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Active Thesis</title>
<link rel="stylesheet" href="style.css" />
</head>
<body>

<header>
    <div class="logo-title-row">
        <button class="back-btn" id="backBtn"><img src="logo.jpg" alt="Logo" /></button>
        <h1 class="site-title">Active Thesis</h1>
    </div>
</header>

<main class="dashboard-main">
    <h2>My Notes</h2>
    <button class="submit-btn" id="addNotesBtn">Add Note</button>

    <div id="popupWindow1" class="popup-window" style="display:none;">
        <div class="popup-content">
            <h3>Add Note</h3>
            <form id="addNoteForm" data-thesis-id="">
                <label for="description">Description:</label>
                <textarea id="description" name="description" required></textarea>
                <button type="submit">Save</button>
            </form>
            <button id="closePopupBtn1" class="close-popup-btn" aria-label="Close">&times;</button>
        </div>
    </div>

    <button class="submit-btn" id="viewNotesBtn">View Notes</button>

    <div id="popupWindow2" class="popup-window" style="display:none;">
        <div class="popup-content" id="notesListContent"></div>
        <button id="closePopupBtn2" class="close-popup-btn" aria-label="Close">&times;</button>
    </div>

    <div id="unassignSection" style="display:none;">
        <h2>Unassign Thesis</h2>
        <p id="unassignMessage"></p>
        <form id="unassignForm" style="display:none;">
            <input type="hidden" id="unassignThesisID"/>
            <button class="submit-btn">Unassign Thesis</button>
        </form>
    </div>

    <div id="startExamSection" style="display:none;">
        <h2>Start Examination</h2>
        <form id="startExamForm" style="display:none;">
            <input type="hidden" id="startExamThesisID"/>
            <button class="submit-btn">Start Examination</button>
        </form>
        <p id="startExamMessage"></p>
    </div>

    <p id="unauthorizedMessage" style="color:red; display:none;">You are not authorized for further actions.</p>
</main>

<footer><p>© 2025</p></footer>

<script src="activethesis.js"></script>

</body>
</html>
