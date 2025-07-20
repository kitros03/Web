<?php
session_start();
    require_once 'dbconnect.php';
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $fname = trim($data['fname']);
    $lname = trim($data['lname']);
    $id = trim($data['id']);
    $username = trim($data['username']);
    $password = trim($data['pass']);
    $r_pass = trim($data['r_pass']);
    $role = $data['role'];

    if($password != $r_pass){
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }

    $hashedPassword = password_hash(trim($password), PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("SELECT username FROM teacher WHERE username=?
    UNION
    SELECT username FROM student WHERE username=?
    UNION
    SELECT username FROM secretary WHERE username=?
    ");
    $stmt->execute([$username, $username, $username]);
   
    if($user = $stmt->fetch(PDO::FETCH_ASSOC)){
        echo json_encode(['success' => false, 'message' => 'Username already exists.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT teacherID FROM teacher WHERE teacherID=?
    UNION
    SELECT studentID FROM student WHERE studentID=?
    UNION
    SELECT secretaryID FROM secretary WHERE secretaryID=?
    ");
    
    $stmt->execute([$id, $id, $id]);

    if($aID = $stmt->fetch(PDO::FETCH_ASSOC)){
        echo json_encode(['success' => false, 'message' => 'Academic ID already exists.']);
        exit;
    }


    if($role==='teacher'){
        $stmt = $pdo->prepare('INSERT INTO teacher (teacherID, t_fname, t_lname, username, pass) VALUES (?, ?, ?, ?, ?)');
    }
    else if($role==='student'){
        $stmt = $pdo->prepare('INSERT INTO student (studentID, s_fname, s_lname, username, pass) VALUES (?, ?, ?, ?, ?)');
    }
    else {
        $stmt = $pdo->prepare('INSERT INTO secretary (secretaryID, secr_fname, secr_lname, username, pass) VALUES (?, ?, ?, ?, ?)');
    }

    if($stmt->execute([$id, $fname, $lname, $username, $hashedPassword])){
        echo json_encode(['success' => true, 'message' => 'User registered successfully!']);
        exit;
    }
    else{
        echo json_encode(['success' => false, 'message' => 'User could not be registered.']);
        exit;
    }
?>

