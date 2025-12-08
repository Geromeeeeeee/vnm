<?php

session_start();
include 'db.php'; 

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user']; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login-dashboard.php"); 
    exit;
}

$car_id = $_POST['car_id'] ?? null;


$license_photo_path = null;
$upload_dir = '../uploads/licenses/'; 

// Ensure upload directory exists
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        error_log("Failed to create upload directory: " . $upload_dir);
        header("Location: rent_form.php?car_id=$car_id&error=server_config_error");
        exit;
    }
}

if (isset($_FILES['driver_license_photo']) && $_FILES['driver_license_photo']['error'] === UPLOAD_ERR_OK) {
    $file_tmp = $_FILES['driver_license_photo']['tmp_name'];
    $file_name = basename($_FILES['driver_license_photo']['name']);
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    $unique_name = uniqid('license_', true) . '.' . $file_ext;
    $target_file = $upload_dir . $unique_name;

    $allowed_types = ['jpg', 'jpeg', 'png'];
    if (!in_array($file_ext, $allowed_types)) {
        header("Location: rent_form.php?car_id=$car_id&error=invalid_file_type");
        exit;
    }
    
    if (move_uploaded_file($file_tmp, $target_file)) {
        $license_photo_path = 'uploads/licenses/' . $unique_name; 
    } else {
        error_log("License upload failed for user $user_id.");
        header("Location: rent_form.php?car_id=$car_id&error=file_upload_failed");
        exit;
    }
} else {
    header("Location: rent_form.php?car_id=$car_id&error=license_required");
    exit;
}



$pickup_date = $_POST['pickup'] ?? null;
$pickup_time = $_POST['time'] ?? null;
$duration = filter_var($_POST['duration'], FILTER_VALIDATE_INT);
$total_cost = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
$request_status = 'Pending'; 

if (!$car_id || !$duration || !$total_cost || !strtotime($pickup_date) || !strtotime($pickup_time)) {
    header("Location: rent_form.php?car_id=" . $car_id . "&error=invalid_input");
    exit;
}

// ====================================================================
// NEW FEATURE IMPLEMENTATION: AUTO-CANCEL CONFLICTING REQUESTS
// This logic cancels any existing active but unapproved requests
// for the same car and same rental date.
// ====================================================================

// Statuses that are active/not approved yet
$statuses_to_cancel = ['Pending', 'Proof Uploaded']; 
$status_list = "'" . implode("','", $statuses_to_cancel) . "'";

$cancel_sql = "
    UPDATE rental_requests 
    SET request_status = 'Cancelled', 
        admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n[AUTO CANCELLED] Conflict with new request submitted by User ID $user_id on ', NOW())
    WHERE car_id = ? 
    AND rental_date = ? 
    AND request_status IN ({$status_list})";

$stmt_cancel = $conn->prepare($cancel_sql);

if ($stmt_cancel === false) {
    error_log("Database Prepare Error for Auto Cancel: " . $conn->error);
    // Continue with insertion, but log the error
} else {
    $stmt_cancel->bind_param("is", $car_id, $pickup_date);
    $stmt_cancel->execute();
    $stmt_cancel->close();
}
// ====================================================================


// Insert the new rental request (which will be 'Pending')
$sql = "INSERT INTO rental_requests (user_id, car_id, driver_license_photo, rental_date, rental_time, rental_duration_days, total_cost, request_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    error_log("Database Prepare Error for Insertion: " . $conn->error . " SQL: " . $sql);
    header("Location: rent_form.php?car_id=" . $car_id . "&error=db_prepare_failed");
    exit;
}
    
$stmt->bind_param("iisssids", 
    $user_id, 
    $car_id, 
    $license_photo_path,
    $pickup_date,
    $pickup_time,
    $duration,
    $total_cost,
    $request_status
);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    header("Location: rentalsc.php?success=rental_submitted");
    exit;
} else {
    error_log("DB insertion error: " . $stmt->error);
    $stmt->close();
    $conn->close();
    header("Location: rent_form.php?car_id=" . $car_id . "&error=db_insert_failed");
    exit;
}