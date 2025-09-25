<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once '../dbconnect.php';
?>

<!DOCTYPE html>
<html lang="el">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Εισαγωγή δεδομένων (Γραμματεία)</title>
        <link rel="stylesheet" href="../css/basic.css">
        <link rel="stylesheet" href="../css/secretary.css">
    </head>
    
    <body>
        <!-- NavBar -->
        <nav class="navbar">
            <div class="navdiv">
                <img src="../images/logo.jpg" alt="upatras">
                <div class="logo">
                    <a href="main.php">Δ.Ε. Π.Π.</a>
                </div>
                <ul>
                    <li><a href="thesis_list.php">Διπλωματικές Εργασίες</a></li>
                    <li><a href="insert_data.php">Εισαγωγή δεδομένων</a></li>
                    <li><a href="manage_thesis.php">Διαχείριση Δ.Ε.</a></li>
                    <li><a href="../logout.php">Log out</a></li>
                </ul>
            </div>
        </nav>

        <h1>Αρχική &#x2192; Εισαγωγή Δεδομένων</h1>

        <!-- Κύριο περιεχόμενο της σελίδας -->
        <div class="content">
            <h2>ΕΙΣΑΓΩΓΗ ΔΕΔΟΜΕΝΩΝ</h2>
            <!-- Φόρμα JSON -->
            <div class="upload-form">
                <form action="../json_to_sql.php" method="post" enctype="multipart/form-data">
                    <label><b>Επιλογή αρχείου JSON (φοιτητές/διδάσκοντες):</b></label>
                    <input type="file" name="json_file" accept=".json" required>
                    <button type="submit">Upload</button>
                </form>
            </div>
                
            <!-- Εμφάνιση μηνύματος κατάστασης -->
            <?php
                if (isset($_SESSION['status'])) {
                    $failed = (strpos($_SESSION['status'], " 0 αποτυχίες") === false);
                    $statusClass = $failed ? 'status-box failed' : 'status-box';

                    echo '<div class="'.$statusClass.'">';
                    echo '<span class="status-title">'.($failed ? 'Υπήρξαν αποτυχίες :(' : 'Επιτυχής Εισαγωγή Δεδομένων :)').'</span><br><br>';
                    echo $_SESSION['status'];
                    echo '</div>';

                    unset($_SESSION['status']);
                }
            ?>
        </div>
    </body>
</html>
