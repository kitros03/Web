<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'secretary') {
  http_response_code(403);
  echo json_encode(['error'=>'Forbidden']); exit;
}
require_once __DIR__ . '/dbconnect.php'; 

$thesisID = isset($_GET['thesisID']) ? (int)$_GET['thesisID'] : 0;
if ($thesisID <= 0) { http_response_code(400); echo json_encode(['error'=>'Invalid thesisID']); exit; }

$stmt = $pdo->prepare("SELECT repository_url FROM thesis_exam_meta WHERE thesisID = ?");
$stmt->execute([$thesisID]);
$tem = $stmt->fetch(PDO::FETCH_ASSOC);
$repo = $tem['repository_url'] ?? null;

$stmt = $pdo->prepare("SELECT AVG(grade) AS final_grade FROM grades WHERE thesisID = ?");
$stmt->execute([$thesisID]);
$g = $stmt->fetch(PDO::FETCH_ASSOC);
$final = isset($g['final_grade']) ? (float)$g['final_grade'] : null;

$stmt = $pdo->prepare("SELECT th_status FROM thesis WHERE thesisID = ?");
$stmt->execute([$thesisID]);
$st = $stmt->fetch(PDO::FETCH_ASSOC);
$status = $st['th_status'] ?? null;

echo json_encode([
  'thesisID' => $thesisID,
  'repository_url' => $repo,
  'final_grade' => $final,
  'th_status' => $status
]);
?>