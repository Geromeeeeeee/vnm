<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action']) && $_POST['action'] === 'delete') {
    $request_id = filter_var($_POST['request_id'], FILTER_SANITIZE_NUMBER_INT);

    $delete_sql = "DELETE FROM rental_requests WHERE request_id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $request_id);

    if ($stmt->execute()) {
        header("Location: rentals.php?status=deleted");
    } else {
        error_log("Deletion failed: " . $conn->error);
        header("Location: rentals.php?error=delete_failed");
    }
    $stmt->close();

} else {
    header("Location: rentals.php?error=missing_data");
}
$conn->close();
exit;
?>