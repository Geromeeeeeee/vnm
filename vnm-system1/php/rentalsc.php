<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'db.php'; 

$system_base_path = '/vnm-system1/'; 
$gcash_qr_path = $system_base_path . 'uploads/payments/gcash_qr.png';
$maya_qr_path = $system_base_path . 'uploads/payments/maya_qr.png';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$current_user_id = (int) $_SESSION['user'];


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_payment_proof') {
    
    $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);
    // RESTORING: Capture and sanitize reference number, expecting a number
    $payment_reference_no = filter_input(INPUT_POST, 'payment_reference_no', FILTER_SANITIZE_NUMBER_INT); 
    
    // RESTORING: Check for reference number
    if (!$request_id || !$payment_method || empty($payment_reference_no)) {
        header("Location: rentalsc.php?error=invalid_payment_data");
        exit;
    }

    $upload_dir = '../uploads/payments/'; 
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            error_log("Failed to create upload directory: " . $upload_dir);
            header("Location: rentalsc.php?error=server_config_error");
            exit;
        }
    }
    
    $proof_path = null;
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['payment_proof']['tmp_name'];
        $file_name = basename($_FILES['payment_proof']['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $unique_name = 'proof_' . $request_id . '_' . uniqid() . '.' . $file_ext;
        $target_file = $upload_dir . $unique_name;

        $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'];
        if (!in_array($file_ext, $allowed_types)) {
            header("Location: rentalsc.php?error=invalid_payment_file_type&id=" . $request_id);
            exit;
        }
        
        if (move_uploaded_file($file_tmp, $target_file)) {
            $proof_path = 'uploads/payments/' . $unique_name; 
        } else {
            error_log("Payment proof upload failed for request $request_id.");
            header("Location: rentalsc.php?error=file_upload_failed&id=" . $request_id);
            exit;
        }
    } else {
        header("Location: rentalsc.php?error=payment_proof_required&id=" . $request_id);
        exit;
    }

    // UPDATED: Added payment_reference_no to the UPDATE statement
    // Note: The status is set to 'Proof Uploaded' even on re-submission for re-verification
    $update_sql = "
        UPDATE rental_requests 
        SET payment_status = 'Proof Uploaded', 
            payment_proof_path = ?,
            payment_method = ?,
            payment_reference_no = ?
        WHERE request_id = ? AND user_id = ?"; 
        
    $stmt_update = $conn->prepare($update_sql);
    
    if ($stmt_update === false) {
        error_log("Database Prepare Error for Payment Update: " . $conn->error);
        header("Location: rentalsc.php?error=db_prepare_failed");
        exit;
    }
    
    // UPDATED: Added $payment_reference_no to bind_param
    $stmt_update->bind_param("sssii", $proof_path, $payment_method, $payment_reference_no, $request_id, $current_user_id);

    if ($stmt_update->execute()) {

        header("Location: rentalsc.php?success=payment_proof_uploaded");
        exit;
    } else {
        error_log("DB update error on payment proof: " . $stmt_update->error);
        header("Location: rentalsc.php?error=db_update_failed");
        exit;
    }
}


// RESTORING: Added rr.admin_notes
$upcoming_sql = "
    SELECT 
        rr.request_id, 
        rr.rental_date, 
        rr.rental_time,
        rr.rental_duration_days,
        rr.total_cost,
        rr.request_status,
        rr.payment_status,
        rr.admin_notes,
        c.car_brand,
        c.model,
        c.daily_rate
    FROM rental_requests rr
    INNER JOIN cars c ON rr.car_id = c.car_id
    WHERE rr.request_status IN ('Pending')
        AND rr.user_id = ?
    ORDER BY rr.rental_date ASC, rr.rental_time ASC";

$stmt_upcoming = $conn->prepare($upcoming_sql);
$stmt_upcoming->bind_param('i', $current_user_id);
$stmt_upcoming->execute();
$upcoming_details = $stmt_upcoming->get_result();

// RESTORING: Added rr.admin_notes and rr.payment_reference_no
$history_sql = "
    SELECT 
        rr.request_id, 
        rr.rental_date, 
        rr.rental_time,
        rr.rental_duration_days,
        rr.total_cost,
        rr.request_status,
        rr.payment_status,
        rr.admin_notes,
        rr.payment_reference_no,
        c.car_brand,
        c.model,
        c.daily_rate
    FROM rental_requests rr
    INNER JOIN cars c ON rr.car_id = c.car_id
    WHERE rr.request_status IN ('Approved', 'Rejected', 'Cancelled')
        AND rr.user_id = ?
    ORDER BY 
        CASE rr.request_status
            WHEN 'Approved' THEN 1
            WHEN 'Rejected' THEN 2
            WHEN 'Cancelled' THEN 3
            ELSE 4
        END,
        rr.rental_date DESC, 
        rr.rental_time DESC";

$stmt_history = $conn->prepare($history_sql);
$stmt_history->bind_param('i', $current_user_id);
$stmt_history->execute();
$history_details = $stmt_history->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/rent_form.css">
    <link rel="stylesheet" href="../css/rental.css?v=1.45"> 
    <title>My Rentals</title>
    <style>
        #payment-popover {
            background-color: black; 
            border: 1px solid #444; 
            padding: 20px;
            border-radius: 8px;
            color: white; 
            max-width: 400px; 
            width: 90vw; 
            box-sizing: border-box; 
        }
        
        #payment-popover img {
            max-width: 250px; 
            height: 250px; 
            width: 100%; 
            object-fit: contain;
            border: 1px solid #ddd;
            margin: 15px auto; 
            display: block; 
            background-color: white; 
        }
        
        #payment-popover h3 {
            margin-top: 0;
            color: #ccc; 
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        #payment-popover p {
            margin: 5px 0;
            color: #eee;
        }
        
        #payment-popover select, #payment-proof-file {
            border: 1px solid #aaa;
            padding: 8px;
            border-radius: 4px;
            box-sizing: border-box; 
            background-color: #333; 
            color: white; 
        }
        
        #payment-popover button[type="submit"] {
            background-color: #28a745; 
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
            font-weight: bold;
        }
        #qr-instructions {
            padding: 20px;
            background-color: #333; 
            color: #ddd;
            border-radius: 4px;
        }
        
        .action-status p {
            font-weight: bold;
            color: #333; 
        }
    </style>
</head>
<body>
    <nav>
        <h3>VNM Car Rental</h3>
        <a href="../php/login-dashboard.php">Home</a>
        <a href="#cars">Cars</a>
        <a href="#aboutUs">About</a>
        <a href="../php/rentalsc.php">Rental Requests</a>
        <a href="../php/customer_lifecycle.php">Rental History</a>
        <button popovertarget="logout">Logout</button>
    </nav>
    <main>
        <section id="upcoming">
            <h3>Pending Rental Requests (Awaiting Approval)</h3>
            <?php if ($upcoming_details->num_rows > 0): ?>
                <?php while ($row = $upcoming_details->fetch_assoc()): 
                    $request_id = htmlspecialchars($row['request_id']);
                    $rental_date_display = date('F j, Y', strtotime($row['rental_date']));
                    $car_display = "{$row['car_brand']} {$row['model']}";
                    $status_text = htmlspecialchars($row['request_status']);
                    $payment_status = htmlspecialchars($row['payment_status']);
                    // RESTORING: admin_notes
                    $admin_notes = htmlspecialchars($row['admin_notes']);
                    
                    $status_color = ($status_text === 'Approved') ? 'green' : (($status_text === 'Pending') ? 'orange' : 'black');

                    $payment_status_color = '#dc3545'; 
                    if ($payment_status === 'Paid') {
                        $payment_status_color = 'darkgreen';
                    } elseif ($payment_status === 'Proof Uploaded') {
                        $payment_status_color = '#007bff'; 
                    }
                    // NEW: Color for Correction Required
                    elseif ($payment_status === 'Correction Required') {
                         $payment_status_color = '#ffc107'; 
                    }
                ?>
                <div class="rental-detail">
                    <div class="detail">
                        <h4><?= $rental_date_display ?> @ <?= htmlspecialchars($row['rental_time']) ?></h4>
                        <p><?= $car_display ?></p>
                        <p>Request Status: <span style="font-weight: bold; color: <?= $status_color ?>;"><?= $status_text ?></span></p>
                        <p>Payment Status: <span style="font-weight: bold; color: <?= $payment_status_color ?>;"><?= $payment_status ?></span></p>
                        
                        <?php if (!empty($admin_notes)): // RESTORING: admin_notes display?>
                            <p style="margin-top: 10px;">
                                <strong>Admin Note:</strong> 
                                <span style="font-style: italic; color: #ccc;"><?= nl2br($admin_notes) ?></span>
                            </p>
                        <?php endif; ?>
                        
                    </div>
                    
                    <form action="cancel_action.php" method="POST">
                        <input type="hidden" name="request_id" value="<?= $request_id ?>">
                        
                        <?php if ($status_text === 'Pending'): ?>
                            <input type="hidden" name="action" value="cancel">
                            <button type="submit" onclick="return confirm('Are you sure you want to cancel this rental?');">Cancel</button>
                        <?php endif; ?>
                    </form>
                </div>
                
                <?php endwhile; ?>
            <?php else: ?>
                <p>You have no pending rental requests.</p>
            <?php endif; ?>
        </section>
        
        <hr>

        <section id="history">
            <h3>Rental History (Approved, Rejected & Cancelled)</h3>
            <?php if ($history_details->num_rows > 0): ?>
                <?php while ($row = $history_details->fetch_assoc()): 
                    $rental_date_display = date('F j, Y', strtotime($row['rental_date']));
                    $car_display = "{$row['car_brand']} {$row['model']}";
                    $status_text = htmlspecialchars($row['request_status']);
                    $payment_status = htmlspecialchars($row['payment_status']);
                    // RESTORING: admin_notes
                    $admin_notes = htmlspecialchars($row['admin_notes']);
                    
                    $status_color = 'grey'; 
                    if ($status_text === 'Rejected') $status_color = 'red';
                    if ($status_text === 'Approved') $status_color = 'green';
                    
                    $payment_status_color = '#dc3545'; 
                    if ($payment_status === 'Paid') {
                        $payment_status_color = 'darkgreen';
                    } elseif ($payment_status === 'Proof Uploaded') {
                        $payment_status_color = '#007bff'; 
                    } 
                    // NEW: Color for Correction Required
                    elseif ($payment_status === 'Correction Required') {
                         $payment_status_color = '#ffc107'; 
                    }
                    
                    $popover_data = json_encode([
                        'request_id' => $row['request_id'],
                        'car_display' => $car_display,
                        'total_cost' => number_format($row['total_cost'], 2)
                    ]);
                ?>
                <div class="rental-detail">
                    <div class="detail">
                        <h4><?= $rental_date_display ?> @ <?= htmlspecialchars($row['rental_time']) ?></h4>
                        <p><?= $car_display ?></p>
                        <p>Request Status: <span style="font-weight: bold; color: <?= $status_color ?>;"><?= $status_text ?></span></p>
                        
                        <?php if ($status_text === 'Approved'): ?>
                            <p>Payment Status: <span style="font-weight: bold; color: <?= $payment_status_color ?>;"><?= $payment_status ?></span></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($admin_notes)): // RESTORING: admin_notes display ?>
                            <p style="margin-top: 10px;">
                                <strong>Admin Note:</strong> 
                                <span style="font-style: italic; color: #ccc;"><?= nl2br($admin_notes) ?></span>
                            </p>
                        <?php endif; ?>
                        
                    </div>
                    <div class="action-status">
                        <?php if ($status_text === 'Approved' && $payment_status === 'Unpaid'): ?>
                        <button 
                            id="payment-button-<?= $row['request_id'] ?>" 
                            data-popover-details='<?= htmlspecialchars($popover_data, ENT_QUOTES, 'UTF-8') ?>' 
                            onclick="openPaymentPopover(this)"
                            popovertarget="payment-popover"
                        >Payment</button>
                        <?php elseif ($status_text === 'Approved' && $payment_status === 'Proof Uploaded'): ?>
                            <p style="color: #007bff; font-weight: bold; margin: 0;">Proof Awaiting Admin Check</p>
                        <?php elseif ($status_text === 'Approved' && $payment_status === 'Correction Required'): // NEW: Re-submit button ?>
                            <p style="color: #ffc107; font-weight: bold; margin: 0;">Payment Correction Needed</p>
                            <button 
                                id="payment-button-<?= $row['request_id'] ?>" 
                                data-popover-details='<?= htmlspecialchars($popover_data, ENT_QUOTES, 'UTF-8') ?>' 
                                onclick="openPaymentPopover(this)"
                                popovertarget="payment-popover"
                                style="background-color: #ffc107; color: black; border: none; padding: 5px 10px; border-radius: 4px; font-weight: bold; margin-top: 5px;"
                            >Re-submit Payment</button>
                        <?php endif; ?>
                        <p>Cost: ₱<?= number_format($row['total_cost'], 2) ?></p>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>You have no past rental history.</p>
            <?php endif; ?>
        </section>
        
        <div id="payment-popover" popover="auto">
            <h3>Payment Summary</h3>
            <p><strong>Car:</strong> <span id="popoverCar"></span></p>
            <p><strong>Amount Due:</strong> ₱<span id="popoverTotalCost"></span></p>
            <hr>

            <form id="payment-form" action="rentalsc.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="submit_payment_proof">
                <input type="hidden" name="request_id" id="popoverRequestId">
                <input type="hidden" name="payment_method" id="popoverPaymentMethod">

                <label for="payment-method-select"><strong>Payment Method:</strong></label>
                <select name="payment_method_select" id="payment-method-select" onchange="showQr(this.value)" style="width: 100%; padding: 5px; margin-bottom: 10px;">
                    <option value="">-- Select --</option>
                    <option value="gcash">GCash</option>
                    <option value="maya">Maya</option>
                </select>
                
                <div id="qr-display-container" style="text-align: center; margin-bottom: 10px;">
                    <p id="qr-instructions" style="font-style: italic; color: #ddd;">QR Code will appear here after selection.</p>
                    <img id="popoverGcashQr" src="<?= htmlspecialchars($gcash_qr_path) ?>" alt="GCash QR Code" style="display:none;">
                    <img id="popoverMayaQr" src="<?= htmlspecialchars($maya_qr_path) ?>" alt="Maya QR Code" style="display:none;">
                </div>
                
                <label for="payment-proof-file"><strong>Upload Proof of Payment:</strong></label>
                <input type="file" name="payment_proof" id="payment-proof-file" required style="width: 100%; margin: 5px 0;">
                
                <label for="payment-reference-no" style="margin-top: 10px; display: block;"><strong>Reference/Transaction Number:</strong></label> 
                <input type="number" name="payment_reference_no" id="payment-reference-no" required placeholder="e.g., 1234567890" style="width: 100%; padding: 8px; border-radius: 4px; box-sizing: border-box; background-color: #333; color: white; border: 1px solid #aaa; margin-bottom: 10px;">
                
                <button type="submit">Upload Proof & Confirm</button>
            </form>
            
            <p style="margin-top: 15px; font-size: 0.85em; color: #ddd; text-align: center;">Your payment status will be updated to "Proof Uploaded" for admin verification.</p>
        </div>
        
    </main>
    
    <script>
        const paymentPopover = document.getElementById("payment-popover");
        const popoverRequestId = document.getElementById('popoverRequestId');
        const popoverPaymentMethod = document.getElementById('popoverPaymentMethod');
        const paymentMethodSelect = document.getElementById('payment-method-select');
        const popoverCar = document.getElementById('popoverCar');
        const popoverTotalCost = document.getElementById('popoverTotalCost');
        const gcashQr = document.getElementById('popoverGcashQr');
        const mayaQr = document.getElementById('popoverMayaQr');
        const qrInstructions = document.getElementById('qr-instructions');
        // RESTORING: Reference to the number input
        const paymentReferenceNo = document.getElementById('payment-reference-no'); 

        function openPaymentPopover(button) {
            try {
                const data = JSON.parse(button.getAttribute('data-popover-details'));
                
                popoverCar.textContent = data.car_display;
                popoverTotalCost.textContent = data.total_cost;
                
                popoverRequestId.value = data.request_id;
                
                paymentMethodSelect.value = ""; 
                showQr(""); 
                
            } catch (e) {
                console.error("Error loading payment data:", e);
                alert("Could not load payment details. Data error.");
            }
        }

        function showQr(method) {
            gcashQr.style.display = 'none';
            mayaQr.style.display = 'none';
            qrInstructions.style.display = 'none';
            popoverPaymentMethod.value = "";
            
            if (method === 'gcash') {
                gcashQr.style.display = 'block';
                popoverPaymentMethod.value = 'gcash';
            } else if (method === 'maya') {
                mayaQr.style.display = 'block';
                popoverPaymentMethod.value = 'maya';
            } else {
                qrInstructions.style.display = 'block';
                popoverPaymentMethod.value = "";
            }
        }
        
        document.getElementById('payment-form').onsubmit = function() {
            if (popoverPaymentMethod.value === "") {
                alert("Please select a payment method (GCash or Maya) before uploading proof.");
                return false;
            }
            if (document.getElementById('payment-proof-file').files.length === 0) {
                 alert("Please select a file for proof of payment.");
                 return false;
            }
            // RESTORING: Numeric validation
            if (paymentReferenceNo.value.trim() === "" || isNaN(paymentReferenceNo.value)) {
                alert("Please enter a valid numeric Reference/Transaction Number.");
                return false;
            }
            return true;
        };

        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('success') === 'payment_proof_uploaded') {
                alert("Payment proof successfully uploaded! Please wait for admin verification.");
                
                if (history.replaceState) {
                    const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                    history.replaceState({path:cleanUrl},'',cleanUrl);
                }
            }
        };
    </script>
</body>
</html>
<?php
$stmt_upcoming->close();
$stmt_history->close();
$conn->close();
?>