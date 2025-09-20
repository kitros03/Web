<?php
declare(strict_types=1);
session_start();

// Κρατάμε την έξοδο «καθαρή» από BOM/echo/warnings
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

require_once __DIR__ . '/../dbconnect.php'; // ΔΕΝ το αλλάζουμε• μας δίνει $host,$db,$user,$pass,$charset,$pdo

// Παίρνουμε το thesisID με ασφάλεια
$thesisID = isset($_GET['thesisID']) ? (int)$_GET['thesisID'] : 0;
if ($thesisID <= 0) {
  http_response_code(400);
  ob_clean();
  echo json_encode(['error' => 'Invalid thesisID'], JSON_UNESCAPED_UNICODE);
  exit;
}

// Ανοίγουμε ΔΙΚΗ μας mysqli σύνδεση με τα ίδια credentials του dbconnect.php
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

// Helper για 1-row SELECT με prepared statement
function select_one_assoc(mysqli $mysqli, string $sql, int $id): ?array {
  $stmt = $mysqli->prepare($sql);
  if (!$stmt) {
    throw new RuntimeException('Prepare failed: ' . $mysqli->error);
  }
  $stmt->bind_param('i', $id);
  if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    throw new RuntimeException('Execute failed: ' . $err);
  }
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  return $row ?: null;
}

try {
  // 1) repository_url
  $tem = select_one_assoc($mysqli, "SELECT repository_url FROM thesis_exam_meta WHERE thesisID = ?", $thesisID);
  $repo = $tem['repository_url'] ?? null;

  // 2) τελικός βαθμός (AVG)
  $g = select_one_assoc($mysqli, "SELECT AVG(grade) AS final_grade FROM grades WHERE thesisID = ?", $thesisID);
  $final = isset($g['final_grade']) ? (float)$g['final_grade'] : null;

  // 3) κατάσταση
  $st = select_one_assoc($mysqli, "SELECT th_status FROM thesis WHERE thesisID = ?", $thesisID);
  $status = $st['th_status'] ?? null;

  ob_clean(); // ό,τι τυχόν γράφτηκε πριν (BOM/echo) καθαρίζεται
  echo json_encode([
    'thesisID'       => $thesisID,
    'repository_url' => $repo,
    'final_grade'    => $final,
    'th_status'      => $status
  ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  ob_clean();
  echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} finally {
  if (isset($mysqli) && $mysqli instanceof mysqli) { @$mysqli->close(); }
}
?>