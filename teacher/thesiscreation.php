<?php
session_start();
require_once "../dbconnect.php"; 

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    header('Location: index.php');
    exit;
}

// ανακτηση δεομενων
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
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
    echo json_encode($theses);
    exit;
}

// POST request για δημιουργια διπλωματικης
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

    $stmt = $pdo->prepare("SELECT id FROM teacher WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$teacher || !isset($teacher['id'])) {
        echo json_encode(['success' => false, 'message' => 'Supervisor not found.']);
        exit;
    }
    $id = $teacher['id'];

    $pdfPath = null;
    if ($pdf && $pdf['error'] === UPLOAD_ERR_OK && $pdf['size'] > 0) {
        $fileType = mime_content_type($pdf['tmp_name']);
        $ext = strtolower(pathinfo($pdf['name'], PATHINFO_EXTENSION));
        if ($fileType !== 'application/pdf' || $ext !== 'pdf') {
            echo json_encode(['success' => false, 'message' => 'Only PDF files are allowed.']);
            exit;
        }
        $uploadDir = '../uploads/';
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <header>
        <div class="logo-title-row">
            <button class="back-btn" id="backBtn">
                <img src="../logo2.jpg" alt="Logo" class="logo" />
            </button>
            <h1 class="site-title">Δημιουργία Διπλωματικής</h1>
        </div>
    </header>
    <div class="dashboard-main">
        <h2>Δημιουργία Καινούριας Διπλωματικής</h2>
        <p>Γεμίστε την παρακάτω φόρμα.</p>
        <form class="form-group" id="thesisForm" method="post" enctype="multipart/form-data">
            <label for="title">Τίτλος:</label>
            <input type="text" name="title" id="title" required><br><br>
            <label for="description">Περιγραφή:</label>
            <textarea rows="8" cols="50" name="desc"></textarea><br><br>
            <label for="pdf">PDF (προαιρετικό):</label>
            <input class="file-input" type="file" name="pdf" id="pdf" accept="application/pdf"><br><br>
            <button class="submit-btn" type="submit">Δημιουργία</button>
        </form>
        <div id="result"></div>

        <h2>Διπλωματικές</h2>
        <table class="table" id="ajaxThesesTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Τίτλος</th>
                    <th>Περιγραφή</th>
                    <th>PDF</th>
                    <th>Ενέργεια</th>
                </tr>
            </thead>
            <tbody>
                <!-- data shown in js -->
            </tbody>
        </table>
        <div id="noThesisMsg"></div>
    </div>
    <footer class="footer">
        <p>&copy; 2025 Thesis Management System</p>
    </footer>

    <script src="thesiscreation.js"></script>
</body>
</html>
