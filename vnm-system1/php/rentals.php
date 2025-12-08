<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db.php';

// --- PHP LOGIC TO HANDLE ADMIN NOTES UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_admin_notes') {
    $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $admin_notes = filter_input(INPUT_POST, 'admin_notes', FILTER_SANITIZE_STRING);

    if ($request_id) {
        $update_sql = "UPDATE rental_requests SET admin_notes = ? WHERE request_id = ?";
        $stmt_update = $conn->prepare($update_sql);

        if ($stmt_update) {
            $stmt_update->bind_param("si", $admin_notes, $request_id);
            if ($stmt_update->execute()) {
                header("Location: rentals.php?success=notes_updated");
                exit;
            } else {
                error_log("DB update error on admin notes: " . $stmt_update->error);
                header("Location: rentals.php?error=db_notes_update_failed");
                exit;
            }
        } else {
            error_log("Database Prepare Error for Notes Update: " . $conn->error);
        }
    }
}
// --------------------------------------------------

$base_select = "
rental_requests.request_id, 
rental_requests.driver_license_photo, 
rental_requests.rental_date, 
rental_requests.rental_time,
rental_requests.total_cost,
rental_requests.rental_duration_days,
rental_requests.payment_status,
rental_requests.request_status,
rental_requests.payment_proof_path,
rental_requests.payment_reference_no,
rental_requests.admin_notes, /* Admin Notes included */
users.fullname,
cars.car_brand,
cars.model,
cars.plate_no
FROM rental_requests
INNER JOIN users ON rental_requests.user_id = users.user_id 
INNER JOIN cars ON rental_requests.car_id = cars.car_id
";

// Queries for each section
$query = "SELECT " . $base_select . " WHERE rental_requests.request_status = 'Pending'"; 
$details = mysqli_query($conn, $query); 

$approved_in_progress_query = "SELECT " . $base_select . " WHERE rental_requests.request_status IN ('Approved', 'Picked Up')";
$approved_in_progress_details = mysqli_query($conn, $approved_in_progress_query);

$completed_query = "SELECT " . $base_select . " WHERE rental_requests.request_status = 'Returned'";
$completed_details = mysqli_query($conn, $completed_query);

$declined_query = "SELECT " . $base_select . " WHERE rental_requests.request_status = 'Rejected'";
$declined_details = mysqli_query($conn, $declined_query);

// NEW TABLE QUERY: Cancelled requests
$cancelled_query = "SELECT " . $base_select . " WHERE rental_requests.request_status = 'Cancelled'";
$cancelled_details = mysqli_query($conn, $cancelled_query);

$system_base_path = '/vnm-system1/';
?>

<!DOCTYPE ahtml>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/common.css ?v=1.2">
    <link rel="stylesheet" href="../css/rentals.css ?v=1.05"> 
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
        
        /* Styles for the Admin Notes Modal */
        #notesModal {
            padding-top: 10%; 
            background-color: rgba(0,0,0,0.8);
        }

        #notesModal .modal-content {
            background-color: #333;
            color: white;
            padding: 20px;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            display: flex;
            flex-direction: column;
        }

        #notesModal textarea {
            width: 100%;
            min-height: 150px;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            border: 1px solid #555;
            background-color: #444;
            color: white;
            box-sizing: border-box;
        }

        #notesModal button[type="submit"] {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        
        /* REVERTED COLORS: notes-btn is now blue like license button */
        .view-license-btn, .notes-btn { 
             background-color: #007bff; /* Utility Blue */
             color: white;
             border: none;
             box-shadow: 0px 0px 5px 0px rgba(0,0,0,0.25);
             padding: 1vh;
             border-radius: 7.5px;
             cursor: pointer;
             margin-top: 5px; 
             display: block;
             width: 100%;
             text-align: center;
        }
        .view-proof-btn { /* Payment proof button remains green */
            background-color: #28a745; 
            color: white;
            border: none;
            box-shadow: 0px 0px 5px 0px rgba(0,0,0,0.25);
            padding: 1vh;
            border-radius: 7.5px;
            cursor: pointer;
            margin-top: 5px; 
            display: block;
            width: 100%;
            text-align: center;
        }


        .lifecycle-redirect-btn {
            background-color: #007bff;
            color: white;
            border: none;
            box-shadow: 0px 0px 5px 0px rgba(0,0,0,0.25);
            padding: 1vh;
            border-radius: 7.5px;
            cursor: pointer;
            margin-bottom: 5px;
            display: block;
            width: 100%;
            text-align: center;
            text-decoration: none;
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
    <div class="logo"><img src="/vnm-system1/photos/VNM logo.png" alt="VNM logo"></div>
    <div class="navLink">
        <a href="/vnm-system1/php/adminindex.php">Dashboard</a>
        <a href="/vnm-system1/php/cars/cars.php">Cars</a>
        <a href="/vnm-system1/php/rentals.php">Rentals</a>
        <a href="/vnm-system1/php/car_lifecycle.php" class="active">Car Status</a> 
        <a href="/vnm-system1/php/landing.php" id="logout">Logout</a>
    </div>
</nav>
    </nav>
    <main>
        <div id="licenseModal" class="modal">
            <span class="close" onclick="closeModal('licenseModal')">&times;</span>
            <img class="modal-content" id="licenseImage" alt="Document Photo">
        </div>

        <div id="notesModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('notesModal')">&times;</span>
                <h4>Admin Notes for Request #<span id="notesRequestIdDisplay"></span></h4>
                <form id="notesForm" action="rentals.php" method="POST">
                    <input type="hidden" name="action" value="update_admin_notes">
                    <input type="hidden" name="request_id" id="notesRequestIdInput">
                    <textarea name="admin_notes" id="adminNotesTextarea" placeholder="Enter notes here..."></textarea>
                    <button type="submit">Save Notes</button>
                </form>
            </div>
        </div>
        
        <?php if (isset($_GET['success']) && $_GET['success'] === 'notes_updated'): ?>
            <p style="color: darkgreen; font-weight: bold; text-align: center;">✅ Admin notes successfully updated.</p>
        <?php endif; ?>

        <h3>Pending Rental Requests</h3>
        <div class="for-approval">
            <table>
                <tr>
                    <th>Renter</th>
                    <th>Car</th>
                    <th>License</th>
                    <th>Payment Proof</th> 
                    <th>Reference No</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Duration (Days)</th>
                    <th>Cost</th>
                    <th>Payment Status</th> 
                    <th>Notes</th> 
                    <th>Action</th>
                </tr>
                <?php
                if ($details === false) {
                    echo "<tr><td colspan='12' style='color: red; text-align: center; padding: 10px;'>Database Query Failed: " . htmlspecialchars(mysqli_error($conn)) . "</td></tr>";
                } elseif(mysqli_num_rows($details) == 0){
                    echo "<tr><td colspan = 12>No pending requests</td></tr>"; 
                } else{
                    while ($row = mysqli_fetch_assoc($details)){
                        $request_id = htmlspecialchars($row['request_id']);
                        $license_photo_url = htmlspecialchars($system_base_path . $row['driver_license_photo']); 
                        $proof_path = htmlspecialchars($row['payment_proof_path']);
                        $proof_url = $proof_path ? htmlspecialchars($system_base_path . $proof_path) : '';
                        $payment_status = htmlspecialchars($row['payment_status']);
                        $reference_no = htmlspecialchars($row['payment_reference_no']) ?: 'N/A';
                        $admin_notes = htmlspecialchars($row['admin_notes']);
                        
                        $status_color = ($payment_status === 'Paid') ? 'darkgreen' : (($payment_status === 'Proof Uploaded') ? 'blue' : 'red');
                        $status_display = "<span style='font-weight: bold; color: {$status_color};'>{$payment_status}</span>";

                        echo "
                        <tr>
                            <td>{$row['fullname']}</td>
                            <td>{$row['car_brand']} ({$row['plate_no']})</td>
                            <td class='license-cell'>
                                <button type='button' class='view-license-btn' data-doc-url='{$license_photo_url}' data-doc-type='License' onclick=\"openModal('licenseModal', this)\">View License</button>
                            </td>
                            <td class='license-cell'>";
                                if ($proof_url) {
                                    echo "<button type='button' class='view-proof-btn' data-doc-url='{$proof_url}' data-doc-type='Payment Proof' onclick=\"openModal('licenseModal', this)\">View Proof</button>";
                                } else {
                                    echo "N/A";
                                }
                            echo "</td>
                            <td>{$reference_no}</td>
                            <td>{$row['rental_date']}</td>
                            <td>{$row['rental_time']}</td>
                            <td>{$row['rental_duration_days']}</td>
                            <td>₱" . number_format($row['total_cost'], 2) . "</td>
                            <td>{$status_display}</td>
                            <td>
                                <button type='button' class='notes-btn' data-request-id='{$request_id}' data-admin-notes='{$admin_notes}' onclick='openNotesModal(this)'>Notes</button>
                            </td>
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

        <h3>Approved & In-Progress Rental History</h3>
        <div class="for-approval">
            <table>
                <tr>
                    <th>Renter</th>
                    <th>Car</th>
                    <th>License</th>
                    <th>Payment Proof</th> 
                    <th>Reference No</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Duration (Days)</th>
                    <th>Cost</th>
                    <th>Rental Status</th> 
                    <th>Notes</th> 
                    <th>Action</th>
                </tr>
                <?php
                if ($approved_in_progress_details === false) {
                    echo "<tr><td colspan='12' style='color: red; text-align: center; padding: 10px;'>Database Query Failed: " . htmlspecialchars(mysqli_error($conn)) . "</td></tr>";
                } elseif(isset($approved_in_progress_details) && mysqli_num_rows($approved_in_progress_details) == 0){
                    echo "<tr><td colspan = 12>No approved or in-progress requests</td></tr>";
                } else{
                    while ($row = mysqli_fetch_assoc($approved_in_progress_details)){
                        $request_id = htmlspecialchars($row['request_id']);

                        $license_photo_url = htmlspecialchars($system_base_path . $row['driver_license_photo']);
                        $proof_path = htmlspecialchars($row['payment_proof_path']);
                        $proof_url = $proof_path ? htmlspecialchars($system_base_path . $proof_path) : '';
                        $payment_status = htmlspecialchars($row['payment_status']);
                        $request_status = htmlspecialchars($row['request_status']); 
                        $reference_no = htmlspecialchars($row['payment_reference_no']) ?: 'N/A';
                        $admin_notes = htmlspecialchars($row['admin_notes']);

                        $status_color = '';
                        switch ($request_status) {
                            case 'Approved': $status_color = 'blue'; break;
                            case 'Picked Up': $status_color = 'green'; break;
                            default: $status_color = 'black'; break;
                        }
                        $status_display = "<span style='font-weight: bold; color: {$status_color};'>{$request_status}</span>";

                        echo "
                        <tr>
                            <td>{$row['fullname']}</td>
                            <td>{$row['car_brand']} ({$row['plate_no']})</td>
                            <td class='license-cell'>
                                <button type='button' class='view-license-btn' data-doc-url='{$license_photo_url}' data-doc-type='License' onclick=\"openModal('licenseModal', this)\">View License</button>
                            </td>
                            <td class='license-cell'>";
                                if ($proof_url) {
                                    echo "<button type='button' class='view-proof-btn' data-doc-url='{$proof_url}' data-doc-type='Payment Proof' onclick=\"openModal('licenseModal', this)\">View Proof</button>";
                                } else {
                                    echo "N/A";
                                }
                            echo "</td>
                            <td>{$reference_no}</td>
                            <td>{$row['rental_date']}</td>
                            <td>{$row['rental_time']}</td>
                            <td>{$row['rental_duration_days']}</td>
                            <td>₱" . number_format($row['total_cost'], 2) . "</td>
                            <td>{$status_display}</td>
                            <td>
                                <button type='button' class='notes-btn' data-request-id='{$request_id}' data-admin-notes='{$admin_notes}' onclick='openNotesModal(this)'>Notes</button>
                            </td>
                            <td id='status-button'>";

                                if ($request_status === 'Approved') {
                                    echo "<a href='car_lifecycle.php' class='lifecycle-redirect-btn'>Process Pickup/Return</a>";
                                } elseif ($request_status === 'Picked Up') {
                                    echo "<a href='car_lifecycle.php' class='lifecycle-redirect-btn' style='background-color: #008CBA;'>Car is Rented (Manage)</a>";
                                }
                                
                                echo "<form action='history_action.php' method='POST' style='margin-top: 5px;'>
                                        <input type='hidden' name='request_id' value='{$request_id}'>
                                        <input type='hidden' name='action' value='delete'>
                                        <button type='submit' class='delete-btn'>Delete</button>
                                    </form>";
                            
                            echo "</td>
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
                    <th>Reference No</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Duration (Days)</th>
                    <th>Cost</th>
                    <th>Notes</th> 
                    <th>Action</th>
                </tr>
                <?php
                if ($declined_details === false) {
                    echo "<tr><td colspan='10' style='color: red; text-align: center; padding: 10px;'>Database Query Failed: " . htmlspecialchars(mysqli_error($conn)) . "</td></tr>";
                } elseif(isset($declined_details) && mysqli_num_rows($declined_details) == 0){
                    echo "<tr><td colspan = 10>No declined requests</td></tr>"; 
                } else{
                    while ($row = mysqli_fetch_assoc($declined_details)){
                        $request_id = htmlspecialchars($row['request_id']);
                        $license_photo_url = htmlspecialchars($system_base_path . $row['driver_license_photo']);
                        $reference_no = htmlspecialchars($row['payment_reference_no']) ?: 'N/A';
                        $admin_notes = htmlspecialchars($row['admin_notes']);

                        echo "
                        <tr>
                            <td>{$row['fullname']}</td>
                            <td>{$row['car_brand']} ({$row['plate_no']})</td>
                            <td class='license-cell'>
                                <button type='button' class='view-license-btn' data-doc-url='{$license_photo_url}' data-doc-type='License' onclick=\"openModal('licenseModal', this)\">View License</button>
                            </td>
                            <td>{$reference_no}</td>
                            <td>{$row['rental_date']}</td>
                            <td>{$row['rental_time']}</td>
                            <td>{$row['rental_duration_days']}</td>
                            <td>₱" . number_format($row['total_cost'], 2) . "</td>
                            <td>
                                <button type='button' class='notes-btn' data-request-id='{$request_id}' data-admin-notes='{$admin_notes}' onclick='openNotesModal(this)'>Notes</button>
                            </td>
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

        <h3>Cancelled Rental History</h3>
        <div class="for-approval">
            <table>
                <tr>
                    <th>Renter</th>
                    <th>Car</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Duration (Days)</th>
                    <th>Cost</th>
                    <th>Rental Status</th> 
                    <th>Notes</th> 
                    <th>Action</th>
                </tr>
                <?php
                if ($cancelled_details === false) {
                    echo "<tr><td colspan='9' style='color: red; text-align: center; padding: 10px;'>Database Query Failed: " . htmlspecialchars(mysqli_error($conn)) . "</td></tr>";
                } elseif(isset($cancelled_details) && mysqli_num_rows($cancelled_details) == 0){
                    echo "<tr><td colspan = 9>No cancelled rentals</td></tr>"; 
                } else{
                    while ($row = mysqli_fetch_assoc($cancelled_details)){
                        $request_id = htmlspecialchars($row['request_id']);
                        $reference_no = htmlspecialchars($row['payment_reference_no']) ?: 'N/A';
                        $admin_notes = htmlspecialchars($row['admin_notes']);
                        $request_status = htmlspecialchars($row['request_status']); 

                        $status_display = "<span style='font-weight: bold; color: gray;'>{$request_status}</span>";

                        // Note: License/Proof columns are often less relevant for cancelled requests, but you may add them back if needed.
                        echo "
                        <tr>
                            <td>{$row['fullname']}</td>
                            <td>{$row['car_brand']} ({$row['plate_no']})</td>
                            <td>{$row['rental_date']}</td>
                            <td>{$row['rental_time']}</td>
                            <td>{$row['rental_duration_days']}</td>
                            <td>₱" . number_format($row['total_cost'], 2) . "</td>
                            <td>{$status_display}</td>
                            <td>
                                <button type='button' class='notes-btn' data-request-id='{$request_id}' data-admin-notes='{$admin_notes}' onclick='openNotesModal(this)'>Notes</button>
                            </td>
                            <td id='status-button'>
                                <p style='color: gray; font-weight: bold;'>Cancelled</p>
                                <form action='history_action.php' method='POST' style='margin-top: 5px;'>
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

        <h3>Rental Completed History</h3>
        <div class="for-approval">
            <table>
                <tr>
                    <th>Renter</th>
                    <th>Car</th>
                    <th>License</th>
                    <th>Payment Proof</th> 
                    <th>Reference No</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Duration (Days)</th>
                    <th>Cost</th>
                    <th>Rental Status</th> 
                    <th>Notes</th> 
                    <th>Action</th>
                </tr>
                <?php
                if ($completed_details === false) {
                    echo "<tr><td colspan='12' style='color: red; text-align: center; padding: 10px;'>Database Query Failed: " . htmlspecialchars(mysqli_error($conn)) . "</td></tr>";
                } elseif(isset($completed_details) && mysqli_num_rows($completed_details) == 0){
                    echo "<tr><td colspan = 12>No completed rentals</td></tr>"; 
                } else{
                    while ($row = mysqli_fetch_assoc($completed_details)){
                        $request_id = htmlspecialchars($row['request_id']);

                        $license_photo_url = htmlspecialchars($system_base_path . $row['driver_license_photo']);
                        $proof_path = htmlspecialchars($row['payment_proof_path']);
                        $proof_url = $proof_path ? htmlspecialchars($system_base_path . $proof_path) : '';
                        $request_status = htmlspecialchars($row['request_status']); 
                        $reference_no = htmlspecialchars($row['payment_reference_no']) ?: 'N/A';
                        $admin_notes = htmlspecialchars($row['admin_notes']);

                        $status_color = 'gray';
                        $status_display = "<span style='font-weight: bold; color: {$status_color};'>{$request_status}</span>";

                        echo "
                        <tr>
                            <td>{$row['fullname']}</td>
                            <td>{$row['car_brand']} ({$row['plate_no']})</td>
                            <td class='license-cell'>
                                <button type='button' class='view-license-btn' data-doc-url='{$license_photo_url}' data-doc-type='License' onclick=\"openModal('licenseModal', this)\">View License</button>
                            </td>
                            <td class='license-cell'>";
                                if ($proof_url) {
                                    echo "<button type='button' class='view-proof-btn' data-doc-url='{$proof_url}' data-doc-type='Payment Proof' onclick=\"openModal('licenseModal', this)\">View Proof</button>";
                                } else {
                                    echo "N/A";
                                }
                            echo "</td>
                            <td>{$reference_no}</td>
                            <td>{$row['rental_date']}</td>
                            <td>{$row['rental_time']}</td>
                            <td>{$row['rental_duration_days']}</td>
                            <td>₱" . number_format($row['total_cost'], 2) . "</td>
                            <td>{$status_display}</td>
                            <td>
                                <button type='button' class='notes-btn' data-request-id='{$request_id}' data-admin-notes='{$admin_notes}' onclick='openNotesModal(this)'>Notes</button>
                            </td>
                            <td id='status-button'>
                                <p style='color: gray; font-weight: bold;'>Rental Completed</p>
                                <form action='history_action.php' method='POST' style='margin-top: 5px;'>
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
        
        const licenseModal = document.getElementById("licenseModal");
        const modalImg = document.getElementById("licenseImage");
        const notesModal = document.getElementById("notesModal");
        const notesRequestIdDisplay = document.getElementById("notesRequestIdDisplay");
        const notesRequestIdInput = document.getElementById("notesRequestIdInput");
        const adminNotesTextarea = document.getElementById("adminNotesTextarea");
        
        
        function openModal(modalId, button) {
            if (modalId === 'licenseModal') {
                const docUrl = button.getAttribute('data-doc-url');
                const docType = button.getAttribute('data-doc-type') || "Document";
                
                modalImg.alt = docType + " Photo";
                modalImg.style.backgroundColor = 'transparent';
                modalImg.style.textAlign = 'inherit';

                if (docUrl) {
                    licenseModal.style.display = "block";
                    modalImg.src = docUrl;
                    
                    modalImg.onerror = function() {
                        modalImg.alt = docType + " image not found or inaccessible at: " + docUrl;
                        console.error("Image loading failed for URL:", docUrl);
                        modalImg.src = '';
                        modalImg.style.backgroundColor = '#222';
                        modalImg.style.textAlign = 'center';
                    }
                } else {
                    console.error(docType + " URL not found in data attribute.");
                    licenseModal.style.display = "block";
                    modalImg.src = ''; 
                    modalImg.alt = docType + " data is missing from database record.";
                }
            }
        }
        
        function openNotesModal(button) {
            const requestId = button.getAttribute('data-request-id');
            // Decode HTML entities (e.g., &quot;) and replace newlines with actual newlines
            const notes = button.getAttribute('data-admin-notes').replace(/&quot;/g, '"').replace(/&#039;/g, "'");

            notesRequestIdDisplay.textContent = requestId;
            notesRequestIdInput.value = requestId;
            adminNotesTextarea.value = notes;
            
            notesModal.style.display = "block";
        }

        
        function closeModal(modalId) {
            if (modalId === 'licenseModal') {
                licenseModal.style.display = "none";
                modalImg.src = ''; 
                modalImg.alt = "Document Photo"; 
                modalImg.onerror = null; 
                modalImg.style.backgroundColor = 'transparent'; 
                modalImg.style.textAlign = 'inherit'; 
            } else if (modalId === 'notesModal') {
                notesModal.style.display = "none";
                document.getElementById('notesForm').reset(); 
            }
        }

        window.onclick = function(event) {
            if (event.target == licenseModal) {
                closeModal('licenseModal');
            } else if (event.target == notesModal) {
                closeModal('notesModal');
            }
        }
    </script>
</body>
</html>