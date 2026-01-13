<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Dynamic path checking for db.php
    if (file_exists('db.php')) { 
        include 'db.php'; 
    } elseif (file_exists('../db.php')) { 
        include '../db.php'; 
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Collect and sanitize inputs
        $user_id = intval($_POST['user_id']);
        $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $license = mysqli_real_escape_string($conn, $_POST['license']);
        $address = mysqli_real_escape_string($conn, $_POST['address']);

        // Update query (Matching your SQL schema)
        $sql = "UPDATE users SET 
                fullname = '$fullname', 
                email = '$email', 
                phone = '$phone', 
                license = '$license', 
                address = '$address' 
                WHERE user_id = $user_id";

        if (mysqli_query($conn, $sql)) {
            // Success: Redirect back to the management page
            header("Location: manage_accounts.php?msg=update_success");
        } else {
            // Error: Show the database error
            echo "Error updating record: " . mysqli_error($conn);
        }
    } else {
        // Redirect if accessed directly without POST
        header("Location: manage_accounts.php");
    }
?>