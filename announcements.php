<?php
session_start();
require_once("dbconnect.php");
header('Content-Type: application/json');

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM teacher WHERE username = ?");
$stmt->execute([$_SESSION['username']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    http_response_code(404);
    echo json_encode(['error' => 'teacher not found']);
    exit;
}

$announcements = [];
$query = "SELECT * FROM thesis_exam_meta WHERE announce=1 ORDER BY updated_at DESC";
$result = $pdo->query($query);
if ($result) {
    $announcements = $result->fetchAll(PDO::FETCH_ASSOC);
}

echo json_encode(['success' => true, 'announcements' => $announcements]);
exit;
