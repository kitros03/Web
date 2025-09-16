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
  $sql = "
    SELECT
      t.thesisID,
      t.title,
      t.th_description,
      t.th_status,
      t.gs_numb,
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

  $today = new DateTimeImmutable('today');
  $out = [];
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
