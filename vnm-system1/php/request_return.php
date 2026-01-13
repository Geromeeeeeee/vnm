<?php
// request_return.php - Customer submits an early return request
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'db.php';

// Require login
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$current_user_id = (int) $_SESSION['user'];

if(isset($_POST['return_button'])){
    // Sanitize and validate inputs.
    $return_id = filter_input(INPUT_POST, 'return_id', FILTER_VALIDATE_INT);
    // This input MUST be a full DATETIME string (e.g., YYYY-MM-DDTHH:MM) from the customer's form.
    $return_date = $_POST['return_date'] ?? null; // Requested early return DATETIME
    
    if (!$return_id || !$return_date) {
        header("Location: customer_lifecycle.php?error=" . urlencode("Missing rental ID or requested return date/time."));
        exit;
    }
    
    // Start transaction for atomic operations
    mysqli_begin_transaction($conn);

    try {
        // 1. Fetch rental details, daily rate, CRUCIAL STATUS, and actual pickup date/time
        $query = "
            SELECT 
                rr.request_status,
                rr.total_cost,
                c.daily_rate,
                pd.pickup_date_actual  -- Fetch the actual pickup DATETIME
            FROM rental_requests rr
            JOIN cars c ON rr.car_id = c.car_id
            INNER JOIN rental_pickup_details pd ON rr.request_id = pd.request_id 
            WHERE rr.request_id = ? AND rr.user_id = ?
            FOR UPDATE 
        ";
        $stmt_select = $conn->prepare($query);
        if ($stmt_select === false) { 
            throw new Exception("SQL Prepare Failed (Select): " . $conn->error); 
        }
        $stmt_select->bind_param("ii", $return_id, $current_user_id);
        $stmt_select->execute();
        $query_result = $stmt_select->get_result();

        if ($query_result->num_rows === 0) {
            throw new Exception("Rental not found for your account, or car has not been picked up yet.");
        }
        
        $row = $query_result->fetch_assoc();
        $current_status = $row['request_status'];
        $daily_rate = (float)$row['daily_rate'];
        $total_cost_paid = (float)$row['total_cost'];
        $pickup_date_actual = $row['pickup_date_actual']; // Actual pickup date/time
        
        $stmt_select->close();

        // CRUCIAL STATUS CHECK
        if ($current_status !== 'Picked Up' || empty($pickup_date_actual)) {
            throw new Exception("Rental not found, or status is incorrect for return (must be 'Picked Up' and car must be picked up). Current Status is: '" . htmlspecialchars($current_status) . "'.");
        }
        
        // --- Calculate Total Used Cost with DATE & TIME PRECISION (Minimum 1 day charge) ---
        
        // Ensure both dates are handled as DateTime objects for precise calculation.
        $startDate = new DateTime($pickup_date_actual);
        $endDate = new DateTime($return_date);
        
        // Check if return date is before pickup date
        if ($endDate < $startDate) {
            throw new Exception("Requested return date/time cannot be before the actual pickup date/time: " . $pickup_date_actual);
        }

        // Calculate the difference in seconds.
        $duration_in_seconds = $endDate->getTimestamp() - $startDate->getTimestamp();
        
        $seconds_in_day = 60 * 60 * 24;
        
        // Convert seconds to fractional days.
        $days_used_float = $duration_in_seconds / $seconds_in_day;
        
        // Days charged is the CEILING of days used, with a minimum charge of 1 day.
        $days_charged = max(1, ceil($days_used_float));

        $total_deducted_cost = $days_charged * $daily_rate; 
        
        // Cap the calculated cost at the original total cost paid.
        $total_deducted_cost_capped = min($total_deducted_cost, $total_cost_paid);

        // Explicitly calculate the refund amount: This is the amount the user gets back.
        $refund_amount = max(0, $total_cost_paid - $total_deducted_cost_capped);


        // 2. Insert/Update the request in the rental_return_requests table
        $return_request = "
            INSERT INTO rental_return_requests 
                (request_id, user_id, requested_at, status, total_deducted_cost) 
            VALUES (?, ?, ?, 'Pending', ?) 
            ON DUPLICATE KEY UPDATE 
                requested_at = VALUES(requested_at),
                status = 'Pending',
                total_deducted_cost = VALUES(total_deducted_cost)
        ";
        $stmt_insert = $conn->prepare($return_request);
        if ($stmt_insert === false) { throw new Exception("SQL Prepare Failed (Insert): " . $conn->error); }
        $stmt_insert->bind_param("iisd", $return_id, $current_user_id, $return_date, $total_deducted_cost_capped); 
        
        if (!$stmt_insert->execute()) {
            throw new Exception("Error initiating return request: " . $stmt_insert->error);
        }
        $stmt_insert->close();

        // 3. Update the rental_requests status to 'Early Return Requested'
        $update_rental_status = "
            UPDATE rental_requests 
            SET request_status = 'Early Return Requested' 
            WHERE request_id = ? AND user_id = ? AND request_status = 'Picked Up'
        ";
        $stmt_update = $conn->prepare($update_rental_status);
        if ($stmt_update === false) { throw new Exception("SQL Prepare Failed (Update): " . $conn->error); }
        $stmt_update->bind_param("ii", $return_id, $current_user_id);

        if (!$stmt_update->execute()) {
            throw new Exception("Error updating rental status: " . $stmt_update->error);
        }
        if ($stmt_update->affected_rows === 0) {
             throw new Exception("Rental status update failed. The rental may have already been requested for return or processed. Current Status was: '" . htmlspecialchars($current_status) . "'.");
        }
        $stmt_update->close();

        mysqli_commit($conn);
        
        // Redirect on success
        header("Location: customer_lifecycle.php?success_return=" . urlencode("Early Return Request submitted successfully. Estimated refund amount: ₱" . number_format($refund_amount, 2) . ". Final amount is confirmed by Admin upon return."));
        exit;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Return Request failed: " . $e->getMessage());
        header("Location: customer_lifecycle.php?error=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    header("Location: customer_lifecycle.php");
    exit;
}
?>