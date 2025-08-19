<?php
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
            <!-- Κρατάμε id με ίδιο naming pattern όπως στο teacher -->
            <li><button class="sidebarButton" id="thesisviewBtn">Προβολή Θέματος</button></li>
            <li><button class="sidebarButton" id="studentprofileBtn">Επεξεργασία Προφίλ</button></li>
            <li><button class="sidebarButton" id="managethesesBtn">Διαχείριση Διπλωματικής Εργασίας</button></li>
            <li><button class="sidebarButton" id="logoutBtn">Logout</button></li>
          </ul>
        </nav>
      </aside>

      <!-- Χρησιμοποιούμε ίδια κλάση wrapper για να μην αλλάξουμε CSS -->
      <main class="dashboard-with-sidebar">
        <!-- Κρατάμε την ίδια section "announcements" για συμβατότητα με το CSS σου -->
        <section class="announcements">
          <h2>Πίνακας Φοιτητή</h2>
          <p>Εδώ θα εμφανίζονται οι επιλογές και τα περιεχόμενα του φοιτητή.</p>



        </section>
      </main>
    </div>

    

    <footer class="footer">
      <p>&copy; 2025 Thesis Management System</p>
    </footer>
  </body>
</html>
