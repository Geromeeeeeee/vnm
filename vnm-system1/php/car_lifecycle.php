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
    <link rel="stylesheet" href="../css/common.css ?v=1.2">
    <link rel="stylesheet" href="../css/rentals.css ?v=1.06"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
<body>
   <nav>
    <div class="logo"><img src="/vnm-system1/photos/VNM logo.png" alt="VNM logo"></div>
    <div class="navLink">
        <a href="/vnm-system1/php/adminindex.php">Dashboard</a>
        <a href="/vnm-system1/php/cars/cars.php">Cars</a>
        <a href="/vnm-system1/php/rentals.php">Rentals</a>
        <a href="/vnm-system1/php/car_lifecycle.php" class="active">Car Status</a> 
        <a href="/vnm-system1/php/manage_accounts.php" class="active">Accounts</a> 
        <a href="/vnm-system1/php/landing.php" id="logout">Logout</a>
    </div>
</nav>
    <main>
        <div class="container">
            <h2>Car Lifecycle Management (Pick Up & Return)</h2>

            <?php if (isset($_GET['error'])): ?>
                <p class="error"> Error: <?= htmlspecialchars($_GET['error']) ?></p>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
                <p class="success"> Success: <?= htmlspecialchars($_GET['success']) ?></p>
            <?php endif; ?>

            <table>
                <tr>
                    <th>Renter</th>
                    <th>Car Details</th>
                    <th>Scheduled Date/Time</th>
                    <th>Duration (Days)</th>
                    <th>Current Status</th>
                    <th>Pickup Mileage</th>
                    <th>Action</th>
                </tr>
                <?php
                if ($details && mysqli_num_rows($details) > 0) {
                    while ($row = mysqli_fetch_assoc($details)) {
                        $request_id = $row['request_id'];
                        $car_display = htmlspecialchars("{$row['car_brand']} {$row['model']} ({$row['plate_no']})");

                        $status_class = '';
                        $status_text = '';
                        $action_button = '';

                        if ($row['request_status'] === 'Approved') {
                            $status_class = 'status-ready';
                            
                            if ($row['payment_status'] === 'Proof Uploaded' || $row['payment_status'] === 'Paid') {
                                $status_text = 'Ready for Pick Up';
                                $action_button = "<a href='car_pickup.php?request_id={$request_id}' class='pickup-btn'>Process Pick Up</a>";
                            } else {
                                $status_text = 'Awaiting Payment Proof/Payment';
                                $action_button = "<span style='color: #777;'>Awaiting Payment</span>";
                            }
                        } elseif ($row['request_status'] === 'Picked Up') {
                            $status_class = 'status-pickedup';
                            $status_text = 'On the Road (Rented)';
                            $action_button = "<a href='car_return.php?request_id={$request_id}' class='return-btn'>Process Return</a>";
                        } 
                        // Early Return Requested (Awaiting Approval 1 - handled by separate table below)
                        elseif ($row['request_status'] === 'Early Return Requested') {
                            $status_class = 'status-early-return';
                            $status_text = 'EARLY RETURN REQUESTED (Awaiting Admin Approval)';
                            $action_button = "<span style='color: #dc3545;'>Awaiting Approval</span>";
                        }
                        // Early Return Approved (Awaiting Customer Scheduling)
                        elseif ($row['request_status'] === 'Early_Return_Approved') {
                            $status_class = 'status-approved-schedule';
                            $status_text = 'EARLY RETURN APPROVED (Awaiting Customer Schedule)';
                            $action_button = "<span style='color: blue;'>Awaiting Customer</span>";
                        }
                        // Early Return Scheduled (Awaiting Final Process/Approval 2)
                        elseif ($row['request_status'] === 'Early_Return_Scheduled') { 
                            $status_class = 'status-scheduled'; 
                            $status_text = 'EARLY RETURN SCHEDULED (Awaiting Final Process)';
                            // This button leads to the final return form, referencing return_action.php logic
                            $action_button = "<a href='car_return.php?request_id={$request_id}' class='return-btn early-return-action-btn'>Process Early Return</a>"; 
                        } 

                        echo "
                        <tr>
                            <td>{$row['fullname']}</td>
                            <td>{$car_display}</td>
                            <td>{$row['rental_date']} @ {$row['rental_time']}</td>
                            <td>{$row['rental_duration_days']}</td>
                            <td><span class='{$status_class}'>{$status_text}</span></td>
                            <td>" . ($row['odometer_pickup'] ? number_format($row['odometer_pickup']) . " km" : "N/A") . "</td>
                            <td class='action-cell'>
                                {$action_button}
                            </td>
                        </tr>
                        ";
                    }
                }
                ?>
            </table>
        </div>

        <div class="return-requests container">
            <h2>Return Requests (Pending Admin Approval)</h2> 
            <table>
                <tr>
                    <th>Renter</th>
                    <th>Car Details</th> 
                    <th>Requested at</th>
                    <th>Total Deducted Cost</th>
                    <th>Action</th> 
                </tr>
            <?php
                if(mysqli_num_rows($query_result)>0){
                    while($row = mysqli_fetch_assoc($query_result)){
                        $car_display = htmlspecialchars("{$row['car_brand']} {$row['model']} ({$row['plate_no']})"); 
                        echo"
                            <tr>
                                <td>{$row['fullname']}</td>
                                <td>{$car_display}</td> 
                                <td>{$row['requested_at']}</td>
                                <td>₱" . number_format($row['total_deducted_cost'], 2) . "</td> 
                                <td>
                                <form action='approve_early_return.php' method='POST'> 
                                    <input type='hidden' name='action' value='approve_early_return'> 
                                    <input type='hidden' name='request_id' value='{$row['request_id']}'> 
                                    <input type='hidden' name='user_id' value='{$row['user_id']}'> 
                                    <button class='approve-btn'>Approve Request</button> 
                                </form>
                                </td>
                            </tr>
                        ";
                    }
                } else{
                    echo "
                        <tr>
                            <td colspan='5' style='text-align: center;'>No rental return requests pending initial approval</td> 
                        </tr>
                    ";
                }
            ?>
            </table>
        </div>
        
        <div class="extension-requests container">
            <h2>Rental Extension Requests (Pending Approval)</h2> 
            <table>
                <thead>
                    <tr>
                        <th>Ext. ID</th>
                        <th>Renter</th>
                        <th>Car Details</th>
                        <th>Current Duration</th>
                        <th>Days Added</th>
                        <th>New End Date</th>
                        <th>Additional Cost (Proof)</th>
                        <th>Requested At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($extension_requests)): ?>
                        <?php foreach ($extension_requests as $ext): 
                            $car_display = htmlspecialchars("{$ext['model']} ({$ext['plate_no']})");
                        ?>
                        <tr id="ext_row_<?php echo $ext['extension_id']; ?>">
                            <td><?php echo $ext['extension_id']; ?></td>
                            <td><?php echo htmlspecialchars($ext['fullname']); ?></td>
                            <td><?php echo $car_display; ?></td>
                            <td><?php echo $ext['current_duration']; ?> days</td>
                            <td><?php echo $ext['days_to_extend']; ?> days</td>
                            <td><?php echo date('M d, Y', strtotime($ext['new_end_date'])); ?></td>
                            <td>
                                ₱ <?php echo number_format($ext['additional_cost'], 2); ?>
                                <br><a href="<?php echo $system_base_path . str_replace('../', '', htmlspecialchars($ext['payment_proof_path'])); ?>" target="_blank" class="text-info" style="font-size: 0.9em; color: #007bff;"><i class="fas fa-eye"></i> View Proof</a>
                            </td>
                            <td><?php echo date('M d, Y h:i A', strtotime($ext['requested_at'])); ?></td>
                            <td class="action-cell">
                                <button class="action-btn-ext approve-ext-btn" data-id="<?php echo $ext['extension_id']; ?>" data-action="approve"><i class="fas fa-check"></i> Approve</button>
                                <button class="action-btn-ext reject-ext-btn" data-id="<?php echo $ext['extension_id']; ?>" data-action="reject" style="margin-top: 5px;"><i class="fas fa-times"></i> Reject</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center;">No rental extension requests pending approval.</td> 
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        </main>

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