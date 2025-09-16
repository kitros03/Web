<?php 
session_start();
header("Content-Type: text/html; charset=utf-8");
require_once("dbconnect.php");

//gather announcemets if there are any
$announcements = [];
$announcement_query = "SELECT * FROM thesis_exam_meta where announce=1 ORDER BY updated_at DESC ";
$announcement_result = $pdo->query($announcement_query);
if ($announcement_result) {
    $announcements = $announcement_result->fetchAll(PDO::FETCH_ASSOC);
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Diploma Support System</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>
  <header>
    <div class="logo-title-row">
      <img src="logo2.jpg" alt="Logo" class="logo" />
      <h1 class="site-title">Thesis Support System</h1>   
      <button class="login-btn" onclick="location.href='login.html'">Login</button>
    </div>
         

  </header>
  <main class="dashboard-centered">
    <div class="centered-body">
      <?php if (!empty($announcements)): ?>
        <h2>Ανακοινώσεις</h2>
        <ul class="announcement-list">
          <?php foreach ($announcements as $announcement): ?>
            <<h3>Ανακοίνωση Παρουσίασης</h3>    
                    <p><strong>Ημερομηνία & Ώρα:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($thesis_meta['exam_datetime']))) ?></p>
                    <?php if ($thesis_meta['exam_meeting_url']): ?>
                        <p><strong>Σύνδεσμος Συνάντησης:</strong> <a href="<?= htmlspecialchars($thesis_meta['exam_meeting_url']) ?>" target="_blank"><?= htmlspecialchars($thesis_meta['exam_meeting_url']) ?></a></p>
                    <?php elseif ($thesis_meta['exam_room']): ?>
                        <p><strong>Αίθουσα:</strong> <?= htmlspecialchars($thesis_meta['exam_room']) ?></p>
                <?php endif; ?> 
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p>Δεν υπάρχουν ανακοινώσεις αυτή τη στιγμή.</p>
      <?php endif; ?>
    <script src="startscreen.js"></script>
    </div>
  </main>
  <footer>
    <p>&copy; 2025 Thesis Management System.</p>
</body>
</html>
