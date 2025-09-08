<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/dbconnect.php'; // ΠΡΕΠΕΙ να ορίζει $pdo (PDO)

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        echo json_encode(['success' => false, 'message' => 'Μη έγκυρο JSON.']);
        exit;
    }

    // ----- helpers -----
    $defaultPasswordHash = password_hash('1', PASSWORD_DEFAULT);

    $onlyDigits = function (?string $s): string {
        return preg_replace('/\D+/', '', (string)$s ?? '');
    };
    $takeOrNull = function ($v) {
        $v = trim((string)$v);
        return ($v === '' || strtoupper($v) === 'NULL' || $v === '-') ? null : $v;
    };
    // φτιάχνει ΜΟΝΑΔΙΚΟ username από επώνυμο (lowercase), προσθέτοντας 2,3,… αν υπάρχει σύγκρουση
    $uniqueUsernameFromSurname = function (PDO $pdo, string $surname): string {
        $base = strtolower(preg_replace('/\s+/', '', trim($surname)));
        if ($base === '') { $base = 'user'; }
        $u = $base;
        $i = 2;
        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE username = ? LIMIT 1");
        while (true) {
            $stmt->execute([$u]);
            if (!$stmt->fetchColumn()) return $u;
            $u = $base . $i;
            $i++;
        }
    };

    // μετρητές & σφάλματα για report
    $summary = [
        'students'  => ['inserted' => 0, 'updated' => 0, 'failed' => 0],
        'teachers'  => ['inserted' => 0, 'updated' => 0, 'failed' => 0],
    ];
    $errors = [];

    // ------------------------------------------------------------------------------------------------
    // ΦΟΙΤΗΤΕΣ (key: students)
    // ------------------------------------------------------------------------------------------------
    $students = $data['students'] ?? [];
    if (is_array($students)) {
        // prepared statements (reused)
        $stmtUserStudent = $pdo->prepare(
            "INSERT INTO users (username, pass, type)
             VALUES (?, ?, 'student')
             ON DUPLICATE KEY UPDATE pass=VALUES(pass), type='student'"
        );

        $stmtStudent = $pdo->prepare(
            "INSERT INTO student
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
               username=VALUES(username)"
        );

        foreach ($students as $idx => $s) {
            try {
                // required
                $studentNumber = (int)($s['student_number'] ?? 0);
                $fname = $takeOrNull($s['name'] ?? '');
                $lname = $takeOrNull($s['surname'] ?? '');
                if ($studentNumber <= 0 || !$fname || !$lname) {
                    throw new Exception("students[$idx]: λείπει/άκυρο student_number ή name/surname");
                }

                // διεύθυνση
                $street  = $takeOrNull($s['street'] ?? '');
                $number  = $takeOrNull($s['number'] ?? '');
                $numberDigits = $onlyDigits($number); // int στο schema
                $city    = $takeOrNull($s['city'] ?? '');
                $postcodeDigits = $onlyDigits($s['postcode'] ?? '');
                $postcode = ($postcodeDigits === '') ? null : (int)substr($postcodeDigits, 0, 5);

                // τηλέφωνα (varchar(10) στο student): κρατάμε τα ΤΕΛΕΥΤΑΙΑ 10 ψηφία
                $home = $onlyDigits($s['landline_telephone'] ?? '');
                $home = ($home === '') ? null : substr($home, -10);
                $cell = $onlyDigits($s['mobile_telephone'] ?? '');
                $cell = ($cell === '') ? null : substr($cell, -10);

                // username/email σύμφωνα με τις προδιαγραφές σου
                $username = $uniqueUsernameFromSurname($pdo, (string)$lname);
                $email    = $studentNumber . '@upatras.gr';

                // 1) users
                $stmtUserStudent->execute([$username, $defaultPasswordHash]);

                // 2) student
                $ok = $stmtStudent->execute([
                    $fname, $lname, $studentNumber, $street, ($numberDigits === '' ? null : (int)$numberDigits),
                    $city, $postcode, $takeOrNull($s['father_name'] ?? ''), $home, $cell, $email, $username
                ]);

                if (!$ok) {
                    $summary['students']['failed']++;
                    $errors[] = "students[$idx]: DB error";
                } else {
                    // affectedRows: 1 = insert, 2 = update (σε MySQL με ON DUP KEY UPDATE)
                    $count = $stmtStudent->rowCount();
                    if ($count === 1) $summary['students']['inserted']++;
                    else              $summary['students']['updated']++;
                }
            } catch (Throwable $e) {
                $summary['students']['failed']++;
                $errors[] = "students[$idx]: " . $e->getMessage();
            }
        }
    }

    // ------------------------------------------------------------------------------------------------
    // ΔΙΔΑΣΚΟΝΤΕΣ (key: professors)
    // ------------------------------------------------------------------------------------------------
    $professors = $data['professors'] ?? [];
    if (is_array($professors)) {
        $stmtUserTeacher = $pdo->prepare(
            "INSERT INTO users (username, pass, type)
             VALUES (?, ?, 'teacher')
             ON DUPLICATE KEY UPDATE pass=VALUES(pass), type='teacher'"
        );

        $stmtTeacher = $pdo->prepare(
            "INSERT INTO teacher
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
               username=VALUES(username)"
        );

        foreach ($professors as $idx => $t) {
            try {
                $id    = (int)($t['id'] ?? 0);
                $fname = $takeOrNull($t['name'] ?? '');
                $lname = $takeOrNull($t['surname'] ?? '');
                if ($id <= 0 || !$fname || !$lname) {
                    throw new Exception("professors[$idx]: λείπει/άκυρο id ή name/surname");
                }

                $topic       = $takeOrNull($t['topic'] ?? '');
                $department  = $takeOrNull($t['department'] ?? '');
                $university  = $takeOrNull($t['university'] ?? '');

                // στο schema σου: teacher.homephone INT(10), teacher.cellphone INT(10)
                // κρατάμε μόνο ψηφία, και τα περιορίζουμε σε 10 για ασφάλεια
                $homeDigits  = $onlyDigits($t['landline'] ?? '');
                $cellDigits  = $onlyDigits($t['mobile'] ?? '');
                $homephone   = ($homeDigits === '') ? null : (int)substr($homeDigits, -10);
                $cellphone   = ($cellDigits === '') ? null : (int)substr($cellDigits, -10);

                $username = $uniqueUsernameFromSurname($pdo, (string)$lname);
                // email όπως συμφωνήσαμε: id@upatras.gr
                $email    = $id . '@upatras.gr';

                // 1) users
                $stmtUserTeacher->execute([$username, $defaultPasswordHash]);

                // 2) teacher
                $ok = $stmtTeacher->execute([
                    $id, $fname, $lname, $email, $topic, $homephone, $cellphone, $department, $university, $username
                ]);

                if (!$ok) {
                    $summary['teachers']['failed']++;
                    $errors[] = "professors[$idx]: DB error";
                } else {
                    $count = $stmtTeacher->rowCount();
                    if ($count === 1) $summary['teachers']['inserted']++;
                    else              $summary['teachers']['updated']++;
                }
            } catch (Throwable $e) {
                $summary['teachers']['failed']++;
                $errors[] = "professors[$idx]: " . $e->getMessage();
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Η εισαγωγή ολοκληρώθηκε',
        'summary' => $summary,
        'errors'  => $errors
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}
