<?php
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'submit_schedule') {
    header('Location: customer_lifecycle.php');
    exit;
}

$request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
$schedule_date = $_POST['schedule_date'] ?? null;
$schedule_time = $_POST['schedule_time'] ?? null;
$is_initial_request = filter_input(INPUT_POST, 'is_initial_request', FILTER_VALIDATE_INT) ?? 0;

if (!$request_id || !$schedule_date || !$schedule_time) {
    header("Location: customer_lifecycle.php?error=" . urlencode("Missing or invalid date/time data."));
    exit;
}

mysqli_begin_transaction($conn);

try {
    // 1. Check the current rental request status
    $check_query = "SELECT request_status FROM rental_requests WHERE request_id = ? AND user_id = ?";
    $stmt_check = $conn->prepare($check_query);
    if ($stmt_check === false) { throw new Exception("SQL Prepare Failed (Check): " . $conn->error); }
    $stmt_check->bind_param("ii", $request_id, $current_user_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $rental_check = $result_check->fetch_assoc();
    $stmt_check->close();

    if (!$rental_check) {
        throw new Exception("Rental request not found.");
    }

    // CASE 1: Initial Early Return Request (from 'Picked Up' status)
    if ($is_initial_request == 1 && $rental_check['request_status'] === 'Picked Up') {
        // Create a new rental_return_requests record and update rental_requests status
        
        // First, check if a rental_return_requests record already exists
        $check_return_query = "SELECT request_id FROM rental_return_requests WHERE request_id = ?";
        $stmt_check_return = $conn->prepare($check_return_query);
        if ($stmt_check_return === false) { throw new Exception("SQL Prepare Failed (Return Check): " . $conn->error); }
        $stmt_check_return->bind_param("i", $request_id);
        $stmt_check_return->execute();
        $return_check = $stmt_check_return->get_result();
        $stmt_check_return->close();

        if ($return_check->num_rows === 0) {
            // Create new rental_return_requests record with initial schedule
            $insert_return_sql = "
                INSERT INTO rental_return_requests (request_id, user_id, status, scheduled_return_date, scheduled_return_time)
                VALUES (?, ?, 'Pending', ?, ?)
            ";
            $stmt_insert = $conn->prepare($insert_return_sql);
            if ($stmt_insert === false) { throw new Exception("SQL Prepare Failed (Insert Return): " . $conn->error); }
            $stmt_insert->bind_param("iiss", $request_id, $current_user_id, $schedule_date, $schedule_time);
            if (!$stmt_insert->execute()) {
                throw new Exception("Error creating return request: " . $stmt_insert->error);
            }
            $stmt_insert->close();
        } else {
            // Update existing record
            $update_return_sql = "
                UPDATE rental_return_requests 
                SET scheduled_return_date = ?, 
                    scheduled_return_time = ?,
                    status = 'Pending'
                WHERE request_id = ?
            ";
            $stmt_update_return = $conn->prepare($update_return_sql);
            if ($stmt_update_return === false) { throw new Exception("SQL Prepare Failed (Update Return): " . $conn->error); }
            $stmt_update_return->bind_param("ssi", $schedule_date, $schedule_time, $request_id);
            if (!$stmt_update_return->execute()) {
                throw new Exception("Error updating return request: " . $stmt_update_return->error);
            }
            $stmt_update_return->close();
        }

        // Update rental_requests status to 'Early_Return_Scheduled' (not 'Early Return Requested')
        $update_rental_sql = "
            UPDATE rental_requests 
            SET request_status = 'Early_Return_Scheduled' 
            WHERE request_id = ? AND user_id = ? AND request_status = 'Picked Up'
        ";
        $stmt_rental = $conn->prepare($update_rental_sql);
        if ($stmt_rental === false) { throw new Exception("SQL Prepare Failed (Rental Status Update): " . $conn->error); }
        $stmt_rental->bind_param("ii", $request_id, $current_user_id);
        if (!$stmt_rental->execute()) {
            throw new Exception("Error updating rental status: " . $stmt_rental->error);
        }
        $stmt_rental->close();

        mysqli_commit($conn);
        
        $success_message = urlencode("Early Return request submitted with scheduled date. Awaiting Admin's approval.");
        header("Location: customer_lifecycle.php?success_schedule={$success_message}");
        exit;
    }
    
    // CASE 2: Scheduling approved early return (from 'Early_Return_Approved' status)
    elseif ($rental_check['request_status'] === 'Early_Return_Approved') {
        // 2. Update the customer's proposed schedule in the rental_return_requests table
        // This is where the initial date and time are added.
        $update_schedule_sql = "
            UPDATE rental_return_requests 
            SET scheduled_return_date = ?, 
                scheduled_return_time = ? 
            WHERE request_id = ? AND user_id = ? AND status = 'Approved'
        ";
        $stmt_schedule = $conn->prepare($update_schedule_sql);
        if ($stmt_schedule === false) {
            throw new Exception("SQL Prepare Failed (Schedule Update): " . $conn->error);
        }
        $stmt_schedule->bind_param("ssii", 
            $schedule_date, 
            $schedule_time, 
            $request_id, 
            $current_user_id
        ); 

        if (!$stmt_schedule->execute()) {
            throw new Exception("Error updating early return schedule: " . $stmt_schedule->error);
        }
        if ($stmt_schedule->affected_rows === 0) {
            throw new Exception("Return request not found or status not 'Approved'. Update aborted.");
        }
        $stmt_schedule->close();

        // 3. Update status in rental_requests to 'Early_Return_Scheduled'
        $update_rental_sql = "
            UPDATE rental_requests 
            SET request_status = 'Early_Return_Scheduled' 
            WHERE request_id = ? AND user_id = ? AND request_status = 'Early_Return_Approved'
        ";
        $stmt_rental = $conn->prepare($update_rental_sql);
        if ($stmt_rental === false) { throw new Exception("SQL Prepare Failed (Rental Status Update): " . $conn->error); }
        $stmt_rental->bind_param("ii", $request_id, $current_user_id);
        if (!$stmt_rental->execute()) {
            throw new Exception("Error updating rental status: " . $stmt_rental->error);
        }
        $stmt_rental->close();

        mysqli_commit($conn);
        
        $success_message = urlencode("Early Return scheduled successfully. Awaiting Admin's final processing.");
        header("Location: customer_lifecycle.php?success_schedule={$success_message}");
        exit;
    }
    
    else {
        throw new Exception("Invalid rental status for early return scheduling. Current status: " . $rental_check['request_status']);
    }

} catch (Exception $e) {
    mysqli_rollback($conn);
    error_log("Early Return Schedule failed: " . $e->getMessage());
    header("Location: customer_lifecycle.php?error=" . urlencode("Database transaction failed: " . $e->getMessage()));
    exit;
}
?>