<?php
// pickup_action.php (Admin side: Handles recording pickup and setting status to 'Picked Up')
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'db.php'; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'confirm_pickup') {
    header('Location: car_lifecycle.php');
    exit;
}

$request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
$car_id = filter_input(INPUT_POST, 'car_id', FILTER_VALIDATE_INT);
$odometer = filter_input(INPUT_POST, 'odometer', FILTER_VALIDATE_INT);
$condition = filter_input(INPUT_POST, 'condition', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$pickup_date_actual = date('Y-m-d'); 

if (!$request_id || !$car_id || $odometer === false || !$condition) {
    header("Location: car_pickup.php?request_id={$request_id}&error=" . urlencode("Missing or invalid input data."));
    exit;
}

mysqli_begin_transaction($conn);

try {
    // 1. Insert pickup details into rental_pickup_details
    $insert_pickup_sql = "
        INSERT INTO rental_pickup_details 
        (request_id, pickup_date_actual, odometer_pickup, car_condition_pickup) 
        VALUES (?, ?, ?, ?)
    ";
    $stmt_insert = $conn->prepare($insert_pickup_sql);
    if ($stmt_insert === false) {
        throw new Exception("SQL Prepare Failed (Insert Pickup): " . $conn->error);
    }
    $stmt_insert->bind_param("isis", $request_id, $pickup_date_actual, $odometer, $condition); 

    if (!$stmt_insert->execute()) {
        throw new Exception("Error inserting pickup details: " . $stmt_insert->error);
    }
    $stmt_insert->close();

    // 2. CRITICAL STEP: Update rental_requests status to 'Picked Up'
    $update_rental_sql = "
        UPDATE rental_requests 
        SET request_status = 'Picked Up' 
        WHERE request_id = ? AND request_status = 'Approved'
    ";
    $stmt_rental = $conn->prepare($update_rental_sql);
    if ($stmt_rental === false) {
        throw new Exception("SQL Prepare Failed (Rental Update): " . $conn->error);
    }
    $stmt_rental->bind_param("i", $request_id);
    if (!$stmt_rental->execute()) {
        throw new Exception("Error updating rental status: " . $stmt_rental->error);
    }
    if ($stmt_rental->affected_rows === 0) {
        throw new Exception("Rental status was not 'Approved' for pickup. Status update aborted.");
    }
    $stmt_rental->close();

    // 3. REMOVED: Update car availability (Scheduling is now date-based)
    /*
    $update_car_sql = "
        UPDATE cars 
        SET is_available = 0 
        WHERE car_id = ?
    ";
    $stmt_car = $conn->prepare($update_car_sql);
    $stmt_car->bind_param("i", $car_id);
    $stmt_car->execute();
    $stmt_car->close();
    */

    mysqli_commit($conn);
    
    $success_message = urlencode("Car ID {$car_id} picked up successfully. Rental is now active ('Picked Up').");
    header("Location: car_lifecycle.php?success={$success_message}");
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    error_log("Car Pickup failed: " . $e->getMessage());
    header("Location: car_pickup.php?request_id={$request_id}&error=" . urlencode("Transaction failed: " . $e->getMessage()));
    exit;
}
?>