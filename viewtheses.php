<?php
session_start();
require_once 'dbconnect.php'; 

// Get current teacher id 
$stmt = $pdo->prepare("SELECT id FROM teacher WHERE username = ?");
$stmt->execute([$_SESSION['username']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch theses same as before 
$stmt = $pdo->prepare("
    SELECT t.* 
    FROM thesis t 
    LEFT JOIN committee c ON t.thesisID = c.thesisID 
    WHERE (t.supervisor = ? OR c.member1 = ? OR c.member2 = ?)
      AND t.th_status != 'NOT_ASSIGNED'
    ORDER BY t.thesisID DESC
");
$stmt->execute([$teacher['id'], $teacher['id'], $teacher['id']]);
$theses = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getTeacherName(PDO $pdo, $id) {
    if (!$id) return null;
    $stmt = $pdo->prepare("SELECT t_fname, t_lname FROM teacher WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    return trim(($row['t_fname'] ?? '') . ' ' . ($row['t_lname'] ?? ''));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Theses</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
 <header>
        <div class="logo-title-row">
            <button class="back-btn" id="backBtn">
                <img src="logo2.jpg" alt="Logo" class="logo" />
            </button>
            <h1 class="site-title">Manage Theses</h1>
        </div>
    </header>
<main class="dashboard-main">
  <h2>Theses</h2>

  <table class="table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Title</th>
        <th>Role</th>
        <th>Status</th>
        <th>Supervisor</th>
        <th>Members</th>
        <th></th>
        <th>Grade</th>
        <th>Changes Timeline</th>
        <th>Manage</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($theses)): ?>
        <tr>
          <td colspan="9">No theses found</td>
        </tr>
      <?php else: ?>
        <?php foreach ($theses as $thesis): ?>
          <?php
            $supervisorName = getTeacherName($pdo, $thesis['supervisor']);

            $grade = isset($thesis['grade']) ? $thesis['grade'] : '';

            $role = 'Committee';
            if ((string)$thesis['supervisor'] === (string)$teacher['id']) {
                $role = 'Supervisor';
            }

            $stmt = $pdo->prepare("SELECT * FROM thesisStatusChanges WHERE thesisID = ?");
            $stmt->execute([$thesis['thesisID']]);
            $changes = $stmt->fetchAll(PDO::FETCH_ASSOC);
          ?>
          <tr>
            <td><?= htmlspecialchars($thesis['thesisID']) ?></td>
            <td><?= htmlspecialchars($thesis['title']) ?></td>
            <td><?= htmlspecialchars($role) ?></td>
            <td><?= htmlspecialchars($thesis['th_status']) ?></td>
            <td><?= htmlspecialchars($supervisorName ?? '') ?></td>
            <?php if(isset($memberNames)): foreach($memberNames as $member):?>
                <td><?= htmlspecialchars($member['t_fname']) ?> <?= htmlspecialchars($member['t_lname'])?></td>
            <?php endforeach; else: ?>
                <td>There are no members in committee.</td>
                <td></td>
            <?php endif; ?>
            <td><?= htmlspecialchars($grade) ?></td>
            <td>
                <button id="popupBtn" class="popup-btn">View Changes</button>
                <div id="popupWindow" class="popup-window" style="display:none;">
                    <div class="popup-content">
                    <?php if (isset($changes)): ?>
                        <h3>Changes Timeline</h3>
                        <ul class="changes-list">
                            <?php foreach ($changes as $change): ?>
                                <li>
                                    <strong><?= htmlspecialchars($change['changeDate']) ?></strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No changes recorded for this thesis.</p>
                    <?php endif; ?>
                    <button id="closePopupBtn" class="close-popup-btn" aria-label="Close">&times;</button>
                    </div>
                </div>
            </td>
            <td>
                <?php if ($thesis['th_status'] === 'ASSIGNED' || $thesis['th_status'] === 'ACTIVE' || $thesis['th_status'] === 'EXAM' ): ?>
                    <button
                        class="submit-btn open-btn"
                        data-thesis-id="<?= htmlspecialchars($thesis['thesisID']) ?>"
                        data-th-status="<?= htmlspecialchars($thesis['th_status']) ?>"
                    >
                    Open
                    </button>
                <?php else: ?>
                    <span>N/A</span>
                <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</main>
<footer class="footer">
    <p>© 2025 Thesis Management System</p>
    <script src="viewtheses.js"></script>
</body>
</html>
