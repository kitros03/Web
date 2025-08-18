<?php
header('Access-Control-Allow-Origin: *');
require 'dbconnect.php';

// Πάρε παραμέτρους από το URL
$from = isset($_GET['from']) ? $_GET['from'] : '';
$to = isset($_GET['to']) ? $_GET['to'] : '';
$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'json';

// Μετατροπή σε DATE (υποθέτουμε πως στην ΒΔ τα dates είναι σε μορφή YYYYMMDD)
$from_date = DateTime::createFromFormat('dmY', $from);
$to_date   = DateTime::createFromFormat('dmY', $to);

if (!$from_date || !$to_date) {
    http_response_code(400);
    echo "Invalid date format";
    exit;
}

// Ερώτημα SQL
$sql = "SELECT date, time, title, announcement_text
        FROM announcements
        WHERE date BETWEEN ? AND ?
        ORDER BY date, time";
$stmt = $pdo->prepare($sql);
$stmt->execute([$from, $to]);
$anns = $stmt->fetchAll();

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "announcements"=>[
            "from"=>$from,
            "to"=>$to,
            "announcement_list"=>$anns
        ]
    ]);
} elseif ($format === 'xml') {
    header('Content-Type: application/xml; charset=utf-8');
    // Μία απλή μετατροπή array σε XML
    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<announcements>\n";
    $xml .= "<from>$from</from><to>$to</to><announcement_list>\n";
    foreach($anns as $ann){
        $xml .= "<announcement>\n";
        foreach($ann as $k=>$v){
            $xml .= "<$k>".htmlspecialchars($v)."</$k>\n";
        }
        $xml .= "</announcement>\n";
    }
    $xml .= "</announcement_list>\n</announcements>";
    echo $xml;
} else {
    echo "Invalid format";
}
?>
