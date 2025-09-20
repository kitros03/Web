<?php
declare(strict_types=1);
session_start();

header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (!isset($_SESSION['username']) || (($_SESSION['role'] ?? '') !== 'secretary')) {
  http_response_code(403);
  echo json_encode(['error' => 'Forbidden'], JSON_UNESCAPED_UNICODE);
  exit;
}

require_once __DIR__ . '/../dbconnect.php';

if (!isset($host, $db, $user, $pass)) {
  http_response_code(500);
  echo json_encode(['error' => 'DB credentials not found in dbconnect.php'], JSON_UNESCAPED_UNICODE);
  exit;
}

$mysqli = @new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_errno) {
  http_response_code(500);
  echo json_encode(['error' => 'DB connection failed', 'details' => $mysqli->connect_error], JSON_UNESCAPED_UNICODE);
  exit;
}
@$mysqli->set_charset(isset($charset) ? $charset : 'utf8mb4');

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
      (SELECT AVG(g.grade) FROM grades g WHERE g.thesisID = t.thesisID AND g.grade IS NOT NULL) AS final_grade,
      (
        tem.repository_url IS NOT NULL AND tem.repository_url <> ''
        AND EXISTS (
          SELECT 1 FROM grades g2
          WHERE g2.thesisID = t.thesisID AND g2.grade IS NOT NULL
        )
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
    WHERE t.th_status IN ('ACTIVE','EXAM','DONE')
    ORDER BY t.thesisID DESC
  ";

  $result = $mysqli->query($sql);
  if (!$result) {
    throw new RuntimeException('Query failed: ' . $mysqli->error);
  }

  $rows = [];
  while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
  }
  $result->free();

  $today = new DateTimeImmutable('today');
  $out = [];
  foreach ($rows as $r) {
    $assigned = $r['assigned_date_assigned'] ?: $r['assigned_date_active'];
    $days = null;
    if ($assigned) {
      try {
        $days = (int)$today->diff(new DateTimeImmutable($assigned))->format('%a');
      } catch (Throwable $e) {
        $days = null;
      }
    }

    $out[] = [
      'thesisID'              => (int)$r['thesisID'],
      'title'                 => $r['title'],
      'th_description'        => $r['th_description'],
      'th_status'             => $r['th_status'],
      'gs_numb'               => ($r['gs_numb'] === null ? null : (int)$r['gs_numb']),
      'supervisor_name'       => ($r['supervisor_name'] !== null && $r['supervisor_name'] !== '') ? $r['supervisor_name'] : null,
      'student_name'          => ($r['student_name'] !== null && $r['student_name'] !== '') ? $r['student_name'] : null,
      'days_since_assignment' => $days,
      'repository_url'        => ($r['repository_url'] !== null && $r['repository_url'] !== '') ? $r['repository_url'] : null,
      // Σημαντικό: έλεγχος !== null ώστε να ΜΗ γίνει 0 όταν είναι NULL
      'final_grade'           => ($r['final_grade'] !== null ? (float)$r['final_grade'] : null),
      'ready_finalize'        => (bool)$r['ready_finalize'],
    ];
  }

  echo json_encode($out, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} finally {
  if (isset($mysqli) && $mysqli instanceof mysqli) { @$mysqli->close(); }
}
