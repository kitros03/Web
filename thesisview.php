<?php
header('Content-Type: application/json');

// ΕΔΩ: Ανάκτηση από βάση δεδομένων στη θέση των στατικών τιμών
$thesis = [
    "title" => "Ανάπτυξη Εφαρμογής Web",
    "desc" => "Δημιουργία δυναμικής ιστοσελίδας διαχείρισης διπλωματικών εργασιών.",
    "file" => "files/description.pdf", // Βάλε το σωστό path
    "status" => "Σε εξέλιξη",
    "committee" => ["Καθηγητής Άλφα", "Καθηγητής Βήτα", "Καθηγητής Γάμμα"],
    "assignment_date" => "2025-06-01" // Αν δεν υπάρχει, βάλε null ή ""
];

// Υπολογισμός ημερών από ανάθεση αν έχει γίνει
if (!empty($thesis["assignment_date"])) {
    $date1 = new DateTime($thesis["assignment_date"]);
    $date2 = new DateTime();
    $interval = $date1->diff($date2);
    $thesis["days_since_assignment"] = $interval->days . " μέρες";
} else {
    $thesis["days_since_assignment"] = "Δεν έχει γίνει ανάθεση";
}

// Έλεγχος για μη ορισμένη επιτροπή
if (empty($thesis["committee"])) {
    $thesis["committee"] = ["Δεν έχουν οριστεί μέλη επιτροπής"];
}

echo json_encode($thesis);
?>

