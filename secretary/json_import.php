<?php
declare(strict_types=1);
session_start();

ini_set('display_errors', '0');
ini_set('log_errors', '1');

/**
 * Έλεγχος πρόσβασης: μόνο secretary
 */
if (!isset($_SESSION['username']) || (($_SESSION['role'] ?? '') !== 'secretary')) {
  http_response_code(403);
  $_SESSION['status'] = 'Απαγορεύεται η πρόσβαση.';
  header("Location: secretaryinsertDataBtn.php");
  exit;
}


require_once __DIR__ . '/../dbconnect.php';

if (!isset($host, $db, $user, $pass)) {
  http_response_code(500);
  $_SESSION['status'] = 'Σφάλμα: Δεν βρέθηκαν στοιχεία σύνδεσης (host/db/user/pass) στο dbconnect.php.';
  header("Location: secretaryinsertDataBtn.php");
  exit;
}

$mysqli = @new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_errno) {
  http_response_code(500);
  $_SESSION['status'] = 'Σφάλμα σύνδεσης DB: ' . $mysqli->connect_error;
  header("Location: secretaryinsertDataBtn.php");
  exit;
}
@$mysqli->set_charset(isset($charset) ? $charset : 'utf8mb4');



// ---------------------- Φοιτητές ----------------------
function insert_students(array $students_info, mysqli $mysqli, array &$student_passwords = []): array {
    $success = 0; $fail = 0;

    foreach ($students_info as $stud) {
        $name                = $stud['name'] ?? null;
        $surname             = $stud['surname'] ?? null;
        $stud_number         = $stud['student_number'] ?? null;
        $street              = $stud['street'] ?? null;
        $number              = $stud['number'] ?? null;         
        $city                = $stud['city'] ?? null;
        $postcode            = $stud['postcode'] ?? null;
        $father_name         = $stud['father_name'] ?? null;
        $landline_telephone  = $stud['landline_telephone'] ?? null; 
        $mobile_telephone    = $stud['mobile_telephone'] ?? null;   
        $email               = $stud['email'] ?? null;              

        if (!$email || !$name || !$surname || !$stud_number) {
            $fail++;
            continue;
        }

        // Κωδικός για testing (σταθερός)
        $password_plain = '1';
        $password = password_hash(trim($password_plain), PASSWORD_DEFAULT);

        // Έλεγχος αν υπάρχει ήδη user με ίδιο username (email)
        $stmt = $mysqli->prepare("SELECT userID FROM users WHERE username = ?");
        $stmt->bind_param("s", $surname);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            $fail++;
            continue;
        }
        $stmt->close();

        // Εισαγωγή στον πίνακα users
        $stmt = $mysqli->prepare("INSERT INTO users (username, pass, type) VALUES (?, ?, 'student')");
        $stmt->bind_param("ss", $surname, $password);
        if ($stmt->execute()) {
            $stmt->close();

            // Εισαγωγή στον πίνακα student
            $sql = "INSERT INTO student
                (s_fname, s_lname, studentID, street, street_number, city, postcode, father_name,
                 homephone, cellphone, email, thesisID, username)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt2 = $mysqli->prepare($sql);

            $thesisID = null; // καμία ανάθεση κατά την εισαγωγή
            $stmt2->bind_param(
                "ssisisissssis",
                $name,
                $surname,
                $stud_number,
                $street,
                $number,
                $city,
                $postcode,
                $father_name,
                $landline_telephone,
                $mobile_telephone,
                $email,
                $thesisID,
                $surname // username = surname
            );

            if ($stmt2->execute()) {
                $success++;
                $student_passwords[$surname] = $password_plain;
            } else {
                $fail++;
            }
            $stmt2->close();
        } else {
            $fail++;
            $stmt->close();
        }
    }

    return [$success, $fail];
}

// ---------------------- Καθηγητές (teacher) ----------------------
function insert_professors(array $profs_info, mysqli $mysqli, array &$professor_passwords = []): array {
    $success = 0; $fail = 0;

    foreach ($profs_info as $prof) {
        $name        = $prof['name'] ?? null;       
        $surname     = $prof['surname'] ?? null;    
        $email       = $prof['email'] ?? null;      
        $topic       = $prof['topic'] ?? null;
        $landline    = $prof['landline'] ?? null;   
        $mobile      = $prof['mobile'] ?? null;
        $department  = $prof['department'] ?? null;
        $university  = $prof['university'] ?? null;

        if (!$email || !$name || !$surname) {
            $fail++;
            continue;
        }

        // Κωδικός για testing
        $password_plain = '0';
        $password = password_hash(trim($password_plain), PASSWORD_DEFAULT);

        // Έλεγχος αν υπάρχει ήδη user με ίδιο username
        $stmt = $mysqli->prepare("SELECT userID FROM users WHERE username = ?");
        $stmt->bind_param("s", $surname);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            $fail++;
            continue;
        }
        $stmt->close();

        // Εισαγωγή στον πίνακα users (type = 'teacher')
        $stmt = $mysqli->prepare("INSERT INTO users (username, pass, type) VALUES (?, ?, 'teacher')");
        $stmt->bind_param("ss", $surname, $password);
        if ($stmt->execute()) {
            $stmt->close();

            // Εισαγωγή στον πίνακα teacher (id = AI)
            $sql = "INSERT INTO teacher
                (t_fname, t_lname, email, topic, homephone, cellphone, department, university, username)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt2 = $mysqli->prepare($sql);
            $stmt2->bind_param(
                "ssssiisss",
                $name,
                $surname,
                $email,
                $topic,
                $landline,
                $mobile,
                $department,
                $university,
                $surname 
            );

            if ($stmt2->execute()) {
                $success++;
                $professor_passwords[$surname] = $password_plain;
            } else {
                $fail++;
            }
            $stmt2->close();
        } else {
            $fail++;
            $stmt->close();
        }
    }

    return [$success, $fail];
}

// ---------------------- Χειρισμός JSON upload ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['json_file'])) {
    $jsonData = @file_get_contents($_FILES['json_file']['tmp_name']);
    $data = json_decode($jsonData ?? '', true);

    // Έλεγχος δομής αρχείου
    if (!$data || !isset($data['students']) || !isset($data['professors'])) {
        $_SESSION['status'] = "Λάθος δομή αρχείου JSON! Απαιτούνται keys: 'students' και 'professors'.";
        header("Location: secretaryinsertDataBtn.php");
        exit;
    }

    $student_passwords = [];
    $professor_passwords = [];

    try {
        // Προαιρετικά, transactions για ασφάλεια μαζικής εισαγωγής
        $mysqli->begin_transaction();

        list($succ_st, $fail_st) = insert_students($data['students'], $mysqli, $student_passwords);
        list($succ_pr, $fail_pr) = insert_professors($data['professors'], $mysqli, $professor_passwords);

        $mysqli->commit();

        $_SESSION['status'] =
            "<b>Φοιτητές:</b> $succ_st επιτυχίες, $fail_st αποτυχίες" .
            "<br><b>Καθηγητές:</b> $succ_pr επιτυχίες, $fail_pr αποτυχίες.";
    } catch (Throwable $e) {
        @$mysqli->rollback();
        $_SESSION['status'] = "Σφάλμα κατά την εισαγωγή: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    header("Location: secretaryinsertDataBtn.php");
    exit;
}

// Αν έρθει GET ή δεν ανέβηκε αρχείο:
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['status'] = "Στείλε JSON αρχείο μέσω POST (field name: json_file).";
    header("Location: secretaryinsertDataBtn.php");
    exit;
}

// Τέλος: κλείσιμο σύνδεσης
if (isset($mysqli) && $mysqli instanceof mysqli) { @$mysqli->close(); }
