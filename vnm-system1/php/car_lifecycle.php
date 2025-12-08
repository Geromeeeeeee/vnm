<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start(); 
include 'db.php'; 

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
    -- MODIFIED: Include 'Early Return Requested' status for admin visibility
    WHERE rr.request_status IN ('Approved', 'Picked Up', 'Early Return Requested')
    ORDER BY rr.request_status DESC, rr.rental_date ASC
";

$details = mysqli_query($conn, $query); 
$system_base_path = '/vnm-system1/'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Lifecycle</title>
    <link rel="stylesheet" href="../css/common.css ?v=1.2">
    <link rel="stylesheet" href="../css/rentals.css ?v=1.05"> 
    <style>
        main { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .container { background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-top: 20px; }
        h2 { color: #333; border-bottom: 2px solid #ccc; padding-bottom: 10px; margin-top: 0; }
        .error { color: red; font-weight: bold; margin-top: 10px;}
        .success { color: green; font-weight: bold; margin-top: 10px;}
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 12px; text-align: left; }
        th { background-color: #f2f2f2; }
        .status-ready { color: orange; font-weight: bold; }
        .status-pickedup { color: green; font-weight: bold; }
        /* NEW STYLE for Early Return Request */
        .status-early-return { 
            color: #dc3545; 
            font-weight: bold; 
            background-color: #fff3cd; /* Light yellow background to highlight */
            padding: 2px 5px;
            border-radius: 3px;
        }
        .pickup-btn, .return-btn { 
            display: inline-block; 
            padding: 8px 12px; 
            color: white; 
            text-decoration: none; 
            border-radius: 4px; 
            text-align: center;
        }
        .pickup-btn { background-color: #007bff; }
        .return-btn { background-color: #dc3545; }
        /* Style for return button when early return is requested */
        .early-return-action-btn { 
            background-color: #ffc107; 
            color: black; 
            font-weight: bold;
        }
        .pickup-btn:hover { background-color: #0056b3; }
        .return-btn:hover { background-color: #c82333; }
        .early-return-action-btn:hover { background-color: #e0a800; }
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
                            
                            // Check payment status to determine if truly ready for pickup
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
                        // NEW LOGIC: Display Early Return Request status
                        elseif ($row['request_status'] === 'Early Return Requested') {
                            $status_class = 'status-early-return';
                            $status_text = 'EARLY RETURN REQUESTED';
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
    </main>
</body>
</html>