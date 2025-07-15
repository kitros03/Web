<?php
session_start();
require_once "dbconnect.php"; // Ensure this contains $pdo

// Handle form submission (AJAX or direct post)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header("Content-Type: application/json");

    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
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
    $stmt = $pdo->prepare("SELECT teacherID FROM teacher WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$teacher || !isset($teacher['teacherID'])) {
        echo json_encode(['success' => false, 'message' => 'Supervisor not found.']);
        exit;
    }
    $teacherID = $teacher['teacherID'];

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

    // Insert the new thesis
    if ($pdfPath) {
        $stmt = $pdo->prepare("INSERT INTO thesis (supervisor, title, th_description, pdf_description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$teacherID, $title, $description, $pdfPath]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO thesis (supervisor, title, th_description) VALUES (?, ?, ?)");
        $stmt->execute([$teacherID, $title, $description]);
    }

    echo json_encode(['success' => true, 'message' => 'Thesis created successfully.']);
    exit;
}
// Only output HTML below for GET requests
header("Content-Type: text/html; charset=UTF-8");

// Fetch all theses for this teacher
$theses = [];
if (!empty($_SESSION['username'])) {
    $stmt = $pdo->prepare("SELECT teacherID FROM teacher WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($teacher && isset($teacher['teacherID'])) {
        $teacherID = $teacher['teacherID'];
        $stmt2 = $pdo->prepare("SELECT * FROM thesis WHERE supervisor = ? ORDER BY thesisID DESC");
        $stmt2->execute([$teacherID]);
        $theses = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Thesis</title>
    <style>
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; }
        th { background-color: #f2f2f2; }
        #result p { font-weight: bold; }
        #result .success { color: green; }
        #result .error { color: red; }
    </style>
</head>
<body>
    <form id="thesisForm" method="post" enctype="multipart/form-data">
        <label for="title">Thesis Title:</label>
        <input type="text" name="title" id="title" required><br><br>

        <label for="description">Description:</label>
        <input type="text" name="description" id="description" required><br><br>

        <label for="pdf">PDF (optional):</label>
        <input type="file" name="pdf" id="pdf" accept="application/pdf"><br><br>

        <button type="submit">Create Thesis</button>
    </form>

    <div id="result"></div>

    <h2>Previously Created Theses</h2>
    <?php if (!empty($theses)): ?>
    <table>
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
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p>No theses found.</p>
    <?php endif; ?>
    <script src="thesiscreation.js"></script>
</body>
</html>