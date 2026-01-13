<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'db.php'; 

$admin_id = $_SESSION['admin_id'] ?? 1; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'confirm_return') {
    header('Location: car_lifecycle.php');
    exit;
}

$request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
$car_id = filter_input(INPUT_POST, 'car_id', FILTER_VALIDATE_INT);
$return_odometer = filter_input(INPUT_POST, 'return_odometer', FILTER_VALIDATE_INT); 
$damage_fee = filter_input(INPUT_POST, 'damage_fee', FILTER_VALIDATE_FLOAT);
$return_condition = filter_input(INPUT_POST, 'return_condition', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$return_date_time = $_POST['return_date_time'] ?? null; 

if (!$request_id || !$car_id || $return_odometer === false || $damage_fee === false || !$return_condition || !$return_date_time) {
    header("Location: car_return.php?request_id={$request_id}&error=" . urlencode("Missing or invalid return data provided."));
    exit;
}

mysqli_begin_transaction($conn);

try {
    // 1. Fetch rental details for FINAL Calculation
    $fetch_details_query = "
        SELECT rr.total_cost, c.daily_rate, pd.pickup_date_actual
        FROM rental_requests rr
        INNER JOIN cars c ON rr.car_id = c.car_id
        INNER JOIN rental_pickup_details pd ON rr.request_id = pd.request_id
        WHERE rr.request_id = ?
        FOR UPDATE 
    ";
    $stmt_fetch = $conn->prepare($fetch_details_query);
    $stmt_fetch->bind_param("i", $request_id);
    $stmt_fetch->execute();
    $details_result = $stmt_fetch->get_result();
    $details_row = $details_result->fetch_assoc();
    $stmt_fetch->close();

    if (!$details_row) {
        throw new Exception("Could not fetch necessary rental details for final return.");
    }

    $daily_rate = (float)$details_row['daily_rate'];
    $total_cost_paid = (float)$details_row['total_cost'];
    $pickup_date_actual = $details_row['pickup_date_actual'];

    // 2. Final Cost Calculation
    $startDate = new DateTime($pickup_date_actual);
    $endDate = new DateTime($return_date_time); 

    if ($endDate < $startDate) {
        throw new Exception("Actual return date/time is before the actual pickup date/time.");
    }

    $seconds_used = $endDate->getTimestamp() - $startDate->getTimestamp();
    $days_charged = max(1, ceil($seconds_used / (60 * 60 * 24)));
    $final_deducted_cost_capped = min($days_charged * $daily_rate, $total_cost_paid);
    $final_refund_amount = max(0, $total_cost_paid - $final_deducted_cost_capped - $damage_fee); 

    // 3. Update status in rental_return_requests
    $update_return_req_sql = "
        UPDATE rental_return_requests 
        SET total_deducted_cost = ?, status = 'Returned'
        WHERE request_id = ?
    ";
    $stmt_update_return_req = $conn->prepare($update_return_req_sql);
    $stmt_update_return_req->bind_param("di", $final_deducted_cost_capped, $request_id);
    $stmt_update_return_req->execute();
    $stmt_update_return_req->close();
    
    // 4. Insert into rental_return_details
    $insert_sql = "
        INSERT INTO rental_return_details (request_id, return_date_actual, odometer_return, car_condition_return, damage_fee, final_refund_amount, return_admin_id)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ";
    $stmt_insert = $conn->prepare($insert_sql);
    $stmt_insert->bind_param("isidsdi", $request_id, $return_date_time, $return_odometer, $return_condition, $damage_fee, $final_refund_amount, $admin_id);
    $stmt_insert->execute();
    $stmt_insert->close();

    // 5. Update rental_requests status to 'Returned'
    $update_rental_sql = "
        UPDATE rental_requests 
        SET request_status = 'Returned' 
        WHERE request_id = ? AND request_status IN ('Picked Up', 'Early_Return_Scheduled')
    ";
    $stmt_rental = $conn->prepare($update_rental_sql);
    $stmt_rental->bind_param("i", $request_id);
    $stmt_rental->execute();
    if ($stmt_rental->affected_rows === 0) {
        throw new Exception("Rental status changed unexpectedly during transaction.");
    }
    $stmt_rental->close();

    // 6. REMOVED: Update car availability (Scheduling is now date-based)
    /*
    $update_car_sql = "
        UPDATE cars 
        SET availability = 1 
        WHERE car_id = ?
    ";
    $stmt_car = $conn->prepare($update_car_sql);
    $stmt_car->bind_param("i", $car_id);
    $stmt_car->execute();
    $stmt_car->close();
    */

    mysqli_commit($conn);
    
    $success_message = urlencode("Rental ID {$request_id} closed. Final Cost: ₱" . number_format($final_deducted_cost_capped, 2) . ". Refund: ₱" . number_format($final_refund_amount, 2));
    header("Location: car_lifecycle.php?success={$success_message}");
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    header("Location: car_return.php?request_id={$request_id}&error=" . urlencode($e->getMessage()));
    exit;
}
?>