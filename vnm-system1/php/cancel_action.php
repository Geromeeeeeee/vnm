<?php
/**
 * cancel_action.php
 * Handles customer-initiated cancellation of a rental request.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'db.php'; 

// Require login
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$current_user_id = (int) $_SESSION['user'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['request_id']) || $_POST['action'] !== 'cancel') {
    header("Location: rentalsc.php?error=access_denied");
    exit;
}

$request_id = filter_var($_POST['request_id'], FILTER_SANITIZE_NUMBER_INT);
$status = 'Cancelled'; 

// 1. Verify the rental belongs to the current user
$verify_sql = "SELECT user_id FROM rental_requests WHERE request_id = ?";
$stmt_verify = $conn->prepare($verify_sql);
$stmt_verify->bind_param("i", $request_id);
$stmt_verify->execute();
$verify_result = $stmt_verify->get_result();

if ($verify_result->num_rows === 0) {
    header("Location: rentalsc.php?error=not_found");
    $stmt_verify->close();
    exit;
}

$rental = $verify_result->fetch_assoc();
if ((int)$rental['user_id'] !== $current_user_id) {
    header("Location: rentalsc.php?error=unauthorized");
    $stmt_verify->close();
    exit;
}
$stmt_verify->close();

// 2. Begin Transaction 
$conn->begin_transaction();
$success = false;

try {
    // A. Update rental request status to 'Cancelled'
    $update_request_sql = "
        UPDATE rental_requests 
        SET request_status = ? 
        WHERE request_id = ? 
        AND request_status IN ('Pending', 'Approved')";
        
    $stmt = $conn->prepare($update_request_sql);
    // Bind only the status and request_id
    $stmt->bind_param("si", $status, $request_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update request status.");
    }
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("Request not found or already processed.");
    }
    $stmt->close();
    
    // B. If the request was approved, make the car available again.
    $car_id_sql = "SELECT car_id FROM rental_requests WHERE request_id = ?";
    $stmt_car = $conn->prepare($car_id_sql);
    $stmt_car->bind_param("i", $request_id);
    $stmt_car->execute();
    $result = $stmt_car->get_result();
    $car_id = $result->fetch_assoc()['car_id'];
    $stmt_car->close();

    // Set car availability back to 1 (available)
    $update_car_sql = "UPDATE cars SET is_available = 1 WHERE car_id = ?";
    $stmt_car_avail = $conn->prepare($update_car_sql);
    $stmt_car_avail->bind_param("i", $car_id);
    if (!$stmt_car_avail->execute()) {
        throw new Exception("Failed to update car availability.");
    }
    $stmt_car_avail->close();

    // C. Commit transaction if all steps succeeded
    $conn->commit();
    $success = true;

} catch (Exception $e) {
    $conn->rollback();
    error_log("Cancellation failed: " . $e->getMessage());
    $success = false;
}

$conn->close();

if ($success) {
    header("Location: rentalsc.php?status=cancelled");
} else {
    header("Location: rentalsc.php?error=cancel_failed");
}
exit;
?>