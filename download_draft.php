<?php
require_once __DIR__.'/dbconnect.php';
session_start();

if (empty($_SESSION['username'])) {
    http_response_code(401);
    echo "Unauthorized";
    exit;
}

$thesisID = (int)($_GET['thesisID'] ?? 0);
if ($thesisID <= 0) {
    http_response_code(400);
    echo "Invalid thesis ID";
    exit;
}

// Έλεγχος πρόσβασης
$role = $_SESSION['role'] ?? '';
$username = $_SESSION['username'];
$canAccess = false;

try {
    if ($role === 'student') {
        $q = $pdo->prepare("SELECT 1 FROM student WHERE username = ? AND thesisID = ?");
        $q->execute([$username, $thesisID]);
        $canAccess = (bool)$q->fetchColumn();
    } elseif ($role === 'teacher') {
        $qt = $pdo->prepare("SELECT id FROM teacher WHERE username = ?");
        $qt->execute([$username]);
        $teacherId = (int)$qt->fetchColumn();
        
        if ($teacherId > 0) {
            $qc = $pdo->prepare("
                SELECT 1 FROM thesis t
                LEFT JOIN committee c ON c.thesisID = t.thesisID
                WHERE t.thesisID = ? AND (t.supervisor = ? OR c.member1 = ? OR c.member2 = ?)
            ");
            $qc->execute([$thesisID, $teacherId, $teacherId, $teacherId]);
            $canAccess = (bool)$qc->fetchColumn();
        }
    }

    if (!$canAccess) {
        http_response_code(403);
        echo "Access denied";
        exit;
    }

    // Φόρτωση αρχείου - μόνο draft_file
    $q = $pdo->prepare("SELECT draft_file FROM thesis_exam_meta WHERE thesisID = ? AND draft_file IS NOT NULL");
    $q->execute([$thesisID]);
    $file = $q->fetch(PDO::FETCH_ASSOC);

    if (!$file || empty($file['draft_file'])) {
        http_response_code(404);
        echo "File not found";
        exit;
    }

    // Καθαρισμός output buffer
    if (ob_get_level()) {
        ob_clean();
    }

    // Headers για download - generic filename αφού δεν έχουμε το πραγματικό όνομα
    $fileName = 'draft_thesis_' . $thesisID . '.pdf';
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . strlen($file['draft_file']));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');

    // Εκτύπωση αρχείου
    echo $file['draft_file'];
    
} catch (Exception $e) {
    http_response_code(500);
    echo "Server error: " . $e->getMessage();
}
?>
