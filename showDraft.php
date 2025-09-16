<?php
    session_start();
    require_once 'dbconnect.php';
    if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
        header('Location: index.html');
        exit;
    }
   
    // Get current teacher id
    $stmt = $pdo->prepare("SELECT id FROM teacher WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    //get thesis id from url
    $thesisID = $_GET['thesisID'] ?? null;
    if (!$thesisID) {
        http_response_code(400);
        echo "Thesis ID is required";
        exit;
    }

    // Φόρτωση PDF
    $stmt = $pdo->prepare("SELECT draft_file FROM thesis_exam_meta WHERE thesisID=?");
    $stmt->execute([$thesisID]);
    $pdfData = $stmt->fetchColumn();

    if ($pdfData) {
        header("Content-Type: application/pdf"); 
        header("Content-Disposition: inline; filename=thesis".$id.".pdf"); 
        echo $pdfData;
    }
?>