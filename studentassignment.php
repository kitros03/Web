<?php
session_start();
require 'dbconnect.php';

$id = null;
$theses = [];
$students = [];
$assignedTheses = [];

if (isset($_SESSION['username'])) {
    $stmt = $pdo->prepare("SELECT id FROM teacher WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($teacher && isset($teacher['id'])) {
        $id = $teacher['id'];

        // Get unassigned theses for dropdown
        $stmt2 = $pdo->prepare("SELECT * FROM thesis WHERE supervisor = ? AND assigned = 0 ORDER BY thesisID DESC");
        $stmt2->execute([$id]);
        $theses = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get unassigned students
    $stmt = $pdo->query("SELECT username FROM student WHERE thesisID IS NULL");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get assigned theses
    $stmt = $pdo->prepare("
        SELECT t.*, s.username AS studentUsername
        FROM thesis t
        JOIN student s ON s.thesisID = t.thesisID
        WHERE t.supervisor = ? AND t.assigned = 1
        ORDER BY t.thesisID DESC
    ");
    $stmt->execute([$id]);
    $assignedTheses = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle form submissions (assignment and removal)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle thesis assignment
    if (isset($_POST['thesis'], $_POST['student'])) {
        $thesisID = trim($_POST['thesis']);
        $studentUsername = trim($_POST['student']);

        if ($thesisID === '' || $studentUsername === '') {
            echo json_encode(['success' => false, 'message' => 'Thesis and student are required.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM thesis WHERE thesisID = ?");
        $stmt->execute([$thesisID]);
        $thesis = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$thesis) {
            echo json_encode(['success' => false, 'message' => 'Thesis not found.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM student WHERE username = ?");
        $stmt->execute([$studentUsername]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found.']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE thesis SET assigned = TRUE, th_status='ASSIGNED' WHERE thesisID = ?");
        $stmt1 = $pdo->prepare("UPDATE student SET thesisID = ? WHERE username = ?");
        $stmt2 = $pdo->prepare("INSERT INTO thesisStatusChanges (thesisID, changeDate) VALUES (?, NOW())");
        if ($stmt->execute([$thesisID]) && $stmt1->execute([$thesisID, $studentUsername]) && $stmt2->execute([$thesisID])) {
            echo json_encode(['success' => true, 'message' => 'Thesis assigned successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to assign thesis.']);
        }
        exit;
    }

    // Handle thesis removal
    if (isset($_POST['remove'], $_POST['thesisID'])) {
        $thesisID = trim($_POST['thesisID']);
        $stmt = $pdo->prepare("SELECT finalized FROM thesis WHERE thesisID = ?");
        $stmt->execute([$thesisID]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && !$result['finalized']) {
            $stmt1 = $pdo->prepare("UPDATE thesis SET assigned = 0, th_status='NOT_ASSIGNED' WHERE thesisID = ?");
            $stmt2 = $pdo->prepare("UPDATE student SET thesisID = NULL WHERE thesisID = ?");
            $stmt3 = $pdo->prepare("INSERT INTO thesisStatusChanges (thesisID, changeDate) VALUES (?, NOW())");
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
<html>
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
      <h1 class="site-title">Thesis Assignment</h1>
    </div>
  </header>
  <div class="dashboard-container">
    <main class="dashboard-main">
      <h2>Assign Thesis to Student</h2>
        <form class="form-group" id="assignmentForm">
          <select class="select" name="thesis">
            <option value="">-- Select Thesis --</option>
            <?php foreach ($theses as $thesis): ?>
            <option value="<?= $thesis['thesisID'] ?>">
              <?= htmlspecialchars($thesis['title']) ?>
            </option>
            <?php endforeach; ?>
          </select>     
          <select class="select" name="student">
            <option value="">-- Select Student --</option>
            <?php foreach ($students as $s): ?>
            <option value="<?= htmlspecialchars($s['username']) ?>">
              <?= htmlspecialchars($s['username']) ?>
            </option>
              <?php endforeach; ?>
          </select>  
          <button class="submit-btn" type="submit">Assign</button>
        </form>
        <div id="result"></div>
        <h2>Assigned Theses</h2>
        <?php if (count($assignedTheses) > 0): ?>
          <table class="table">
            <thead>
              <tr>
                <th>Thesis ID</th>
                <th>Title</th>
                <th>Description</th>
                <th>Student</th>
                <th>Finalized</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($assignedTheses as $thesis): ?>
                <tr>
                  <td><?= htmlspecialchars($thesis['thesisID']) ?></td>
                  <td><?= htmlspecialchars($thesis['title']) ?></td>
                  <td><?= htmlspecialchars($thesis['th_description']) ?></td>
                  <td><?= htmlspecialchars($thesis['studentUsername']) ?></td>
                  <td><?= $thesis['finalized'] ? 'Yes' : 'No' ?></td>
                  <td>
                    <?php if (!$thesis['finalized']): ?>
                      <form method="post" class="remove-form" data-thesis-id="<?= $thesis['thesisID'] ?>">
                        <input class="submit-btn" type="submit" value="Remove">
                      </form>
                    <?php else: ?>
                      Finalized
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
        <p>No theses assigned yet.</p>
      <?php endif; ?>
      <script src="studentassignment.js"></script>
    </main>
  </div>
  <footer >
    <div class="footer-content">
      <p>&copy; 2025 Thesis Management System.</p>
    </div>
  </footer>
</body>
</html>
