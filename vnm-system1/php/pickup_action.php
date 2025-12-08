<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start(); 
include 'db.php'; 

$admin_id = $_SESSION['admin_id'] ?? 1; 

// 1. Input Validation and Sanitization
if (!isset($_POST['request_id'], $_POST['car_id'], $_POST['odometer'], $_POST['condition'], $_POST['action']) || $_POST['action'] !== 'confirm_pickup') {
    header("Location: rentals.php?error=" . urlencode("Missing or invalid input data."));
    exit;
}

$request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
$car_id = filter_input(INPUT_POST, 'car_id', FILTER_VALIDATE_INT);
$odometer = filter_input(INPUT_POST, 'odometer', FILTER_VALIDATE_INT);
$condition = trim($_POST['condition']);

if (!$request_id || !$car_id || $odometer === false || $odometer < 0 || empty($condition)) {
    header("Location: car_pickup.php?request_id={$request_id}&error=" . urlencode("Invalid data provided for odometer or condition notes."));
    exit;
}

$conn->begin_transaction();

try {
    // A. Insert pickup details into the dedicated rental_pickup_details table
    $sql_pickup_insert = "
        INSERT INTO rental_pickup_details (
            request_id, 
            pickup_admin_id, 
            pickup_date_actual,
            car_condition_pickup, 
            odometer_pickup
        ) VALUES (?, ?, NOW(), ?, ?)
    ";
    $stmt_insert = $conn->prepare($sql_pickup_insert);

    if ($stmt_insert === false) {
        throw new Exception("SQL Prepare Failed (Pickup Insert): " . $conn->error);
    }
    
    $stmt_insert->bind_param("iisi", $request_id, $admin_id, $condition, $odometer);
    
    if (!$stmt_insert->execute() || $stmt_insert->affected_rows !== 1) {
        throw new Exception("Failed to record pickup details. (Request may already be picked up).");
    }
    $stmt_insert->close();
    
    // B. Update rental_requests status to 'Picked Up'
    $sql_rental_update = "
        UPDATE rental_requests 
        SET request_status = 'Picked Up'
        WHERE request_id = ? AND request_status = 'Approved'
    ";
    $stmt_rental = $conn->prepare($sql_rental_update);
    $stmt_rental->bind_param("i", $request_id);

    if (!$stmt_rental->execute() || $stmt_rental->affected_rows !== 1) {
        throw new Exception("Failed to update rental request status. (Request may not be in 'Approved' status).");
    }
    $stmt_rental->close();

    // C. FIX: Update car availability status to 0 (Unavailable/Rented)
    $sql_car = "
        UPDATE cars 
        SET availability = 0 
        WHERE car_id = ? AND availability = 1
    ";
    $stmt_car = $conn->prepare($sql_car);

    if ($stmt_car === false) {
        throw new Exception("SQL Prepare Failed (Car Update): " . $conn->error);
    }
    
    $stmt_car->bind_param("i", $car_id);
    
    if (!$stmt_car->execute() && $stmt_car->affected_rows !== 0) { 
        error_log("Warning: Car $car_id availability was not updated to 0 (may already be set).");
    }
    $stmt_car->close();
    
    // 3. Commit Transaction and Redirect on Success
    $conn->commit();
    header("Location: car_pickup.php?request_id=$request_id&success=1"); 
    exit;

} catch (Exception $e) {

    // 4. Rollback on Error and Redirect
    $conn->rollback();
    error_log("Car Pick Up Transaction Failed: " . $e->getMessage());
    header("Location: car_pickup.php?request_id=$request_id&error=" . urlencode("Transaction failed: " . $e->getMessage()));
    exit;
}
?>