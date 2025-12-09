<?php
include 'db.php';

if(isset($_POST['return_button'])){
    $return_id = $_POST['return_id'];
    $return_date = $_POST['return_date'];
    $start_date = $_POST['start_date'];
    
    $query = "SELECT 
    rental_requests.request_id,
    rental_requests.rental_date,
    rental_requests.total_cost,
    cars.car_id,
    cars.model,
    cars.daily_rate
    FROM rental_requests JOIN cars ON rental_requests.car_id = cars.car_id
    WHERE rental_requests.request_id = $return_id
    ";

    $query_result = mysqli_query($conn, $query);

    if($row = mysqli_fetch_assoc($query_result)){
        $id_request = $row['request_id'];
        $rental_date = $row['rental_date'];
        $total_cost = $row['total_cost'];
        $car_id = $row['car_id'];
        $car_model = $row['model'];
        $daily_rate = $row['daily_rate'];

        $startDate = new DateTime($start_date);
        $endDate = new DateTime($return_date);
        
        $interval = $startDate->diff($endDate);
        $days = $interval->days;

        $total_cost = $days * $daily_rate;

        echo "Days rented: $days <br>";
        echo "Total cost: ₱" . number_format($total_cost, 2);
    }
}
?>