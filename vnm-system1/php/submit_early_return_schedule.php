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

if (!$request_id || !$schedule_date || !$schedule_time) {
    header("Location: customer_lifecycle.php?error=" . urlencode("Missing or invalid date/time data."));
    exit;
}

mysqli_begin_transaction($conn);

try {
    // 1. Check if the rental request is in the 'Early_Return_Approved' state for this user
    $check_query = "SELECT request_status FROM rental_requests WHERE request_id = ? AND user_id = ?";
    $stmt_check = $conn->prepare($check_query);
    if ($stmt_check === false) { throw new Exception("SQL Prepare Failed (Check): " . $conn->error); }
    $stmt_check->bind_param("ii", $request_id, $current_user_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $rental_check = $result_check->fetch_assoc();
    $stmt_check->close();

    if (!$rental_check || $rental_check['request_status'] !== 'Early_Return_Approved') {
        throw new Exception("Rental request is not approved for early return scheduling (Status: " . ($rental_check['request_status'] ?? 'N/A') . ").");
    }

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

} catch (Exception $e) {
    mysqli_rollback($conn);
    error_log("Early Return Schedule failed: " . $e->getMessage());
    header("Location: customer_lifecycle.php?error=" . urlencode("Database transaction failed: " . $e->getMessage()));
    exit;
}
?>