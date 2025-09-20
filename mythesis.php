    <?php
    session_start();
    header('Content-Type: application/json');
    require_once '../dbconnect.php';

    if (!isset($_SESSION['username']) || $_SESSION['role']!== 'student'){
        echo json_encode(['success' => false]);
        exit;
    }
    $stmt = $pdo-> prepare(
        "SELECT t.tittle, t.th_description, t.pdf_description, t.status, s2.name AS supervisor_name, t.assigment_date, (SELECT GROUP_CONCAT(DISTINCT tr2.name SEPARATOR ', ')
        FROM committee c2
        JOIN teacher tr2 ON tr2.id = c2.id
        WHERE c2.thesis.ID = t.thesisID) AS commitee_members
        FROM thesis t
        JOIN teacher s2 ON s2.id = t.supervisor
        WHERE t.studentID = (SELECT studentID from student where username = ?)"

    );
    $stmt -> execute ([$_SESSION['username']]);
    $thesis = $stmt-> Fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'thesis' => $thesis]);
    ?>