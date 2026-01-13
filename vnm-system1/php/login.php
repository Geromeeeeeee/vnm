<?php
session_start();

// CONNECT TO DATABASE
$host = "localhost";
$user = "root";
$pass = "";
$db   = "vnm";    

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    // Note: In a production environment, avoid exposing detailed error messages.
    die("Database connection failed: " . $conn->connect_error);
}

// Variables to hold status messages
$signup_error = "";
$signup_success = "";
$login_error = "";
$initial_form = "login"; // Default form to show

/* ------------------- SIGNUP ------------------- */
if (isset($_POST['signup'])) {

    $fullname = trim($_POST['fullname']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $address  = trim($_POST['address']);
    $license  = trim($_POST['license']);
    $password = $_POST['password']; 

    $initial_form = "signup"; // Keep signup form active if signing up

    // --- Server-Side Validation ---

    // Full name validation
    $nameParts = array_filter(explode(" ", $fullname));
    if (count($nameParts) < 2) {
        $signup_error = "Full name must include first and last name.";
    } elseif (!preg_match("/^[a-zA-Z ]+$/", $fullname)) {
        $signup_error = "Full name must contain letters only.";
    } elseif (strlen($fullname) < 5) {
        $signup_error = "Full name is too short.";
    }

    // Email validation
    if (empty($signup_error)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $signup_error = "Invalid email format.";
        }
        // DNS check is removed here to prevent "domain doesn't exist" errors on local servers
    }

    // Password validation
    if (empty($signup_error)) {
        if (strlen($password) < 8 || strlen($password) > 15) {
            $signup_error = "Password must be 8–15 characters.";
        }
    }
    
    // License validation
    if (empty($signup_error)) {
        if (!preg_match("/^[A-Z]{3}\s?\d{2,4}$/", $license)) {
            $signup_error = "Invalid license format. Expected: ABC 123 or ABC 1234.";
        }
    }

    // Check email exists - Using TRIM in SQL to handle existing messy data
    if (empty($signup_error)) {
        $check = $conn->prepare("SELECT user_id FROM users WHERE TRIM(email)=?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) $signup_error = "Email already exists!";
        $check->close();
    }

    // Insert user
    if (empty($signup_error)) {
      $hashed_password = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $conn->prepare("INSERT INTO users(fullname,email,phone,address,license,password) 
                  VALUES (?, ?, ?, ?, ?, ?)");
      $stmt->bind_param("ssssss", $fullname, $email, $phone, $address, $license, $hashed_password);
      if ($stmt->execute()) {
        $signup_success = "Account created successfully! You can now log in.";
        $initial_form = "login"; 
      } else {
        $signup_error = "Failed to create account.";
      }
      $stmt->close();
    }
}

/* ------------------- LOGIN ------------------- */
if (isset($_POST['login'])) {

    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $initial_form = "login"; 

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $login_error = "Invalid email format.";
    } else {
        // TRIM(email) handles the leading space in your SQL dump (e.g., user 9)
        $stmt = $conn->prepare("SELECT user_id, password FROM users WHERE TRIM(email)=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $data = $result->fetch_assoc();
            $stored_password = $data['password'];

            // 1. Try secure hash verification first
            if (password_verify($password, $stored_password)) {
                $_SESSION['user'] = $data['user_id'];
                header("Location: login-dashboard.php");
                exit;
            } 
            // 2. FALLBACK: Check plain text for users like 'Gregory' or 'Gab' in your SQL
            elseif ($password === $stored_password) {
                $_SESSION['user'] = $data['user_id'];
                header("Location: login-dashboard.php");
                exit;
            } else {
                $login_error = "Incorrect password!";
            }
        } else {
            $login_error = "Account not found!";
        }
        $stmt->close();
    }
}

$conn->close();

include '../html/login.html';
?>