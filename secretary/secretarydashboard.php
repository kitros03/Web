<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: ../index.php');
    exit;
}
header("Content-Type: text/html; charset=utf-8");
require_once("../dbconnect.php");
if ($_SESSION['role'] !== 'secretary') {
    header('Location: ../index.php');
    exit;
}
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
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <header>
        <div class="logo-title-row">
            <img src="../logo2.jpg" alt="Logo" class="logo" />
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
                    <?php if (!empty($announcements)): ?>
                        <h2>Ανακοινώσεις</h2>
                        <ul class="announcement-list">
                            <?php foreach ($announcements as $announcement):
                                $stmt = $pdo->prepare("SELECT thesisID, exam_datetime, exam_meeting_url, exam_room FROM thesis_exam_meta WHERE thesisID = ?");
                                $stmt->execute([$announcement['thesisID']]);
                                $thesis_meta = $stmt->fetch(PDO::FETCH_ASSOC);
                                $stmt = $pdo->prepare("SELECT s_fname, s_lname FROM student WHERE thesisID = ?");
                                $stmt->execute([$thesis_meta['thesisID']]);
                                $student = $stmt->fetch(PDO::FETCH_ASSOC);
                                $stmt = $pdo->prepare("SELECT title FROM thesis WHERE thesisID = ?");
                                $stmt->execute([$thesis_meta['thesisID']]);
                                $thesis = $stmt->fetch(PDO::FETCH_ASSOC);
                            ?>
                                <li class="announcement-item">
                                    <h3>Ανακοίνωση Παρουσίασης</h3>   
                                    <p><strong>Φοιτητής:</strong> <?= htmlspecialchars($student['s_fname'] . ' ' . $student['s_lname']) ?></p>
                                    <p><strong>Θέμα:</strong> <?= htmlspecialchars($thesis['title']) ?></p>
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
                </section>
        </main>
    </div>
    <script src="secretarydashboard.js"></script>
    <footer class="footer">
        <p>&copy; 2025 Thesis Management System</p>
    </footer>
</body>
</html>
