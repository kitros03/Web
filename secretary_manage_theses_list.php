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
      tem.repository_url,
      (
        SELECT AVG(g.grade) FROM grades g WHERE g.thesisID = t.thesisID
      ) AS final_grade,
      (
        tem.repository_url IS NOT NULL
        AND EXISTS (SELECT 1 FROM grades g2 WHERE g2.thesisID = t.thesisID)
      ) AS ready_finalize,
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
    LEFT JOIN thesis_exam_meta tem ON tem.thesisID = t.thesisID
    WHERE
      t.th_status IN ('ACTIVE','DONE')
      OR (t.th_status = 'EXAM' AND tem.repository_url IS NOT NULL
          AND EXISTS (SELECT 1 FROM grades gg WHERE gg.thesisID = t.thesisID))
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
      'days_since_assignment' => $days,
      'repository_url' => $r['repository_url'] ?: null,
      'final_grade'    => isset($r['final_grade']) ? (float)$r['final_grade'] : null,
      'ready_finalize' => (bool)$r['ready_finalize'],
    ];
  }
  echo json_encode($out);
} catch (Throwable $e) {
  http_response_code(500);
  echo 'Server error: ' . $e->getMessage();
}
