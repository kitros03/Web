<?php 
session_start();
header("Content-Type: text/html; charset=utf-8");

require_once("dbconnect.php");

if(!isset($_SESSION['username'])){
    header("Location: login.php");
    exit();
}

// Get current teacher id
$stmt = $pdo->prepare("SELECT id FROM teacher WHERE username = ?");
$stmt->execute([$_SESSION['username']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

//get thesis id from url
$thesisID = isset($_GET['thesisID']) ? $_GET['thesisID'] : null;
if (!$thesisID) {
    http_response_code(400);
    echo "Thesis ID is required";
    exit;
}

// Fetch thesis details
$stmt = $pdo->prepare("SELECT * FROM thesis WHERE thesisID = ?");
$stmt->execute([$thesisID]);
$thesis = $stmt->fetch(PDO::FETCH_ASSOC);

// get the committee members
$stmt = $pdo->prepare("SELECT supervisor, member1, member2 FROM committee WHERE thesisID = ?");
$stmt->execute([$thesisID]);
$committeeID = $stmt->fetch(PDO::FETCH_ASSOC);

// get the names of committee members
$stmt = $pdo->prepare("SELECT t_fname, t_lname FROM teacher WHERE id = ?");
$stmt->execute([$committeeID['supervisor']]);
$supervisor = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare("SELECT t_fname, t_lname FROM teacher WHERE id = ?");
$stmt->execute([$committeeID['member1']]);
$member1 = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare("SELECT t_fname, t_lname FROM teacher WHERE id = ?");
$stmt->execute([$committeeID['member2']]);
$member2 = $stmt->fetch(PDO::FETCH_ASSOC);

// get thesis details
$stmt = $pdo->prepare("SELECT * FROM thesis WHERE thesisID = ?");
$stmt->execute([$thesisID]);
$thesis = $stmt->fetch(PDO::FETCH_ASSOC);


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
        <button class="back-btn" id="backBtn">
            <img src="logo2.jpg" alt="Logo" class="logo" />
        </button>
        <h1 class="site-title">Υπό Εξέταση</h1>
    </div>
</header>
<main class="dashboard-main">
    <div class="dashboard-container">

  <button class="btn" onclick="showPopup('popup1')">Προβολή Κειμένου</button>
  <button class="btn" onclick="showPopup('popup2')">Παρουσίαση</button>
  <button class="btn" onclick="showPopup('popup3')">Βαθμολογία</button>
  
  <div id="overlay" class="overlay" onclick="closeAllPopups()"></div>

  <div id="popup1" class="popup">
    <h3>Προβολή Κειμένου</h3>
    <button class="close-btn" onclick="closeAllPopups()">Κλείσιμο</button>
  </div>
  <div id="popup2" class="popup">
    <h3>Παρουσίαση</h3>
    <button class="close-btn" onclick="closeAllPopups()">Κλείσιμο</button>
  </div>
  <div id="popup3" class="popup">
    <h3>Βαθμολογία</h3>
    <?php if($teacher['id']===$supervisor['supervisor'] && !$thesis['grading']):?>
        <p> Ενεργοποιήστε την βαθμολόγηση</p>
        <button class="btn">Ενεργοποίηση Βαθμολόγησης</button>
    <?php elseif(!$thesis['grading']):?>
        <p> Ο επιβλέπων καθηγητής δεν έχει ενεργοποιήσει την βαθμολόγηση</p>
    <?php endif; ?>
    <?php if($thesis['grading']):?>
        <form id="gradeForm">
            <div class="form-group">
                <label for="quality-grade">Βαθμός Ποιότητας Δ.Ε.</label>
                <input type="number" id="quality-grade" name="quality-grade" min="0" max="10" step="0.1" required>
                <label for="time-grade">Βαθμός Χρονικού Διαστήματος</label>
                <input type="number" id="time-grade" name="time-grade" min="0" max="10" step="0.1" required>
                <label for="rest-quality-grade">Βαθμός Ποιότητας και Πληρότητας υπόλοιπων παραδοτέων</label>
                <input type="number" id="rest-quality-grade" name="rest-quality-grade" min="0" max="10" step="0.1" required>
                <label for="presentation-grade">Βαθμός Παρουσίασης</label>
                <input type="number" id="presentation-grade" name="presentation-grade" min="0" max="10" step="0.1" required>
            </div>
            <button type="submit" class="submit-btn">Υποβολή Βαθμολογίας</button>
        </form>
        <table class="grades-table">
            <thead>
                <tr>
                    <th>Καθηγητής</th>
                    <th>Βαθμός Ποιότητας Δ.Ε.</th>
                    <th>Βαθμός Χρονικού Διαστήματος</th>
                    <th>Βαθμός Ποιότητας και Πληρότητας υπόλοιπων παραδοτέων</th>
                    <th>Βαθμός Παρουσίασης</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $pdo->prepare("SELECT g.*, t.t_fname, t.t_lname FROM grades g JOIN teacher t ON g.teacherID = t.id WHERE g.thesisID = ?");
                $stmt->execute([$thesisID]);
                $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($grades as $grade): ?>
                    <tr>
                        <td><?= htmlspecialchars($grade['t_fname'] . ' ' . $grade['t_lname']) ?></td>
                        <td><?= htmlspecialchars($grade['quality_grade']) ?></td>
                        <td><?= htmlspecialchars($grade['time_grade']) ?></td>
                        <td><?= htmlspecialchars($grade['rest_quality_grade']) ?></td>
                        <td><?= htmlspecialchars($grade['presentation_grade']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
    <?php endif; ?>
    <button class="close-btn" onclick="closeAllPopups()">Κλείσιμο</button>
  </div>

</div>


    </div>

</main>