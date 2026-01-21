<?php
include 'db.php';
$car_id = isset($_GET['car_id']) ? $_GET['car_id'] : 0;

$all_dates = [];
if ($car_id > 0) {
    $date_query = "SELECT rental_date, rental_duration_days FROM rental_requests WHERE car_id = ? AND request_status IN ('Approved', 'Picked Up', 'Pending')";
    $stmt = $conn->prepare($date_query);
    $stmt->bind_param("i", $car_id);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $start = new DateTime($row['rental_date']);
        $days = (int)$row['rental_duration_days'];
        
        // Loop <= $days to include all rental days PLUS the maintenance day
        for ($i = 0; $i <= $days; $i++) { 
            $all_dates[] = $start->format('Y-m-d');
            $start->modify('+1 day');
        }
    }
}
header('Content-Type: application/json');
echo json_encode($all_dates);
?>