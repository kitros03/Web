<?php
require_once 'dbconnect.php'; // Ensure this connects to your database

// Fetch all users
$stmt = $pdo->query("SELECT username, pass FROM teacher");
$users = $stmt->fetchAll();

foreach ($users as $user) {
    $plainPassword = $user['pass'];
    $hashedPassword = password_hash(trim($plainPassword), PASSWORD_DEFAULT);

    // Update the password with the hash
    $update = $pdo->prepare("UPDATE teacher SET pass = ? WHERE username = ?");
    $update->execute([$hashedPassword, $user['username']]);
}

echo "Passwords have been hashed successfully.";
?>
