<?php
session_start();
require_once 'dbconnect.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo json_encode(['error' => 'unauthorized']);
    } else {
        echo "<h1>Unauthorized. Please log in as a teacher.</h1>";
    }
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM teacher WHERE username = ?");
$stmt->execute([$_SESSION['username']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$teacher) {
    http_response_code(404);
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo json_encode(['error' => 'teacher not found']);
    } else {
        echo "<h1>Teacher not found.</h1>";
    }
    exit;
}

function safeRound($val) {
    return ($val === false || $val === null) ? null : round($val, 2);
}

$teacherIDArr = ['teacherID' => $teacher['id']];
$teacherIDDoubleArr = ['teacherID1' => $teacher['id'], 'teacherID2' => $teacher['id']];

// Detect AJAX request to return only JSON
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    $stmt = $pdo->prepare("
        SELECT AVG(DATEDIFF(done.changeDate, active.changeDate)) AS avgCompletionDays
        FROM thesis t
        JOIN thesisStatusChanges active ON t.thesisID = active.thesisID AND active.changeTo = 'ACTIVE'
        JOIN thesisStatusChanges done ON t.thesisID = done.thesisID AND done.changeTo = 'DONE'
        WHERE t.supervisor = :teacherID
    ");
    $stmt->execute($teacherIDArr);
    $avgTimeSupervise = safeRound($stmt->fetchColumn());

    $stmt = $pdo->prepare("
        SELECT AVG(DATEDIFF(done.changeDate, active.changeDate)) AS avgCompletionDays
        FROM committee c
        JOIN thesisStatusChanges active ON c.thesisID = active.thesisID AND active.changeTo = 'ACTIVE'
        JOIN thesisStatusChanges done ON c.thesisID = done.thesisID AND done.changeTo = 'DONE'
        WHERE c.member1 = :teacherID1 OR c.member2 = :teacherID2
    ");
    $stmt->execute($teacherIDDoubleArr);
    $avgTimeCommittee = safeRound($stmt->fetchColumn());

    $stmt = $pdo->prepare("
        SELECT AVG(calc_grade) AS avgGrade
        FROM grades g
        JOIN thesis t ON g.thesisID = t.thesisID
        WHERE t.supervisor = :teacherID
    ");
    $stmt->execute($teacherIDArr);
    $avgGradeSupervise = safeRound($stmt->fetchColumn());

    $stmt = $pdo->prepare("
        SELECT AVG(calc_grade) AS avgGrade
        FROM grades g
        JOIN committee c ON g.thesisID = c.thesisID
        WHERE c.member1 = :teacherID1 OR c.member2 = :teacherID2
    ");
    $stmt->execute($teacherIDDoubleArr);
    $avgGradeCommittee = safeRound($stmt->fetchColumn());

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM thesis WHERE supervisor = :teacherID");
    $stmt->execute($teacherIDArr);
    $countSupervise = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM committee WHERE member1 = :teacherID1 OR member2 = :teacherID2");
    $stmt->execute($teacherIDDoubleArr);
    $countCommittee = (int)$stmt->fetchColumn();

    echo json_encode([
        'avgTimeSupervise' => $avgTimeSupervise,
        'avgTimeCommittee' => $avgTimeCommittee,
        'avgGradeSupervise' => $avgGradeSupervise,
        'avgGradeCommittee' => $avgGradeCommittee,
        'countSupervise' => $countSupervise,
        'countCommittee' => $countCommittee,
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8" />
<title>View Statistics</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="style.css" />
<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f9f9f9; }
h1, h2 { color: #222; }
.stats-container { max-width: 700px; margin: 20px auto; }
.stats-btn { margin: 5px; padding: 10px 15px; }
.message { color: #d00; font-weight: bold; margin-top: 10px; }
canvas { background: #fff; margin-top: 10px; border: 1px solid #ccc; }
</style>
</head>
<body>

<header>
  <div class="logo-title-row">
    <button class="back-btn" onclick="history.back()">
      <img src="logo2.jpg" alt="Logo" class="logo" style="height:40px;" />
    </button>
    <h1 class="site-title">View Statistics</h1>
  </div>
</header>

<main class="dashboard-centered">
    <div class="centered-body">
    <h2>Επιλέξτε πίνακα στατιστικών</h2>
        <div class="centered-body">
            <button id="superviseStatsBtn" class="submit-btn">Στατιστικά Επιβλέποντα</button>
        </div>
        <div class="centered-body">
            <button id="committeeStatsBtn" class="submit-btn">Στατιστικά Επιτροπής</button>
        </div>
        <div class="centered-body">
            <button id="bothStatsBtn" class="submit-btn">Στατιστικά Και των Δύο</button>
        </div>
        
        <div id="statsPopup" class="popup-window" role="dialog" aria-modal="true" aria-labelledby="popupTitle" aria-describedby="popupDesc">
            <div class="popup-content">
                <button id="closePopupBtn" class="close-popup-btn" aria-label="Close popup">&times;</button>
                <h2 id="popupTitle">Στατιστικά</h2>
                <div id="popupDesc" style="margin-bottom:1rem;">
                </div>
                <canvas id="popupCompletionTimeChart" width="600" height="300"></canvas>
                <canvas id="popupAverageGradeChart" width="600" height="300"></canvas>
                <canvas id="popupCountChart" width="600" height="300"></canvas>
            </div>
        </div>
    </div>
</main>
<script src=viewstats.js></script>
<footer>
    <p class="footer">© 2025 Thesis Management System</p>
</footer>

</body>
</html>
