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
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="logo-title-row">
            <img src="logo2.jpg" alt="Logo" class="logo" />
            <h1 class="site-title">Secretary Dashboard</h1>
        </div>
    </header>
    <div class="dashboard-container">
        <aside class="sidebar">
            <nav>
                <ul>
                    <li><button class="sidebarButton" id="viewstatsBtn">Προβολή ΔΕ</button></li>
                    <li><button class="sidebarButton" id="secretaryInsertDataBtn">Εισαγωγή Δεδομένων</button></li>
                    <li><button class="sidebarButton" id="managethesesBtn">Διαχείριση Διπλωματικής Εργασίας</button></li>
                    <li><button class="sidebarButton" id="logoutBtn">Logout</button></li>
                </ul>
            </nav>
        </aside>
        <main class="dashboard-with-sidebar">
            <section class="announcements">
                <h2>Announcements</h2>
                <p>Your announcements go here...</p>
            </section>
        </main>
    </div>
    <script src="secretarydashboard.js"></script>
    <footer class="footer">
        <p>&copy; 2025 Thesis Management System</p>
    </footer>
</body>
</html>
