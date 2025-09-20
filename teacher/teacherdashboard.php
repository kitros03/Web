<?php
session_start();
require_once("../dbconnect.php");

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    header('Location: index.html');
    exit;
}

// Συλλογή ανακοινώσεων — θα χρησιμοποιηθεί στο AJAX request, όχι κατά το load της σελίδας
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8" />
    <title>Teacher Dashboard</title>
    <link rel="stylesheet" href="../style.css" />
</head>
<body>
    <header>
        <div class="logo-title-row">
            <img src="../logo2.jpg" alt="Logo" class="logo" />
            <h1 class="site-title">Teacher Dashboard</h1>
        </div>
    </header>
    <div class="dashboard-container">
        <aside class="sidebar">
            <nav>
                <ul>
                    <li><button class="sidebarButton" id="thesiscreationBtn">Thesis Creation</button></li>
                    <li><button class="sidebarButton" id="studentassignmentBtn">Student Assignment</button></li>
                    <li><button class="sidebarButton" id="committeeinvitationsBtn">Committee Invitations</button></li>
                    <li><button class="sidebarButton" id="viewstatsBtn">View Stats</button></li>
                    <li><button class="sidebarButton" id="managethesesBtn">Manage Theses</button></li>
                    <li><button class="sidebarButton" id="logoutBtn">Logout</button></li>
                </ul>
            </nav>
        </aside>
        <main class="dashboard-with-sidebar">
            <section class="announcements" id="announcementsSection">
                <p>Φόρτωση ανακοινώσεων...</p>
            </section>
        </main>
    </div>

    <script src="teacherdashboard.js"></script>
    <footer class="footer">
        <p>&copy; 2025 Thesis Management System</p>
    </footer>
</body>
</html>
