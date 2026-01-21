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
    <link rel="stylesheet" href="../css/common.css ?v=1.2">
    <link rel="stylesheet" href="../css/rentals.css ?v=1.05"> 
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
            <h2>Car Pick Up Confirmation</h2>
            
            <?php if (isset($_GET['error'])): ?>
                <p class="error"> Error: <?= htmlspecialchars($_GET['error']) ?></p>
            <?php endif; ?>
            
            <?php if (isset($_GET['success']) && $_GET['success'] === '1'): ?>
                <p class="success"> Rental successfully started! Car status is now 'Picked Up' and the car has been set to **Unavailable**.</p>
                <a href="car_lifecycle.php" style="display: block; text-align: center; margin-top: 20px; padding: 10px; background-color: #007bff; color: white; border-radius: 4px; text-decoration: none;">Go back to Car Lifecycle</a>
            <?php endif; ?>
            
            <?php if (!isset($_GET['success']) || $_GET['success'] !== '1'): // Only show form if not successful ?>
                <div class="info">
                    <p><strong>Renter:</strong> <?= $renter_name ?></p>
                    <p><strong>Car Details:</strong> <?= $car_details ?></p>
                    <p><strong>Scheduled Pick Up:</strong> <?= $pickup_datetime ?></p>
                    <p style="color: blue; font-weight: bold;">Current Rental Status: <?= htmlspecialchars($rental_data['request_status']) ?> (<?= htmlspecialchars($rental_data['payment_status']) ?>)</p>
                </div>
                
                <hr>

                <form action="pickup_action.php" method="POST">
                    <input type="hidden" name="request_id" value="<?= $request_id ?>">
                    <input type="hidden" name="car_id" value="<?= $car_id ?>">
                    
                    <label for="odometer">Odometer Reading (Current Mileage):</label>
                    <input type="number" id="odometer" name="odometer" required min="0" placeholder="e.g., 15000">

                    <label for="condition">Car Condition at Pick Up (Notes):</label>
                    <textarea id="condition" name="condition" rows="4" required placeholder="e.g., Minor scratch on the rear bumper. Fuel: Full."></textarea>
                    
                    <button type="submit" name="action" value="confirm_pickup">Confirm Pick Up and Start Rental</button>
                </form>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>