<?php
require_once '../dbconnect.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'student') {
  echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
}

$action = $_POST['action'] ?? '';
if ($action !== 'save_profile_field') {
  echo json_encode(['success'=>false,'message'=>'Invalid action']); exit;
}

$field = $_POST['field'] ?? '';

$st = $pdo->prepare("SELECT studentID FROM student WHERE username=? LIMIT 1");
$st->execute([$_SESSION['username']]);
$studentID = (int)$st->fetchColumn();
if ($studentID <= 0) {
  echo json_encode(['success'=>false,'message'=>'No student']); exit;
}

try {
  if ($field === 'email') {
    $value = (string)($_POST['value'] ?? '');
    $q = $pdo->prepare("UPDATE student SET email = ? WHERE studentID = ?");
    $q->execute([$value !== '' ? $value : null, $studentID]);
    echo json_encode(['success'=>true]); exit;
  }

  if ($field === 'mobile') {
    $value = (string)($_POST['value'] ?? '');
    $q = $pdo->prepare("UPDATE student SET cellphone = ? WHERE studentID = ?");
    $q->execute([$value !== '' ? $value : null, $studentID]);
    echo json_encode(['success'=>true]); exit;
  }

  if ($field === 'phone') {
    $value = (string)($_POST['value'] ?? '');
    $q = $pdo->prepare("UPDATE student SET homephone = ? WHERE studentID = ?");
    $q->execute([$value !== '' ? $value : null, $studentID]);
    echo json_encode(['success'=>true]); exit;
  }

  if ($field === 'address') {
    $street   = (string)($_POST['street']   ?? '');
    $streetno = (string)($_POST['streetno'] ?? '');
    $city     = (string)($_POST['city']     ?? '');
    $postcode = (string)($_POST['postcode'] ?? '');

    $q = $pdo->prepare("UPDATE student
                        SET street = ?, street_number = ?, city = ?, postcode = ?
                        WHERE studentID = ?");
    $q->execute([
      $street   !== '' ? $street   : null,
      $streetno !== '' ? $streetno : null,
      $city     !== '' ? $city     : null,
      $postcode !== '' ? $postcode : null,
      $studentID
    ]);
    echo json_encode(['success'=>true]); exit;
  }

  echo json_encode(['success'=>false,'message'=>'Unsupported field']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error']);
}
