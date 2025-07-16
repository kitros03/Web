<?php
session_start();
require 'dbconnect.php';

$teacherID = null;
$theses = [];
$students = [];
$assignedTheses = [];

if (isset($_SESSION['username'])) {
    $stmt = $pdo->prepare("SELECT teacherID FROM teacher WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($teacher && isset($teacher['teacherID'])) {
        $teacherID = $teacher['teacherID'];

        // Get unassigned theses for dropdown
        $stmt2 = $pdo->prepare("SELECT * FROM thesis WHERE supervisor = ? AND assigned = 0 ORDER BY thesisID DESC");
        $stmt2->execute([$teacherID]);
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
    $stmt->execute([$teacherID]);
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

        $stmt = $pdo->prepare("UPDATE thesis SET assigned = TRUE WHERE thesisID = ?");
        $stmt1 = $pdo->prepare("UPDATE student SET thesisID = ? WHERE username = ?");
        if ($stmt->execute([$thesisID]) && $stmt1->execute([$thesisID, $studentUsername])) {
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
            $stmt1 = $pdo->prepare("UPDATE thesis SET assigned = 0 WHERE thesisID = ?");
            $stmt2 = $pdo->prepare("UPDATE student SET thesisID = NULL WHERE thesisID = ?");
            if ($stmt1->execute([$thesisID]) && $stmt2->execute([$thesisID])) {
                echo "Success";
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
  <title>Assign Thesis</title>
</head>
<body>
  <h2>Assign Thesis to Student</h2>
  <form id="assignmentForm">
    <label>Thesis:
      <select name="thesis">
        <option value="">-- Select Thesis --</option>
        <?php foreach ($theses as $thesis): ?>
          <option value="<?= $thesis['thesisID'] ?>">
            <?= htmlspecialchars($thesis['title']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>Student:
      <select name="student">
        <option value="">-- Select Student --</option>
        <?php foreach ($students as $s): ?>
          <option value="<?= htmlspecialchars($s['username']) ?>">
            <?= htmlspecialchars($s['username']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <button type="submit">Assign</button>
  </form>
  <div id="result"></div>

  <h2>Assigned Theses</h2>
  <?php if (count($assignedTheses) > 0): ?>
    <table border="1">
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
                  <input type="submit" value="Remove">
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
</body>
</html>
