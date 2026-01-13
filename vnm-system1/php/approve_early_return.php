<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'db.php'; 

// Basic check for admin session can be added here
// if (!isset($_SESSION['admin_id'])) { header('Location: admin_login.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'approve_early_return') {
    header('Location: car_lifecycle.php');
    exit;
}

$request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

if (!$request_id || !$user_id) {
    header("Location: car_lifecycle.php?error=" . urlencode("Missing or invalid request ID or user ID."));
    exit;
}

mysqli_begin_transaction($conn);

try {
    // 1. Update status in rental_requests to 'Early_Return_Approved'
    // This status tells the customer they must now provide the return schedule.
    $update_rental_sql = "
        UPDATE rental_requests 
        SET request_status = 'Early_Return_Approved' 
        WHERE request_id = ? AND user_id = ? AND request_status = 'Early Return Requested'
    ";
    $stmt_rental = $conn->prepare($update_rental_sql);
    if ($stmt_rental === false) {
        throw new Exception("SQL Prepare Failed (Rental Update): " . $conn->error);
    }
    $stmt_rental->bind_param("ii", $request_id, $user_id);
    if (!$stmt_rental->execute()) {
        throw new Exception("Error updating rental status: " . $stmt_rental->error);
    }
    if ($stmt_rental->affected_rows === 0) {
        throw new Exception("Rental request status changed or not found (Expected 'Early Return Requested'). Update aborted.");
    }
    $stmt_rental->close();

    // 2. Update status in rental_return_requests to 'Approved'
    $update_return_req_sql = "
        UPDATE rental_return_requests 
        SET status = 'Approved' 
        WHERE request_id = ? AND user_id = ? AND status = 'pending'
    ";
    $stmt_return_req = $conn->prepare($update_return_req_sql);
    if ($stmt_return_req === false) {
        throw new Exception("SQL Prepare Failed (Return Request Update): " . $conn->error);
    }
    $stmt_return_req->bind_param("ii", $request_id, $user_id);
    if (!$stmt_return_req->execute()) {
        throw new Exception("Error updating return request status: " . $stmt_return_req->error);
    }
    $stmt_return_req->close();
    
    mysqli_commit($conn);
    
    $success_message = urlencode("Early Return Request ID {$request_id} approved. Customer must now schedule the return.");
    header("Location: car_lifecycle.php?success={$success_message}");
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    error_log("Early Return Approval failed: " . $e->getMessage());
    header("Location: car_lifecycle.php?error=" . urlencode("Database transaction failed: " . $e->getMessage()));
    exit;
}
?>