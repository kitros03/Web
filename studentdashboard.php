<?php
declare(strict_types=1);
session_start();
if (!isset($_SESSION['username'])) {
  header('Location: index.html');
  exit;
}
?>
<!DOCTYPE html>
<html lang="el">
  <head>
    <meta charset="UTF-8">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="style.css">
  </head>
  <body>
    <header>
      <div class="logo-title-row">
        <img src="logo2.jpg" alt="Logo" class="logo" />
        <h1 class="site-title">Student Dashboard</h1>
      </div>
    </header>

    <div class="dashboard-container">
      <aside class="sidebar">
        <nav>
          <ul>
            <li><button class="sidebarButton" id="thesisviewBtn">Προβολή Θέματος</button></li>
            <li><button class="sidebarButton" id="studentprofileBtn">Επεξεργασία Προφίλ</button></li>
            <li><button class="sidebarButton" id="managethesesBtn">Διαχείριση Διπλωματικής Εργασίας</button></li>
            <li><button class="sidebarButton" id="logoutBtn">Logout</button></li>
          </ul>
        </nav>
      </aside>

      <main class="dashboard-with-sidebar">
        <section class="announcements">
          <h2>Πίνακας Φοιτητή</h2>
          <p>Εδώ θα εμφανίζονται οι επιλογές και τα περιεχόμενα του φοιτητή.</p>
          <!-- Χωρίς include — το περιεχόμενο θα φορτωθεί δυναμικά -->
        </section>
      </main>
    </div>

    <script src="studentdashboard.js"></script>
    <footer class="footer">
      <p>&copy; 2025 Thesis Management System</p>
    </footer>
  </body>
</html>
