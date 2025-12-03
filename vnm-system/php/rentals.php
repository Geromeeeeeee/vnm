<?php
    error_reporting(E_ALL);
	ini_set('display_errors', 1);
    include 'db.php';

    $query = "SELECT 
    rental_requests.user_id, 
    rental_requests.car_id, 
    rental_requests.driver_license_photo_path, 
    rental_requests.rental_date, 
    rental_requests.rental_time,
    rental_requests.total_cost,
    rental_requests.rental_duration_days,
    users.fullname,
    cars.car_brand,
    cars.plate_no
    FROM rental_requests
    INNER JOIN users ON rental_requests.user_id = users.user_id INNER JOIN cars ON rental_requests.car_id = cars.car_id";

    $details = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/common.css ?v=1.2">
    <link rel="stylesheet" href="../css/rentals.css ?v=1.03">
    <title>Rentals</title>
</head>
<body>
    <nav>
    <div class="logo"><img src="/vnm-system/photos/VNM logo.png" alt="VNM logo"></div>
    <div class="navLink">
        <a href="/vnm-system/php/adminindex.php">Dashboard</a>
        <a href="/vnm-system/php/cars/cars.php">Cars</a>
        <a href="/vnm-system/php/rentals.php">Rentals</a>
        <a href="/vnm-system/php/landing.php" id="logout">Logout</a>
    </div>
</nav>
    <main>
        <h3>Rental Requests</h3>
        <div class="for-approval">
            <table>
                <tr>
                    <th>Renter</th>
                    <th>Car</th>
                    <th>License</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Duration</th>
                    <th>Cost</th>
                    <th>Approve</th>
                </tr>
                <?php
                if(mysqli_num_rows($details) == 0){
                    echo "
                        <tr>
                            <td colspan = 8>No pending requests</td>
                        </tr>
                    ";
                } else{
                    while ($row = mysqli_fetch_assoc($details)){
                        echo "
                        <tr>
                            <td>{$row['fullname']}</td>
                            <td>{$row['car_brand']}</td>
                            <td>{$row['driver_license_photo_path']}</td>
                            <td>{$row['rental_date']}</td>
                            <td>{$row['rental_time']}</td>
                            <td>{$row['rental_duration_days']}</td>
                            <td>{$row['total_cost']}</td>
                            <td id='status-button'>
                                <form action=''>
                                    <button type='submit'>Approve</button>
                                </form>
                                <form action=''>
                                    <button type='submit'>Decline</button>
                                </form>
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