<?php
declare(strict_types=1);
session_start();

// Κρατάμε την έξοδο καθαρή από BOM/echo/warnings
ob_start();
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (!isset($_SESSION['username']) || (($_SESSION['role'] ?? '') !== 'secretary')) {
  http_response_code(403);
  ob_clean();
  echo json_encode(['error' => 'Forbidden'], JSON_UNESCAPED_UNICODE);
  exit;
}

require_once __DIR__ . '/../dbconnect.php'; // ΔΕΝ το αλλάζουμε — παίρνουμε $host,$db,$user,$pass,$charset

// Παίρνουμε και ελέγχουμε thesisID
$thesisID = isset($_GET['thesisID']) ? (int)$_GET['thesisID'] : 0;
if ($thesisID <= 0) {
  http_response_code(400);
  ob_clean();
  echo json_encode(['error' => 'Invalid thesisID'], JSON_UNESCAPED_UNICODE);
  exit;
}

// Άνοιξε ΔΙΚΗ μας mysqli σύνδεση με τα ίδια credentials (όχι PDO)
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
@$mysqli->set_charset(isset($charset) ? $charset : 'utf8mb4');

// Helpers
function select_one_assoc(mysqli $mysqli, string $sql, array $params, string $types): ?array {
  $stmt = $mysqli->prepare($sql);
  if (!$stmt) throw new RuntimeException('Prepare failed: ' . $mysqli->error);
  if ($types !== '' && $params) { $stmt->bind_param($types, ...$params); }
  if (!$stmt->execute()) {
    $err = $stmt->error; $stmt->close();
    throw new RuntimeException('Execute failed: ' . $err);
  }
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  return $row ?: null;
}

try {
  // ===== Βασικά στοιχεία διπλωματικής =====
  $sql = "
    SELECT
      t.thesisID,
      t.title,
      t.th_description,
      t.th_status,
      CONCAT(te.t_fname, ' ', te.t_lname) AS supervisor_name,
      CONCAT(s.s_fname, ' ', s.s_lname)   AS student_name,
      (
        SELECT MIN(changeDate)
        FROM thesisStatusChanges sc
        WHERE sc.thesisID = t.thesisID AND sc.changeTo = 'ASSIGNED'
      ) AS assigned_date_assigned,
      (
        SELECT MIN(changeDate)
        FROM thesisStatusChanges sc
        WHERE sc.thesisID = t.thesisID AND sc.changeTo = 'ACTIVE'
      ) AS assigned_date_active
    FROM thesis t
    LEFT JOIN teacher te ON te.id = t.supervisor
    LEFT JOIN student s  ON s.thesisID = t.thesisID
    WHERE t.thesisID = ?
    LIMIT 1
  ";
  $th = select_one_assoc($mysqli, $sql, [$thesisID], 'i');
  if (!$th) {
    http_response_code(404);
    ob_clean();
    echo json_encode(['error' => 'Not found'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // ===== Επιτροπή (1 γραμμή όπως στο αρχικό) =====
  $sqlC = "
    SELECT
      CONCAT(t1.t_fname, ' ', t1.t_lname) AS supervisor_name,
      CONCAT(t2.t_fname, ' ', t2.t_lname) AS member1_name,
      CONCAT(t3.t_fname, ' ', t3.t_lname) AS member2_name
    FROM committee c
    LEFT JOIN teacher t1 ON t1.id = c.supervisor
    LEFT JOIN teacher t2 ON t2.id = c.member1
    LEFT JOIN teacher t3 ON t3.id = c.member2
    WHERE c.thesisID = ?
    LIMIT 1
  ";
  $comm = select_one_assoc($mysqli, $sqlC, [$thesisID], 'i');

  $committee = [];
  if ($comm) {
    if (!empty($comm['supervisor_name'])) $committee[] = 'Επιβλέπων: ' . $comm['supervisor_name'];
    if (!empty($comm['member1_name']))   $committee[] = 'Μέλος: ' . $comm['member1_name'];
    if (!empty($comm['member2_name']))   $committee[] = 'Μέλος: ' . $comm['member2_name'];
  }

  // ===== Ημέρες από ανάθεση =====
  $assigned = $th['assigned_date_assigned'] ?: $th['assigned_date_active'];
  $days = null;
  if ($assigned) {
    try {
      $today = new DateTimeImmutable('today');
      $days  = (int)$today->diff(new DateTimeImmutable($assigned))->format('%a');
    } catch (Throwable $e) { $days = null; }
  }

  ob_clean();
  echo json_encode([
    'thesisID'              => (int)$th['thesisID'],
    'title'                 => $th['title'],
    'th_description'        => $th['th_description'],
    'th_status'             => $th['th_status'],
    'supervisor_name'       => $th['supervisor_name'] ?: null,
    'student_name'          => $th['student_name'] ?: null,
    'assigned_date'         => $assigned ?: null,
    'days_since_assignment' => $days,
    'committee'             => $committee
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  ob_clean();
  echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} finally {
  if (isset($mysqli) && $mysqli instanceof mysqli) { @$mysqli->close(); }
}
