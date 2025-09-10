<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'secretary') {
  http_response_code(403);
  echo 'Forbidden'; exit;
}
require_once __DIR__ . '/dbconnect.php'; // $pdo

try {
  // Ελέγχουμε αν υπάρχει η στήλη gs_numb στον thesis
  $q = $pdo->prepare("
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'thesis' AND column_name = 'gs_numb'
  ");
  $q->execute();
  $hasGs = (bool)$q->fetchColumn();

  $selectGs = $hasGs ? "t.gs_numb AS gs_numb," : "NULL AS gs_numb,";

  $sql = "
    SELECT
      t.thesisID,
      t.title,
      t.th_description,
      t.th_status,
      $selectGs
      CONCAT(te.t_fname, ' ', te.t_lname) AS supervisor_name,
      CONCAT(s.s_fname, ' ', s.s_lname)   AS student_name,
      (
        SELECT MIN(changeDate) FROM thesisStatusChanges sc
        WHERE sc.thesisID = t.thesisID AND sc.changeTo = 'ASSIGNED'
      ) AS assigned_date_assigned,
      (
        SELECT MIN(changeDate) FROM thesisStatusChanges sc
        WHERE sc.thesisID = t.thesisID AND sc.changeTo = 'ACTIVE'
      ) AS assigned_date_active
    FROM thesis t
    LEFT JOIN teacher te ON te.id = t.supervisor
    LEFT JOIN student s  ON s.thesisID = t.thesisID
    WHERE t.th_status = 'ACTIVE'
    ORDER BY t.thesisID DESC
  ";

  $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

  $out = [];
  $today = new DateTimeImmutable('today');
  foreach ($rows as $r) {
    $assigned = $r['assigned_date_assigned'] ?: $r['assigned_date_active'];
    $days = null;
    if ($assigned) {
      try { $days = (int)$today->diff(new DateTimeImmutable($assigned))->format('%a'); } catch (Throwable $e) {}
    }
    $out[] = [
      'thesisID' => (int)$r['thesisID'],
      'title'    => $r['title'],
      'th_description' => $r['th_description'],
      'th_status'=> $r['th_status'],
      'gs_numb'  => $r['gs_numb'],
      'supervisor_name' => $r['supervisor_name'] ?: null,
      'student_name'    => $r['student_name'] ?: null,
      'days_since_assignment' => $days
    ];
  }

  echo json_encode($out);
} catch (Throwable $e) {
  http_response_code(500);
  echo 'Server error: ' . $e->getMessage();
}
