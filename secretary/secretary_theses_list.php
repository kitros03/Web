<?php
declare(strict_types=1);
session_start();

// Πάντα buffer για να μην “ξεφύγει” άσχετη έξοδος
ob_start();
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Access check
if (!isset($_SESSION['username']) || (($_SESSION['role'] ?? '') !== 'secretary')) {
  http_response_code(403);
  ob_clean();
  echo json_encode(['error' => 'Forbidden'], JSON_UNESCAPED_UNICODE);
  exit;
}

// Φέρε το dbconnect ΜΕΧΡΙ να πάρουμε τα $host, $db, $user, $pass, $charset
require_once __DIR__ . '/../dbconnect.php';

// Άνοιξε δική μας mysqli σύνδεση χρησιμοποιώντας τις ΜΕΤΑΒΛΗΤΕΣ από το dbconnect.php
if (!isset($host, $db, $user, $pass)) {
  http_response_code(500);
  ob_clean();
  echo json_encode(['error' => 'DB credentials not found in dbconnect.php'], JSON_UNESCAPED_UNICODE);
  exit;
}

$mysqli = @new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_errno) {
  http_response_code(500);
  ob_clean();
  echo json_encode(['error' => 'DB connection failed', 'details' => $mysqli->connect_error], JSON_UNESCAPED_UNICODE);
  exit;
}
@$mysqli->set_charset($charset ?? 'utf8mb4');

try {
  $sql = "
    SELECT
      t.thesisID,
      t.title,
      t.th_description,
      t.th_status,
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
    WHERE t.th_status IN ('ACTIVE','EXAM')
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
      'thesisID' => (int)$r['thesisID'],
      'title'    => $r['title'],
      'th_description' => $r['th_description'],
      'th_status'=> $r['th_status'],
      'supervisor_name' => $r['supervisor_name'] ?: null,
      'student_name'    => $r['student_name'] ?: null,
      'days_since_assignment' => $days
    ];
  }

  ob_clean(); // καθάρισε τυχόν BOM/echo
  echo json_encode($out, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  ob_clean();
  echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} finally {
  @$mysqli->close();
}
?>