<?php
session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['type']) || $_SESSION['type'] !== 'secretary') {
    header('Location: index.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8" />
  <title>Secretary · Εισαγωγή Δεδομένων (JSON)</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>
  <header>
    <div class="logo-title-row">
      <img src="logo2.jpg" alt="Logo" class="logo" />
      <h1 class="site-title">Secretary Dashboard</h1>
    </div>
  </header>

  <div class="dashboard-container" id="dashboard-container">
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
        <h2>Εισαγωγή δεδομένων από JSON</h2>
        <p>Επιλέξτε αρχείο JSON με στοιχεία φοιτητών και διδασκόντων για εισαγωγή στη βάση.</p>
      </section>

      <section>
        <div class="form-group">
          <label for="jsonFile">Αρχείο JSON</label>
          <input id="jsonFile" type="file" accept=".json" />
        </div>

        <div class="form-group">
          <button id="uploadBtn" class="submit-btn">Ανέβασμα & Εισαγωγή</button>
        </div>

        <div id="result" class="centered-body" style="display:none;"></div>
      </section>
    </main>
  </div>

  <script src="secretarydashboard.js"></script>
  <script src="secretary_import.js"></script>
  <footer class="footer">
    <p>&copy; 2025 Thesis Management System</p>
  </footer>
</body>
</html>
