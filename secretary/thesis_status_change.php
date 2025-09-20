<?php
require_once '../dbconnect.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

function fail($m,$c=400){
  http_response_code($c);
  echo json_encode(['success'=>false,'message'=>$m], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  if ($pdo instanceof PDO) {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
  }
} catch (Throwable $e) {}

if (empty($_SESSION['username'])) fail('Unauthorized', 401);

$role = $_SESSION['role'] ?? '';
// Προσαρμόστε αν θέλετε και φοιτητές
if (!in_array($role, ['teacher','secretary'], true)) fail('Forbidden', 403);

$thesisID  = (int)($_POST['thesisID']  ?? 0);
$toStatus  = trim((string)($_POST['toStatus'] ?? ''));

if ($thesisID <= 0 || $toStatus === '') fail('Bad request');

$valid = ['NOT_ASSIGNED','ASSIGNED','ACTIVE','EXAM','DONE','CANCELLED'];
if (!in_array($toStatus, $valid, true)) fail('Invalid status');

try {
  $pdo->beginTransaction();

  $chk = $pdo->prepare("SELECT thesisID FROM thesis WHERE thesisID=? LIMIT 1");
  $chk->execute([$thesisID]);
  if (!$chk->fetchColumn()) { $pdo->rollBack(); fail('Thesis not found', 404); }

  $up = $pdo->prepare("UPDATE thesis SET th_status=? WHERE thesisID=?");
  $up->execute([$toStatus, $thesisID]);

  $hist = $pdo->prepare("INSERT INTO thesisStatusChanges (thesisID, changeDate, changeTo) VALUES (?, CURDATE(), ?)");
  $hist->execute([$thesisID, $toStatus]);

  if ($toStatus === 'EXAM') {
    $pdo->prepare("
      INSERT IGNORE INTO thesis_exam_meta (thesisID, created_at, updated_at)
      VALUES (?, NOW(), NOW())
    ")->execute([$thesisID]);
  }

  $pdo->commit();
  echo json_encode(['success'=>true,'message'=>'Status updated'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error','detail'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
