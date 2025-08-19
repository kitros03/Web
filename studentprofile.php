<?php
session_start();
header('Content_Type: application/json');
require_once '../dbconnect.php';

if(!isset($_SESSION['username']) || $_SESSION['role']!== 'student'){
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$username = $_SESSION['username'];

if($_SERVER['REQUEST_METHOD']=== 'GET'){
    $stmt = $pdo->prepare ("SELECT name, email, address, phone, mobile FROM student WHERE username = ?");
    $stmt->execute([$username]);
    $data = $stmt-> Fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'profile' =>$data]);

}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST'){
    $input = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo-> prepare("UPDATE student SET email=?, adddress=?, phone=?, mobile=? Where username=?");
    $stmt -> ececute([
        trim($input['email']),
        trim($input['adress']),
        trim($input['phone']),
        trim($input['mobile']),
        $username
    ]);
    echo json_encode(['success'=> true, 'message' => 'PROFILE UPDATED
    ']);
}
?>