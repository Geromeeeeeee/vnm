<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'])) {
    $request_id = filter_var($_POST['request_id'], FILTER_SANITIZE_NUMBER_INT);
    
    // Update payment_status to 'Paid'
    $update_sql = "UPDATE rental_requests SET payment_status = 'Paid' WHERE request_id = ?";
    $stmt = $conn->prepare($update_sql);
    
    if ($stmt) {
        $stmt->bind_param("i", $request_id);
        if ($stmt->execute()) {
            header("Location: rentals.php?success=payment_approved");
        } else {
            error_log("Payment approval failed: " . $stmt->error);
            header("Location: rentals.php?error=payment_approval_failed");
        }
        $stmt->close();
    } else {
        error_log("Database Prepare Error for Payment Approval: " . $conn->error);
        header("Location: rentals.php?error=db_prepare_failed");
    }
} else {
    header("Location: rentals.php?error=invalid_request");
}
?>
