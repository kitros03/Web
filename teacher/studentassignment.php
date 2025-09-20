<?php
session_start();
require '../dbconnect.php';

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

        // Verify thesis exists
        $stmt = $pdo->prepare("SELECT * FROM thesis WHERE thesisID = ?");
        $stmt->execute([$thesisId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Thesis not found.']);
            exit;
        }

        // Verify student exists
        $stmt = $pdo->prepare("SELECT * FROM student WHERE username = ?");
        $stmt->execute([$studentUsername]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Student not found.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE thesis SET assigned = 1, th_status='ASSIGNED' WHERE thesisID = ?");
            $stmt->execute([$thesisId]);

            $stmt = $pdo->prepare("UPDATE student SET thesisID = ? WHERE username = ?");
            $stmt->execute([$thesisId, $studentUsername]);

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

                $stmt = $pdo->prepare("UPDATE thesis SET assigned = 0, th_status='NOT_ASSIGNED' WHERE thesisID = ?");
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
            echo json_encode(['success' => false, 'message' => 'Finalized or nonexistent thesis.']);
        }
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Return minimal page on direct load (no JSON)
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Student Assignment</title>
  <link rel="stylesheet" href="../style.css" />
</head>
<body>
  <header>
    <div class="logo-title-row">
        <button class="back-btn" id="backBtn">
            <img src="../logo2.jpg" alt="Logo" class="logo" />
        </button>
        <h1 class="site-title">Student Assignment</h1>
    </div>
  </header>
  <div class="dashboard-container">
    <main class="dashboard-main">
      <h2>Assign Thesis to Student</h2>
      <form id="assignmentForm" class="form-group">
        <select name="thesis" class="select"></select>
        <select name="student" class="select"></select>
        <button type="submit" class="submit-btn">Assign</button>
      </form>
      <div id="result"></div>
      <h2>Assigned Theses</h2>
      <div id="assignedTheses"></div>
    </main>
  </div>
  <footer>
  <p>&copy; 2025</p>
  </footer>
  <script src="studentassignment.js"></script>
</body>
</html>
<?php
        exit;
    }
    // Ajax GET request, return data JSON
    header('Content-Type: application/json');
    $stmt = $pdo->prepare("SELECT * FROM thesis WHERE supervisor = ? AND assigned = 0 ORDER BY thesisID DESC");
    $stmt->execute([$id]);
    $theses = $stmt->fetchAll();

    $stmt = $pdo->query("SELECT username FROM student WHERE thesisID IS NULL");
    $students = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT t.*, s.username AS studentUsername FROM thesis t JOIN student s ON s.thesisID = t.thesisID WHERE t.supervisor = ? AND t.assigned = 1 ORDER BY t.thesisID DESC");
    $stmt->execute([$id]);
    $assignedTheses = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'theses' => $theses,
        'students' => $students,
        'assignedTheses' => $assignedTheses
    ]);
    exit;
}
