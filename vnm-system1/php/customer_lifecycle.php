<?php
// customer_rental_lifecycle.php (Customer View with Order Tracker)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'db.php'; 

$system_base_path = '/vnm-system1/'; 
$gcash_qr_path = $system_base_path . 'uploads/payments/gcash_qr.png';
$maya_qr_path = $system_base_path . 'uploads/payments/maya_qr.png';

// Require login
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$current_user_id = (int) $_SESSION['user'];

// --- Payment Proof Submission Handler ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_payment_proof') {
    
    $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);
    
    if (!$request_id || !$payment_method) {
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
            // Note: The path stored in DB should be relative to the application's root for easy access
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

    $update_sql = "
        UPDATE rental_requests 
        SET payment_status = 'Proof Uploaded', 
            payment_proof_path = ?,
            payment_method = ?
        WHERE request_id = ? AND user_id = ?"; 
        
    $stmt_update = $conn->prepare($update_sql);
    
    if ($stmt_update === false) {
        error_log("Database Prepare Error for Payment Update: " . $conn->error);
        header("Location: rentalsc.php?error=db_prepare_failed");
        exit;
    }
    
    $stmt_update->bind_param("ssii", $proof_path, $payment_method, $request_id, $current_user_id);

    if ($stmt_update->execute()) {
        // NOTE: Redirecting to rentalsc.php to maintain consistency with the original handler
        header("Location: rentalsc.php?success=payment_proof_uploaded"); 
        exit;
    } else {
        error_log("DB update error on payment proof: " . $stmt_update->error);
        header("Location: rentalsc.php?error=db_update_failed");
        exit;
    }
}

// --- 1. Fetch CURRENT/UPCOMING/ACTIVE Rentals (Pending, Approved, Picked Up) ---
$current_sql = "
    SELECT 
        rr.request_id, rr.rental_date, rr.rental_time, rr.rental_duration_days, rr.total_cost, rr.request_status, rr.payment_status,
        c.car_brand, c.model, c.plate_no,
        pd.pickup_date_actual, pd.odometer_pickup, pd.car_condition_pickup
    FROM rental_requests rr
    INNER JOIN cars c ON rr.car_id = c.car_id
    LEFT JOIN rental_pickup_details pd ON rr.request_id = pd.request_id
    WHERE rr.request_status IN ('Pending', 'Approved', 'Picked Up')
        AND rr.user_id = ?
    ORDER BY FIELD(rr.request_status, 'Picked Up', 'Approved', 'Pending') ASC, rr.rental_date ASC";

$stmt_current = $conn->prepare($current_sql);
$stmt_current->bind_param('i', $current_user_id);
$stmt_current->execute();
$current_details = $stmt_current->get_result();

// --- 2. Fetch HISTORY/COMPLETED Rentals (Returned, Rejected, Cancelled) ---
$history_sql = "
    SELECT 
        rr.request_id, rr.rental_date, rr.rental_time, rr.total_cost, rr.request_status, rr.payment_status,
        c.car_brand, c.model, c.plate_no,
        pd.odometer_pickup,
        rd.odometer_return, rd.return_date_actual, rd.damage_fee
    FROM rental_requests rr
    INNER JOIN cars c ON rr.car_id = c.car_id
    LEFT JOIN rental_pickup_details pd ON rr.request_id = pd.request_id
    LEFT JOIN rental_return_details rd ON rr.request_id = rd.request_id
    WHERE rr.request_status IN ('Returned', 'Rejected', 'Cancelled')
        AND rr.user_id = ?
    ORDER BY rr.rental_date DESC";

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
    <link rel="stylesheet" href="../css/rental.css?v=1.41"> 
    <title>My Rental Lifecycle</title>
    <style>
        /* CSS from rentalsc.php for consistent popover styling */
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
            <h3>Active & Upcoming Rentals (Pending, Approved, Picked Up)</h3>
            <?php if ($current_details->num_rows > 0): ?>
                <?php while ($row = $current_details->fetch_assoc()): 
                    $request_id = htmlspecialchars($row['request_id']);
                    $rental_date_display = date('F j, Y', strtotime($row['rental_date']));
                    $car_display = htmlspecialchars("{$row['car_brand']} {$row['model']} ({$row['plate_no']})");
                    $status_text = htmlspecialchars($row['request_status']);
                    $payment_status = htmlspecialchars($row['payment_status']);
                    
                    // Color coding for main status
                    $status_color = 'grey';
                    if ($status_text === 'Pending') $status_color = 'orange';
                    if ($status_text === 'Approved') $status_color = '#007bff';
                    if ($status_text === 'Picked Up') $status_color = 'green';
                    
                    $payment_status_color = '#dc3545'; 
                    if ($payment_status === 'Paid') {
                        $payment_status_color = 'darkgreen';
                    } elseif ($payment_status === 'Proof Uploaded') {
                        $payment_status_color = '#007bff'; 
                    }
                    
                    $popover_data = json_encode([
                        'request_id' => $row['request_id'],
                        'car_display' => $car_display,
                        'total_cost' => number_format($row['total_cost'], 2)
                    ]);
                ?>
                <div class="rental-detail">
                    <div class="detail">
                        <h4><?= $car_display ?></h4>
                        <p>Scheduled Pickup: <strong><?= $rental_date_display ?> @ <?= htmlspecialchars($row['rental_time']) ?></strong></p>
                        <p>Duration: <?= htmlspecialchars($row['rental_duration_days']) ?> Days | Cost: ₱<?= number_format($row['total_cost'], 2) ?></p>
                        <p>Request Status: <span style="font-weight: bold; color: <?= $status_color ?>;"><?= $status_text ?></span></p>
                        <p>Payment Status: <span style="font-weight: bold; color: <?= $payment_status_color ?>;"><?= $payment_status ?></span></p>
                        
                        <?php if ($status_text === 'Picked Up'): ?>
                            <p style="font-size: 0.9em; color: #ccc; margin-top: 5px;">
                                Actual Pickup Date: <?= date('M j, Y', strtotime($row['pickup_date_actual'])) ?><br>
                                Pickup Mileage: <?= number_format($row['odometer_pickup'] ?? 0) ?> km
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
                                style="background-color: #ffc107; color: black; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold;"
                            >Upload Payment Proof</button>
                        <?php elseif ($status_text === 'Approved' && $payment_status === 'Proof Uploaded'): ?>
                            <p style="color: #007bff; font-weight: bold; margin: 0;">Proof Awaiting Admin Check</p>
                        <?php elseif ($status_text === 'Pending'): ?>
                            <p style="color: orange; font-weight: bold; margin: 0;">Awaiting Admin Approval</p>
                            <form action="cancel_action.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel this rental?');">
                                <input type="hidden" name="request_id" value="<?= $request_id ?>">
                                <input type="hidden" name="action" value="cancel">
                                <button type="submit" style="background-color: grey; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; margin-top: 10px;">Cancel Request</button>
                            </form>
                        <?php elseif ($status_text === 'Picked Up'): ?>
                            <p style="color: green; font-weight: bold; margin: 0;">ACTIVE RENTAL</p>
                        <?php endif; ?>

                        <form action="request_return.php" method="post">
                            <input type="hidden" name="return_id" value="<?php echo $request_id?>">
                            <input type="hidden" name="return_date" value="<?php echo date('Y-m-d'); ?>">
                            <input type="hidden" name="start_date" value="<?php echo $row['pickup_date_actual']?>">
                            <button type="submit" name="return_button">Return</button>
                        </form>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>You have no pending, approved, or active rentals.</p>
            <?php endif; ?>
        </section>

        <hr>

        <section id="history">
            <h3>Rental History (Returned, Rejected, Cancelled)</h3>
            <?php if ($history_details->num_rows > 0): ?>
                <?php while ($row = $history_details->fetch_assoc()): 
                    $rental_date_display = date('F j, Y', strtotime($row['rental_date']));
                    $car_display = htmlspecialchars("{$row['car_brand']} {$row['model']} ({$row['plate_no']})");
                    $status_text = htmlspecialchars($row['request_status']);
                    
                    $status_color = 'grey'; 

                    if ($status_text === 'Returned') {
                        $status_color = 'darkgreen';
                    } elseif ($status_text === 'Rejected') {
                        $status_color = 'red';
                    } elseif ($status_text === 'Cancelled') {
                        $status_color = 'grey';
                    }
                ?>
                <div class="rental-detail">
                    <div class="detail">
                        <h4><?= $car_display ?></h4>
                        <p>Scheduled Pickup: <strong><?= $rental_date_display ?> @ <?= htmlspecialchars($row['rental_time']) ?></strong></p>
                        <p>Cost: ₱<?= number_format($row['total_cost'], 2) ?></p>
                        <p>Final Status: <span style="font-weight: bold; color: <?= $status_color ?>;"><?= strtoupper($status_text) ?></span></p>
                        
                        <?php if ($status_text === 'Returned'): ?>
                            <p style="font-size: 0.9em; margin-top: 10px; color: #ccc;">
                                <strong>Return Date:</strong> <?= date('F j, Y', strtotime($row['return_date_actual'])) ?><br>
                                <strong>Distance Traveled:</strong> <?= number_format($row['odometer_return'] - $row['odometer_pickup']) ?> km
                            </p>
                            <?php if ($row['damage_fee'] > 0): ?>
                                <p style="color: red; font-weight: bold;">Damage/Extra Fee: ₱<?= number_format($row['damage_fee'], 2) ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="action-status">
                        <?php if ($status_text === 'Returned'): ?>
                            <p style="color: darkgreen; font-weight: bold; margin: 0;">COMPLETED</p>
                        <?php else: ?>
                            <p style="color: <?= $status_color ?>; font-weight: bold; margin: 0;">STOPPED</p>
                        <?php endif; ?>
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

            <form id="payment-form" action="customer_lifecycle.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="submit_payment_proof">
                <input type="hidden" name="request_id" id="popoverRequestId">
                <input type="hidden" name="payment_method" id="popoverPaymentMethod">

                <label for="payment-method-select"><strong>Payment Method:</strong></label>
                <select name="payment_method_select" id="payment-method-select" onchange="showQr(this.value)" style="width: 100%; padding: 8px; margin-bottom: 10px;">
                    <option value="">-- Select --</option>
                    <option value="gcash">GCash</option>
                    <option value="maya">Maya</option>
                </select>
                
                <div id="qr-display-container" style="text-align: center; margin-bottom: 10px;">
                    <p id="qr-instructions" style="font-style: italic;">QR Code will appear here after selection.</p>
                    <img id="popoverGcashQr" src="<?= htmlspecialchars($gcash_qr_path) ?>" alt="GCash QR Code" style="display:none;">
                    <img id="popoverMayaQr" src="<?= htmlspecialchars($maya_qr_path) ?>" alt="Maya QR Code" style="display:none;">
                </div>
                
                <label for="payment-proof-file"><strong>Upload Proof of Payment (Image/PDF):</strong></label>
                <input type="file" name="payment_proof" id="payment-proof-file" accept="image/*,application/pdf" required style="width: 100%; margin: 5px 0;">
                
                <button type="submit">Upload Proof & Confirm</button>
            </form>
            
            <p style="margin-top: 15px; font-size: 0.85em; color: #ddd; text-align: center;">Your payment status will be updated to "Proof Uploaded" for admin verification.</p>
        </div>
        
    </main>
    
    <script>
        const popoverRequestId = document.getElementById('popoverRequestId');
        const popoverPaymentMethod = document.getElementById('popoverPaymentMethod');
        const paymentMethodSelect = document.getElementById('payment-method-select');
        const popoverCar = document.getElementById('popoverCar');
        const popoverTotalCost = document.getElementById('popoverTotalCost');
        const gcashQr = document.getElementById('popoverGcashQr');
        const mayaQr = document.getElementById('popoverMayaQr');
        const qrInstructions = document.getElementById('qr-instructions');

        function openPaymentPopover(button) {
            try {
                // Parse details from the button's data attribute
                const data = JSON.parse(button.getAttribute('data-popover-details'));
                
                popoverCar.textContent = data.car_display;
                popoverTotalCost.textContent = data.total_cost;
                popoverRequestId.value = data.request_id;
                
                // Reset QR display and selection
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
            return true;
        };

        // Handle URL parameters after successful upload
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('success') === 'payment_proof_uploaded') {
                alert("Payment proof successfully uploaded! Please wait for admin verification.");
                
                // Clean the URL to remove the success parameter after the alert
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
$stmt_current->close();
$stmt_history->close();
$conn->close();
?>