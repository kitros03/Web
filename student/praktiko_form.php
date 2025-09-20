<?php
require_once  '../dbconnect.php';
session_start();
header('Content-Type: text/html; charset=utf-8');

// Access control
if (empty($_SESSION['username'])) { http_response_code(401); echo 'Μη εξουσιοδοτημένη πρόσβαση'; exit; }

$thesisID = (int)($_GET['thesisID'] ?? 0);
if ($thesisID <= 0) { echo 'Λείπει thesisID'; exit; }
?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <title>Πρακτικό Εξέτασης Διπλωματικής Εργασίας</title>
  <link rel="stylesheet" href="../praktiko_exam.css">
  <script>
    window.__PRAKTIKO__ = { thesisID: <?php echo json_encode($thesisID, JSON_UNESCAPED_UNICODE); ?> };
  </script>
  <script defer src="praktiko_form.js"></script>
</head>
<body>
  <div class="header">
    <div class="university-name">ΠΡΟΓΡΑΜΜΑ ΣΠΟΥΔΩΝ</div>
    <div class="department-name">«ΤΜΗΜΑΤΟΣ ΜΗΧΑΝΙΚΩΝ, ΗΛΕΚΤΡΟΝΙΚΩΝ ΥΠΟΛΟΓΙΣΤΩΝ ΚΑΙ ΠΛΗΡΟΦΟΡΙΚΗΣ»</div>

    <div class="document-title">ΠΡΑΚΤΙΚΟ ΣΥΝΕΔΡΙΑΣΗΣ</div>
    <div class="document-subtitle">ΤΗΣ ΤΡΙΜΕΛΟΥΣ ΕΠΙΤΡΟΠΗΣ</div>
    <div class="document-subtitle2">ΓΙΑ ΤΗΝ ΠΑΡΟΥΣΙΑΣΗ ΚΑΙ ΚΡΙΣΗ ΤΗΣ ΔΙΠΛΩΜΑΤΙΚΗΣ ΕΡΓΑΣΙΑΣ</div>

    <div class="italic">του/της φοιτητή/φοιτήτρια</div>
    <div class="student-name" id="studentName">Κ. ………………………………</div>
  </div>

  <div class="content">
    <p>
      Η συνεδρίαση πραγματοποιήθηκε στην αίθουσα
      <span id="examRoom">……………………………</span>
      στις <span id="examDate">……………………………</span>
      ημέρα <span id="examDay">……</span>
      και ώρα <span id="examTime">………………</span>
    </p>

    <p>Στην συνεδρίαση είναι παρόντα τα μέλη της Τριμελούς Επιτροπής κ.κ.:</p>

    <ol id="membersOriginalList">
      <li>………………………………………………………………………………,</li>
      <li>………………………………………………………………………………,</li>
      <li>………………………………………………………………………………,</li>
    </ol>

    <p>
      οι οποίοι ορίσθηκαν από την Συνέλευση του ΤΜΗΥΠ, στην συνεδρίαση της με αριθμό
      <span id="gsText">…………………</span>
    </p>

    <p><strong>Ο/Η φοιτητής/φοιτήτρια</strong> κ. <span id="studentNameInline">………………………………</span>
       ανέπτυξε το θέμα της Διπλωματικής του/της Εργασίας, με τίτλο
    </p>

    <p style="margin-left:20px;">
      «<span id="thesisTitle">………………………………</span>»
    </p>

    <p>
      Στην συνέχεια υποβλήθηκαν ερωτήσεις στον υποψήφιο από τα μέλη της Τριμελούς Επιτροπής και τους άλλους
      παρευρισκόμενους, προκειμένου να διαμορφώσουν σαφή άποψη για το περιεχόμενο της εργασίας, για την
      επιστημονική συγκρότηση του μεταπτυχιακού φοιτητή.
    </p>

    <p>Μετά το τέλος της ανάπτυξης της εργασίας του και των ερωτήσεων, ο υποψήφιος αποχωρεί.</p>

    <p>
      Ο Επιβλέπων καθηγητής κ. <span id="supervisorFull">………………………………</span>,
      προτείνει στα μέλη της Τριμελούς Επιτροπής, να ψηφίσουν για το αν εγκρίνεται η διπλωματική εργασία του
      <span id="studentNameInline2">………………………………</span>
    </p>

    <p>Τα μέλη της Τριμελούς Επιτροπής, ψηφίζουν κατ’ αλφαβητική σειρά:</p>

    <ol id="membersAlphaList">
      <li>………………………………………………………………………………,</li>
      <li>………………………………………………………………………………,</li>
      <li>………………………………………………………………………………,</li>
    </ol>

    <p>
      υπέρ της εγκρίσεως της Διπλωματικής Εργασίας του φοιτητή
      <span id="studentNameInline3">………………………………</span> επειδή θεωρούν επιστημονικά επαρκή και το
      περιεχόμενο της ανταποκρίνεται στο θέμα που του δόθηκε.
    </p>

    <p>
      Μετά της έγκριση, ο εισηγητής κ. <span id="supervisorFull2">………………………………</span>,
      προτείνει στα μέλη της Τριμελούς Επιτροπής, να απονεμηθεί στο/στη φοιτητή/τρια
      κ. <span id="studentNameInline4">………………………………</span>
      ο βαθμός <span class="grade-slot" id="finalGradeText">………………</span>
    </p>

    <p>Τα μέλη της Τριμελούς Επιτροπής, απομένουν την παραπάνω βαθμολογία:</p>

    <table class="signature-table" id="signatureTable">
      <thead>
        <tr>
          <th>ΟΝΟΜΑΤΕΠΩΝΥΜΟ</th>
          <th>ΙΔΙΟΤΗΤΑ</th>
        </tr>
      </thead>
      <tbody>
        <tr><td></td><td></td></tr>
        <tr><td></td><td></td></tr>
        <tr><td></td><td></td></tr>
      </tbody>
    </table>

    <p>
      Μετά την έγκριση και την απονομή του βαθμού
      <span class="grade-slot" id="finalGradeText2">………………</span>, η Τριμελής Επιτροπή, προτείνει να προχωρήσει
      στην διαδικασία για να ανακηρύξει τον κ. <span id="studentNameInline5">………………………………</span>, σε διπλωματούχο του Προγράμματος
      Σπουδών του «ΤΜΗΜΑΤΟΣ ΜΗΧΑΝΙΚΩΝ, ΗΛΕΚΤΡΟΝΙΚΩΝ ΥΠΟΛΟΓΙΣΤΩΝ ΚΑΙ ΠΛΗΡΟΦΟΡΙΚΗΣ ΠΑΝΕΠΙΣΤΗΜΙΟΥ ΠΑΤΡΩΝ»
      και να του απονέμει το Δίπλωμα Μηχανικού Η/Υ το οποίο αναγνωρίζεται ως Ενιαίος Τίτλος Σπουδών Μεταπτυχιακού Επιπέδου.
    </p>
  </div>
</body>
</html>
