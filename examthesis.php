<?php 
session_start();
header("Content-Type: text/html; charset=utf-8");

require_once("dbconnect.php");

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    header('Location: index.html');
    exit;
}

// Βρες το τρέχον id καθηγητή
$stmt = $pdo->prepare("SELECT id FROM teacher WHERE username = ?");
$stmt->execute([$_SESSION['username']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

// Λήψη thesisID από GET ή POST
$thesisID = $_GET['thesisID'] ?? $_POST['thesisID'] ?? null;
if (!$thesisID) {
    http_response_code(400);
    echo "Thesis ID is required";
    exit;
}

// Βρες στοιχεία εργασίας
$stmt = $pdo->prepare("SELECT * FROM thesis WHERE thesisID = ?");
$stmt->execute([$thesisID]);
$thesis = $stmt->fetch(PDO::FETCH_ASSOC);

// Βρες επιτροπή
$stmt = $pdo->prepare("SELECT supervisor, member1, member2 FROM committee WHERE thesisID = ?");
$stmt->execute([$thesisID]);
$committeeID = $stmt->fetch(PDO::FETCH_ASSOC);

// Βρες στοιχεία καθηγητών επιτροπής
$stmt = $pdo->prepare("SELECT t_fname, t_lname FROM teacher WHERE id = ?");
$stmt->execute([$committeeID['supervisor']]);
$supervisor = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare("SELECT t_fname, t_lname FROM teacher WHERE id = ?");
$stmt->execute([$committeeID['member1']]);
$member1 = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare("SELECT t_fname, t_lname FROM teacher WHERE id = ?");
$stmt->execute([$committeeID['member2']]);
$member2 = $stmt->fetch(PDO::FETCH_ASSOC);

// Ενέργεια ενεργοποίησης βαθμολόγησης
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'activateGrading' && isset($_POST['thesisID'])) {
        $stmt = $pdo->prepare("UPDATE thesis SET grading = 1 WHERE thesisID = ?");
        $stmt->execute([$_POST['thesisID']]);
        echo json_encode(['success' => true, 'message' => 'Grading activated.']);
        exit;
    }
    if ($_POST['action'] === 'submitGrades' && isset($_POST['thesisID'])) {
        $teacherID = $teacher['id'];
        $thesisID = $_POST['thesisID'];
        $quality_grade = $_POST['quality_grade'];
        $time_grade = $_POST['time_grade'];
        $rest_quality_grade = $_POST['rest_quality_grade'];
        $presentation_grade = $_POST['presentation_grade'];
        $calc_grade = $quality_grade * 0.6 + $time_grade * 0.15 + $rest_quality_grade * 0.15 + $presentation_grade * 0.1;
        $stmt = $pdo->prepare('INSERT INTO grades (thesisID, teacherID, quality_grade, time_grade, rest_quality_grade, presentation_grade, calc_grade) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$thesisID, $teacherID, $quality_grade, $time_grade, $rest_quality_grade, $presentation_grade, $calc_grade]);
        echo json_encode(['success' => true, 'message' => 'Η βαθμολογία μπήκε!']);
        exit;
    }
}

    // fetch grades if grading is active
    $grades = [];
    if ($thesis['grading']) {
        $stmt = $pdo->prepare("SELECT g.*, t.t_fname, t.t_lname FROM grades g JOIN teacher t ON g.teacherID = t.id WHERE g.thesisID = ?");
        $stmt->execute([$thesisID]);
        $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //fetch thesis meta
    $stmt = $pdo->prepare("SELECT * FROM thesis_exam_meta WHERE thesisID = ?");
    $stmt->execute([$thesisID]);
    $thesis_meta = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="style.css">
    <title>Υπό Εξέταση</title>
</head>
<body>
<header>
    <div class="logo-title-row">
        <button class="back-btn" id="backBtn">
            <img src="logo2.jpg" alt="Logo" class="logo" />
        </button>
        <h1 class="site-title">Υπό Εξέταση</h1>
    </div>
</header>
<main class="dashboard-centered">
    <div class="centered-body">
        <button class="submit-btn" id="textbtn">Προβολή Κειμένου</button>
        <div id="popup1" class="popup-window">
            <div class="popup-content">
                <h3>Πρόχειρο Κείμενο Εργασίας</h3>
                <?= htmlspecialchars($thesis_meta['draft_text'] ?? 'Δεν έχει προστεθεί πρόχειρο κείμενο.') ?>
                <button id="closePopupBtn1" class="close-popup-btn" aria-label="Close">&times;</button>
            </div>
        </div>
    </div>
    <div class="centered-body">
        <button class="submit-btn" id="presentationbtn">Παρουσίαση</button>
        <div id="popup2" class="popup-window">
            <div class="popup-content">
                <button id="closePopupBtn2" class="close-popup-btn" aria-label="Close">&times;</button>
                <!-- Εδώ η παρουσίαση -->
            </div>
        </div>
    </div>
    <div class="centered-body">
        <button class="submit-btn" id="gradebtn">Βαθμολογία</button>
        <div id="popup3" class="popup-window">
            <div class="popup-content">
                <h3>Βαθμολογία</h3>
                <?php if($teacher['id']===$committeeID['supervisor'] && !$thesis['grading']):?>
                    <form id="activateGradingForm" method="POST">
                        <input type="hidden" name="thesisID" value="<?= htmlspecialchars($thesisID) ?>">
                        <button type="submit" class="submit-btn">Ενεργοποίηση Βαθμολόγησης</button>
                    </form>
                <?php elseif(!$thesis['grading']):?>
                    <p> Ο επιβλέπων καθηγητής δεν έχει ενεργοποιήσει την βαθμολόγηση</p>
                <?php endif; ?>
                <?php if($thesis['grading']):?>
                    <?php if (isset($grades) && array_search($teacher['id'], array_column($grades, 'teacherID')) === false): ?>
                    <form id="gradeForm">
                        <input type="hidden" name="thesisID" value="<?= htmlspecialchars($thesisID) ?>">
                        <div class="form-group">
                            <label for="quality-grade">Βαθμός Ποιότητας Δ.Ε.</label>
                            <input type="number" id="quality-grade" name="quality_grade" min="0" max="10" step="0.1" required>
                            <label for="time-grade">Βαθμός Χρονικού Διαστήματος</label>
                            <input type="number" id="time-grade" name="time_grade" min="0" max="10" step="0.1" required>
                            <label for="rest-quality-grade">Βαθμός Ποιότητας και Πληρότητας υπόλοιπων παραδοτέων</label>
                            <input type="number" id="rest-quality-grade" name="rest_quality_grade" min="0" max="10" step="0.1" required>
                            <label for="presentation-grade">Βαθμός Παρουσίασης</label>
                            <input type="number" id="presentation-grade" name="presentation_grade" min="0" max="10" step="0.1" required>
                        </div>
                        <button type="submit" class="submit-btn">Υποβολή Βαθμολογίας</button>
                    </form>
                    <?php else: ?>
                        <p>Έχετε ήδη υποβάλει τη βαθμολογία σας για αυτή την εργασία.</p>
                    <?php endif; ?>
                    <table class="table grades-table">
                        <thead>
                            <tr>
                                <th>Καθηγητής</th>
                                <th>Βαθμός Ποιότητας Δ.Ε.</th>
                                <th>Βαθμός Χρονικού Διαστήματος</th>
                                <th>Βαθμός Ποιότητας και Πληρότητας υπόλοιπων παραδοτέων</th>
                                <th>Βαθμός Παρουσίασης</th>
                                <th>Μέσος Όρος</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($grades as $grade): ?>
                                <tr>
                                    <td><?= htmlspecialchars($grade['t_fname'] . ' ' . $grade['t_lname']) ?></td>
                                    <td><?= htmlspecialchars($grade['quality_grade']) ?></td>
                                    <td><?= htmlspecialchars($grade['time_grade']) ?></td>
                                    <td><?= htmlspecialchars($grade['rest_quality_grade']) ?></td>
                                    <td><?= htmlspecialchars($grade['presentation_grade']) ?></td>
                                    <td><?= htmlspecialchars($grade['calc_grade']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <button id="closePopupBtn3" class="close-popup-btn" aria-label="Close">&times;</button>
            </div>
        </div>
    </div>
    <script src="examthesis.js"> </script>
</main>
<footer>
    <p class="footer">© 2025 Thesis Management System</p>
</footer>
</body>
</html>
