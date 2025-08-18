<?php
session_start();
require_once "dbconnect.php"; // Ensure this contains $pdo

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header("Content-Type: application/json");

    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description = isset($_POST['desc']) ? trim($_POST['desc']) : '';
    $pdf = isset($_FILES['pdf']) ? $_FILES['pdf'] : null;

    if ($title === '' || $description === '') {
        echo json_encode(['success' => false, 'message' => 'Title and description are required.']);
        exit;
    }

    if (empty($_SESSION['username'])) {
        echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
        exit;
    }

    // Retrieve the teacher's ID
    $stmt = $pdo->prepare("SELECT id FROM teacher WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$teacher || !isset($teacher['id'])) {
        echo json_encode(['success' => false, 'message' => 'Supervisor not found.']);
        exit;
    }
    $id = $teacher['id'];

    // Handle optional PDF upload
    $pdfPath = null;
    if ($pdf && $pdf['error'] === UPLOAD_ERR_OK && $pdf['size'] > 0) {
        $fileType = mime_content_type($pdf['tmp_name']);
        $ext = strtolower(pathinfo($pdf['name'], PATHINFO_EXTENSION));
        if ($fileType !== 'application/pdf' || $ext !== 'pdf') {
            echo json_encode(['success' => false, 'message' => 'Only PDF files are allowed.']);
            exit;
        }
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $pdfName = uniqid() . '_' . basename($pdf['name']);
        $pdfPath = $uploadDir . $pdfName;
        if (!move_uploaded_file($pdf['tmp_name'], $pdfPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to upload PDF file.']);
            exit;
        }
    }

    // Insert the new thesis and create a committee
    if ($pdfPath) {
        $stmt = $pdo->prepare("INSERT INTO thesis (supervisor, title, th_description, pdf_description, th_status) VALUES (?, ?, ?, ?, 'NOT_ASSIGNED')");
        $stmt->execute([$id, $title, $description, $pdfPath]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO thesis (supervisor, title, th_description, th_status) VALUES (?, ?, ?, 'NOT_ASSIGNED')");
        $stmt->execute([$id, $title, $description]);
    }
    $stmt = $pdo->prepare("INSERT INTO committee (thesisID, supervisor) VALUES (?, ?)");
    $thesisID = $pdo->lastInsertId();
    $stmt->execute([$thesisID, $id]);
    echo json_encode(['success' => true, 'message' => 'Thesis created successfully.']);
    exit;

}

$theses = [];
if (!empty($_SESSION['username'])) {
    $stmt = $pdo->prepare("SELECT id FROM teacher WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($teacher && isset($teacher['id'])) {
        $id = $teacher['id'];
        $stmt2 = $pdo->prepare("SELECT * FROM thesis WHERE supervisor = ? ORDER BY thesisID DESC");
        $stmt2->execute([$id]);
        $theses = $stmt2->fetchAll(PDO::FETCH_ASSOC);
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
            <h1 class="site-title">Thesis Creation</h1>
        </div>
    </header>
    <div class="dashboard-main">
        <h2>Create a New Thesis</h2>
        <p>Fill out the form below to create a new thesis.</p>
        <form class="form-group" id="thesisForm" method="post" enctype="multipart/form-data">
            <label for="title">Thesis Title:</label>
            <input type="text" name="title" id="title" required><br><br>
            <label for="description">Description:</label>
            <textarea rows="8" cols="50" name="desc"></textarea><br><br>
            <label for="pdf">PDF (optional):</label>
            <input class="file-input" type="file" name="pdf" id="pdf" accept="application/pdf"><br><br>
            <button class="submit-btn" type="submit">Create Thesis</button>
        </form>
        <div id="result"></div>
        <h2>Previously Created Theses</h2>
        <?php if (!empty($theses)): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Description</th>
                    <th>PDF</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($theses as $thesis): ?>
                <tr>
                    <td><?php echo htmlspecialchars($thesis['thesisID']); ?></td>
                    <td><?php echo htmlspecialchars($thesis['title']); ?></td>
                    <td><?php echo htmlspecialchars($thesis['th_description']); ?></td>
                    <td>
                        <?php if (!empty($thesis['pdf_description'])): ?>
                            <a href="<?php echo htmlspecialchars($thesis['pdf_description']); ?>" target="_blank">View PDF</a>
                        <?php else: ?>
                            No PDF
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="edit-btn" id="edit-form" type="click" data-thesis-id="<?= $thesis['thesisID'] ?>">Edit</button>
                    </td>
                    <td>
                        <button class="delete-btn" id="delete-form" type="button" data-thesis-id="<?= $thesis['thesisID'] ?>">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No theses found.</p>
        <?php endif; ?>
        <script src="thesiscreation.js"></script>
    </div>
    <footer class="footer">
        <p>&copy; 2025 Thesis Management System</p>
</body>
</html>