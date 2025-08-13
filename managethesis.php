<?php
session_start();
    if(!isset($_SESSION['username'])){
        header('Location: index.html');
    exit;
    }
    require_once 'dbconnect.php';

    //get the thesisID from url
    if (!isset($_GET['id']) || empty($_GET['id'])) { 
    echo json_encode(['success' => false, 'message' => 'Thesis ID is required.']);
    exit;
    }

    $thesisID = $_GET['id'];

    //gather info for this thesis
    $stmt = $pdo->prepare("SELECT * FROM thesis WHERE thesisID = ?");
    $stmt->execute([$thesisID]);
    $thesis = $stmt->fetch(PDO::FETCH_ASSOC);
    //get student name 
    $stmt = $pdo->prepare("SELECT s_fname, s_lname FROM student WHERE thesisID = ?");
    $stmt->execute([$thesisID]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    //gather committee
    $stmt = $pdo->prepare("SELECT * FROM committee WHERE thesisID = ?");
    $stmt->execute([$thesisID]);
    $committee = $stmt->fetch(PDO::FETCH_ASSOC);
    if(isset($committee['member1']) && isset($committee['member2'])){
        $stmt = $pdo->prepare("SELECT t_fname, t_lname FROM teacher WHERE id = ? OR id = ?");
        $stmt->execute([$committee['member1'], $committee['member2']]);
        $memberNames = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    else if(isset($committee['member1'])){
        $stmt = $pdo->prepare("SELECT t_fname, t_lname FROM teacher WHERE id = ?");
        $stmt->execute([$committee['member1']]);
        $memberNames = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    else if(isset($committee['member2'])){
        $stmt = $pdo->prepare("SELECT t_fname, t_lname FROM teacher WHERE id = ?");
        $stmt->execute([$committee['member2']]);
        $memberNames = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    $stmt = $pdo->prepare("SELECT t_fname, t_lname FROM teacher WHERE id = ?");
    $stmt->execute([$committee['supervisor']]);
    $supervisorName = $stmt->fetch(PDO::FETCH_ASSOC);
    //get id to identify role in thesis
    $stmt = $pdo->prepare("SELECT id FROM teacher WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    $id = $stmt->fetch(PDO::FETCH_ASSOC);
    if($id['id']===$committee['supervisor']){
        $role="supervisor";
    }
    else if($id['id']===$committee['member1'] || $id['id']===$committee['member2']){
        $role="member";
    }
?>

<!DOCTYPE html>
    <html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="style.css">
    <title>Edit Thesis</title>
</head>
<body>
    <header>
        <div class="logo-title-row">
            <button class="back-btn" id="backBtn">
                <img src="logo2.jpg" alt="Logo" class="logo" />
            </button>
            <h1 class="site-title">Manage Thesis</h1>
        </div>
    </header>
    <main class="dashboard-main">
        <h2>Thesis Info</h2>
        <div class="dashboard-container">
            <table class="table">
                 <tr>
                    <th>Title</th>
                    <th>Student</th>
                    <th>Supervisor</th>
                        <th>Members</th>
                    <th></th>
                    <th>Changes</th>
                    <?php if($thesis['th_status']==="DONE"): ?>
                        <th>Grade</th>
                    <?php endif; ?>
                </tr>
                <tr>
                    <td><?= htmlspecialchars($thesis['title']) ?></td>
                    <td><?= htmlspecialchars($student['s_fname']) ?> <?= htmlspecialchars($student['s_lname'])?></td>
                    <td><?= htmlspecialchars($supervisorName['t_fname']) ?> <?= htmlspecialchars($supervisorName['t_lname'])?></td>
                    <?php if(isset($memberNames)): foreach($memberNames as $member):?>
                        <td><?= htmlspecialchars($member['t_fname']) ?> <?= htmlspecialchars($member['t_lname'])?></td>
                    <?php endforeach; else: ?>
                        <td>There are no members in committee.</td>
                    <?php endif; ?>
                    <?php if($thesis['th_status']==="DONE"): ?>
                        <td><?= htmlspecialchars($thesis['grade']) ?></td>
                    <?php endif; ?>
                </tr>
            </table>
        </div>
    </main>
    <footer class="footer">
        <p>© 2025 Thesis Management System</p>
    <script src="managethesis.js"></script>
</html>