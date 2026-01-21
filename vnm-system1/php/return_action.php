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
    // 1. Fetch rental details AND the customer's scheduled early return data
    $fetch_details_query = "
        SELECT 
            rr.total_cost, 
            rr.rental_duration_days, 
            rr.rental_date, 
            rr.rental_time,
            rrr.scheduled_return_date,
            rrr.scheduled_return_time
        FROM rental_requests rr
        LEFT JOIN rental_return_requests rrr ON rr.request_id = rrr.request_id
        WHERE rr.request_id = ?
        FOR UPDATE 
    ";
    $stmt_fetch = $conn->prepare($fetch_details_query);
    $stmt_fetch->bind_param("i", $request_id);
    $stmt_fetch->execute();
    $details_row = $stmt_fetch->get_result()->fetch_assoc();
    $stmt_fetch->close();

    if (!$details_row) {
        throw new Exception("Could not fetch necessary rental details for final return.");
    }

    $total_cost_paid = (float)$details_row['total_cost'];
    $duration_days = (int)$details_row['rental_duration_days'];
    $daily_rate = $total_cost_paid / $duration_days;

    // Define Pickup and Original Deadline
    $pickup_dt = new DateTime($details_row['rental_date'] . ' ' . $details_row['rental_time']);
    $deadline_dt = clone $pickup_dt;
    $deadline_dt->modify("+$duration_days days");

    // Define Actual Return (Admin submitted value)
    $actual_return_dt = new DateTime($return_date_time);

    // 2. ACCURATE COST CALCULATION BASED ON 3 RULES
    
    // RULE 3: If return is on or after the original deadline, no refund.
    if ($actual_return_dt >= $deadline_dt) {
        $days_to_charge = $duration_days;
        $final_refund_amount = 0;
    } else {
        // RULE 1: Measure strictly by 24-hour blocks
        $diff = $pickup_dt->diff($actual_return_dt);
        
        // Convert interval to total hours to accurately use ceil
        $total_hours = ($diff->days * 24) + $diff->h + ($diff->i / 60);
        $days_used = ceil($total_hours / 24);

        // RULE 2: Same-day return (within first 24h) always counts as Day 1
        if ($days_used < 1) {
            $days_used = 1;
        }

        $days_to_charge = $days_used;
        $remaining_days = $duration_days - $days_to_charge;
        
        // Calculate refund based on remaining full days
        $final_refund_amount = max(0, $remaining_days * $daily_rate);
    }

    // Final cost is what the business keeps (Total Paid - Refund)
    $final_deducted_cost = $total_cost_paid - $final_refund_amount;

    // 3. Update status in rental_return_requests
    $update_return_req_sql = "
        UPDATE rental_return_requests 
        SET total_deducted_cost = ?, status = 'Processed'
        WHERE request_id = ?
    ";
    $stmt_update_return_req = $conn->prepare($update_return_req_sql);
    $stmt_update_return_req->bind_param("di", $final_deducted_cost, $request_id);
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
    $stmt_rental->close();

    mysqli_commit($conn);
    
    $success_message = urlencode("Rental closed. Final Usage Cost: ₱" . number_format($final_deducted_cost, 2) . ". Refund issued: ₱" . number_format($final_refund_amount, 2));
    header("Location: car_lifecycle.php?success={$success_message}");
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    header("Location: car_return.php?request_id={$request_id}&error=" . urlencode($e->getMessage()));
    exit;
}
?>