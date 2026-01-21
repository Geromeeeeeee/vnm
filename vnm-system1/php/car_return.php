<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start(); 
include 'db.php'; 

$request_id = filter_input(INPUT_GET, 'request_id', FILTER_VALIDATE_INT);
if (!$request_id) {
    header("Location: car_lifecycle.php?error=" . urlencode("Invalid rental request ID for return."));
    exit;
}

$query = "
    SELECT 
        rr.request_id,
        rr.rental_date,
        rr.rental_time,
        rr.rental_duration_days,
        rr.total_cost,
        rr.request_status,
        u.fullname AS renter_name,
        c.car_id,
        c.car_brand,
        c.model,
        c.plate_no,
        pd.odometer_pickup,
        pd.car_condition_pickup,
        rrr.total_deducted_cost,
        rrr.scheduled_return_date, 
        rrr.scheduled_return_time,
        rrr.requested_at AS scheduled_return_datetime
    FROM rental_requests rr
    INNER JOIN users u ON rr.user_id = u.user_id 
    INNER JOIN cars c ON rr.car_id = c.car_id
    INNER JOIN rental_pickup_details pd ON rr.request_id = pd.request_id
    LEFT JOIN rental_return_requests rrr ON rr.request_id = rrr.request_id
    WHERE rr.request_id = ? AND rr.request_status IN ('Picked Up', 'Early_Return_Scheduled')
";

$stmt = $conn->prepare($query);

if ($stmt === false) {
    header("Location: car_lifecycle.php?error=" . urlencode("Database Error: Could not prepare data query. Please check database fields."));
    exit;
}

$stmt->bind_param('i', $request_id);
$stmt->execute();
$result = $stmt->get_result();
$rental_data = $result->fetch_assoc();
$stmt->close();

if (!$rental_data) {
    header("Location: car_lifecycle.php?error=" . urlencode("Rental not found, or status is incorrect for return (must be 'Picked Up' or 'Early Return Scheduled')."));
    exit;
}

$car_id = $rental_data['car_id'];
$renter_name = htmlspecialchars($rental_data['renter_name']);
$car_details = htmlspecialchars("{$rental_data['car_brand']} {$rental_data['model']} ({$rental_data['plate_no']})");
$pickup_datetime = date('F j, Y, g:i A', strtotime("{$rental_data['rental_date']} {$rental_data['rental_time']}"));
$pickup_odometer = number_format($rental_data['odometer_pickup']);
$pickup_condition = htmlspecialchars($rental_data['car_condition_pickup']);

$scheduled_return_datetime_display = 'N/A';

$is_early_return = $rental_data['request_status'] === 'Early_Return_Scheduled';
$status_display = $is_early_return ? "Early Return Scheduled" : "Picked Up (Regular Return)";

if ($is_early_return) {
    $deducted_cost_formatted = number_format($rental_data['total_deducted_cost'] ?? 0, 2);
    $original_cost_formatted = number_format($rental_data['total_cost'] ?? 0, 2);
    $refund_amount = ($rental_data['total_cost'] ?? 0) - ($rental_data['total_deducted_cost'] ?? 0);
    $refund_formatted = number_format(max(0, $refund_amount), 2);
    
    if (!empty($rental_data['scheduled_return_datetime'])) {
        $scheduled_return_datetime_display = date('F j, Y, g:i A', strtotime($rental_data['scheduled_return_datetime']));
    } 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script> 
    <title>Process Car Return</title>
    <style>

main { padding: 20px; max-width: 800px; margin: 0 auto; }
        .container { background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-top: 20px; }
        h2 { color: #333; border-bottom: 2px solid #ccc; padding-bottom: 10px; margin-top: 0; }
        .info p { margin: 10px 0; font-size: 1.1em; }
        .info strong { display: inline-block; width: 180px; font-weight: bold; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        textarea, input[type="number"], input[type="datetime-local"] { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { background-color: #dc3545; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; margin-top: 20px; width: 100%; font-size: 1.1em; }
        button:hover { background-color: #c82333; }
        .error { color: red; font-weight: bold; margin-top: 10px;}
        .success { color: green; font-weight: bold; margin-top: 10px;}
        .early-return-details { border: 2px solid #007bff; background-color: #e9f5ff; padding: 15px; border-radius: 4px; margin-top: 15px; }
        .early-return-details h3 { color: #007bff; margin-top: 0; padding-bottom: 5px; border-bottom: 1px solid #b3d9ff;}
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
    <aside class="main-sidebar sidebar-light-primary elevation-4 layout-fixed">
  <a href="/vnm-system1/php/adminindex.php" class="brand-link">
    <img src="/vnm-system1/photos/VNM logo.png" 
         alt="VNM Logo" 
         class="brand-image img-square "
         style="opacity: .8">
    <span class="brand-text font-weight-light">VNM Admin</span>
  </a>
  <div class="sidebar">
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" 
          data-widget="treeview" role="menu" data-accordion="false">
        <li class="nav-item">
          <a href="/vnm-system1/php/adminindex.php" class="nav-link">
            <p>Dashboard</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="/vnm-system1/php/cars/cars.php" class="nav-link">
            <p>Cars</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="/vnm-system1/php/rentals.php" class="nav-link bg-gray">
            <p>Rentals</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="/vnm-system1/php/manage_accounts.php" class="nav-link">
            <p>Accounts</p>
          </a>
        </li>
      </ul>
    </nav>
  </div>
</aside>
    <div class="content-wrapper">
<section class="content pt-4">
<div class="container-fluid">

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Car Return Confirmation</h3>
    </div>

    <div class="card-body">

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>

        <?php 
        if (isset($_GET['success'])): 
            $message = htmlspecialchars(urldecode($_GET['success']));
        ?>
            <div class="alert alert-success">
                <?= $message ?>
            </div>

            <a href="car_lifecycle.php" class="btn btn-primary btn-block mt-3">
                <i class="fas fa-arrow-left"></i> Go back to Car Lifecycle
            </a>
        <?php endif; ?>

        <?php if (!isset($_GET['success'])): ?>

            <div class="callout callout-info">
                <p><strong>Renter:</strong> <?= $renter_name ?></p>
                <p><strong>Car Details:</strong> <?= $car_details ?></p>
                <p><strong>Scheduled Pick Up:</strong> <?= $pickup_datetime ?></p>

                <hr>

                <p><strong>Pickup Odometer:</strong> <?= $pickup_odometer ?> km</p>
                <p><strong>Pickup Condition Notes:</strong> <?= $pickup_condition ?></p>
                <p>
                    <strong>Current Rental Status:</strong>
                    <span class="badge badge-success"><?= $status_display ?></span>
                </p>
            </div>

            <?php if ($rental_data): ?>
   <?php 
    // --- 1. PREPARE ALL VARIABLES FIRST ---
    $pickup_odometer = $rental_data['odometer_pickup'] ?? 0;
    
    // Calculate boundaries based on pickup date and duration
    $min_date_string = "{$rental_data['rental_date']} {$rental_data['rental_time']}";
    $min_date_val = date('Y-m-d\TH:i', strtotime($min_date_string));

    $duration_days = (int)$rental_data['rental_duration_days'];
    $max_timestamp = strtotime($min_date_string . " +{$duration_days} days");
    $max_date_val = date('Y-m-d\TH:i', $max_timestamp);

    // --- LINK CUSTOMER'S CHOSEN RETURN TIME ---
    if (!empty($rental_data['scheduled_return_date']) && !empty($rental_data['scheduled_return_time'])) {
        $user_picked_dt = $rental_data['scheduled_return_date'] . ' ' . $rental_data['scheduled_return_time'];
        $default_return_value = date('Y-m-d\TH:i', strtotime($user_picked_dt));
    } else {
        $default_return_value = date('Y-m-d\TH:i');
    }

    // Friendly display for the UI
    $pickup_display = date('F j, Y, g:i A', strtotime($min_date_string));
    $return_deadline_display = date('F j, Y, g:i A', $max_timestamp);

    // ================================================================
    // ACCURATE REFUND CALCULATION LOGIC
    // ================================================================
    $pickup_dt = new DateTime($min_date_string); 
    $original_return_dt = new DateTime($min_date_string . " +{$duration_days} days");
    $actual_return_dt = new DateTime($default_return_value);

    // Daily rate based on total contract cost
    $daily_rate = $rental_data['total_cost'] / $duration_days;

    // Rule 3: If returned at or after the original deadline, no refund
    if ($actual_return_dt >= $original_return_dt) {
        $days_to_charge = $duration_days;
        $refund_amount = 0;
    } else {
        // Rule 1: Measure strictly by 24-hour blocks
        $interval = $pickup_dt->diff($actual_return_dt);
        
        // Convert interval to total hours used
        $total_hours = ($interval->days * 24) + $interval->h + ($interval->i / 60);
        
        // Use ceil to count any part of a 24hr block as a full day
        $days_used = ceil($total_hours / 24);

        // Rule 2: If duration is 0 or same-day, it counts as Day 1
        if ($days_used < 1) $days_used = 1; 

        $days_to_charge = $days_used;
        $remaining_days = $duration_days - $days_to_charge;
        
        // Calculate final refund (₱)
        $refund_amount = max(0, $remaining_days * $daily_rate);
    }
    // ================================================================
?>

    <?php if ($is_early_return): ?>
        <div class="callout callout-warning">
            <h5><i class="fas fa-exclamation-triangle"></i> Early Return Financial Details (Estimate)</h5>

           
            <p><strong>Original Total Cost:</strong> ₱<?= $original_cost_formatted ?></p>
            <p><strong>Estimated Cost Used:</strong> ₱<?= $deducted_cost_formatted ?></p>
            <p>
                <strong>Estimated Refund / Credit:</strong>
                <span class="text-success font-weight-bold">₱<?= $refund_formatted ?></span>
            </p>

            <p class="text-danger font-weight-bold mt-2">
                NOTE: Final cost & refund will be calculated based on the
                <u>Actual Return Date & Time</u> entered below.
            </p>
        </div>
    <?php endif; ?>

    <form action="return_action.php" method="POST">

        <input type="hidden" name="request_id" value="<?= $request_id ?>">
        <input type="hidden" name="car_id" value="<?= $car_id ?>">

        <div class="form-group">
            <label for="return_odometer">Return Odometer Reading (Current Mileage)</label>
            <input
                type="number"
                id="return_odometer"
                name="return_odometer"
                class="form-control"
                required
                min="<?= $pickup_odometer ?>"
                placeholder="Must be greater than pickup mileage (<?= $pickup_odometer ?>)"
            >
        </div>

        <div class="form-group">
            <label for="return_condition">Car Condition at Return (Notes)</label>
            <textarea
                id="return_condition"
                name="return_condition"
                rows="4"
                class="form-control"
                required
                placeholder="e.g., Car returned clean. New scratch on driver side door."
            ></textarea>
        </div>

        <div class="form-group">
            <label for="damage_fee">Damage / Extra Fee (₱)</label>
            <input
                type="number"
                id="damage_fee"
                name="damage_fee"
                class="form-control"
                step="0.01"
                min="0"
                value="0.00"
                required
            >
        </div>

      <div class="form-group">
    <label for="return_date_time">Actual Return Date & Time</label>
    <input
        type="datetime-local"
        id="return_date_time"
        name="return_date_time"
        class="form-control"
        required
        min="<?= $min_date_val ?>"
        max="<?= $max_date_val ?>"
        onkeydown="return false"
        /* This uses the 'default_return_value' variable from Step 1 */
        value="<?= $default_return_value ?>" 
    >
    
    <?php if (!empty($rental_data['scheduled_return_date'])): ?>
        <div class="mt-2 p-2 bg-light border-left border-primary">
            <small class="text-primary font-weight-bold">
                <i class="fas fa-clock"></i> CUSTOMER SCHEDULED RETURN: 
                <?= date('F j, Y, g:i A', strtotime($rental_data['scheduled_return_date'] . ' ' . $rental_data['scheduled_return_time'])) ?>
            </small>
        </div>
    <?php endif; ?>

    <small class="text-muted d-block mt-1">
        <strong>Strict Boundary Rule:</strong><br>
        Pickup: <?= $pickup_display ?><br>
        Deadline: <?= $return_deadline_display ?>
    </small>
</div>
   
</div>

        <button
            type="submit"
            name="action"
            value="confirm_return"
            class="btn btn-success btn-block"
        >
            <i class="fas fa-check-circle"></i> Confirm Return and Finalize Rental
        </button>

    </form>
<?php endif; ?>

        <?php endif; ?>

    </div>
</div>

</div>
</section>
</div>
</body>
</html>