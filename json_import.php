<?php
header('Content-Type: application/json');
require_once 'dbconnect.php'; // προσαρμογή στο δικό σου αρχείο

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Invalid method']);
  exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
  echo json_encode(['success' => false, 'message' => 'Μη έγκυρο JSON']);
  exit;
}

$defaultPassword = password_hash("1", PASSWORD_DEFAULT);
$errors = [];
$summary = [
  'students' => ['inserted' => 0, 'updated' => 0, 'failed' => 0],
  'teachers' => ['inserted' => 0, 'updated' => 0, 'failed' => 0],
];

function onlyDigits($s) { return preg_replace('/\D+/', '', (string)$s); }
function takeOrNull($v) { $v = trim((string)$v); return $v === '' ? null : $v; }

// δημιουργεί μοναδικό username με βάση επώνυμο (lowercase) + αύξοντα αριθμό αν χρειαστεί
function uniqueUsernameFromSurname(mysqli $conn, string $surname): string {
  $base = strtolower(trim($surname));
  $base = preg_replace('/\s+/', '', $base);
  if ($base === '') $base = 'user';
  $u = $base;
  $i = 2;
  $stmt = $conn->prepare("SELECT 1 FROM users WHERE username=? LIMIT 1");
  while (true) {
    $stmt->bind_param('s', $u);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
      $stmt->free_result();
      return $u;
    }
    $stmt->free_result();
    $u = $base . $i;
    $i++;
  }
}

// === ΦΟΙΤΗΤΕΣ ===
if (isset($data['students']) && is_array($data['students'])) {
  foreach ($data['students'] as $idx => $s) {
    try {
      // Mapping & καθαρισμοί
      $studentNumber = intval($s['student_number'] ?? 0);
      if ($studentNumber <= 0) { throw new Exception("students[$idx]: Άκυρο student_number"); }

      $fname = takeOrNull($s['name'] ?? '');
      $lname = takeOrNull($s['surname'] ?? '');
      if (!$fname || !$lname) { throw new Exception("students[$idx]: Λείπει name/surname"); }

      $street = takeOrNull($s['street'] ?? '');
      $street_number = onlyDigits($s['number'] ?? '');
      $city = takeOrNull($s['city'] ?? '');
      // postcode int(5) στο schema – κρατάμε έως 5 ψηφία
      $postcode = substr(onlyDigits($s['postcode'] ?? ''), 0, 5);
      $postcode = ($postcode === '') ? null : intval($postcode);

      $father_name = takeOrNull($s['father_name'] ?? '');
      // τηλέφωνα: varchar(10) – κρατάμε τα τελευταία 10 ψηφία
      $homephone = onlyDigits($s['landline_telephone'] ?? '');
      $homephone = $homephone === '' ? null : substr($homephone, -10);
      $cellphone = onlyDigits($s['mobile_telephone'] ?? '');
      $cellphone = $cellphone === '' ? null : substr($cellphone, -10);

      // usernames από επώνυμο (unique)
      $username = uniqueUsernameFromSurname($conn, $lname);
      $email = $studentNumber . '@upatras.gr';

      // 1) users (insert/update)
      $stmt = $conn->prepare("INSERT INTO users (username, pass, type) VALUES (?, ?, 'student')
                              ON DUPLICATE KEY UPDATE pass=VALUES(pass), type='student'");
      $stmt->bind_param('ss', $username, $defaultPassword);
      if (!$stmt->execute()) { throw new Exception('users: ' . $stmt->error); }

      // 2) student (insert/update) – unique key: studentID
      $sql = "INSERT INTO student
              (s_fname, s_lname, studentID, street, street_number, city, postcode, father_name, homephone, cellphone, email, username)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE
                s_fname=VALUES(s_fname),
                s_lname=VALUES(s_lname),
                street=VALUES(street),
                street_number=VALUES(street_number),
                city=VALUES(city),
                postcode=VALUES(postcode),
                father_name=VALUES(father_name),
                homephone=VALUES(homephone),
                cellphone=VALUES(cellphone),
                email=VALUES(email),
                username=VALUES(username)";
      $stmt = $conn->prepare($sql);
      $postcodeParam = $postcode; // nullable int
      $stmt->bind_param(
        'ssisssisssss',
        $fname, $lname, $studentNumber, $street, $street_number, $city, $postcodeParam,
        $father_name, $homephone, $cellphone, $email, $username
      );

      if (!$stmt->execute()) {
        // unique conflict σε cellphone/email (unique στο schema) – απλώς μετράμε ως failed με μήνυμα
        $summary['students']['failed']++;
        $errors[] = "students[$idx]: " . $stmt->error;
      } else {
        if ($stmt->affected_rows === 1) { // νέα εισαγωγή
          $summary['students']['inserted']++;
        } else { // ενημέρωση
          $summary['students']['updated']++;
        }
      }
    } catch (Exception $e) {
      $summary['students']['failed']++;
      $errors[] = $e->getMessage();
    }
  }
}

// === ΔΙΔΑΣΚΟΝΤΕΣ ===
if (isset($data['professors']) && is_array($data['professors'])) {
  foreach ($data['professors'] as $idx => $t) {
    try {
      $id = intval($t['id'] ?? 0);
      if ($id <= 0) { throw new Exception("professors[$idx]: Άκυρο id"); }

      $fname = takeOrNull($t['name'] ?? '');
      $lname = takeOrNull($t['surname'] ?? '');
      if (!$fname || !$lname) { throw new Exception("professors[$idx]: Λείπει name/surname"); }

      $topic = takeOrNull($t['topic'] ?? '');
      $homephone = onlyDigits($t['landline'] ?? '');
      $homephone = $homephone === '' ? null : substr($homephone, -10);
      $cellphone = onlyDigits($t['mobile'] ?? '');
      $cellphone = $cellphone === '' ? null : substr($cellphone, -10);
      $department = takeOrNull($t['department'] ?? '');
      $university = takeOrNull($t['university'] ?? '');
      $username = uniqueUsernameFromSurname($conn, $lname);
      $email = $id . '@upatras.gr';

      // 1) users
      $stmt = $conn->prepare("INSERT INTO users (username, pass, type) VALUES (?, ?, 'teacher')
                              ON DUPLICATE KEY UPDATE pass=VALUES(pass), type='teacher'");
      $stmt->bind_param('ss', $username, $defaultPassword);
      if (!$stmt->execute()) { throw new Exception('users: ' . $stmt->error); }

      // 2) teacher (PK: id)
      $sql = "INSERT INTO teacher
              (id, t_fname, t_lname, email, topic, homephone, cellphone, department, university, username)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE
                t_fname=VALUES(t_fname),
                t_lname=VALUES(t_lname),
                email=VALUES(email),
                topic=VALUES(topic),
                homephone=VALUES(homephone),
                cellphone=VALUES(cellphone),
                department=VALUES(department),
                university=VALUES(university),
                username=VALUES(username)";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param('issssissss',
        $id, $fname, $lname, $email, $topic, $homephone, $cellphone, $department, $university, $username
      );

      if (!$stmt->execute()) {
        $summary['teachers']['failed']++;
        $errors[] = "professors[$idx]: " . $stmt->error;
      } else {
        if ($stmt->affected_rows === 1) {
          $summary['teachers']['inserted']++;
        } else {
          $summary['teachers']['updated']++;
        }
      }
    } catch (Exception $e) {
      $summary['teachers']['failed']++;
      $errors[] = $e->getMessage();
    }
  }
}

echo json_encode([
  'success' => true,
  'message' => 'Η εισαγωγή ολοκληρώθηκε',
  'summary' => $summary,
  'errors'  => $errors
]);
