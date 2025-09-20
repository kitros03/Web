<?php
session_start();
require_once '../dbconnect.php'; 

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo json_encode(['error' => 'unauthorized']);
    } else {
        header('Location: index.php');
    }
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM teacher WHERE username = ?");
$stmt->execute([$_SESSION['username']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    http_response_code(404);
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo json_encode(['error' => 'teacher not found']);
    } else {
        echo "<h1>Teacher not found.</h1>";
    }
    exit;
}

if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    $teacherId = $teacher['id'];
    $stmt = $pdo->prepare("
        SELECT t.*, c.member1, c.member2
        FROM thesis t
        LEFT JOIN committee c ON t.thesisID = c.thesisID
        WHERE (t.supervisor = ? OR c.member1 = ? OR c.member2 = ?)
        AND t.th_status != 'NOT_ASSIGNED'
        ORDER BY t.thesisID DESC
    ");
    $stmt->execute([$teacherId, $teacherId, $teacherId]);
    $theses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($theses as &$thesis) {
        $thesis['member1Name'] = null;
        $thesis['member2Name'] = null;
        if ($thesis['member1']) {
            $stmt = $pdo->prepare("SELECT CONCAT(t_fname, ' ', t_lname) FROM teacher WHERE id = ?");
            $stmt->execute([$thesis['member1']]);
            $thesis['member1Name'] = $stmt->fetchColumn();
        }
        if ($thesis['member2']) {
            $stmt = $pdo->prepare("SELECT CONCAT(t_fname, ' ', t_lname) FROM teacher WHERE id = ?");
            $stmt->execute([$thesis['member2']]);
            $thesis['member2Name'] = $stmt->fetchColumn();
        }
        // allages katastasis
        $stmt = $pdo->prepare("SELECT changeDate, changeTo FROM thesisStatusChanges WHERE thesisID = ? ORDER BY changeDate ASC");
        $stmt->execute([$thesis['thesisID']]);
        $thesis['changes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $thesis['role'] = $thesis['supervisor'] == $teacherId ? 'Supervisor' : 'Committee';
        $stmt = $pdo->prepare("SELECT CONCAT(t_fname, ' ', t_lname) FROM teacher WHERE id = ?");
        $stmt->execute([$thesis['supervisor']]);
        $thesis['supervisorName'] = $stmt->fetchColumn() ?: '';
        $thesis['grade'] = $thesis['grade'] ?? '';
    }
    unset($thesis);
    echo json_encode(['success'=>true, 'theses'=>$theses]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<link rel="stylesheet" href="../style.css" />
</head>
<body>
<header>
    <div class="logo-title-row">
        <button class="back-btn" id="backBtn">
            <img src="../logo2.jpg" alt="Logo" class="logo" />
        </button>
        <h1 class="site-title">Διαχείρηση Διπλωματικών</h1>
    </div>
</header>
<main class="dashboard-main">
    <h2>Διπλωματικές</h2>
    <table class="table" id="thesesTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Τίτλος</th>
                <th>Ρόλος</th>
                <th>Κατάσταση</th>
                <th>Επιβλέπων</th>
                <th>Μέλος</th>
                <th>Μέλος</th>
                <th>Βαθμός</th>
                <th>Αλλαγές Κατάστασης</th>
                <th>Διαχείρηση</th>
            </tr>
        </thead>
        <tbody>
            <!-- AJAX -->
        </tbody>
    </table>
    <p id="noThesesMsg" style="display:none;">Δεν βρέθηκαν διπλωματικές</p>
</main>
<footer class="footer">
    <p>© 2025 Thesis Management System</p>
</footer>
<script src="viewtheses.js"></script>
</body>
</html>
