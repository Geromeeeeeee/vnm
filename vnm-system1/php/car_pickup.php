<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start(); 
include 'db.php'; 

$request_id = filter_input(INPUT_GET, 'request_id', FILTER_VALIDATE_INT);
if (!$request_id) {
    
    header("Location: rentals.php?error=" . urlencode("Invalid request ID for pickup."));
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
        rr.payment_status,
        u.fullname AS renter_name,
        c.car_id,
        c.car_brand,
        c.model,
        c.plate_no
    FROM rental_requests rr
    INNER JOIN users u ON rr.user_id = u.user_id 
    INNER JOIN cars c ON rr.car_id = c.car_id
    WHERE rr.request_id = ? AND rr.request_status = 'Approved'
";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $request_id);
$stmt->execute();
$result = $stmt->get_result();
$rental_data = $result->fetch_assoc();
$stmt->close();

if (!$rental_data) {
    header("Location: rentals.php?error=" . urlencode("Rental request not found, or status is incorrect for pickup (must be 'Approved')."));
    exit;
}

$car_id = $rental_data['car_id'];
$renter_name = htmlspecialchars($rental_data['renter_name']);
$car_details = htmlspecialchars("{$rental_data['car_brand']} {$rental_data['model']} ({$rental_data['plate_no']})");
$pickup_datetime = htmlspecialchars(date('F j, Y, g:i A', strtotime("{$rental_data['rental_date']} {$rental_data['rental_time']}")));

$system_base_path = '/vnm-system1/';
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
    <title>Process Car Pick Up</title>
    <style>
        main { padding: 20px; max-width: 800px; margin: 0 auto; }
        .container { background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-top: 20px; }
        h2 { color: #333; border-bottom: 2px solid #ccc; padding-bottom: 10px; margin-top: 0; }
        .info p { margin: 10px 0; font-size: 1.1em; }
        .info strong { display: inline-block; width: 150px; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        textarea, input[type="number"] { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { background-color: #28a745; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; margin-top: 20px; width: 100%; font-size: 1.1em; }
        button:hover { background-color: #1e7e34; }
        .error { color: red; font-weight: bold; margin-top: 10px;}
        .success { color: green; font-weight: bold; margin-top: 10px;}
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
          <a href="/vnm-system1/php/rentals.php" class="nav-link bg-gray text-white">
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
        <h3 class="card-title">Car Pick Up Confirmation</h3>
    </div>

    <div class="card-body">

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success']) && $_GET['success'] === '1'): ?>
            <div class="alert alert-success">
                <strong>Success!</strong> Rental successfully started.  
                Car status is now <b>Picked Up</b> and the car has been set to <b>Unavailable</b>.
            </div>

            <a href="car_lifecycle.php" class="btn btn-primary btn-block mt-3">
                <i class="fas fa-arrow-left"></i> Go back to Car Lifecycle
            </a>
        <?php endif; ?>

        <?php if (!isset($_GET['success']) || $_GET['success'] !== '1'): ?>

            <div class="callout callout-info">
                <p><strong>Renter:</strong> <?= $renter_name ?></p>
                <p><strong>Car Details:</strong> <?= $car_details ?></p>
                <p><strong>Scheduled Pick Up:</strong> <?= $pickup_datetime ?></p>
                <p>
                    <strong>Current Rental Status:</strong>
                    <span class="badge badge-primary">
                        <?= htmlspecialchars($rental_data['request_status']) ?>
                    </span>
                    <span class="badge badge-warning">
                        <?= htmlspecialchars($rental_data['payment_status']) ?>
                    </span>
                </p>
            </div>

            <form action="pickup_action.php" method="POST">
                <input type="hidden" name="request_id" value="<?= $request_id ?>">
                <input type="hidden" name="car_id" value="<?= $car_id ?>">

                <div class="form-group">
                    <label for="odometer">Odometer Reading (Current Mileage)</label>
                    <input
                        type="number"
                        id="odometer"
                        name="odometer"
                        class="form-control"
                        required
                        min="0"
                        placeholder="e.g., 15000"
                    >
                </div>

                <div class="form-group">
                    <label for="condition">Car Condition at Pick Up (Notes)</label>
                    <textarea
                        id="condition"
                        name="condition"
                        rows="4"
                        class="form-control"
                        required
                        placeholder="e.g., Minor scratch on the rear bumper. Fuel: Full."
                    ></textarea>
                </div>

                <button
                    type="submit"
                    name="action"
                    value="confirm_pickup"
                    class="btn btn-success btn-block"
                >
                    <i class="fas fa-car"></i> Confirm Pick Up and Start Rental
                </button>
            </form>

        <?php endif; ?>

    </div>
</div>

</div>
</section>
</div>
</body>
</html>