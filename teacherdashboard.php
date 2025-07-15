<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: index.html');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Dashboard</title>
        <link rel="stylesheet" href="css/teacherdashboard.css">
    </head>
    <body>
        <div id="div">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
            <p>This is your teacher dashboard.</p>
            <ul>
                <li><button id="thesiscreationBtn">View and Create Thesis</button></li>
                <li><button id="studentassignmentBtn">Assign Thesis to a student</button></li>
                <li><button id="committeeinvitationsBtn">View incoming committee invitations</button></li>
                <li><button id="viewstatsBtn">View Statistics</button></li>
                <li><button id="managethesesBtn">Manage your Theses</button></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        <script src="teacherdashboard.js"></script>
    </body>
</html>
