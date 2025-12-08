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
    header("Location: car_return.php?request_id={$request_id}&error=" . urlencode("Missing or invalid input data."));
    exit;
}

$check_query = "SELECT request_status FROM rental_requests WHERE request_id = ?";
$stmt_check = $conn->prepare($check_query);
if ($stmt_check === false) {
    header("Location: car_lifecycle.php?error=" . urlencode("System error during rental status check."));
    exit;
}
    $stmt_check->bind_param("i", $request_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $rental_check = $result_check->fetch_assoc();
    $stmt_check->close();

if (!$rental_check) {
    header("Location: car_lifecycle.php?error=" . urlencode("Rental request ID not found in the database."));
    exit;
}

    $current_status = $rental_check['request_status'];
if ($current_status !== 'Picked Up') {
    $error_msg = "Rental cannot be processed for return. Current status is '{$current_status}'. It must be 'Picked Up'.";
    header("Location: car_return.php?request_id={$request_id}&error=" . urlencode($error_msg));
    exit;
}

mysqli_begin_transaction($conn);

try {
    $insert_sql = "
        INSERT INTO rental_return_details (
            request_id, 
            return_admin_id, 
            return_date_actual, 
            car_condition_return, 
            odometer_return,
            damage_fee
        ) VALUES (?, ?, ?, ?, ?, ?)
    ";
    $stmt_insert = $conn->prepare($insert_sql);
    if ($stmt_insert === false) {
        throw new Exception("SQL Prepare Failed (Return Insert): " . $conn->error);
    }
    $stmt_insert->bind_param("iissid", 
        $request_id, 
        $admin_id, 
        $return_date_time, 
        $return_condition, 
        $return_odometer, 
        $damage_fee
    ); 

    if (!$stmt_insert->execute()) {
        throw new Exception("Error inserting return details: " . $stmt_insert->error);
    }
    $stmt_insert->close();

    $update_rental_sql = "
        UPDATE rental_requests 
        SET request_status = 'Returned' 
        WHERE request_id = ? AND request_status = 'Picked Up'
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
        throw new Exception("Rental status changed unexpectedly during transaction. Update aborted.");
    }
    $stmt_rental->close();

    $update_car_sql = "
        UPDATE cars 
        SET availability = 1 
        WHERE car_id = ?
    ";
    $stmt_car = $conn->prepare($update_car_sql);
    if ($stmt_car === false) {
        throw new Exception("SQL Prepare Failed (Car Update): " . $conn->error);
    }
    $stmt_car->bind_param("i", $car_id);
    if (!$stmt_car->execute()) {
        throw new Exception("Error updating car availability: " . $stmt_car->error);
    }
    $stmt_car->close();

    mysqli_commit($conn);
    
    $success_message = urlencode("Rental ID {$request_id} successfully closed. Car is now available.");
    header("Location: car_lifecycle.php?success={$success_message}");
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    error_log("Return Transaction failed: " . $e->getMessage());
    header("Location: car_return.php?request_id={$request_id}&error=" . urlencode("Database transaction failed: " . $e->getMessage()));
    exit;
}
?>