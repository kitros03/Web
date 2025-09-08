<?php
session_start();
header('Content-Type: application/json');

require_once 'dbconnect.php';

$data = json_decode(file_get_contents('php://input'), true);
$username = trim($data['username']);
$password = trim($data['password']);
$role = $data['role'];

if(empty($role)){
    echo json_encode(['success' => false, 'message' => 'Role is required.']);
    exit;
}

$stmt = $pdo->prepare("SELECT username, pass FROM users WHERE username = ?");


$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user){
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit;
    }
else if (!password_verify($password, $user['pass'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid password.']);
    exit;
} else {
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $role;
    if ($role === 'teacher') {
        echo json_encode(['success' => true, 'dashboard' => 'teacherdashboard.php']);
    } elseif ($role === 'student') {
        echo json_encode(['success' => true, 'dashboard' => 'studentdashboard.php']);
    } else {
        echo json_encode(['success' => true, 'dashboard' => 'secretarydashboard.php']);
    }
    exit;
}


?>
