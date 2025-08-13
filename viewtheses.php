<?php
session_start();
    require 'dbconnect.php';
    if (!isset($_SESSION['username'])) {
        header('Location: index.html');
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    $stmt = $pdo->prepare("SELECT id FROM teacher WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    //need to find all theses for current teacher that are either finished, running, or cancelled
    $stmt = $pdo->prepare("SELECT t.* FROM thesis t LEFT JOIN committee c ON t.thesisID=c.thesisID WHERE (t.supervisor=? OR c.member1=? OR c.member2=?) AND t.th_status!='TBG'");
    $stmt->execute([$teacher['id'], $teacher['id'], $teacher['id']]);
    $theses = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            <h1 class="site-title">View Theses</h1>
        </div>
        </header>
        <div class="dashboard-main">
            <main class="dashboard-container">
                <table class="table">
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Role</th>
                        <th>Status</th>
                    </tr>
                    <?php if (empty($theses)): ?>
                        <tr><th>No theses found</th></tr>
                    <?php else: ?>
                    <?php foreach ($theses as $thesis): ?>
                        <tr>
                            <td><?= htmlspecialchars($thesis['thesisID'])?></td>
                            <td><?=htmlspecialchars($thesis['title'])?></td>
                            <?php if($teacher['id']===$thesis['supervisor']):?>
                                <td>Supervisor</td>
                            <?php else: ?>
                                <td>Committee</td>
                            <?php endif; ?>
                            <td><?=htmlspecialchars($thesis['th_status'])?></td>
                            <td>
                                <form class="selectThesis" method="get">
                                    <button class="submit-btn" type="button" data-thesis-id="<?= htmlspecialchars($thesis['thesisID']) ?>">Select</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php endif;?>
                </table>
                <script src="viewtheses.js"></script>
            </main>
        </div>
        <footer class="footer">
             <p>&copy; 2025 Thesis Management System</p>
        </footer>
</html>