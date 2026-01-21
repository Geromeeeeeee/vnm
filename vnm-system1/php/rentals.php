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

$approved_in_progress_query = "SELECT " . $base_select . " WHERE rental_requests.request_status IN ('Approved', 'Picked Up', 'Early_Return_Approved', 'Early_Return_Scheduled')";
$approved_in_progress_details = mysqli_query($conn, $approved_in_progress_query);

// NEW QUERY: Early Return Requests (Awaiting Admin approval for the early return itself)
$early_return_query = "
    SELECT 
        rr.request_id, rr.driver_license_photo, rr.rental_date, rr.rental_time,
        rr.total_cost, rr.rental_duration_days, rr.payment_status, rr.request_status,
        rr.payment_proof_path, rr.payment_reference_no, rr.admin_notes,
        users.fullname, cars.car_brand, cars.model, cars.plate_no,
        rrr.total_deducted_cost /* Added to fetch the calculated final charged amount for review */
    FROM rental_requests rr
    INNER JOIN users ON rr.user_id = users.user_id 
    INNER JOIN cars ON rr.car_id = cars.car_id
    LEFT JOIN rental_return_requests rrr ON rr.request_id = rrr.request_id 
    WHERE rr.request_status = 'Early Return Requested'
";
$early_return_details = mysqli_query($conn, $early_return_query);


// MODIFIED QUERY: Completed Rentals, including total_deducted_cost for refund calculation
$completed_query = "
    SELECT 
        rr.request_id, rr.driver_license_photo, rr.rental_date, rr.rental_time,
        rr.total_cost, rr.rental_duration_days, rr.payment_status, rr.request_status,
        rr.payment_proof_path, rr.payment_reference_no, rr.admin_notes,
        users.fullname, cars.car_brand, cars.model, cars.plate_no,
        rrr.total_deducted_cost /* Added to fetch the final charged amount */
    FROM rental_requests rr
    INNER JOIN users ON rr.user_id = users.user_id 
    INNER JOIN cars ON rr.car_id = cars.car_id
    LEFT JOIN rental_return_requests rrr ON rr.request_id = rrr.request_id /* Added join for refund check */
    WHERE rr.request_status = 'Returned'
";
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
    <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

    <title>Rentals</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
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
          <a href="/vnm-system1/php/car_lifecycle.php" class="nav-link">
            <p>Car Status</p>
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

            <?php if (isset($_GET['success']) && $_GET['success'] === 'payment_approved'): ?>
                <p style="color: darkgreen; font-weight: bold; text-align: center;">✅ Payment successfully approved.</p>
            <?php endif; ?>

            <section class="content-header">
                <div class="container-fluid">
                    <h1>Rental Requests</h1>
                </div>
            </section>

            <section class="content">
                <div class="container-fluid">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Pending Rental Requests</h3>
                        </div>
                        <div class="card-body table-responsive p-0" style="max-height: 500px;">
                            <table class="table table-hover table-bordered text-center">
                                <tr>
                                    <th>Renter</th>
                                    <th>Car</th>
                                    <th>License</th>
                                    <th>Payment Proof</th>
                                    <th>Reference No</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Duration (Days)</th>
                                    <th>Original Cost</th>
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
                                    <button type='submit' class='btn btn-success btn-sm w-100' >Approve</button>
                                </form>
                                <form action='rental_action.php' method='POST'>
                                    <input type='hidden' name='request_id' value='{$request_id}'>
                                    <input type='hidden' name='action' value='decline'>
                                    <button type='submit' class='btn btn-danger btn-sm w-100 mt-1'>Decline</button>
                                </form>
                            </td>
                        </tr>
                        ";
                    }
                }
                ?>
                            </table>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header">
                            <h3 class="card-title">Early Return Requests (Needs Approval)</h3>
                        </div>
                        <div class="card-body table-responsive p-0" style="max-height: 500px;">
                            <table class="table table-hover table-bordered text-center">
                                <tr>
                                    <th>Renter</th>
                                    <th>Car</th>
                                    <th>Original Cost</th>
                                    <th>Est. Final Charge</th>
                                    <th>Est. Refund</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Rental Status</th>
                                    <th>Notes</th>
                                    <th>Action</th>
                                </tr>
                                <?php
                if ($early_return_details === false) {
                    echo "<tr><td colspan='10' style='color: red; text-align: center; padding: 10px;'>Database Query Failed: " . htmlspecialchars(mysqli_error($conn)) . "</td></tr>";
                } elseif(isset($early_return_details) && mysqli_num_rows($early_return_details) == 0){
                    echo "<tr><td colspan = 10>No early return requests awaiting approval</td></tr>";
                } else{
                    while ($row = mysqli_fetch_assoc($early_return_details)){
                        $request_id = htmlspecialchars($row['request_id']);
                        $original_cost = (float)($row['total_cost'] ?? 0.00);
                        $final_charge = (float)($row['total_deducted_cost'] ?? $original_cost);
                        $refund_amount = max(0, $original_cost - $final_charge);
                        
                        $request_status = htmlspecialchars($row['request_status']); 
                        $admin_notes = htmlspecialchars($row['admin_notes']);

                        $status_display = "<span style='font-weight: bold; color: purple;'>{$request_status}</span>";

                        echo "
                        <tr>
                            <td>{$row['fullname']}</td>
                            <td>{$row['car_brand']} ({$row['plate_no']})</td>
                            <td>₱" . number_format($original_cost, 2) . "</td>
                            <td style='color: #ffc107; font-weight: bold;'>₱" . number_format($final_charge, 2) . "</td>
                            <td style='color: yellow; font-weight: bold;'>₱" . number_format($refund_amount, 2) . "</td>
                            <td>{$row['rental_date']}</td>
                            <td>{$row['rental_time']}</td>
                            <td>{$status_display}</td>
                            <td>
                                <button type='button' class='notes-btn' data-request-id='{$request_id}' data-admin-notes='{$admin_notes}' onclick='openNotesModal(this)'>Notes</button>
                            </td>
                            <td id='status-button'>
                                <a href='approve_early_return.php?request_id={$request_id}&action=approve_early' class='lifecycle-redirect-btn' style='background-color: #28a745;'>Approve Return</a>
                                <a href='early_return_action.php?request_id={$request_id}&action=reject_early' class='btn btn-danger btn-sm w-100 mt-1' style='margin-top: 5px;'>Reject Return</a>
                            </td>
                        </tr>
                        ";
                    }
                }
                ?>
                            </table>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header">
                            <h3 class="card-title">Approved & In-Progress Rental History</h3>
                        </div>
                        <div class="card-body table-responsive p-0" style="max-height: 500px;">
                            <table class="table table-hover table-bordered text-center">
                                <tr>
                                    <th>Renter</th>
                                    <th>Car</th>
                                    <th>License</th>
                                    <th>Payment Proof</th>
                                    <th>Reference No</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Duration (Days)</th>
                                    <th>Original Cost</th>
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
                            case 'Early_Return_Approved': $status_color = '#ffc107'; break;
                            case 'Early_Return_Scheduled': $status_color = 'blueviolet'; break;
                            default: $status_color = 'black'; break;
                        }
                        $status_display = "<span style='font-weight: bold; color: {$status_color};'>". str_replace('_', ' ', $request_status) ."</span>";

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

                                // Check if today is the rental date
                                $today = date('Y-m-d');
                                $rental_date = htmlspecialchars($row['rental_date']);
                                $is_rental_date = ($today === $rental_date);

                                if ($request_status === 'Approved') {
                                    $approve_disabled = $is_rental_date ? '' : 'opacity: 0.5; pointer-events: none; cursor: not-allowed;';
                                    echo "<a href='car_lifecycle.php' class='lifecycle-redirect-btn' style='{$approve_disabled}'>Process Pickup/Return</a>";
                                } elseif ($request_status === 'Picked Up') {
                                    $manage_disabled = $is_rental_date ? '' : 'opacity: 0.5; pointer-events: none; cursor: not-allowed;';
                                    echo "<a href='car_lifecycle.php' class='lifecycle-redirect-btn' style='background-color: #008CBA; {$manage_disabled}'>Car is Rented (Manage)</a>";
                                } elseif ($request_status === 'Early_Return_Approved' || $request_status === 'Early_Return_Scheduled') {
                                    $early_disabled = $is_rental_date ? '' : 'opacity: 0.5; pointer-events: none; cursor: not-allowed;';
                                    echo "<a href='car_lifecycle.php' class='lifecycle-redirect-btn' style='background-color: purple; {$early_disabled}'>Early Return Flow</a>";
                                }
                                
                                // Add Approve Payment button if payment status is not 'Paid'
                                if ($payment_status !== 'Paid') {
                                    // Check if payment proof has been uploaded
                                    $has_proof = !empty($proof_path);
                                    
                                    if ($has_proof) {
                                        echo "<form action='approve_payment.php' method='POST' style='margin-top: 5px;'>
                                                <input type='hidden' name='request_id' value='{$request_id}'>
                                                <button type='submit' class='btn btn-success btn-sm w-100'>Approve Payment</button>
                                            </form>";
                                    } else {
                                        echo "<button type='button' class='btn btn-success btn-sm w-100' disabled style='opacity: 0.5; cursor: not-allowed;'>Approve Payment (No Proof)</button>";
                                    }
                                }
                                
                                echo "<form action='history_action.php' method='POST' style='margin-top: 5px;'>
                                        <input type='hidden' name='request_id' value='{$request_id}'>
                                        <input type='hidden' name='action' value='delete'>
                                        <button type='submit' class='btn btn-danger btn-sm w-100 mt-1'>Delete</button>
                                    </form>";
                            
                            echo "</td>
                        </tr>
                        ";
                    }
                }
                ?>
                            </table>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header">
                            <h3 class="card-title">Declined Rental Requests</h3>
                        </div>
                        <div class="card-body table-responsive p-0" style="max-height: 500px;">
                            <table class="table table-hover table-bordered text-center">
                                <tr>
                                    <th>Renter</th>
                                    <th>Car</th>
                                    <th>License</th>
                                    <th>Payment Proof</th>
                                    <th>Reference No</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Duration (Days)</th>
                                    <th>Original Cost</th>
                                    <th>Rental Status</th>
                                    <th>Notes</th>
                                </tr>
                                <?php
                if ($declined_details === false) {
                    echo "<tr><td colspan='11' style='color: red; text-align: center; padding: 10px;'>Database Query Failed: " . htmlspecialchars(mysqli_error($conn)) . "</td></tr>";
                } elseif(isset($declined_details) && mysqli_num_rows($declined_details) == 0){
                    echo "<tr><td colspan = 11>No declined requests</td></tr>"; 
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
                                    <button type='submit' class='btn btn-danger btn-sm w-100 mt-1'>Delete</button>
                                </form>
                            </td>
                        </tr>
                        ";
                    }
                }
                ?>
                            </table>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header">
                            <h3 class="card-title">Cancelled Rental Requests</h3>
                        </div>
                        <div class="card-body table-responsive p-0" style="max-height: 500px;">
                            <table class="table table-hover table-bordered text-center">
                                <tr>
                                    <th>Renter</th>
                                    <th>Car</th>
                                    <th>License</th>
                                    <th>Payment Proof</th>
                                    <th>Reference No</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Duration (Days)</th>
                                    <th>Original Cost</th>
                                    <th>Rental Status</th>
                                    <th>Notes</th>
                                </tr>
                                <?php
                if ($cancelled_details === false) {
                    echo "<tr><td colspan='11' style='color: red; text-align: center; padding: 10px;'>Database Query Failed: " . htmlspecialchars(mysqli_error($conn)) . "</td></tr>";
                } elseif(isset($cancelled_details) && mysqli_num_rows($cancelled_details) == 0){
                    echo "<tr><td colspan = 11>No cancelled rentals</td></tr>"; 
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
                    </div>

                    <div class="card mt-4">
                        <div class="card-header">
                            <h3 class="card-title">Completed Rentals</h3>
                        </div>
                        <div class="card-body table-responsive p-0" style="max-height: 500px;">
                            <table class="table table-hover table-bordered text-center">
                                <tr>
                                    <th>Renter</th>
                                    <th>Car</th>
                                    <th>License</th>
                                    <th>Payment Proof</th>
                                    <th>Reference No</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Duration (Days)</th>
                                    <th>Original Cost</th>
                                    <th>Rental Status</th>
                                    <th>Notes</th>
                                </tr>
                                <?php
                if ($completed_details === false) {
                    echo "<tr><td colspan='13' style='color: red; text-align: center; padding: 10px;'>Database Query Failed: " . htmlspecialchars(mysqli_error($conn)) . "</td></tr>";
                } elseif(isset($completed_details) && mysqli_num_rows($completed_details) == 0){
                    echo "<tr><td colspan = 13>No completed rentals</td></tr>"; 
                } else{
                    while ($row = mysqli_fetch_assoc($completed_details)){
                        $request_id = htmlspecialchars($row['request_id']);

                        $license_photo_url = htmlspecialchars($system_base_path . $row['driver_license_photo']);
                        $proof_path = htmlspecialchars($row['payment_proof_path']);
                        $proof_url = $proof_path ? htmlspecialchars($system_base_path . $proof_path) : '';
                        $request_status = htmlspecialchars($row['request_status']); 
                        $reference_no = htmlspecialchars($row['payment_reference_no']) ?: 'N/A';
                        $admin_notes = htmlspecialchars($row['admin_notes']);
                        
                        $original_cost = (float)($row['total_cost'] ?? 0.00);
                        $final_charge = (float)($row['total_deducted_cost'] ?? $original_cost);
                        $refund_amount = max(0, $original_cost - $final_charge);

                        $status_color = 'gray';
                        $status_display = "<span style='font-weight: bold; color: darkgreen;'>{$request_status}</span>";

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
                            <td>₱" . number_format($original_cost, 2) . "</td>
                            <td style='color: #28a745; font-weight: bold;'>₱" . number_format($final_charge, 2) . "</td>
                            <td style='color: yellow; font-weight: bold;'>₱" . number_format($refund_amount, 2) . "</td>
                            <td>{$status_display}</td>
                            <td>
                                <button type='button' class='notes-btn' data-request-id='{$request_id}' data-admin-notes='{$admin_notes}' onclick='openNotesModal(this)'>Notes</button>
                            </td>
                            <td id='status-button'>
                                <p style='color: darkgreen; font-weight: bold;'>Rental Completed</p>
                                <form action='history_action.php' method='POST' style='margin-top: 5px;'>
                                    <input type='hidden' name='request_id' value='{$request_id}'>
                                    <input type='hidden' name='action' value='delete'>
                                    <button type='submit' class='btn btn-danger btn-sm w-100 mt-1'>Delete</button>
                                </form>
                            </td>
                        </tr>
                        ";
                    }
                }
                ?>
                            </table>
                        </div>
                    </div>

                </div>
            </section>
        </div>
    </div>

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