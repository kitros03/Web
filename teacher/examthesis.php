<?php
session_start();
header("Content-Type: text/html; charset=utf-8");

require_once("../dbconnect.php");

// Save thesisID from GET to session if present
if (isset($_GET['thesisID']) && !empty($_GET['thesisID'])) {
    $_SESSION['thesisID'] = $_GET['thesisID'];
}

// Use thesisID from GET or session
$thesisId = $_GET['thesisID'] ?? ($_SESSION['thesisID'] ?? null);

if (!$thesisId) {
    echo "Thesis ID is required";
    exit;
}

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, t_fname, t_lname FROM teacher WHERE username=?");
$stmt->execute([$_SESSION['username']]);
$teacher = $stmt->fetch();

$teacherId = $teacher['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'activateGrading' && isset($_POST['thesisID'])) {
        $stmt = $pdo->prepare("UPDATE thesis SET grading = 1 WHERE thesisID = ?");
        $stmt->execute([$_POST['thesisID']]);
        echo json_encode(['success' => true, 'message' => 'Grading activated!']);
        exit;
    }

    if ($action === 'submitGrades') {
        try {
            $stmt_check = $pdo->prepare("SELECT id FROM grades WHERE thesisID = ? AND teacherID = ?");
            $stmt_check->execute([$_POST['thesisID'], $teacherId]);
            if ($stmt_check->fetch()) {
                echo json_encode(['success' => false, 'message' => 'You have already submitted grades.']);
                exit;
            }

            $quality = floatval($_POST['quality_grade'] ?? 0);
            $time = floatval($_POST['time'] ?? 0);
            $rest = floatval($_POST['rest'] ?? 0);
            $presentation = floatval($_POST['presentation'] ?? 0);
            $calc = $quality * 0.6 + $time * 0.15 + $rest * 0.15 + $presentation * 0.1;

            // Insert using correct column names
            $stmt = $pdo->prepare("INSERT INTO grades (thesisID, teacherID, quality_grade, time_grade, rest_quality_grade, presentation_grade, calc_grade) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt->execute([$_POST['thesisID'], $teacherId, $quality, $time, $rest, $presentation, $calc])) {
                throw new Exception("Database insert failed");
            }
            echo json_encode(['success' => true, 'message' => 'Grades submitted!']);
            exit;
        } catch (Exception $ex) {
            error_log("SubmitGrades error: " . $ex->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to submit grades: ' . $ex->getMessage()]);
            exit;
        }
    }

    if ($action === 'announce' && isset($_POST['thesisID'])) {
        $stmt = $pdo->prepare("UPDATE thesis SET announce = 1 WHERE thesisID = ?");
        $stmt->execute([$_POST['thesisID']]);
        echo json_encode(['success' => true, 'message' => 'Announcement sent!']);
        exit;
    }
}

$loadData = function () use ($pdo, $thesisId, $teacherId, $teacher) {
    $stmt = $pdo->prepare("SELECT * FROM thesis WHERE thesisID = ?");
    $stmt->execute([$thesisId]);
    $thesis = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT s_fname, s_lname FROM student WHERE thesisID = ?");
    $stmt->execute([$thesisId]);
    $student = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT * FROM committee WHERE thesisID = ?");
    $stmt->execute([$thesisId]);
    $committee = $stmt->fetch();

    $getName = function ($id) use ($pdo) {
        if (!$id) return null;
        $stmt = $pdo->prepare("SELECT CONCAT(t_fname, ' ', t_lname) FROM teacher WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn();
    };

    $committeeNames = [
        'supervisor' => $getName($committee['supervisor'] ?? null),
        'member1' => $getName($committee['member1'] ?? null),
        'member2' => $getName($committee['member2'] ?? null),
    ];

    $grades = [];
    if (!empty($thesis) && intval($thesis['grading']) === 1) {
        $stmt = $pdo->prepare("SELECT g.*, t.t_fname, t.t_lname FROM grades g JOIN teacher t ON g.teacherID = t.id WHERE g.thesisID = ?");
        $stmt->execute([$thesisId]);
        $grades = $stmt->fetchAll();
    }

    $stmt = $pdo->prepare("SELECT * FROM thesis_exam_meta WHERE thesisID = ?");
    $stmt->execute([$thesisId]);
    $meta = $stmt->fetch();

    if ($committee) {
        $thesis['supervisor'] = intval($committee['supervisor']);
    }

    return [
        'thesis' => $thesis,
        'student' => $student,
        'committee' => $committee,
        'committeeNames' => $committeeNames,
        'grades' => $grades,
        'meta' => $meta,
        'teacher' => $teacher,
    ];
};

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($loadData());
    exit;
}
?>

<!DOCTYPE html>
<html lang="el">

<head>
    <meta charset="UTF-8" />
    <title>Examined Thesis</title>
    <link rel="stylesheet" href="../style.css" />
</head>

<body>
    <header>
        <div class="logo-container">
            <img src="../logo.jpg" alt="Logo" class="logo" />
            <h1 class="site-title">Examined Thesis</h1>
        </div>
    </header>
    <main class="dashboard-main">
        <div class="centered-body">
            <a id="draftLink" href="#" target="_blank" class="submit-btn">View Draft</a>
        </div>
        <div class="centered-body">
            <button id="btnPresentation" class="submit-btn">Presentation</button>
            <div id="popupPresentation" class="popup-window" style="display:none;">
                <div class="popup-content">
                    <h3>Presentation</h3>
                    <div id="presentationInfo"></div>
                    <button id="closePresentation" class="close-popup-btn" aria-label="Close">×</button>
                </div>
            </div>
        </div>
        <div class="centered-body">
            <button id="btnGrading" class="submit-btn">Grading</button>
            <div id="popupGrading" class="popup-window" style="display:none;">
                <div class="popup-content">
                    <h3>Grading</h3>
                    <div id="gradingContent"></div>
                    <button id="closeGrading" class="close-popup-btn" aria-label="Close">×</button>
                </div>
            </div>
        </div>
    </main>
    <footer>
        <p class="footer">© 2023</p>
    </footer>
    <script src="examthesis.js"></script>
</body>

</html>
