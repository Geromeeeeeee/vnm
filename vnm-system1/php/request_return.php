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
    cars.daily_rate,
    users.user_id
    FROM rental_requests 
    JOIN cars ON rental_requests.car_id = cars.car_id
    JOIN users ON rental_requests.user_id = users.user_id
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
        $user_id = $row['user_id'];

        $startDate = new DateTime($start_date);
        $endDate = new DateTime($return_date);
        
        $interval = $startDate->diff($endDate);
        $days = $interval->days;

        if ($days < 1) $days = 1;

        $total_deducted_cost = $days * $daily_rate;

        $return_request = "INSERT INTO rental_return_requests (request_id, user_id, requested_at, total_deducted_cost) VALUES ($id_request, $user_id, '$return_date', $total_deducted_cost)"; 

          if(mysqli_query($conn, $return_request)){
            $update_status = "UPDATE rental_requests 
                SET request_status = 'Early_Return_Pending'
                WHERE request_id = $id_request";

                mysqli_query($conn, $update_status);

            header("Location: customer_lifecycle.php");
            exit();
        }
    }
}
?>