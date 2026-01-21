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
        header("Location: rentalsc.php?success=payment_proof_uploaded"); 
        exit;
    } else {
        error_log("DB update error on payment proof: " . $stmt_update->error);
        header("Location: rentalsc.php?error=db_update_failed");
        exit;
    }
}

// --- 1. Fetch CURRENT/UPCOMING/ACTIVE Rentals (UPDATED WITH MAINTENANCE LOGIC) ---
$current_sql = "
    SELECT 
        rr.request_id, rr.rental_date, rr.rental_time, rr.rental_duration_days, rr.total_cost, rr.request_status, rr.payment_status, rr.car_id, 
        c.car_brand, c.model, c.plate_no, c.daily_rate, 
        pd.pickup_date_actual, 
        DATE_ADD(rr.rental_date, INTERVAL rr.rental_duration_days DAY) AS expected_return_date,
        rrr.scheduled_return_date, rrr.scheduled_return_time,
        rrr.total_deducted_cost,
        -- MAINTENANCE VALIDATOR (Updated to +2 to account for extension buffer):
        -- If B ends Jan 23, Maintenance is Jan 24.
        -- If A starts Jan 25, extension is blocked because even a 1-day extension moves maintenance to Jan 25.
        (SELECT COUNT(*) FROM rental_requests r2 
         WHERE r2.car_id = rr.car_id 
         AND r2.request_id != rr.request_id
         AND r2.request_status IN ('Approved', 'Picked Up', 'Pending', 'Early_Return_Scheduled') 
         AND r2.rental_date <= DATE_ADD(rr.rental_date, INTERVAL (rr.rental_duration_days + 2) DAY)
         AND r2.rental_date > rr.rental_date
        ) AS maintenance_conflict_count
    FROM rental_requests rr
    INNER JOIN cars c ON rr.car_id = c.car_id
    LEFT JOIN rental_pickup_details pd ON rr.request_id = pd.request_id
    LEFT JOIN rental_return_requests rrr ON rr.request_id = rrr.request_id 
    WHERE rr.request_status IN ('Pending', 'Approved', 'Picked Up', 'Early Return Requested', 'Early_Return_Approved', 'Early_Return_Scheduled') 
        AND rr.user_id = ?
    ORDER BY FIELD(rr.request_status, 'Picked Up', 'Early_Return_Scheduled', 'Early_Return_Approved', 'Early Return Requested', 'Approved', 'Pending') ASC, rr.rental_date ASC";

$stmt_current = $conn->prepare($current_sql);

// Crucial check: Fixes the 'bind_param on bool' error by displaying the underlying SQL issue.
if ($stmt_current === false) { 
    die("SQL Prepare Error on CURRENT Rentals: " . $conn->error . "<br>Please ensure you have run the database ALTER commands to add 'scheduled_return_date', 'scheduled_return_time', 'total_deducted_cost' to rental_return_requests and updated the ENUM in rental_requests.");
} 

$stmt_current->bind_param('i', $current_user_id);
$stmt_current->execute();
$current_details = $stmt_current->get_result();


// --- 2. Fetch HISTORY/COMPLETED Rentals ---
$history_sql = "
    SELECT 
        rr.request_id, rr.rental_date, rr.rental_time, rr.total_cost, rr.request_status, rr.payment_status,
        c.car_brand, c.model, c.plate_no,
        pd.odometer_pickup,
        rd.odometer_return, rd.return_date_actual, rd.damage_fee, 
        rrr.total_deducted_cost 
    FROM rental_requests rr
    INNER JOIN cars c ON rr.car_id = c.car_id
    LEFT JOIN rental_pickup_details pd ON rr.request_id = pd.request_id
    LEFT JOIN rental_return_details rd ON rr.request_id = rd.request_id
    LEFT JOIN rental_return_requests rrr ON rr.request_id = rrr.request_id 
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
    <link rel="stylesheet" href="../css/rent_form.css?v=1.01">
    <link rel="stylesheet" href="../css/rental.css?v=1.5"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <title>My Rental Lifecycle</title>
    <style>
        /* CSS for forms/popovers (MODIFIED to ensure centering) */
        #payment-popover, #schedule-popover, #extend-popover { 
            /* popover="auto" handles the initial display/backdrop, but we enforce centering */
            background-color: black; 
            border: 1px solid #444; 
            padding: 20px;
            border-radius: 8px;
            color: white; 
            max-width: 400px; 
            width: 90vw; 
            box-sizing: border-box; 
            
            /* Enforce middle positioning */
            margin: auto; 
            top: 0; bottom: 0; left: 0; right: 0;
            position: fixed; 
        }
        
        #payment-popover img, #extend-popover img { 
            max-width: 250px; 
            height: 250px; 
            width: 100%; 
            object-fit: contain;
            border: 1px solid #ddd;
            margin: 15px auto; 
            display: block; 
            background-color: white; 
        }
        
        #payment-popover h3, #schedule-popover h3, #extend-popover h3 { 
            margin-top: 0;
            color: #ccc; 
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        #payment-popover p, #schedule-popover p, #extend-popover p { 
            margin: 5px 0;
            color: #eee;
        }
        
        /* Ensure all inputs and selects across all popovers are styled consistently and vertically */
        #payment-popover select, #payment-proof-file, 
        #schedule-popover input[type="date"], #schedule-popover input[type="time"],
        #extend-popover input, #extend-popover select, #extend-popover input[type="file"] { 
            width: 100%; 
            padding: 8px; 
            margin-top: 5px; 
            background-color: #333; 
            color: white; 
            border: 1px solid #aaa; 
            border-radius: 4px;
            box-sizing: border-box; 
        }

        /* --- START: Vertical Form Fixes for Extend Popover --- */
        /* Ensure the form groups stack vertically */
        .refund-box {
    background: #2d2d2d; 
    border-left: 4px solid #ffc107; 
    padding: 15px; 
    margin: 15px 0; 
    font-size: 0.9em;
}
.refund-box ul { 
    padding-left: 20px; 
    margin: 5px 0; 
}
        #extend-popover .form-group {
            display: block; 
            margin-bottom: 15px; 
        }

        /* Ensure the label is a block element so the input starts on a new line */
        #extend-popover .form-group label {
            display: block;
            margin-bottom: 5px; 
            font-weight: bold; 
        }
        /* --- END: Vertical Form Fixes for Extend Popover --- */
        
        /* New CSS for centering the schedule date/time inputs (PRESERVED) */
        #schedule-popover .schedule-form-content {
            max-width: 250px; /* Limit the width of the inputs/labels */
            width: 100%;
            margin: 0 auto; /* Center the container horizontally */
            text-align: left; /* Ensure labels start from the left of this container */
        }
        
        /* NEW: Base style for all VNM action buttons in the action-status block (GREYISH) */
        .vnm-action-button { 
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none; 
            background-color: #6c757d; /* Neutral Grey for Return/Extend buttons */
        }

        /* Submit buttons inside popovers (Slightly darker grey for contrast) */
        #payment-popover button[type="submit"], 
        #schedule-popover button[type="submit"], 
        #extend-popover button[type="submit"] { 
            background-color: #5a6268; /* Darker grey for submit actions */
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            margin-top: 20px;
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
        #extendRentalForm{
            display:flex;
            flex-direction:column;
            height:75vh;
            overflow:auto;
            gap:1.5vh;
        }
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
        <section id="upcoming">
            <h3>Active & Upcoming Rentals (Pending, Approved, Picked Up)</h3>
            <?php if ($current_details->num_rows > 0): ?>
    <?php while ($row = $current_details->fetch_assoc()): 
        $request_id = htmlspecialchars($row['request_id']);
        $car_id = htmlspecialchars($row['car_id']); 
        $daily_rate = (float)($row['daily_rate'] ?? 0.00); 
        $rental_duration_days = (int)($row['rental_duration_days']);
        
        $rental_date_display = date('F j, Y', strtotime($row['rental_date']));
        $car_display = htmlspecialchars("{$row['car_brand']} {$row['model']} ({$row['plate_no']})");
        $status_text = htmlspecialchars($row['request_status']);
        $payment_status = htmlspecialchars($row['payment_status']);
        
        // Calculate original expected return date
        $old_end_date = date('Y-m-d', strtotime($row['rental_date'] . ' + ' . $rental_duration_days . ' days'));
        
        // --- START RENTAL BOOKING VALIDATOR LOGIC ---
        // Rule: Priority Rule - Check if car is reserved by Customer B
        $is_blocked_by_future_reservation = (isset($row['future_booking_count']) && $row['future_booking_count'] > 0);
        
        // Rule: Enforce Return - Prepare the notification message
        $return_deadline_formatted = date('F j, Y, g:i a', strtotime($old_end_date . ' ' . $row['rental_time']));
        $extension_warning_html = "";
        
        if ($is_blocked_by_future_reservation) {
            $extension_warning_html = '
                <div style="background: #fff3cd; color: #856404; padding: 12px; border: 1px solid #ffeeba; border-radius: 4px; margin-top: 10px; font-size: 14px; line-height: 1.5;">
                    <strong>Notice:</strong> Your vehicle is reserved by another client immediately following your term. 
                    Extensions are unavailable; please return the vehicle by <strong>' . $return_deadline_formatted . '</strong> to avoid late penalties.
                </div>';
        }
        // --- END RENTAL BOOKING VALIDATOR LOGIC ---

        // --- Refund/Cost Calculation for Active Rentals ---
        $refund_amount = 0.00;
        $final_charge = (float)($row['total_cost'] ?? 0.00); 
        $original_cost = (float)($row['total_cost'] ?? 0.00);

        if (!empty($row['total_deducted_cost'])) {
            $final_charge = (float)($row['total_deducted_cost']);
            $refund_amount = max(0, $original_cost - $final_charge);
        }
        
        // Color coding for main status
        $status_color = 'grey';
        if ($status_text === 'Pending') $status_color = 'orange';
        if ($status_text === 'Approved') $status_color = '#007bff';
        if ($status_text === 'Picked Up') $status_color = 'green';
        if ($status_text === 'Early Return Requested') $status_color = 'purple'; 
        if ($status_text === 'Early_Return_Approved') $status_color = '#ffc107'; 
        if ($status_text === 'Early_Return_Scheduled') $status_color = 'blueviolet'; 

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
        
       // Ensure these are strictly YYYY-MM-DD
// Should look like this in your PHP loop:
$min_date_iso = date('Y-m-d', strtotime($row['rental_date']));
$max_date_iso = date('Y-m-d', strtotime($row['expected_return_date']));

$schedule_popover_data = json_encode([
    'request_id' => $row['request_id'],
    'car_display' => $car_display,
    'min_date' => $min_date_iso,
    'max_date' => $max_date_iso
]);
        $extend_popover_data = json_encode([
            'request_id' => $row['request_id'],
            'car_display' => $car_display,
            'daily_rate' => $daily_rate,
            'old_end_date' => $old_end_date,
            'car_id' => $car_id
        ]);
    ?>
                <div class="rental-detail">
    <div class="detail">
        <h4><?= $car_display ?></h4>
        <p>Scheduled Pickup: <strong><?= $rental_date_display ?> @ <?= htmlspecialchars($row['rental_time']) ?></strong></p>
        <p>Duration: <?= $rental_duration_days ?> Days | Original Cost: ₱<?= number_format($original_cost, 2) ?></p>
        <p>Request Status: <span style="font-weight: bold; color: <?= $status_color ?>;"><?= str_replace('_', ' ', $status_text) ?></span></p> 
        <p>Payment Status: <span style="font-weight: bold; color: <?= $payment_status_color ?>;"><?= $payment_status ?></span></p>
        
        <?php if ($status_text === 'Picked Up'): ?>
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
        <?php elseif ($status_text === 'Early Return Requested'): ?>
            <p style="color: purple; font-weight: bold; margin: 0;">Early Return Request Submitted</p>
            <?php if ($refund_amount > 0): ?>
                <p style="font-size: 0.9em; color: yellow; font-weight: bold; margin-top: 5px;">
                    Est. Refund: ₱<?= number_format($refund_amount, 2) ?>
                </p>
            <?php else: ?>
                <p style="font-size: 0.9em; color: #ccc; margin-top: 5px;">
                    Est. Final Charge: ₱<?= number_format($final_charge, 2) ?> (No Refund Due)
                </p>
            <?php endif; ?>
        <?php elseif ($status_text === 'Early_Return_Approved'): ?>
            <p style="color: #ffc107; font-weight: bold; margin: 0;">Return Approved! Schedule Now:</p>
            <?php if ($refund_amount > 0): ?>
                <p style="font-size: 0.9em; color: yellow; font-weight: bold; margin-top: 5px;">
                    Est. Refund: ₱<?= number_format($refund_amount, 2) ?>
                </p>
            <?php else: ?>
                <p style="font-size: 0.9em; color: #ccc; margin-top: 5px;">
                    Est. Final Charge: ₱<?= number_format($final_charge, 2) ?> (No Refund Due)
                </p>
            <?php endif; ?>
            <button 
                data-popover-details='<?= htmlspecialchars($schedule_popover_data, ENT_QUOTES, 'UTF-8') ?>' 
                onclick="openSchedulePopover(this)"
                popovertarget="schedule-popover"
                style="background-color: #ffc107; color: black; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; margin-top: 10px;"
            >Schedule Actual Return</button>
        <?php elseif ($status_text === 'Early_Return_Scheduled'): ?>
            <p style="color: blueviolet; font-weight: bold; margin: 0;">Return Scheduled:</p>
            <?php if ($refund_amount > 0): ?>
                <p style="font-size: 0.9em; color: yellow; font-weight: bold; margin-top: 5px;">
                    Est. Refund: ₱<?= number_format($refund_amount, 2) ?>
                </p>
            <?php endif; ?>
            <p style="font-size: 0.9em; color: #ccc; margin-top: 5px;">
                <strong style="color: yellow;">Awaiting Admin Processing</strong><br>
                Date: <?= date('M j, Y', strtotime($row['scheduled_return_date'])) ?><br>
                Time: <?= date('h:i A', strtotime($row['scheduled_return_time'])) ?>
            </p>
       <?php elseif ($status_text === 'Picked Up'): ?>
    <p style="color: green; font-weight: bold; margin: 0;">ACTIVE RENTAL</p>
    
    <div style="margin-top: 10px;">
        <form action="request_return.php" method="post" style="display: inline-block; margin-right: 10px;">
            <input type="hidden" name="return_id" value="<?php echo $request_id?>">
            <input type="hidden" name="return_date" value="<?php echo date('Y-m-d'); ?>">
            <input type="hidden" name="start_date" value="<?php echo $row['pickup_date_actual'] ?? $row['rental_date']; ?>">                               
            <button type="submit" name="return_button" class="vnm-action-button">Early Return</button> 
        </form>
        
        <?php 
            // 3. Maintenance Logic: Check the conflict count from SQL (includes the +2 day buffer)
            $is_blocked = (isset($row['maintenance_conflict_count']) && (int)$row['maintenance_conflict_count'] > 0);
            
            // 4. Prepare the deadline message
            $return_deadline = date('F j, Y, g:i a', strtotime($old_end_date . ' ' . $row['rental_time']));
            $block_message = "Extension Unavailable: This vehicle is reserved by another client immediately following your term. Please return the vehicle by " . $return_deadline . " to avoid late penalties.";
        ?>

        <button 
            id="extend-button-<?= $row['request_id'] ?>" 
            data-popover-details='<?= htmlspecialchars($extend_popover_data, ENT_QUOTES, 'UTF-8') ?>' 
            onclick="<?php echo $is_blocked ? "alert('" . addslashes($block_message) . "'); return false;" : "openExtendPopover(this)"; ?>"
            <?php if (!$is_blocked) echo 'popovertarget="extend-popover"'; ?> 
            class="vnm-action-button"
        >
            <i class="fas fa-calendar-plus"></i> Extend Rental
        </button>
    </div>
<?php endif; ?>

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
                    
                    // --- Refund/Cost Calculation for History ---
                    $refund_amount_hist = 0.00;
                    $original_cost_hist = (float)($row['total_cost'] ?? 0.00);
                    $final_cost_display = number_format($original_cost_hist, 2);

                    if ($status_text === 'Returned' && !empty($row['total_deducted_cost'])) {
                        $final_charge_hist = (float)($row['total_deducted_cost']);
                        $refund_amount_hist = max(0, $original_cost_hist - $final_charge_hist);
                        $final_cost_display = number_format($final_charge_hist, 2);
                    }
                    // --- End Refund/Cost Calculation ---
                    
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
                        <p>Original Cost: ₱<?= number_format($original_cost_hist, 2) ?></p>
                        
                        <?php if ($status_text === 'Returned'): ?>
                            <?php if ($refund_amount_hist > 0): ?>
                                <p style="color: #28a745; font-weight: bold;">Final Charge: ₱<?= $final_cost_display ?></p>
                                <p style="color: yellow; font-weight: bold;">Refund Processed: ₱<?= number_format($refund_amount_hist, 2) ?></p>
                            <?php elseif (!empty($row['total_deducted_cost'])): ?>
                                <p style="color: #28a745; font-weight: bold;">Final Charge: ₱<?= $final_cost_display ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <p>Final Status: <span style="font-weight: bold; color: <?= $status_color ?>;"><?= strtoupper($status_text) ?></span></p>
                        
                        <?php if ($status_text === 'Returned'): ?>
    <p style="font-size: 0.9em; margin-top: 10px; color: #ccc;">
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
                
                <div style="margin-top: 10px;">
                    <a href="generate_receipt.php?request_id=<?= $row['request_id'] ?>" 
                       class="btn-receipt" 
                       style="display: inline-flex; align-items: center; background: #007bff; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; font-size: 0.85em; transition: background 0.3s;">
                        <svg style="width:16px; height:16px; margin-right:8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Download Final Receipt
                    </a>
                </div>

            <?php else: ?>
                <p style="color: <?= $status_color ?>; font-weight: bold; margin: 0;">STOPPED</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endwhile; ?>
<?php else: ?>
    <p style="text-align: center; color: #888; margin-top: 20px;">You have no past rental history.</p>
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
                <select name="payment_method_select" id="payment-method-select" onchange="showQr(this.value)">
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
                <input type="file" name="payment_proof" id="payment-proof-file" accept="image/*,application/pdf" required>
                
                <button type="submit">Upload Proof & Confirm</button>
            </form>
            
            <p style="margin-top: 15px; font-size: 0.85em; color: #ddd; text-align: center;">Your payment status will be updated to "Proof Uploaded" for admin verification.</p>
        </div>
        
      <div id="schedule-popover" popover="auto">
    <h3>Early Return Schedule</h3>
    <p><strong>Car:</strong> <span id="schedulePopoverCar"></span></p>
     <div class="refund-box">

        <strong>Refund Rules:</strong>

        <ul>

            <li>Returns on the <b>Pickup Date</b> count as Day 1 of usage (charged).</li>

            <li>Returns on the <b>Original Return Date</b> result in <b>No Refund</b>.</li>

     

        </ul>

    </div>
    <form id="schedule-form" action="submit_early_return_schedule.php" method="POST">
        <input type="hidden" name="action" value="submit_schedule">
        <input type="hidden" name="request_id" id="schedulePopoverRequestId">
        
        <div class="schedule-form-content">
            <label for="schedule-date"><strong>Return Date:</strong></label>
            
            <input type="date" name="schedule_date" id="schedule-date" required>
            
            <small id="date-constraints" style="color: #aaa; display: block; margin-top: 2px;"></small>

            <label for="schedule-time" style="margin-top: 10px;"><strong>Return Time:</strong></label>
            <input type="time" name="schedule_time" id="schedule-time" required>
        </div>
        
        <button type="submit">Confirm Return Schedule</button>
    </form>
</div>
        
        <div id="extend-popover" popover="auto">
            <h3>Extend Rental Period</h3>
            <p><strong>Car:</strong> <span id="extendPopoverCar"></span></p>
            <p>Original End Date: <strong id="extendPopoverOldEndDate"></strong></p>
            <hr>

            <form id="extendRentalForm" enctype="multipart/form-data">
                <input type="hidden" name="request_id" id="extendPopoverRequestId">
                <input type="hidden" name="car_id" id="extendPopoverCarId">
                <input type="hidden" name="daily_rate" id="extendPopoverDailyRate">
                <input type="hidden" name="old_end_date" id="extendPopoverOldEndDateHidden">

                <div class="form-group">
                    <label for="days_to_extend"><strong>Days to Extend:</strong></label>
                    <input type="number" class="form-control" id="days_to_extend" name="days_to_extend" min="1" required>
                </div>
                
                <h6 style="margin-top: 15px;">Extension Summary:</h6>
                <p>
                    <span class="font-weight-bold" style="color: #ff5722;">New Estimated Cost: </span>
                    <span id="extensionCost" class="font-weight-bold" style="color: #ff5722;">₱ 0.00</span>
                </p>
                <p>
                    <span class="font-weight-bold" style="color: #00bcd4;">New Estimated End Date: </span>
                    <span id="newEndDate" class="font-weight-bold" style="color: #00bcd4;">N/A</span>
                </p>

                <hr>
                <h6 class="font-weight-bold">Payment Details for Extension</h6>
                <div class="alert alert-info" style="padding: 10px; background-color: #007bff; color: white; border-radius: 4px; font-size: 0.9em;" role="alert">
                    Please pay the calculated **New Estimated Cost** and upload your proof below.
                </div>
                
                <div class="form-group">
                    <label for="payment_method"><strong>Payment Method:</strong></label>
                    <select class="form-control" id="extend_payment_method" name="payment_method" required onchange="showExtendQr(this.value)">
                        <option value="">Select Method</option>
                        <option value="gcash">GCash</option>
                        <option value="maya">Maya</option>
                    </select>
                    
                    <div id="extend_qr_codes" class="mt-2 text-center" style="display: none;">
                        <img id="extend_gcash_qr" src="<?= htmlspecialchars($gcash_qr_path) ?>" alt="GCash QR" style="max-width: 150px; display: none;">
                        <img id="extend_maya_qr" src="<?= htmlspecialchars($maya_qr_path) ?>" alt="Maya QR" style="max-width: 150px; display: none;">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="payment_reference_no"><strong>Payment Reference Number:</strong></label>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="payment_reference_no" 
                        name="payment_reference_no" 
                        required 
                        pattern="^\d{13}$" 
                        title="Reference number must be exactly 13 digits - no more, no less."
                        maxlength="13"
                        minlength="13"
                        inputmode="numeric"
                        placeholder="Enter exactly 13 digits"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 13);"
                    >
                </div>
                
                <div class="form-group">
                    <label for="payment_proof_path"><strong>Upload Payment Proof (Image):</strong></label>
                    <input type="file" class="form-control-file" id="payment_proof_path_extend" name="payment_proof_path" accept="image/*" required>
                </div>
                
                <button type="submit">Submit Extension Request</button>
            </form>
        </div>
        </main>
    
    <script>
        // Existing Popover Elements (UNCHANGED)
        const popoverRequestId = document.getElementById('popoverRequestId');
        const popoverPaymentMethod = document.getElementById('popoverPaymentMethod');
        const paymentMethodSelect = document.getElementById('payment-method-select');
        const popoverCar = document.getElementById('popoverCar');
        const popoverTotalCost = document.getElementById('popoverTotalCost');
        const gcashQr = document.getElementById('popoverGcashQr');
        const mayaQr = document.getElementById('popoverMayaQr');
        const qrInstructions = document.getElementById('qr-instructions');

        // Schedule Popover Elements (UNCHANGED)
        const schedulePopoverRequestId = document.getElementById('schedulePopoverRequestId');
        const schedulePopoverCar = document.getElementById('schedulePopoverCar');
        const scheduleDateInput = document.getElementById('schedule-date');

        // NEW Extend Popover Elements
        const extendPopoverRequestId = document.getElementById('extendPopoverRequestId');
        const extendPopoverCarId = document.getElementById('extendPopoverCarId');
        const extendPopoverDailyRate = document.getElementById('extendPopoverDailyRate');
        const extendPopoverOldEndDateHidden = document.getElementById('extendPopoverOldEndDateHidden');
        const extendPopoverCar = document.getElementById('extendPopoverCar');
        const extendPopoverOldEndDate = document.getElementById('extendPopoverOldEndDate');
        const daysToExtendInput = document.getElementById('days_to_extend');
        const extensionCostSpan = document.getElementById('extensionCost');
        const newEndDateSpan = document.getElementById('newEndDate');
        const extendPaymentMethodSelect = document.getElementById('extend_payment_method');
        const extendGcashQr = document.getElementById('extend_gcash_qr');
        const extendMayaQr = document.getElementById('extend_maya_qr');
        
        // --- Existing Popover Functions (UNCHANGED) ---
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
        
     function openSchedulePopover(button) {
    try {
        const data = JSON.parse(button.getAttribute('data-popover-details'));
        
        // 1. Reference the input elements
        const scheduleDateInput = document.getElementById('schedule-date');
        const schedulePopoverCar = document.getElementById('schedulePopoverCar');
        const schedulePopoverRequestId = document.getElementById('schedulePopoverRequestId');

        // 2. Clear old values and constraints to force the browser to refresh
        scheduleDateInput.value = '';
        scheduleDateInput.removeAttribute('min');
        scheduleDateInput.removeAttribute('max');

        // 3. Assign display labels
        schedulePopoverCar.textContent = data.car_display;
        schedulePopoverRequestId.value = data.request_id;

        // 4. Set the new boundaries
        // min_date should be Jan 28. This grays out all previous dates.
        scheduleDateInput.setAttribute('min', data.min_date);
        
        // max_date is the end of the contract. This grays out everything after.
        scheduleDateInput.setAttribute('max', data.max_date);

        // 5. Ensure the browser doesn't allow manual typing of invalid dates
        scheduleDateInput.onkeydown = (e) => e.preventDefault();

        console.log("Range set: From " + data.min_date + " to " + data.max_date);

    } catch (e) {
        console.error("Error parsing schedule data:", e);
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
        
        // --- NEW: Extend Popover Functions and Logic (UNCHANGED) ---

        function openExtendPopover(button) {
            try {
                const data = JSON.parse(button.getAttribute('data-popover-details'));
                
                // Set hidden form values
                extendPopoverRequestId.value = data.request_id;
                extendPopoverCarId.value = data.car_id;
                extendPopoverDailyRate.value = data.daily_rate;
                extendPopoverOldEndDateHidden.value = data.old_end_date;

                // Set display values
                extendPopoverCar.textContent = data.car_display;
                extendPopoverOldEndDate.textContent = new Date(data.old_end_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                
                // Reset form inputs
                daysToExtendInput.value = '';
                extensionCostSpan.textContent = '₱ 0.00';
                newEndDateSpan.textContent = 'N/A';
                document.getElementById('payment_reference_no').value = '';
                document.getElementById('payment_proof_path_extend').value = '';
                extendPaymentMethodSelect.value = '';
                showExtendQr('');

            } catch (e) {
                console.error("Error loading extension data:", e);
                alert("Could not load extension details. Data error.");
            }
        }
        
        function showExtendQr(method) {
            extendGcashQr.style.display = 'none';
            extendMayaQr.style.display = 'none';
            document.getElementById('extend_qr_codes').style.display = 'none';
            
            if (method === 'gcash') {
                extendGcashQr.style.display = 'block';
                document.getElementById('extend_qr_codes').style.display = 'block';
            } else if (method === 'maya') {
                extendMayaQr.style.display = 'block';
                document.getElementById('extend_qr_codes').style.display = 'block';
            }
        }

        // Calculation and AJAX Logic (UNCHANGED)
        $(document).ready(function() {
            // 1. Calculate Cost and New End Date on Input Change (for extend form)
            $('#days_to_extend').on('input', function() {
                const dailyRate = parseFloat($('#extendPopoverDailyRate').val());
                const oldEndDateStr = $('#extendPopoverOldEndDateHidden').val();
                let days = parseInt($(this).val());
                
                if (days > 0 && dailyRate) {
                    const additionalCost = days * dailyRate;
                    
                    // Calculate new end date
                    let oldDate = new Date(oldEndDateStr);
                    let newDate = new Date(oldDate.getTime());
                    newDate.setDate(newDate.getDate() + days);
                    
                    const options = { year: 'numeric', month: 'long', day: 'numeric' };
                    const formattedNewDate = newDate.toLocaleDateString('en-US', options);

                    $('#extensionCost').text('₱ ' + additionalCost.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ","));
                    $('#newEndDate').text(formattedNewDate);
                } else {
                    $('#extensionCost').text('₱ 0.00');
                    $('#newEndDate').text('N/A');
                }
            });

            // 2. Handle Extend Rental Form Submission via AJAX
            $('#extendRentalForm').on('submit', function(e) {
                e.preventDefault();
                
                const dailyRate = parseFloat($('#extendPopoverDailyRate').val());
                const oldEndDateStr = $('#extendPopoverOldEndDateHidden').val();
                const daysToExtend = parseInt($('#days_to_extend').val());

                if (daysToExtend <= 0 || isNaN(daysToExtend)) {
                    Swal.fire('Validation Error', 'Please enter a valid number of days to extend.', 'error');
                    return;
                }
                if ($('#extend_payment_method').val() === "") {
                    Swal.fire('Validation Error', 'Please select a payment method.', 'error');
                    return;
                }
                // Client-side file check (basic)
                if (document.getElementById('payment_proof_path_extend').files.length === 0) {
                    Swal.fire('Validation Error', 'Payment proof is required.', 'error');
                    return;
                }
                
                // Validate payment reference number - must be exactly 13 digits
                const refValue = $('#payment_reference_no').val().trim();
                const isThirteenDigits = /^\d{13}$/.test(refValue);
                if (!isThirteenDigits) {
                    Swal.fire('Validation Error', 'Reference Number must be exactly 13 digits and contain only numbers.', 'error');
                    document.getElementById('payment_reference_no').focus();
                    return;
                }

                const formData = new FormData(this);
                const additionalCostRaw = daysToExtend * dailyRate;
                formData.append('additional_cost', additionalCostRaw.toFixed(2));
                
                // Calculate and append the new end date (in YYYY-MM-DD format)
                let oldDate = new Date(oldEndDateStr);
                let newDate = new Date(oldDate.getTime());
                newDate.setDate(newDate.getDate() + daysToExtend);
                
                // Use ISO string split to ensure YYYY-MM-DD format without time zone issues
                formData.append('new_end_date', newDate.toISOString().split('T')[0]);

                $.ajax({
                    url: 'extend_rental_endpoint.php', // This is the file that will process the extension request
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Success!', response.message, 'success').then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error!', 'An error occurred while submitting the request.', 'error');
                    }
                });
            });
        });
        
        // --- Existing Form Submissions (UNCHANGED) ---
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

        // Schedule Form Validation
        document.getElementById('schedule-form').onsubmit = function() {
            if (scheduleDateInput.value === "" || document.getElementById('schedule-time').value === "") {
                alert("Please input both the date and time for the early return schedule.");
                return false;
            }
            return true;
        };


        // Handle URL parameters after successful upload (UNCHANGED)
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('success') === 'payment_proof_uploaded') {
                alert("Payment proof successfully uploaded! Please wait for admin verification.");
                
                if (history.replaceState) {
                    const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                    history.replaceState({path:cleanUrl},'',cleanUrl);
                }
            } else if (urlParams.get('success_schedule')) { 
                alert(decodeURIComponent(urlParams.get('success_schedule')));
                 
                if (history.replaceState) {
                    const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                    history.replaceState({path:cleanUrl},'',cleanUrl);
                }
            } else if (urlParams.get('success_return')) { 
                alert(decodeURIComponent(urlParams.get('success_return')));
                 
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
if (isset($stmt_current) && $stmt_current) $stmt_current->close();
if (isset($stmt_history) && $stmt_history) $stmt_history->close();
if (isset($conn)) $conn->close();
?>