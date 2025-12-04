<?php
    error_reporting(E_ALL);
	ini_set('display_errors', 1);
    include 'db.php';

    $base_select = "
        rental_requests.request_id, 
        rental_requests.driver_license_photo, 
        rental_requests.rental_date, 
        rental_requests.rental_time,
        rental_requests.total_cost,
        rental_requests.rental_duration_days,
        users.fullname,
        cars.car_brand,
        cars.plate_no
    FROM rental_requests
    INNER JOIN users ON rental_requests.user_id = users.user_id 
    INNER JOIN cars ON rental_requests.car_id = cars.car_id
    ";

    $query = "SELECT " . $base_select . " WHERE rental_requests.request_status = 'Pending'"; 
    $details = mysqli_query($conn, $query); 

    $approved_query = "SELECT " . $base_select . " WHERE rental_requests.request_status = 'Approved'";
    $approved_details = mysqli_query($conn, $approved_query);

    
    $declined_query = "SELECT " . $base_select . " WHERE rental_requests.request_status = 'Rejected'";
    $declined_details = mysqli_query($conn, $declined_query);

    
    $cancelled_query = "SELECT " . $base_select . " WHERE rental_requests.request_status = 'Cancelled'";
    $cancelled_details = mysqli_query($conn, $cancelled_query);
    

    
    $system_base_path = '/vnm-system/';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/common.css ?v=1.2">
    <link rel="stylesheet" href="../css/rentals.css ?v=1.04"> 
    <title>Rentals</title>
    <style>
       
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.9); 
            padding-top: 60px;
        }

        .modal-content {
            margin: auto;
            display: block;
            width: 80%;
            max-width: 700px;
            max-height: 90vh;
            object-fit: contain;
        }

        .close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
        }

        .close:hover,
        .close:focus {
            color: #bbb;
            text-decoration: none;
            cursor: pointer;
        }
        
        #status-button form .delete-btn {
             background-color: #d9534f;
             color: white;
             border: none;
             box-shadow: 0px 0px 5px 0px rgba(0,0,0,0.25);
        }
        .view-license-btn {
             background-color: #007bff; 
             color: white;
             border: none;
             box-shadow: 0px 0px 5px 0px rgba(0,0,0,0.25);
             padding: 1vh;
             border-radius: 7.5px;
             cursor: pointer;
        }
        
        @media only screen and (max-width: 700px){
            .modal-content {
                width: 100%;
            }
        }
    </style>
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
        <div id="licenseModal" class="modal">
            <span class="close" onclick="closeModal()">&times;</span>
            <img class="modal-content" id="licenseImage" alt="Driver's License Photo">
        </div>

        <h3>Pending Rental Requests</h3>
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
                    <th>Action</th>
                </tr>
                <?php
                if ($details === false) {
                    echo "<tr><td colspan='8' style='color: red; text-align: center; padding: 10px;'>Database Query Failed: " . htmlspecialchars(mysqli_error($conn)) . "</td></tr>";
                } elseif(mysqli_num_rows($details) == 0){
                    echo "<tr><td colspan = 8>No pending requests</td></tr>";
                } else{
                    while ($row = mysqli_fetch_assoc($details)){
                        $request_id = htmlspecialchars($row['request_id']);
                        $license_photo_url = htmlspecialchars($system_base_path . $row['driver_license_photo']);
                        echo "
                        <tr>
                            <td>{$row['fullname']}</td>
                            <td>{$row['car_brand']} ({$row['plate_no']})</td>
                            <td class='license-cell'>
                                <button type='button' class='view-license-btn' data-license-url='{$license_photo_url}' onclick='openModal(this)'>View License</button>
                            </td>
                            <td>{$row['rental_date']}</td>
                            <td>{$row['rental_time']}</td>
                            <td>{$row['rental_duration_days']}</td>
                            <td>{$row['total_cost']}</td>
                            <td id='status-button'>
                                <form action='rental_action.php' method='POST'>
                                    <input type='hidden' name='request_id' value='{$request_id}'>
                                    <input type='hidden' name='action' value='approve'>
                                    <button type='submit' class='approve-btn'>Approve</button>
                                </form>
                                <form action='rental_action.php' method='POST'>
                                    <input type='hidden' name='request_id' value='{$request_id}'>
                                    <input type='hidden' name='action' value='decline'>
                                    <button type='submit' class='decline-btn'>Decline</button>
                                </form>
                            </td>
                        </tr>
                        ";
                    }
                }
                ?>
            </table>
        </div>

        <hr>

        <h3>Approved Rental History</h3>
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
                    <th>Action</th>
                </tr>
                <?php
                if ($approved_details === false) {
                    echo "<tr><td colspan='8' style='color: red; text-align: center; padding: 10px;'>Database Query Failed: " . htmlspecialchars(mysqli_error($conn)) . "</td></tr>";
                } elseif(isset($approved_details) && mysqli_num_rows($approved_details) == 0){
                    echo "<tr><td colspan = 8>No approved requests</td></tr>";
                } else{
                    while ($row = mysqli_fetch_assoc($approved_details)){
                        $request_id = htmlspecialchars($row['request_id']);

                        $license_photo_url = htmlspecialchars($system_base_path . $row['driver_license_photo']);
                        echo "
                        <tr>
                            <td>{$row['fullname']}</td>
                            <td>{$row['car_brand']} ({$row['plate_no']})</td>
                            <td class='license-cell'>
                                <button type='button' class='view-license-btn' data-license-url='{$license_photo_url}' onclick='openModal(this)'>View License</button>
                            </td>
                            <td>{$row['rental_date']}</td>
                            <td>{$row['rental_time']}</td>
                            <td>{$row['rental_duration_days']}</td>
                            <td>{$row['total_cost']}</td>
                            <td id='status-button'>
                                <form action='history_action.php' method='POST'>
                                    <input type='hidden' name='request_id' value='{$request_id}'>
                                    <input type='hidden' name='action' value='delete'>
                                    <button type='submit' class='delete-btn'>Delete</button>
                                </form>
                            </td>
                        </tr>
                        ";
                    }
                }
                ?>
            </table>
        </div>

        <hr>

        <h3>Declined Rental History</h3>
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
                    <th>Action</th>
                </tr>
                <?php
                if ($declined_details === false) {
                    echo "<tr><td colspan='8' style='color: red; text-align: center; padding: 10px;'>Database Query Failed: " . htmlspecialchars(mysqli_error($conn)) . "</td></tr>";
                } elseif(isset($declined_details) && mysqli_num_rows($declined_details) == 0){
                    echo "<tr><td colspan = 8>No declined requests</td></tr>";
                } else{
                    while ($row = mysqli_fetch_assoc($declined_details)){
                        $request_id = htmlspecialchars($row['request_id']);
                        $license_photo_url = htmlspecialchars($system_base_path . $row['driver_license_photo']);
                        echo "
                        <tr>
                            <td>{$row['fullname']}</td>
                            <td>{$row['car_brand']} ({$row['plate_no']})</td>
                            <td class='license-cell'>
                                <button type='button' class='view-license-btn' data-license-url='{$license_photo_url}' onclick='openModal(this)'>View License</button>
                            </td>
                            <td>{$row['rental_date']}</td>
                            <td>{$row['rental_time']}</td>
                            <td>{$row['rental_duration_days']}</td>
                            <td>{$row['total_cost']}</td>
                            <td id='status-button'>
                                <form action='history_action.php' method='POST'>
                                    <input type='hidden' name='request_id' value='{$request_id}'>
                                    <input type='hidden' name='action' value='delete'>
                                    <button type='submit' class='delete-btn'>Delete</button>
                                </form>
                            </td>
                        </tr>
                        ";
                    }
                }
                ?>
            </table>
        </div>
        
        <hr>

        <h3>Cancelled Rental History (Customer Cancelled)</h3>
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
                    <th>Action</th>
                </tr>
                <?php
                if ($cancelled_details === false) {
                    echo "<tr><td colspan='8' style='color: red; text-align: center; padding: 10px;'>Database Query Failed: " . htmlspecialchars(mysqli_error($conn)) . "</td></tr>";
                } elseif(isset($cancelled_details) && mysqli_num_rows($cancelled_details) == 0){
                    echo "<tr><td colspan = 8>No cancelled requests</td></tr>";
                } else{
                    while ($row = mysqli_fetch_assoc($cancelled_details)){
                        $request_id = htmlspecialchars($row['request_id']);
                        $license_photo_url = htmlspecialchars($system_base_path . $row['driver_license_photo']);
                        echo "
                        <tr>
                            <td>{$row['fullname']}</td>
                            <td>{$row['car_brand']} ({$row['plate_no']})</td>
                            <td class='license-cell'>
                                <button type='button' class='view-license-btn' data-license-url='{$license_photo_url}' onclick='openModal(this)'>View License</button>
                            </td>
                            <td>{$row['rental_date']}</td>
                            <td>{$row['rental_time']}</td>
                            <td>{$row['rental_duration_days']}</td>
                            <td>{$row['total_cost']}</td>
                            <td id='status-button'>
                                <form action='history_action.php' method='POST'>
                                    <input type='hidden' name='request_id' value='{$request_id}'>
                                    <input type='hidden' name='action' value='delete'>
                                    <button type='submit' class='delete-btn'>Delete</button>
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

    <script>
       
        const modal = document.getElementById("licenseModal");
        const modalImg = document.getElementById("licenseImage");
        
        
        function openModal(button) {
            const imageUrl = button.getAttribute('data-license-url');
            
            modalImg.alt = "Driver's License Photo";
            modalImg.style.backgroundColor = 'transparent';
            modalImg.style.textAlign = 'inherit';

            if (imageUrl) {
                modal.style.display = "block";
                modalImg.src = imageUrl;
                
                modalImg.onerror = function() {
                    modalImg.alt = "Image not found or inaccessible at: " + imageUrl;
                    console.error("Image loading failed for URL:", imageUrl);
                }
            } else {
                console.error("License URL not found in data attribute.");
                modal.style.display = "block";
                modalImg.src = ''; 
                modalImg.alt = "License data is missing from database record.";
            }
        }

        
        function closeModal() {
            modal.style.display = "none";
            modalImg.src = ''; 
            modalImg.alt = "Driver's License Photo"; 
            modalImg.onerror = null; 
            modalImg.style.backgroundColor = 'transparent'; 
            modalImg.style.textAlign = 'inherit'; 
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>