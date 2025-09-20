<?php
require_once 'dbconnect.php'; // Ensure this connects to your database

// Fetch all teachers
$stmt = $pdo->query("SELECT username, pass FROM teacher");
$users = $stmt->fetchAll();

foreach ($users as $teacher) {
    $plainPassword = $teacher['pass'];
    $hashedPassword = password_hash(trim($plainPassword), PASSWORD_DEFAULT);

    // Update the password with the hash
    $update = $pdo->prepare("UPDATE teacher SET pass = ? WHERE username = ?");
    $update->execute([$hashedPassword, $teacher['username']]);
}

// Fetch all students
$stmt = $pdo->query("SELECT username, pass FROM student");
$users = $stmt->fetchAll();

foreach ($users as $student) {
    $plainPassword = $student['pass'];
    $hashedPassword = password_hash(trim($plainPassword), PASSWORD_DEFAULT);

    // Update the password with the hash
    $update = $pdo->prepare("UPDATE student SET pass = ? WHERE username = ?");
    $update->execute([$hashedPassword, $student['username']]);
}

// Fetch all secretaries
$stmt = $pdo->query("SELECT username, pass FROM secretary");
$users = $stmt->fetchAll();

foreach ($users as $secretary) {
    $plainPassword = $secretary['pass'];
    $hashedPassword = password_hash(trim($plainPassword), PASSWORD_DEFAULT);

    // Update the password with the hash
    $update = $pdo->prepare("UPDATE secretary SET pass = ? WHERE username = ?");
    $update->execute([$hashedPassword, $secretary['username']]);
}

echo "Passwords have been hashed successfully.";
?>
