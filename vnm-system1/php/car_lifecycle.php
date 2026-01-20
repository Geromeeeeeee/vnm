<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start(); 
include 'db.php'; 

// --- 1. Query for Active Rental Lifecycle Management (UNCHANGED) ---
$query = "
    SELECT 
        rr.request_id,
        rr.rental_date,
        rr.rental_time,
        rr.rental_duration_days,
        rr.total_cost,
        rr.request_status,
        rr.payment_status,
        u.fullname,
        c.car_brand,
        c.model,
        c.plate_no,
        -- Get pickup details from the separate pickup details table
        pd.odometer_pickup
    FROM rental_requests rr
    INNER JOIN users u ON rr.user_id = u.user_id 
    INNER JOIN cars c ON rr.car_id = c.car_id
    -- JOIN with the dedicated pickup details table
    LEFT JOIN rental_pickup_details pd ON rr.request_id = pd.request_id
    -- MODIFIED: Include all new statuses for admin visibility in main list
    WHERE rr.request_status IN ('Approved', 'Picked Up', 'Early Return Requested', 'Early_Return_Approved', 'Early_Return_Scheduled')
    ORDER BY rr.request_status DESC, rr.rental_date ASC
";

$details = mysqli_query($conn, $query); 
$system_base_path = '/vnm-system1/'; 

// --- 2. Query for Return Requests (Pending Admin Approval 1) (UNCHANGED) ---
$display_return_req = "SELECT 
    rrr.request_id, 
    users.user_id, 
    users.fullname,
    rrr.requested_at,
    rrr.total_deducted_cost,
    c.car_brand, 
    c.model,
    c.plate_no
    FROM rental_return_requests rrr 
    JOIN users ON rrr.user_id = users.user_id
    JOIN rental_requests rr ON rrr.request_id = rr.request_id 
    JOIN cars c ON rr.car_id = c.car_id 
    WHERE rrr.status = 'pending'"; // This only selects requests awaiting initial approval
    $query_result = mysqli_query($conn, $display_return_req);

// --- 3. Query for Extension Requests (NEW) ---
$extension_requests = [];
$ext_sql = "SELECT 
    re.extension_id, 
    re.request_id, 
    re.days_to_extend, 
    re.new_end_date, 
    re.additional_cost, 
    re.payment_proof_path, 
    re.requested_at, 
    u.fullname, 
    c.model, 
    c.plate_no,
    rr.rental_duration_days as current_duration
    FROM rental_extension_requests re
    JOIN rental_requests rr ON re.request_id = rr.request_id
    JOIN users u ON re.user_id = u.user_id
    JOIN cars c ON rr.car_id = c.car_id
    WHERE re.status = 'Paid_Pending_Approval'
    ORDER BY re.requested_at DESC";

$ext_result = mysqli_query($conn, $ext_sql);
if ($ext_result) {
    while ($row = mysqli_fetch_assoc($ext_result)) {
        $extension_requests[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Lifecycle</title>
    <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
     <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
    <style>
        main { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .container { background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-top: 20px; }
        h2 { color: #333; border-bottom: 2px solid #ccc; padding-bottom: 10px; margin-top: 0; }
        h3 { color: #007bff; margin-top: 20px; }
        .error { color: red; font-weight: bold; margin-top: 10px;}
        .success { color: green; font-weight: bold; margin-top: 10px;}
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 12px; text-align: left; }
        th { background-color: #f2f2f2; }
        .status-ready { color: orange; font-weight: bold; }
        .status-pickedup { color: green; font-weight: bold; }
        /* NEW STYLE for Early Return Request Pending Approval 1 */
        .status-early-return { 
            color: #dc3545; 
            font-weight: bold; 
            background-color: #fff3cd; 
            padding: 2px 5px;
            border-radius: 3px;
        }
        /* NEW STYLE for Early Return Approved (Customer scheduling) and Scheduled (Final Approval) */
        .status-approved-schedule, .status-scheduled {
            color: blue;
            font-weight: bold;
            background-color: #e0f7fa;
            padding: 2px 5px;
            border-radius: 3px;
        }
        /* NEW STYLE for Extension Pending Approval */
        .status-extension-pending {
            color: #00bcd4; 
            font-weight: bold;
            background-color: #e0f7fa;
            padding: 2px 5px;
            border-radius: 3px;
        }
        .pickup-btn, .return-btn, .approve-btn, .action-btn-ext { 
            display: inline-block; 
            padding: 8px 12px; 
            color: white; 
            text-decoration: none; 
            border-radius: 4px; 
            text-align: center;
            cursor: pointer;
            border: none;
        }
        .pickup-btn { background-color: #007bff; }
        .return-btn { background-color: #dc3545; }
        .approve-btn, .approve-ext-btn { background-color: #28a745; } 
        .reject-ext-btn { background-color: #dc3545; } 
        /* Style for return button when early return is scheduled/requested */
        .early-return-action-btn { 
            background-color: #ffc107; 
            color: black; 
            font-weight: bold;
        }
        .pickup-btn:hover { background-color: #0056b3; }
        .return-btn:hover { background-color: #c82333; }
        .early-return-action-btn:hover { background-color: #e0a800; }
        .approve-btn:hover, .approve-ext-btn:hover { background-color: #218838; } 
        .reject-ext-btn:hover { background-color: #a71d2a; }
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
</nav>
    <div class="content-wrapper">
<section class="content pt-4">
<div class="container-fluid">

<!-- ===================== PICKUP & RETURN ===================== -->
<div class="card">
    <div class="card-header bg-gray">
        <h3 class="card-title text-white">Car Lifecycle Management (Pick Up & Return)</h3>
    </div>

    <div class="card-body">

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_GET['success']) ?>
            </div>
        <?php endif; ?>

        <table class="table table-bordered table-hover">
            <thead class="thead-light">
                <tr>
                    <th>Renter</th>
                    <th>Car Details</th>
                    <th>Scheduled Date/Time</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Pickup Mileage</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php
            if ($details && mysqli_num_rows($details) > 0) {
                while ($row = mysqli_fetch_assoc($details)) {

                    $request_id = $row['request_id'];
                    $car_display = htmlspecialchars("{$row['car_brand']} {$row['model']} ({$row['plate_no']})");

                    $status_badge = '';
                    $action_button = '';

                    if ($row['request_status'] === 'Approved') {

                        if ($row['payment_status'] === 'Proof Uploaded' || $row['payment_status'] === 'Paid') {
                            $status_badge = "<span class='badge badge-success'>Ready for Pick Up</span>";
                            $action_button = "<a href='car_pickup.php?request_id={$request_id}' class='btn btn-primary btn-sm'>Process Pick Up</a>";
                        } else {
                            $status_badge = "<span class='badge badge-warning'>Awaiting Payment</span>";
                            $action_button = "<span class='text-muted'>Awaiting Payment</span>";
                        }

                    } elseif ($row['request_status'] === 'Picked Up') {

                        $status_badge = "<span class='badge badge-info'>On the Road</span>";
                        $action_button = "<a href='car_return.php?request_id={$request_id}' class='btn btn-success btn-sm'>Process Return</a>";

                    } elseif ($row['request_status'] === 'Early Return Requested') {

                        $status_badge = "<span class='badge badge-danger'>Early Return Requested</span>";
                        $action_button = "<span class='text-danger'>Awaiting Approval</span>";

                    } elseif ($row['request_status'] === 'Early_Return_Approved') {

                        $status_badge = "<span class='badge badge-primary'>Awaiting Customer</span>";
                        $action_button = "<span class='text-primary'>Awaiting Schedule</span>";

                    } elseif ($row['request_status'] === 'Early_Return_Scheduled') {

                        $status_badge = "<span class='badge badge-warning'>Early Return Scheduled</span>";
                        $action_button = "<a href='car_return.php?request_id={$request_id}' class='btn btn-warning btn-sm'>Process Early Return</a>";
                    }

                    echo "
                    <tr>
                        <td>{$row['fullname']}</td>
                        <td>{$car_display}</td>
                        <td>{$row['rental_date']} @ {$row['rental_time']}</td>
                        <td>{$row['rental_duration_days']} days</td>
                        <td>{$status_badge}</td>
                        <td>" . ($row['odometer_pickup'] ? number_format($row['odometer_pickup']) . " km" : "N/A") . "</td>
                        <td>{$action_button}</td>
                    </tr>";
                }
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===================== EARLY RETURN REQUESTS ===================== -->
<div class="card">
    <div class="card-header bg-gray">
        <h3 class="card-title text-white">Return Requests (Pending Admin Approval)</h3>
    </div>

    <div class="card-body">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Renter</th>
                    <th>Car</th>
                    <th>Requested At</th>
                    <th>Total Deducted Cost</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php
            if (mysqli_num_rows($query_result) > 0) {
                while ($row = mysqli_fetch_assoc($query_result)) {
                    $car_display = htmlspecialchars("{$row['car_brand']} {$row['model']} ({$row['plate_no']})");
                    echo "
                    <tr>
                        <td>{$row['fullname']}</td>
                        <td>{$car_display}</td>
                        <td>{$row['requested_at']}</td>
                        <td>₱" . number_format($row['total_deducted_cost'], 2) . "</td>
                        <td>
                            <form method='POST' action='approve_early_return.php'>
                                <input type='hidden' name='action' value='approve_early_return'>
                                <input type='hidden' name='request_id' value='{$row['request_id']}'>
                                <input type='hidden' name='user_id' value='{$row['user_id']}'>
                                <button class='btn btn-success btn-sm'>
                                    <i class='fas fa-check'></i> Approve
                                </button>
                            </form>
                        </td>
                    </tr>";
                }
            } else {
                echo "<tr><td colspan='5' class='text-center'>No rental return requests pending initial approval</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===================== EXTENSION REQUESTS ===================== -->
<div class="card extension-requests">
    <div class="card-header bg-gray">
        <h3 class="card-title text-white">Rental Extension Requests (Pending Approval)</h3>
    </div>

    <div class="card-body">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Renter</th>
                    <th>Car</th>
                    <th>Current Duration</th>
                    <th>Days Added</th>
                    <th>New End Date</th>
                    <th>Additional Cost</th>
                    <th>Requested At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($extension_requests)): ?>
                <?php foreach ($extension_requests as $ext): ?>
                <tr id="ext_row_<?= $ext['extension_id'] ?>">
                    <td><?= $ext['extension_id'] ?></td>
                    <td><?= htmlspecialchars($ext['fullname']) ?></td>
                    <td><?= htmlspecialchars("{$ext['model']} ({$ext['plate_no']})") ?></td>
                    <td><?= $ext['current_duration'] ?> days</td>
                    <td><?= $ext['days_to_extend'] ?> days</td>
                    <td><?= date('M d, Y', strtotime($ext['new_end_date'])) ?></td>
                    <td>
                        ₱<?= number_format($ext['additional_cost'], 2) ?><br>
                        <a href="<?= $system_base_path . str_replace('../', '', htmlspecialchars($ext['payment_proof_path'])) ?>"
                           target="_blank" class="text-info">
                           <i class="fas fa-eye"></i> View Proof
                        </a>
                    </td>
                    <td><?= date('M d, Y h:i A', strtotime($ext['requested_at'])) ?></td>
                    <td>
                        <button class="btn btn-success btn-sm action-btn-ext" data-id="<?= $ext['extension_id'] ?>" data-action="approve">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn btn-danger btn-sm action-btn-ext mt-1" data-id="<?= $ext['extension_id'] ?>" data-action="reject">
                            <i class="fas fa-times"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="9" class="text-center">No rental extension requests pending approval.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
</section>
</div>

    <script>
        // Assuming jQuery and SweetAlert (Swal) are loaded
        $(document).ready(function() {
            $('.extension-requests').on('click', '.action-btn-ext', function() {
                const extId = $(this).data('id');
                const action = $(this).data('action');
                const message = action === 'approve' 
                    ? 'Are you sure you want to approve this extension? This will update the rental period and total cost.' 
                    : 'Are you sure you want to reject this extension?';

                // Use SweetAlert for confirmation
                Swal.fire({
                    title: 'Confirm Action',
                    text: message,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: action === 'approve' ? '#28a745' : '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: action === 'approve' ? 'Yes, Approve!' : 'Yes, Reject!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'admin_approve_extension.php', // Assuming this endpoint handles both approve/reject
                            type: 'POST',
                            data: {
                                extension_id: extId,
                                action: action
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire('Success!', response.message, 'success').then(() => {
                                        // Remove the row on successful action
                                        $('#ext_row_' + extId).remove();
                                    });
                                } else {
                                    Swal.fire('Error!', response.message, 'error');
                                }
                            },
                            error: function(xhr, status, error) {
                                Swal.fire('Error!', 'An error occurred during the request. Check admin_approve_extension.php.', 'error');
                                console.error("AJAX Error: ", status, error);
                            }
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>