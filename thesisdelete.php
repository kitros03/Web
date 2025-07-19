<?php
session_start();
require_once "dbconnect.php";
header("Content-Type: application/json");

if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'No thesis ID specified.']);
    exit;
}
$thesisID = $_POST['id'];

// Remove PDF file if exists
$stmt = $pdo->prepare("SELECT pdf_description FROM thesis WHERE thesisID = ?");
$stmt->execute([$thesisID]);
$thesis = $stmt->fetch(PDO::FETCH_ASSOC);

if ($thesis && !empty($thesis['pdf_description']) && file_exists($thesis['pdf_description'])) {
    unlink($thesis['pdf_description']);
}

// Delete the thesis record
$stmt = $pdo->prepare("DELETE FROM thesis WHERE thesisID = ?");
if ($stmt->execute([$thesisID])) {
    echo json_encode(['success' => true, 'message' => 'Thesis deleted successfully.']);
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'Error deleting thesis.']);
    exit;
}
?>
