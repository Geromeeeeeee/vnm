<?php
session_start();
include 'db.php'; 

// 1. Authentication Check
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user']; 

// 2. Request Method Check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login-dashboard.php"); 
    exit;
}

// 3. Data Collection
$car_id = $_POST['car_id'] ?? null;

$pickup_date = $_POST['pickup'] ?? null;
$pickup_time = $_POST['time'] ?? null;
$duration = $_POST['duration'] ?? null;
$total_cost = $_POST['price'] ?? null;
$request_status = 'Pending';

// ====================================================================
// NEW VALIDATION: Check if date is today or in the past
// ====================================================================
$today = date('Y-m-d');
if (!$pickup_date || $pickup_date <= $today) {
    // Redirect back with an error message
    header("Location: rent_form.php?car_id=" . $car_id . "&error=invalid_date_min_tomorrow");
    exit;
}
// ====================================================================

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

// 4. File Upload Handling
if (isset($_FILES['driver_license_photo']) && $_FILES['driver_license_photo']['error'] === UPLOAD_ERR_OK) {
    $file_tmp = $_FILES['driver_license_photo']['tmp_name'];
    $file_name = basename($_FILES['driver_license_photo']['name']);
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    $unique_name = uniqid('license_', true) . '.' . $file_ext;
    $target_file = $upload_dir . $unique_name;

    $allowed_types = ['jpg', 'jpeg', 'png'];
    if (in_array($file_ext, $allowed_types)) {
        if (move_uploaded_file($file_tmp, $target_file)) {
           $license_photo_path = 'uploads/licenses/' . $unique_name;
        } else {
            header("Location: rent_form.php?car_id=$car_id&error=upload_failed");
            exit;
        }
    } else {
        header("Location: rent_form.php?car_id=$car_id&error=invalid_file_type");
        exit;
    }
} else {
    header("Location: rent_form.php?car_id=$car_id&error=license_required");
    exit;
}

// 5. Auto-Cancel Conflict Checks (Existing Logic)
$status_list = "'Pending', 'Approved'";
$cancel_sql = "UPDATE rental_requests 
    SET request_status = 'Cancelled' 
    WHERE car_id = ? 
    AND rental_date = ? 
    AND request_status IN ({$status_list})";

$stmt_cancel = $conn->prepare($cancel_sql);
if ($stmt_cancel) {
    $stmt_cancel->bind_param("is", $car_id, $pickup_date);
    $stmt_cancel->execute();
    $stmt_cancel->close();
}

// 6. Database Insertion
$sql = "INSERT INTO rental_requests (user_id, car_id, driver_license_photo, rental_date, rental_time, rental_duration_days, total_cost, request_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
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
    header("Location: rentalsc.php?status=success");
} else {
    header("Location: rent_form.php?car_id=$car_id&error=booking_failed");
}

$stmt->close();
$conn->close();
?>