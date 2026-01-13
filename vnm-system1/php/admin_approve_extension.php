<?php
// admin_approve_extension.php
// Handles Admin approval/rejection of rental extension requests
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'db.php'; // Assuming db.php handles the $conn connection object

// NOTE: Add Admin login/authentication here in a production environment

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

$extension_id = filter_input(INPUT_POST, 'extension_id', FILTER_VALIDATE_INT);
$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING); // 'approve' or 'reject'

if (!$extension_id || empty($action)) {
    $response['message'] = 'Missing extension ID or action.';
    echo json_encode($response);
    exit();
}

if ($action === 'reject') {
    // --- Reject Logic ---
    $update_extension_sql = "UPDATE rental_extension_requests SET status = 'Rejected' WHERE extension_id = ?";
    $stmt = $conn->prepare($update_extension_sql);
    $stmt->bind_param("i", $extension_id);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Rental extension request rejected successfully.';
    } else {
        $response['message'] = 'Database error on rejection: ' . $stmt->error;
    }
    $stmt->close();

} elseif ($action === 'approve') {
    // --- Approve Logic ---
    
    // 1. Get extension details and current rental details
    $extension_query = "SELECT 
                        re.request_id, 
                        r.rental_duration_days, 
                        r.total_cost, 
                        re.days_to_extend, 
                        re.additional_cost, 
                        re.new_end_date 
                        FROM rental_extension_requests re
                        JOIN rental_requests r ON re.request_id = r.request_id
                        WHERE re.extension_id = ?";
    $stmt = $conn->prepare($extension_query);
    $stmt->bind_param("i", $extension_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $extension_data = $result->fetch_assoc();
    $stmt->close();

    if ($extension_data) {
        $request_id = $extension_data['request_id'];
        $current_duration = $extension_data['rental_duration_days'];
        $current_total_cost = $extension_data['total_cost'];
        $days_to_extend = $extension_data['days_to_extend'];
        $additional_cost_paid = $extension_data['additional_cost'];
        $new_end_date = $extension_data['new_end_date'];

        // Calculate new values
        $new_duration = $current_duration + $days_to_extend;
        $new_total_cost = $current_total_cost + $additional_cost_paid; 

        // 2. Start Transaction
        $conn->begin_transaction();

        try {
            // A. Update rental_requests table
            // NOTE: There is no column for 'requested_extension_date' in vnm.sql rental_requests, 
            // so we only update duration and cost.
            $update_rental_sql = "UPDATE rental_requests SET rental_duration_days = ?, total_cost = ? WHERE request_id = ?";
            $stmt_rental = $conn->prepare($update_rental_sql);
            $stmt_rental->bind_param("idi", $new_duration, $new_total_cost, $request_id);
            $stmt_rental->execute();

            // B. Update rental_extension_requests table
            $update_extension_sql = "UPDATE rental_extension_requests SET status = 'Approved' WHERE extension_id = ?";
            $stmt_extension = $conn->prepare($update_extension_sql);
            $stmt_extension->bind_param("i", $extension_id);
            $stmt_extension->execute();

            // C. Commit Transaction
            $conn->commit();

            $response['success'] = true;
            $response['message'] = 'Extension approved. Rental ID ' . $request_id . ' duration updated to ' . $new_duration . ' days (New Total Cost: ₱' . number_format($new_total_cost, 2) . ').';

        } catch (Exception $e) {
            // D. Rollback on error
            $conn->rollback();
            $response['message'] = 'Transaction failed during approval: ' . $e->getMessage();
        }

    } else {
        $response['message'] = 'Extension request data not found.';
    }
} else {
    $response['message'] = 'Invalid action specified.';
}

$conn->close();
echo json_encode($response);
?>