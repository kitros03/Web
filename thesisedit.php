<?php
session_start();
require_once "dbconnect.php";

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    if (isset($_GET['ajax'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    header('Location: index.html');
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    if (isset($_GET['ajax'])) {
        echo json_encode(['success' => false, 'message' => 'Thesis ID is required.']);
        exit;
    }
    echo "Thesis ID is required.";
    exit;
}

$thesisID = $_GET['id'];

// AJAX GET request για δεδομένα thesis
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare("SELECT * FROM thesis WHERE thesisID = ?");
    $stmt->execute([$thesisID]);
    $thesis = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$thesis) {
        echo json_encode(['success' => false, 'message' => 'Thesis not found.']);
        exit;
    }
    echo json_encode(['success' => true, 'thesis' => $thesis]);
    exit;
}

// POST request για επεξεργασία thesis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header("Content-Type: application/json");

    // Πρώτα φορτώνουμε το τρέχον thesis για ενημέρωση pdf κλπ
    $stmt = $pdo->prepare("SELECT * FROM thesis WHERE thesisID = ?");
    $stmt->execute([$thesisID]);
    $thesis = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$thesis) {
        echo json_encode(['success' => false, 'message' => 'Thesis not found.']);
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $removePdf = isset($_POST['remove_pdf']) && $_POST['remove_pdf'] == '1';
    $newPdf = $_FILES['new_pdf'] ?? null;

    if (empty($title) || empty($description)) {
        echo json_encode(['success' => false, 'message' => 'Title and Description are required.']);
        exit;
    }

    if ($removePdf && !empty($thesis['pdf_description'])) {
        if (file_exists($thesis['pdf_description'])) {
            unlink($thesis['pdf_description']);
        }
        $thesis['pdf_description'] = null;
    }

    if ($newPdf && $newPdf['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $uploadFile = $uploadDir . uniqid() . '_' . basename($newPdf['name']);
        if (move_uploaded_file($newPdf['tmp_name'], $uploadFile)) {
            $thesis['pdf_description'] = $uploadFile;
        } else {
            echo json_encode(['success' => false, 'message' => 'Error uploading PDF file.']);
            exit;
        }
    }

    $stmt = $pdo->prepare("UPDATE thesis SET title = ?, th_description = ?, pdf_description = ? WHERE thesisID = ?");
    if ($stmt->execute([$title, $description, $thesis['pdf_description'], $thesisID])) {
        echo json_encode(['success' => true, 'message' => 'Thesis updated successfully.']);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating thesis.']);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <link rel="stylesheet" href="style.css" />
    <title>Edit Thesis</title>
</head>
<body>
    <header>
        <div class="logo-title-row">
            <button class="back-btn" id="backBtn">
                <img src="logo2.jpg" alt="Logo" class="logo" />
            </button>
            <h1 class="site-title">Thesis Edit</h1>
        </div>
    </header>
    <div class="dashboard-main">
        <h2>Edit Thesis</h2>
        <main class="dashboard-container">
            <form id="thesisForm" method="post" enctype="multipart/form-data" autocomplete="off">
                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" name="title" id="title" required />
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" rows="6" required></textarea>
                </div>
                <div class="form-group" id="pdfSection">
                    <label>PDF File</label>
                    <div id="currentPdfStatus" style="margin-bottom: 0.6rem;"></div>
                    <input class="input" type="file" name="new_pdf" id="new_pdf" accept="application/pdf" />
                    <div style="font-size:small; color:#555; margin-top:0.3em;">Leave empty if not replacing PDF.</div>
                    <label style="font-weight:400;">
                        <input type="checkbox" name="remove_pdf" value="1" id="removePdfCheckbox" /> Remove PDF
                    </label>
                </div>
                <div style="margin-top: 2rem;">
                    <button type="submit" class="submit-btn">Save Changes</button>
                    <button type="button" id="backBtn2" class="submit-btn">Back</button>
                </div>
                <div id="result" style="margin-top:1.1em;"></div>
            </form>
        </main>
    </div>
    <footer>
        <p class="footer">© 2025 Thesis Management System</p>
    </footer>
    <script src="thesisedit.js"></script>
</body>
</html>
