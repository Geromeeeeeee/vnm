<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php'; 

session_start();

// Database Connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "vnm";
$conn = new mysqli($host, $user, $pass, $db);

// FIX 1: CLEAR SESSION ON FRESH VISIT
// If the user visits the page without a POST request or a 'step' parameter, we start fresh at Step 1.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['step'])) {
    unset($_SESSION['reset_step']);
    unset($_SESSION['reset_email']);
}

$message = "";
$message_type = ""; 

// --- PART 1: SEND CODE ---
if (isset($_POST['send_code'])) {
    $email = trim($_POST['email']);
    
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE TRIM(email)=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $code = rand(100000, 999999);
        
        // FIX 2: SYNCHRONIZED EXPIRY
        // We use MySQL's DATE_ADD(NOW()) to ensure the code and the check use the exact same server clock.
        $save = $conn->prepare("INSERT INTO usersreset (email, reset_code, reset_expiry) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE)) 
                                ON DUPLICATE KEY UPDATE reset_code=?, reset_expiry=DATE_ADD(NOW(), INTERVAL 15 MINUTE)");
        $save->bind_param("sss", $email, $code, $code);
        $save->execute();

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'gabbailon5@gmail.com'; 
            $mail->Password   = 'gvaotveeclakpcbi';   
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('no-reply@vnmrental.com', 'VNM Car Rental');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Code';
            $mail->Body    = "Your VNM verification code is: <b>$code</b>";

            if($mail->send()) {
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_step'] = 2;
                // Redirect with ?step=2 to prevent the "Fresh Visit" logic from clearing the session
                header("Location: forgetpassword.php?step=2");
                exit;
            }
        } catch (Exception $e) {
            $message = "Mail Error: {$mail->ErrorInfo}";
            $message_type = "error";
        }
    } else {
        $message = "Email not found in our records.";
        $message_type = "error";
    }
}

// --- PART 2: VERIFY CODE ---
if (isset($_POST['verify_code'])) {
    if (!isset($_SESSION['reset_email'])) {
        $message = "Session expired. Please request a new code.";
        $message_type = "error";
        $_SESSION['reset_step'] = 1;
    } else {
        $email = $_SESSION['reset_email'];
        $code = trim($_POST['code']);

        // Check code and ensure expiry is in the future based on SQL clock
        $check = $conn->prepare("SELECT * FROM usersreset WHERE email=? AND reset_code=? AND reset_expiry > NOW()");
        $check->bind_param("ss", $email, $code);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $_SESSION['reset_step'] = 3;
            header("Location: forgetpassword.php?step=3");
            exit;
        } else {
            $message = "Invalid or expired code.";
            $message_type = "error";
        }
    }
}

// --- PART 3: UPDATE PASSWORD ---
if (isset($_POST['update_password'])) {
    $email = $_SESSION['reset_email'];
    $pass1 = $_POST['new_pass'];
    $pass2 = $_POST['confirm_pass'];

    if ($pass1 !== $pass2) {
        $message = "Passwords do not match!";
        $message_type = "error";
    } else {
        $hashed = password_hash($pass1, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password=? WHERE TRIM(email)=?");
        $update->bind_param("ss", $hashed, $email);
        
        if ($update->execute()) {
            $conn->query("DELETE FROM usersreset WHERE email='$email'");
            session_destroy();
            echo "<script>alert('Password Updated Successfully!'); window.location='login.php';</script>";
            exit;
        }
    }
}

$step = isset($_SESSION['reset_step']) ? $_SESSION['reset_step'] : 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>VNM Car Rental - Reset Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background-color: #f4f7f6; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .reset-container { background: #fff; padding: 40px; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        .logo { width: 80px; margin-bottom: 20px; }
        h2 { color: #222; margin-bottom: 10px; font-weight: 700; }
        p { color: #777; font-size: 13px; margin-bottom: 25px; }
        .status-msg { padding: 10px; border-radius: 5px; font-size: 13px; margin-bottom: 20px; }
        .error { background-color: #ffe5e5; color: #d93025; border: 1px solid #f9cccc; }
        input { width: 100%; padding: 15px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; transition: all 0.3s ease; }
        input:focus { border-color: #333; outline: none; }
        button { width: 100%; padding: 12px; background-color: #333; color: white; border: none; border-radius: 5px; font-size: 16px; font-weight: 700; cursor: pointer; text-transform: uppercase; transition: 0.3s; }
        button:hover { background-color: #555; }
        .back-link { display: block; margin-top: 25px; text-decoration: none; color: #333; font-size: 14px; font-weight: 600; }
    </style>
</head>
<body>
<div class="reset-container">
    <img src="../photos/VNM logo.png" alt="VNM Logo" class="logo">
    <h2>Reset Password</h2>
    <?php if ($message): ?><div class="status-msg error"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($step == 1): ?>
        <p>Enter your email to receive a code.</p>
        <form method="POST"><input type="email" name="email" placeholder="Email Address" required><button type="submit" name="send_code">Send Verification Code</button></form>
    <?php elseif ($step == 2): ?>
        <p>Enter the 6-digit code sent to your email.</p>
        <form method="POST"><input type="text" name="code" placeholder="6-Digit Code" required maxlength="6" style="text-align:center; font-weight:bold;"><button type="submit" name="verify_code">Verify Code</button></form>
    <?php elseif ($step == 3): ?>
        <p>Create and confirm your new password.</p>
        <form method="POST"><input type="password" name="new_pass" placeholder="New Password" required minlength="8"><input type="password" name="confirm_pass" placeholder="Confirm Password" required minlength="8"><button type="submit" name="update_password">Update Password</button></form>
    <?php endif; ?>
    <a href="login.php" class="back-link">Back to Login</a>
</div>
</body>
</html>