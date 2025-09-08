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
        foreach ($data['students'] as $s) {
            $studentID = intval($s['student_number']);
            $fname = $s['name'];
            $lname = $s['surname'];
            $street = $s['street'];
            $street_number = $s['number'];
            $city = $s['city'];
            $postcode = intval($s['postcode']);
            $father_name = $s['father_name'];
            $homephone = $s['landline_telephone'];
            $cellphone = $s['mobile_telephone'];
            $email = $studentID . "@upatras.gr";
            $username = strtolower($lname);

            $stmtUser = $conn->prepare("INSERT INTO users (username, pass, type)
                                        VALUES (?, ?, 'student')
                                        ON DUPLICATE KEY UPDATE pass=VALUES(pass), type='student'");
            $stmtUser->bind_param("ss", $username, $defaultPassword);
            $stmtUser->execute();

            $stmtStud = $conn->prepare("INSERT INTO student 
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
                    username=VALUES(username)");

            $stmtStud->bind_param("ssisssisssss",
                $fname, $lname, $studentID, $street, $street_number, $city, $postcode,
                $father_name, $homephone, $cellphone, $email, $username
            );
            $stmtStud->execute();
        }
    }

    if (isset($data['professors'])) {
        foreach ($data['professors'] as $t) {
            $id = intval($t['id']);
            $fname = $t['name'];
            $lname = $t['surname'];
            $email = $id . "@upatras.gr";
            $topic = $t['topic'];
            $homephone = $t['landline'];
            $cellphone = $t['mobile'];
            $department = $t['department'];
            $university = $t['university'];
            $username = strtolower($lname);

            $stmtUser = $conn->prepare("INSERT INTO users (username, pass, type)
                                        VALUES (?, ?, 'teacher')
                                        ON DUPLICATE KEY UPDATE pass=VALUES(pass), type='teacher'");
            $stmtUser->bind_param("ss", $username, $defaultPassword);
            $stmtUser->execute();

            $stmtTeach = $conn->prepare("INSERT INTO teacher 
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
                    username=VALUES(username)");

            $stmtTeach->bind_param("issssissss",
                $id, $fname, $lname, $email, $topic,
                $homephone, $cellphone, $department, $university, $username
            );
            $stmtTeach->execute();
        }
    }

    echo json_encode(["success" => true, "message" => "Επιτυχής εισαγωγή δεδομένων"]);
}
?>
