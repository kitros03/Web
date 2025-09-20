<?php
declare(strict_types=1);
session_start();

// Κρατάμε καθαρή την έξοδο
ob_start();
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Έλεγχος ρόλου
if (!isset($_SESSION['username']) || (($_SESSION['role'] ?? '') !== 'secretary')) {
  http_response_code(403);
  ob_clean();
  echo json_encode(['success' => false, 'message' => 'Forbidden'], JSON_UNESCAPED_UNICODE);
  exit;
}

// Φέρνουμε credentials από το υπάρχον dbconnect.php (ΔΕΝ το αλλάζουμε)
require_once __DIR__ . '/../dbconnect.php';

// Άνοιγμα ΔΙΚΗΣ μας mysqli σύνδεσης με τα ίδια credentials (όχι PDO)
if (!isset($host, $db, $user, $pass)) {
  http_response_code(500);
  ob_clean();
  echo json_encode(['success' => false, 'message' => 'DB credentials not found in dbconnect.php'], JSON_UNESCAPED_UNICODE);
  exit;
}

$mysqli = @new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_errno) {
  http_response_code(500);
  ob_clean();
  echo json_encode(['success' => false, 'message' => 'DB connection failed', 'details' => $mysqli->connect_error], JSON_UNESCAPED_UNICODE);
  exit;
}
@$mysqli->set_charset(isset($charset) ? $charset : 'utf8mb4');

// Helpers
function select_one_assoc(mysqli $mysqli, string $sql, array $params, string $types): ?array {
  $stmt = $mysqli->prepare($sql);
  if (!$stmt) throw new RuntimeException('Prepare failed: ' . $mysqli->error);
  if ($types !== '' && !empty($params)) {
    $stmt->bind_param($types, ...$params);
  }
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

function exec_stmt(mysqli $mysqli, string $sql, array $params, string $types): void {
  $stmt = $mysqli->prepare($sql);
  if (!$stmt) throw new RuntimeException('Prepare failed: ' . $mysqli->error);
  if ($types !== '' && !empty($params)) {
    $stmt->bind_param($types, ...$params);
  }
  if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    throw new RuntimeException('Execute failed: ' . $err);
  }
  $stmt->close();
}

try {
  // Διαβάζουμε JSON σώμα
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!is_array($data)) throw new Exception('Μη έγκυρο αίτημα.');

  $action   = trim((string)($data['action'] ?? ''));
  $thesisID = (int)($data['thesisID'] ?? 0);
  if ($thesisID <= 0) throw new Exception('Άκυρο thesisID.');

  // Βεβαιώσου ότι η διπλωματική υπάρχει και είναι ACTIVE
  $row = select_one_assoc($mysqli, "SELECT th_status FROM thesis WHERE thesisID=? LIMIT 1", [$thesisID], 'i');
  if (!$row) throw new Exception('Η διπλωματική δεν βρέθηκε.');
  if ($row['th_status'] !== 'ACTIVE') throw new Exception('Η ενέργεια επιτρέπεται μόνο για Ενεργές διπλωματικές.');

  if ($action === 'startExam') {
    // Καταχώρηση GS Number — ΔΕΝ αλλάζει status
    $gs = trim((string)($data['gs_numb'] ?? ''));
    if ($gs === '') throw new Exception('Συμπλήρωσε GS Number.');
    if (!ctype_digit($gs)) throw new Exception('Το GS πρέπει να είναι ακέραιος αριθμός.');

    exec_stmt($mysqli, "UPDATE thesis SET gs_numb=? WHERE thesisID=?", [(int)$gs, $thesisID], 'ii');

    ob_clean();
    echo json_encode(['success' => true, 'message' => 'Καταχωρήθηκε το GS Number. Η κατάσταση παραμένει ACTIVE.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($action === 'cancelThesis') {
    // Ακύρωση διπλωματικής με GA Number/Date/Reason
    $gaNumber = trim((string)($data['gaNumber'] ?? ''));
    $gaDate   = trim((string)($data['gaDate'] ?? ''));
    $reason   = trim((string)($data['reason'] ?? ''));
    if ($reason === '') $reason = 'από Διδάσκοντα';

    if ($gaNumber === '' || $gaDate === '') throw new Exception('Συμπλήρωσε GA Number και GA Date.');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $gaDate)) throw new Exception('Μη έγκυρη ημερομηνία (YYYY-MM-DD).');

    $mysqli->begin_transaction();
    try {
      exec_stmt($mysqli, "UPDATE thesis SET th_status='CANCELLED' WHERE thesisID=?", [$thesisID], 'i');
      exec_stmt($mysqli, "INSERT INTO cancelledThesis (thesisID, gaNumber, gaDate, reason) VALUES (?, ?, ?, ?)",
        [$thesisID, $gaNumber, $gaDate, $reason], 'isss');
      exec_stmt($mysqli, "INSERT INTO thesisStatusChanges (thesisID, changeDate, changeTo) VALUES (?, CURDATE(), 'CANCELLED')",
        [$thesisID], 'i');

      $mysqli->commit();
    } catch (Throwable $inner) {
      $mysqli->rollback();
      throw $inner;
    }

    ob_clean();
    echo json_encode(['success' => true, 'message' => 'Η διπλωματική ακυρώθηκε.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  throw new Exception('Άγνωστη ενέργεια.');
} catch (Throwable $e) {
  if ($mysqli instanceof mysqli && $mysqli->errno) {
    // best effort
  }
  http_response_code(200);
  ob_clean();
  echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} finally {
  if (isset($mysqli) && $mysqli instanceof mysqli) { @$mysqli->close(); }
}
