<?php

include 'db.php'; 


$user_id = 9; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login-dashboard.php"); 
    exit;
}

$car_id = $_POST['car_id'] ?? null;


$license_photo_path = null;
$upload_dir = '../uploads/licenses/'; 

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
    
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            error_log("Failed to create upload directory: " . $upload_dir);
            header("Location: rent_form.php?car_id=$car_id&error=server_config_error");
            exit;
        }
    }

    if (move_uploaded_file($file_tmp, $target_file)) {
        $license_photo_path = 'uploads/licenses/' . $unique_name; 
    } else {
        error_log("File upload move failed for request from user $user_id. Target: $target_file");
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



$stmt = $conn->prepare("INSERT INTO rental_requests (user_id, car_id, driver_license_photo, rental_date, rental_time, rental_duration_days, total_cost, request_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
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
    header("Location: ../php/rentalsc.php?success=pending");
    exit;
} else {

    error_log("Database error on rental request: " . $stmt->error);
    header("Location: rent_form.php?car_id=" . $car_id . "&error=db_insert_failed");
    exit;
}

$stmt->close();
$conn->close();
?>