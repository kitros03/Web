<?php
session_start();
require_once 'dbconnect.php';

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit;
}

$stmt = $pdo->prepare("
SELECT 
    t.title,
    t.th_description,
    t.pdf_description,
    t.th_status,
    t.assigment_date,
    CONCAT(s2.t_fname, ' ', s2.t_lname) AS supervisor_name,
    (
      SELECT GROUP_CONCAT(
        CONCAT(tr2.t_fname, ' ', tr2.t_lname) ORDER BY tr2.t_lname SEPARATOR ', '
      )
      FROM committee c2
      JOIN teacher tr2 
        ON tr2.teacherID = c2.member1 OR tr2.teacherID = c2.member2
      WHERE c2.thesisID = t.thesisID
    ) AS committee_members
FROM thesis t
JOIN teacher s2 ON s2.teacherID = t.supervisor
WHERE t.thesisID = (
    SELECT thesisID FROM student WHERE username = ?
)
");
$stmt->execute([$_SESSION['username']]);
$thesis = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$thesis) {
    echo json_encode(['success' => false, 'message' => 'Δεν έχεις ανατεθειμένη διπλωματική.']);
    exit;
}

$days_since_assignment = null;
if (!empty($thesis['assigment_date'])) {
    $date1 = new DateTime($thesis['assigment_date']);
    $date2 = new DateTime();
    $interval = $date1->diff($date2);
    $days_since_assignment = $interval->days;
}
$thesis['days_since_assignment'] = $days_since_assignment;
echo json_encode(['success' => true, 'thesis' => $thesis]);
?>
