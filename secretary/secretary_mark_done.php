<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'secretary') {
  http_response_code(403);
  echo json_encode(['success'=>false,'message'=>'Forbidden']); exit;
}
require_once '../dbconnect.php'; 
try {
  $data = json_decode(file_get_contents('php://input'), true);
  $thesisID = (int)($data['thesisID'] ?? 0);
  if ($thesisID <= 0) throw new Exception('Invalid thesisID.');

  $pdo->beginTransaction();
  $u = $pdo->prepare("UPDATE thesis SET th_status='DONE' WHERE thesisID=?");
  $u->execute([$thesisID]);

  $i = $pdo->prepare("INSERT INTO thesisStatusChanges (thesisID, changeDate, changeTo) VALUES (?, CURDATE(), 'DONE')");
  $i->execute([$thesisID]);
  $stmt = $pdo->prepare("UPDATE thesis_exam_meta SET announce=0 WHERE thesisID=?");
  $stmt->execute([$thesisID]);

  $pdo->commit();
  echo json_encode(['success'=>true]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>