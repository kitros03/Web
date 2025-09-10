<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'secretary') {
  http_response_code(403);
  echo json_encode(['success'=>false,'message'=>'Forbidden']); exit;
}
require_once __DIR__ . '/dbconnect.php'; // $pdo

try {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!is_array($data)) throw new Exception('Μη έγκυρο αίτημα.');

  $action   = trim((string)($data['action'] ?? ''));
  $thesisID = (int)($data['thesisID'] ?? 0);
  if ($thesisID <= 0) throw new Exception('Άκυρο thesisID.');

  // τρέχουσα κατάσταση
  $st = $pdo->prepare("SELECT th_status FROM thesis WHERE thesisID=? LIMIT 1");
  $st->execute([$thesisID]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) throw new Exception('Η διπλωματική δεν βρέθηκε.');
  if ($row['th_status'] !== 'ACTIVE') throw new Exception('Η ενέργεια επιτρέπεται μόνο για Ενεργές διπλωματικές.');

  if ($action === 'startExam') {
    // ➜ ΤΩΡΑ: ΜΟΝΟ καταχώριση GS Number, ΔΕΝ αλλάζουμε status
    $gs = trim((string)($data['gs_numb'] ?? ''));
    if ($gs === '') throw new Exception('Συμπλήρωσε GS Number.');

    // υπάρχει η στήλη gs_numb;
    $q = $pdo->prepare("
      SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'thesis' AND column_name = 'gs_numb'
    ");
    $q->execute();
    $hasGs = (bool)$q->fetchColumn();
    if (!$hasGs) {
      throw new Exception("Δεν υπάρχει στήλη 'gs_numb' στον πίνακα thesis. Τρέξε: ALTER TABLE thesis ADD COLUMN gs_numb VARCHAR(50) NULL;");
    }

    $u = $pdo->prepare("UPDATE thesis SET gs_numb=? WHERE thesisID=?");
    $u->execute([$gs, $thesisID]);

    echo json_encode(['success'=>true, 'message'=>'Καταχωρήθηκε το GS Number. Η κατάσταση παραμένει ACTIVE.']);
    exit;
  }

  if ($action === 'cancelThesis') {
    $gaNumber = trim((string)($data['gaNumber'] ?? ''));
    $gaDate   = trim((string)($data['gaDate'] ?? ''));
    $reason   = trim((string)($data['reason'] ?? '')) ?: 'από Διδάσκοντα';
    if ($gaNumber === '' || $gaDate === '') throw new Exception('Συμπλήρωσε GA Number και GA Date.');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $gaDate)) throw new Exception('Μη έγκυρη ημερομηνία.');

    $pdo->beginTransaction();
    $u = $pdo->prepare("UPDATE thesis SET th_status='CANCELLED' WHERE thesisID=?");
    $u->execute([$thesisID]);

    $c = $pdo->prepare("INSERT INTO cancelledThesis (thesisID, gaNumber, gaDate, reason) VALUES (?, ?, ?, ?)");
    $c->execute([$thesisID, $gaNumber, $gaDate, $reason]);

    $i = $pdo->prepare("INSERT INTO thesisStatusChanges (thesisID, changeDate, changeTo) VALUES (?, CURDATE(), 'CANCELLED')");
    $i->execute([$thesisID]);

    $pdo->commit();
    echo json_encode(['success'=>true,'message'=>'Η διπλωματική ακυρώθηκε.']);
    exit;
  }

  throw new Exception('Άγνωστη ενέργεια.');
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
