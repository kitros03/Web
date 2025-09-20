<?php
require_once '../dbconnect.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'student') {
  http_response_code(401);
  echo json_encode(['error'=>'Unauthorized'], JSON_UNESCAPED_UNICODE); exit;
}

try {
  $q = $pdo->query("SELECT thesisID, exam_datetime, exam_meeting_url, exam_room FROM thesis_exam_meta WHERE announce=1 ORDER BY updated_at DESC");
  $rows = $q ? $q->fetchAll(PDO::FETCH_ASSOC) : [];
  $items = [];
  foreach ($rows as $r) {
    $sid = (int)$r['thesisID'];
    $st  = $pdo->prepare("SELECT s_fname, s_lname FROM student WHERE thesisID=?");
    $st->execute([$sid]);
    $student = $st->fetch(PDO::FETCH_ASSOC) ?: ['s_fname'=>'','s_lname'=>''];

    $tt = $pdo->prepare("SELECT title FROM thesis WHERE thesisID=?");
    $tt->execute([$sid]);
    $thesis = $tt->fetch(PDO::FETCH_ASSOC) ?: ['title'=>''];

    $items[] = [
      'student'         => trim(($student['s_fname']??'').' '.($student['s_lname']??'')),
      'title'           => $thesis['title'] ?? '',
      'exam_datetime'   => $r['exam_datetime'],
      'exam_meeting_url'=> $r['exam_meeting_url'],
      'exam_room'       => $r['exam_room'],
    ];
  }
  echo json_encode(['items'=>$items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>'Σφάλμα φόρτωσης ανακοινώσεων'], JSON_UNESCAPED_UNICODE);
}
