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

    $stmt = $pdo->prepare("SELECT username FROM users WHERE username=?");
    $stmt->execute([$username]);
   
    if($user = $stmt->fetch(PDO::FETCH_ASSOC)){
        echo json_encode(['success' => false, 'message' => 'Username already exists.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM teacher WHERE id=?
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
        $stmt = $pdo->prepare('INSERT INTO teacher (id, t_fname, t_lname, username) VALUES (?, ?, ?, ?)');
    }
    else if($role==='student'){
        $stmt = $pdo->prepare('INSERT INTO student (studentID, s_fname, s_lname, username) VALUES (?, ?, ?, ?)');
    }
    else {
        $stmt = $pdo->prepare('INSERT INTO secretary (secretaryID, secr_fname, secr_lname, username) VALUES (?, ?, ?, ?)');
    }
    $stmt1 = $pdo->prepare('INSERT INTO users (username, pass, type) VALUES (?, ?, ?)');

    if(($stmt1->execute([$username, $hashedPassword, $role])) && ($stmt->execute([$id, $fname, $lname, $username]))){
        echo json_encode(['success' => true, 'message' => 'User registered successfully!']);
        exit;
    }
    else{
        echo json_encode(['success' => false, 'message' => 'User could not be registered.']);
        exit;
    }
?>

