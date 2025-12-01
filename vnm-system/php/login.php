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

    // --- Server-Side Validation (Re-implemented for robustness) ---

    // Full name validation
    $nameParts = array_filter(explode(" ", $fullname));
    if (count($nameParts) < 2) {
        $signup_error = "Full name must include first and last name.";
    } elseif (!preg_match("/^[a-zA-Z ]+$/", $fullname)) {
        $signup_error = "Full name must contain letters only.";
    } elseif (strlen($fullname) < 5) {
        $signup_error = "Full name is too short.";
    } elseif (strtolower($nameParts[0]) == strtolower($nameParts[1])) {
        $signup_error = "First and last name cannot be identical.";
    }

    // Email validation
    if (empty($signup_error)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $signup_error = "Invalid email format.";
        } else {
            // FIXED DNS VALIDATION (MX OR A record)
            $domain = substr(strrchr($email, "@"), 1);
            // Note: checkdnsrr might be slow or blocked on some hosting.
            if (!(checkdnsrr($domain, "MX") || checkdnsrr($domain, "A"))) {
                $signup_error = "Email domain does not exist.";
            }
        }
    }

    // Password validation
    if (empty($signup_error)) {
        if (strlen($password) < 8 || strlen($password) > 15) {
            $signup_error = "Password must be 8–15 characters.";
        } elseif (!preg_match("/[A-Za-z]/", $password) || !preg_match("/[0-9]/", $password)) {
            $signup_error = "Password must contain letters and numbers.";
        }
    }
    
    // License validation (optional, but good practice for server-side)
    if (empty($signup_error)) {
        if (!preg_match("/^[A-Z]{3}\s?\d{2,4}$/", $license)) {
            $signup_error = "Invalid license format. Expected: ABC 123 or ABC 1234.";
        }
    }

    // Check email exists
    if (empty($signup_error)) {
        $check = $conn->prepare("SELECT * FROM users WHERE email=?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) $signup_error = "Email already exists!";
    }

    // Insert user
    if (empty($signup_error)) {
      $hashed_password = password_hash($password, PASSWORD_DEFAULT);
      // NOTE: Ensure your `users` table fields (phone, address, license) are large enough to hold the data.
      $stmt = $conn->prepare("INSERT INTO users(fullname,email,phone,address,license,password) 
                  VALUES (?, ?, ?, ?, ?, ?)");
      $stmt->bind_param("ssssss", $fullname, $email, $phone, $address, $license, $hashed_password);
      if ($stmt->execute()) {
        $signup_success = "Account created successfully! You can now log in.";
        $initial_form = "login"; // Switch to login after successful signup
      } else {
        $signup_error = "Failed to create account. Please try again.";
        $initial_form = "signup";
      }
      $stmt->close();
    }
}

/* ------------------- LOGIN ------------------- */
if (isset($_POST['login'])) {

    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $initial_form = "login"; // Keep login form active if logging in

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $login_error = "Invalid email format.";
    } else {
        $stmt = $conn->prepare("SELECT id, password FROM users WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
          $data = $result->fetch_assoc();
          if (password_verify($password, $data['password'])) {
            $_SESSION['user'] = $data['id'];
            // Successful login, redirect
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

// Change the include path to go up one directory (..) and then into 'html'
include '../html/login.html';
?>