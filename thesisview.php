<?php
declare(strict_types=1);
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'student') {
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
            <li><button class="sidebarButton" id="profileBtn">Επεξεργασία Προφίλ</button></li>
            <li><button class="sidebarButton" id="managethesesBtn">Διαχείριση Διπλωματικής Εργασίας</button></li>
            <li><button class="sidebarButton" id="logoutBtn">Logout</button></li>
          </ul>
        </nav>
      </aside>

      <main class="dashboard-with-sidebar">
        <section class="announcements">
          <h2>Προβολή Θέματος</h2>
          <!-- Το JavaScript θα γεμίσει αυτό το div με τα στοιχεία -->
          <div id="thesis-details">
            <p class="muted">Φόρτωση στοιχείων πτυχιακής...</p>
          </div>
        </section>
      </main>
    </div>

    <script src="studentdashboard.js"></script>
    <!-- Βάλε εδώ το JS που έστειλες (ή αποθήκευσέ το ως thesisview.js και κάνε include) -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
      fetch('thesis_details.php')
        .then(response => response.json())
        .then(data => {
          const el = document.getElementById('thesis-details');
          if (!data || data.success === false) {
            el.innerHTML = '<section class="card"><p class="muted">' + (data.message || 'Δεν βρέθηκαν στοιχεία.') + '</p></section>';
            return;
          }
          el.innerHTML = `
            <ul>
              <li><strong>Θέμα:</strong> ${data.title ?? '—'}</li>
              <li><strong>Περιγραφή:</strong> ${data.desc ?? '—'}</li>
              <li><strong>Συνημμένο αρχείο:</strong>
                ${data.file ? `<a href="${data.file}" target="_blank" rel="noopener">Προβολή ή Λήψη</a>` : '—'}
              </li>
              <li><strong>Κατάσταση:</strong> ${data.status ?? '—'}</li>
              <li><strong>Επιτροπή:</strong>
                <ul>
                  ${(data.committee || []).map(m => `<li>${m}</li>`).join('')}
                </ul>
              </li>
              <li><strong>Χρόνος από ανάθεση:</strong> ${data.days_since_assignment ?? '—'}</li>
            </ul>
          `;
        })
        .catch(() => {
          document.getElementById('thesis-details').innerHTML =
            '<section class="card"><p class="muted">Σφάλμα φόρτωσης.</p></section>';
        });
    });
    </script>

    <footer class="footer">
      <p>&copy; 2025 Thesis Management System</p>
    </footer>
  </body>
</html>
