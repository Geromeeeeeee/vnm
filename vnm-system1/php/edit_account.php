<?php
session_start();
include 'db.php'; 

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user'];
$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $address  = trim($_POST['address']);
    $license  = trim($_POST['license']);
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $current_user = $stmt->get_result()->fetch_assoc();

    if (!password_verify($old_password, $current_user['password'])) {
        $error = "Current password is incorrect.";
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } else {
        if (!empty($new_password)) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET fullname=?, email=?, phone=?, address=?, license=?, password=? WHERE user_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssi", $fullname, $email, $phone, $address, $license, $hashed, $user_id);
        } else {
            $sql = "UPDATE users SET fullname=?, email=?, phone=?, address=?, license=? WHERE user_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssi", $fullname, $email, $phone, $address, $license, $user_id);
        }

        if ($stmt->execute()) {
            $success = "Account updated successfully!";
        } else {
            $error = "Error updating account.";
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/login-dashboard.css?v=1.01">
    <title>Account Settings - VNM</title>
    <style>
        .edit-container {
            max-width: 900px; 
            margin: 80px auto;
            padding: 50px;
            background: var(--bg-card);
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(245, 245, 245, 0.57);
            border: 1px solid #333; 
        }

        .edit-container h2 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            color: var(--font-color);
            text-align: center;
        }

        .section-divider {
            height: 2px;
            background: linear-gradient(to right, transparent, var(--accent-color), transparent);
            margin: 20px 0 40px 0;
            border: none;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .form-group { margin-bottom: 25px; }
        .form-group.full-width { grid-column: span 2; }

        .form-group label {
            display: block;
            font-size: 1.1rem;
            margin-bottom: 8px;
            color: #bbb; 
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-group input, .form-group textarea {
            width: 100%;
            padding: 15px;
            font-size: 1.1rem;
            border-radius: 8px;
            border: 2px solid #444; 
            background: #111; 
            color: #fff;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        input:disabled, textarea:disabled {
            border: 2px solid #222 !important; 
            background: #0a0a0a !important;
            color: #888 !important;
            cursor: not-allowed;
        }

        .editing-mode input, .editing-mode textarea {
            border: 2px solid var(--accent-color);
            background: #1a1a1a;
        }

        .btn-group {
            margin-top: 40px;
            display: flex;
            gap: 20px;
            justify-content: center;
            border-top: 1px solid #333; 
            padding-top: 30px;
        }

        .btn-vnm {
            background-color: var(--accent-color);
            color: white;
            padding: 15px 40px;
            font-size: 1.1rem;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            min-width: 180px;
            transition: 0.3s;
        }

        #saveBtn { 
            display: none; 
            background-color: #218838; 
        }
        
        #saveBtn:hover {
            background-color: #1e7e34;
        }

      
        .btn-secondary { background-color: #333; }
        
        .btn-vnm:hover { opacity: 0.9; transform: translateY(-2px); }
    </style>
</head>
<body>
<nav>
    <h3>VNM Car Rental</h3>
    <a href="../php/login-dashboard.php">Home</a>
    <a href="../php/login-dashboard.php#cars">Cars</a> 
    <a href="../php/login-dashboard.php#aboutUs">About</a>
    <a href="../php/rentalsc.php">Rental Requests</a>
    <a href="../php/customer_lifecycle.php">Rental History</a>
    <a href="../php/edit_account.php">Account</a>
    <button popovertarget="logout">Logout</button>
</nav>

    <main>
        <div class="edit-container" id="containerBlock">
            <h2>Account Profile</h2>
            <hr class="section-divider">
            
            <?php if($success): ?> 
                <div style="background: rgba(0,255,0,0.1); border: 1px solid #0f0; color: #0f0; padding: 15px; border-radius: 8px; text-align:center; margin-bottom:20px;">
                    <?php echo $success; ?>
                </div> 
            <?php endif; ?>

            <?php if($error): ?> 
                <div style="background: rgba(255,0,0,0.1); border: 1px solid #f00; color: #f00; padding: 15px; border-radius: 8px; text-align:center; margin-bottom:20px;">
                    <?php echo $error; ?>
                </div> 
            <?php endif; ?>

            <form action="edit_account.php" method="POST" id="profileForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" disabled required>
                    </div>

                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled required>
                    </div>

                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" disabled required>
                    </div>

                    <div class="form-group">
                        <label>Driver's License</label>
                        <input type="text" name="license" value="<?php echo htmlspecialchars($user['license']); ?>" disabled required>
                    </div>

                    <div class="form-group full-width">
                        <label>Home Address</label>
                        <textarea name="address" rows="2" disabled required><?php echo htmlspecialchars($user['address']); ?></textarea>
                    </div>

                    <div id="passwordFields" style="display:none; grid-column: span 2; grid-template-columns: 1fr 1fr; gap: 30px;">
                        <div class="form-group full-width">
                            <label>Current Password </label>
                            <input type="password" name="old_password" id="old_password">
                        </div>
                        <div class="form-group">
                            <label>New Password </label>
                            <input type="password" name="new_password" placeholder="Enter new password">
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" placeholder="Re-type new password">
                        </div>
                    </div>
                </div>

                <div class="btn-group">
                    <button type="button" id="editBtn" class="btn-vnm btn-secondary">Edit Information</button>
                    <button type="submit" id="saveBtn" class="btn-vnm">Save Changes</button>
                    <a href="edit_account.php" class="btn-vnm btn-secondary">Back</a>
                </div>
            </form>
        </div>
    </main>

    <script>
        const editBtn = document.getElementById('editBtn');
        const saveBtn = document.getElementById('saveBtn');
        const profileForm = document.getElementById('profileForm');
        const containerBlock = document.getElementById('containerBlock');
        const inputs = profileForm.querySelectorAll('input, textarea');
        const passwordFields = document.getElementById('passwordFields');
        const oldPassInput = document.getElementById('old_password');

        editBtn.addEventListener('click', () => {
            containerBlock.classList.add('editing-mode');
            
            inputs.forEach(input => {
                input.disabled = false;
            });

            editBtn.style.display = 'none';
            saveBtn.style.display = 'block';
            passwordFields.style.display = 'grid';
            oldPassInput.setAttribute('required', 'true');
        });
    </script>
</body>
</html>