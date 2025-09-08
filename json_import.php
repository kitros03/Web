<?php
header("Content-Type: application/json");
include "db_connect.php"; 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents("php://input");
    $data = json_decode($json, true);

    if (!$data) {
        echo json_encode(["success" => false, "message" => "Μη έγκυρο JSON"]);
        exit;
    }

    $defaultPassword = password_hash("1", PASSWORD_DEFAULT);

    if (isset($data['students'])) {
        $stmt = $conn->prepare("INSERT INTO student 
            (studentID, s_fname, s_lname, address, email, cellphone, homephone, currentects, currentNPclasses, username, pass)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                s_fname=VALUES(s_fname),
                s_lname=VALUES(s_lname),
                address=VALUES(address),
                email=VALUES(email),
                cellphone=VALUES(cellphone),
                homephone=VALUES(homephone),
                username=VALUES(username),
                pass=VALUES(pass)");

        foreach ($data['students'] as $s) {
            $studentID = intval($s['student_number']);
            $fname = $s['name'];
            $lname = $s['surname'];
            $address = $s['street'] . " " . $s['number'] . ", " . $s['city'] . ", " . $s['postcode'];
            $email = $s['student_number'] . "@upatras.gr";
            $cellphone = $s['mobile_telephone'];
            $homephone = $s['landline_telephone'];
            $ects = 0;
            $npclasses = 0;
            $username = strtolower($lname); 
            $pass = $defaultPassword;

            $stmt->bind_param("issssssiiis", 
                $studentID, $fname, $lname, $address, $email, 
                $cellphone, $homephone, $ects, $npclasses, 
                $username, $pass
            );
            $stmt->execute();
        }
    }

    if (isset($data['professors'])) {
        $stmt = $conn->prepare("INSERT INTO teacher 
            (teacherID, t_fname, t_lname, email, username, pass)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                t_fname=VALUES(t_fname),
                t_lname=VALUES(t_lname),
                email=VALUES(email),
                username=VALUES(username),
                pass=VALUES(pass)");

        foreach ($data['professors'] as $t) {
            $teacherID = intval($t['id']);
            $fname = $t['name'];
            $lname = $t['surname'];
            $email = $t['id'] . "@upatras.gr"; 
            $username = strtolower($lname);
            $pass = $defaultPassword;

            $stmt->bind_param("isssss", 
                $teacherID, $fname, $lname, $email, 
                $username, $pass
            );
            $stmt->execute();
        }
    }

    echo json_encode(["success" => true, "message" => "Επιτυχής εισαγωγή δεδομένων"]);
}
?>
