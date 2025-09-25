<?php
session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'secretary') {
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8" />
  <title>Secretary · Εισαγωγή Δεδομένων (JSON)</title>
  <link rel="stylesheet" href="../style.css" />
</head>
<body>
  <header>
    <div class="logo-title-row">
      <img src="../logo2.jpg" alt="Logo" class="logo" />
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
          <form action="json_import.php" method="post" enctype="multipart/form-data">
                    <label>Αρχείο JSON</label>
                    <input name="json_file" type="file" accept=".json" />
                    <button type="submit"> & Εισαγωγή</button>
                </form>
          
        </div>
    <!-- Εμφάνιση μηνύματος κατάστασης -->
            <?php
                if (isset($_SESSION['status'])) {
                    $failed = (strpos($_SESSION['status'], " 0 αποτυχίες") === false);
                    $statusClass = $failed ? 'status-box failed' : 'status-box';

                    echo '<div class="'.$statusClass.'">';
                    echo '<span class="status-title">'.($failed ? 'Υπήρξαν αποτυχίες :(' : 'Επιτυχής Εισαγωγή Δεδομένων :)').'</span><br><br>';
                    echo $_SESSION['status'];
                    echo '</div>';

                    unset($_SESSION['status']);
                }
            ?>
  </div>
  <script src="secretarydashboard.js"></script>
  <footer class="footer">
    <p>&copy; 2025 Thesis Management System</p>
  </footer>
</body>
</html>
