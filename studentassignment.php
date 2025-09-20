<?php
session_start();
require 'dbconnect.php';

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

$id = $teacher['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (isset($_POST['thesis'], $_POST['student'])) {
        $thesisId = trim($_POST['thesis']);
        $studentUsername = trim($_POST['student']);

        if ($thesisId === '' || $studentUsername === '') {
            echo json_encode(['success' => false, 'message' => 'Thesis and Student are required.']);
            exit;
        }

        // Check if thesis exists
        $stmt = $pdo->prepare("SELECT * FROM thesis WHERE thesisID = ?");
        $stmt->execute([$thesisId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Thesis not found.']);
            exit;
        }

        // Check if student exists
        $stmt = $pdo->prepare("SELECT * FROM student WHERE username = ?");
        $stmt->execute([$studentUsername]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Student not found.']);
            exit;
        }

        try {
            $pdo->beginTransaction();
            // Update thesis
            $stmt = $pdo->prepare("UPDATE thesis SET assigned = 1, th_status = 'ASSIGNED' WHERE thesisID = ?");
            $stmt->execute([$thesisId]);

            // Update student
            $stmt = $pdo->prepare("UPDATE student SET thesisID = ? WHERE username = ?");
            $stmt->execute([$thesisId, $studentUsername]);

            // Log status change
            $stmt = $pdo->prepare("INSERT INTO thesisStatusChanges (thesisID, changeDate, changeTo) VALUES (?, NOW(), 'ASSIGNED')");
            $stmt->execute([$thesisId]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Thesis assigned successfully.']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Assignment failed: ' . $e->getMessage()]);
        }
        exit;
    }

    if (isset($_POST['remove'], $_POST['thesisID'])) {
        $thesisId = $_POST['thesisID'];
        $stmt = $pdo->prepare("SELECT th_status FROM thesis WHERE thesisID = ?");
        $stmt->execute([$thesisId]);
        $result = $stmt->fetch();

        if ($result && $result['th_status'] === 'ASSIGNED') {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE thesis SET assigned = 0, th_status = 'NOT_ASSIGNED' WHERE thesisID = ?");
                $stmt->execute([$thesisId]);

                $stmt = $pdo->prepare("UPDATE student SET thesisID = NULL WHERE thesisID = ?");
                $stmt->execute([$thesisId]);

                $stmt = $pdo->prepare("INSERT INTO thesisStatusChanges (thesisID, changeDate, changeTo) VALUES (?, NOW(), 'NOT_ASSIGNED')");
                $stmt->execute([$thesisId]);

                $stmt = $pdo->prepare("UPDATE committee SET member1 = NULL, member2 = NULL, m1_confirmation = NULL, m2_confirmation = NULL WHERE thesisID = ?");
                $stmt->execute([$thesisId]);

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Thesis unassigned successfully.']);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Unassignment failed: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Cannot unassign finalized or nonexistent thesis.']);
        }
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare("SELECT * FROM thesis WHERE supervisor = ? AND assigned = 0 ORDER BY thesisID DESC");
    $stmt->execute([$id]);
    $theses = $stmt->fetchAll();

    $stmt = $pdo->query("SELECT username FROM student WHERE thesisID IS NULL");
    $students = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT t.*, s.username AS studentUsername
        FROM thesis t
        JOIN student s ON s.thesisID = t.thesisID
        WHERE t.supervisor = ? AND t.assigned = 1
        ORDER BY t.thesisID DESC
    ");
    $stmt->execute([$id]);
    $assignedTheses = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'theses' => $theses,
        'students' => $students,
        'assignedTheses' => $assignedTheses,
    ]);
    exit;
}

// For any other requests, output minimal page with script loading only
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Student Assignment</title>
    <link rel="stylesheet" href="style.css" />
</head>
<body>
<header>
    <div class="logo-container">
        <img src="logo.jpg" alt="Logo" class="logo" />
        <h1 class="site-title">Student Assignment</h1>
    </div>
</header>
<div class="dashboard-container">
<main class="dashboard-main">
<h2>Assign Thesis to Student</h2>
<form id="assignmentForm" class="form-group">
    <select name="thesis" class="select">
        <option value="">-- Select Thesis --</option>
    </select>
    <select name="student" class="select">
        <option value="">-- Select Student --</option>
    </select>
    <button type="submit" class="submit-btn">Assign</button>
</form>
<div id="result"></div>
<h2>Assigned Theses</h2>
<div id="assignedTheses"></div>
</main>
</div>
<footer>
<p>© 2025</p>
</footer>
<script src="studentassignment.js"></script>
</body>
</html>
