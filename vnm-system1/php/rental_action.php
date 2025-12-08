<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = filter_var($_POST['request_id'], FILTER_SANITIZE_NUMBER_INT);
    $action = $_POST['action'];

    if ($action === 'approve') {
        $status = 'Approved';
        
        $conn->begin_transaction();
        $success = false;
        
        try {
            // 1. Get car ID
            $car_id_sql = "SELECT car_id FROM rental_requests WHERE request_id = ?";
            $stmt = $conn->prepare($car_id_sql);
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $car_id = $row['car_id'];
            } else {
                throw new Exception("Request ID not found.");
            }
            $stmt->close();
            
            // 2. Update rental request status
            $update_request_sql = "UPDATE rental_requests SET request_status = ? WHERE request_id = ?";
            $stmt = $conn->prepare($update_request_sql);
            $stmt->bind_param("si", $status, $request_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update request status.");
            }
            $stmt->close();

            // 3. Update car availability status to 0 (Unavailable)
            $update_car_sql = "UPDATE cars SET availability = 0 WHERE car_id = ?";
            $stmt = $conn->prepare($update_car_sql);
            if ($stmt === false) {
                 throw new Exception("SQL Prepare Failed (Car Update): " . $conn->error);
            }
            $stmt->bind_param("i", $car_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update car availability.");
            }
            $stmt->close();
            
            $conn->commit();
            $success = true;

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Approval failed: " . $e->getMessage());
            $success = false;
        }

        if ($success) {
            header("Location: rentals.php?status=approved");
        } else {
            header("Location: rentals.php?error=approval_failed");
        }

    } elseif ($action === 'decline') {
        $status = 'Rejected';
        
        $update_request_sql = "UPDATE rental_requests SET request_status = ? WHERE request_id = ?";
        $stmt = $conn->prepare($update_request_sql);
        $stmt->bind_param("si", $status, $request_id);

        if ($stmt->execute()) {
            header("Location: rentals.php?status=declined");
        } else {
            error_log("Decline failed: " . $conn->error);
            header("Location: rentals.php?error=decline_failed");
        }
        $stmt->close();
    }
} else {
    header("Location: rentals.php?error=invalid_action");
}
?>