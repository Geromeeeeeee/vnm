<?php

session_start();
include 'db.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $car_id = $_POST['car_id']; // Use POST car_id
    $pickup = $_POST['pickup'];
    $duration = (int)$_POST['duration'];

    // Find the next booking relative to the chosen pickup date
    $next_booking_sql = "SELECT rental_date FROM rental_requests 
                         WHERE car_id = ? 
                         AND request_status IN ('Approved', 'Picked Up', 'Pending', 'Early_Return_Scheduled') 
                         AND rental_date > ? 
                         ORDER BY rental_date ASC LIMIT 1";
    $stmt_next = $conn->prepare($next_booking_sql);
    $stmt_next->bind_param("is", $car_id, $pickup);
    $stmt_next->execute();
    $next_booking_result = $stmt_next->get_result();
    
    if ($row_n = $next_booking_result->fetch_assoc()) {
        $next_booking_date = $row_n['rental_date'];
        $gap = floor((strtotime($next_booking_date) - strtotime($pickup)) / 86400);
        
        // Gap minus 2 (1 day for return, 1 day for maintenance)
        $max_allowed = $gap - 2;
        
        if ($duration > $max_allowed) {
            $safe_max = ($max_allowed > 0) ? $max_allowed : 0;
            echo "<script>alert('BOOKING REJECTED: Next reservation is $next_booking_date. Including maintenance, max duration is $safe_max day(s).'); window.history.back();</script>";
            exit;
        }
    }
    // --- END OF ADDED FEATURE ---

    // 1. Set the correct relative directory
    $target_dir = "../uploads/licenses/";
    
    // 2. Create it if it doesn't exist
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $license_path = "";
    if (isset($_FILES['driver_license_photo']) && $_FILES['driver_license_photo']['error'] === UPLOAD_ERR_OK) {
        // Create a unique filename
        $file_name = "license_" . $_SESSION['user'] . "_" . time() . "_" . basename($_FILES['driver_license_photo']['name']);
        $target_file = $target_dir . $file_name;

        // 3. Physically move the file to the folder
        if (move_uploaded_file($_FILES['driver_license_photo']['tmp_name'], $target_file)) {
            $license_path = $target_file; // Use this variable in your SQL INSERT query
        }
    }
}

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
} 

if (!isset($_GET['car_id']) || !is_numeric($_GET['car_id'])) {
    header("Location: login-dashboard.php");
    exit;
}

$car_id = $_GET['car_id'];

// UPDATED: Removed 'AND availability = 1' so the page loads for all cars
$car_sql = "SELECT model, daily_rate, image FROM cars WHERE car_id = ?";
$stmt = $conn->prepare($car_sql);
$stmt->bind_param("i", $car_id);
$stmt->execute();
$car_result = $stmt->get_result();

$next_booking_sql = "SELECT rental_date FROM rental_requests 
                     WHERE car_id = ? 
                     AND request_status IN ('Approved', 'Picked Up', 'Pending', 'Early_Return_Scheduled') 
                     AND rental_date > CURDATE() 
                     ORDER BY rental_date ASC LIMIT 1";
$stmt_next = $conn->prepare($next_booking_sql);
$stmt_next->bind_param("i", $car_id);
$stmt_next->execute();
$next_booking_result = $stmt_next->get_result();
$next_booking_date = ($next_booking_result->num_rows > 0) ? $next_booking_result->fetch_assoc()['rental_date'] : null;

if ($car_result->num_rows === 0) {
    header("Location: login-dashboard.php?error=" . urlencode("The requested car does not exist."));
    exit;
}

$car = $car_result->fetch_assoc();
$car_model = htmlspecialchars($car['model']);
$daily_rate = $car['daily_rate'];

// --- UPDATED: FETCH BOOKED DATES + 1 MAINTENANCE DAY ---
// --- UPDATED: BLOCK RENTAL PERIOD (Jan 10-15) + MAINTENANCE (Jan 16) ---
$disable_dates = [];
$date_query = "SELECT rental_date, rental_duration_days FROM rental_requests 
               WHERE car_id = ? AND request_status IN ('Approved', 'Picked Up', 'Pending', 'Early_Return_Scheduled')";
$stmt_dates = $conn->prepare($date_query);
$stmt_dates->bind_param("i", $car_id);
$stmt_dates->execute();
$res_dates = $stmt_dates->get_result();

// --- CHANGE 3: THE CALENDAR LOOP ---
while ($row = $res_dates->fetch_assoc()) {
    $start = new DateTime($row['rental_date']); // Jan 10
    $days = (int)$row['rental_duration_days'];  // 5
    
    // Loop for $days + 1 to block 7 total dates (Pickup + Duration + Maintenance)
    for ($i = 0; $i <= $days + 1; $i++) { 
        $disable_dates[] = $start->format('Y-m-d');
        $start->modify('+1 day');
    }
}

$images = [];

if (!empty($car['image'])) {
    $images[] = $car['image'];
}

$images_sql = "SELECT image_path FROM car_images WHERE car_id = ? ORDER BY image_id ASC LIMIT 3";
$stmt_img = $conn->prepare($images_sql);
$stmt_img->bind_param("i", $car_id);
$stmt_img->execute();
$images_result = $stmt_img->get_result();

while ($row = $images_result->fetch_assoc()) {
    $images[] = $row['image_path'];
}

$images = array_pad($images, 4, ''); 

?>
<script>
    const DAILY_RATE = <?= $daily_rate ?>;
    const CAR_ID = '<?= $car_id ?>';
    const CAR_MODEL = '<?= $car_model ?>';
    
    // Use 'var' so it is globally available for the script in rent_form.html
    var DISABLED_DATES = <?php echo json_encode($disable_dates); ?>;
</script>

<style>
    /* --- ELEGANT AESTHETIC CALENDAR --- */
    .flatpickr-calendar {
        width: 100% !important;
        max-width: 450px;
        background: #ffffff;
        box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        border: 1px solid #eee;
        border-radius: 20px;
        margin: 20px 0;
        padding: 15px;
    }

    /* Header: Month and Year (Thin & Black) */
    .flatpickr-current-month {
        font-weight: 300 !important; /* Makes text thin */
        color: #000 !important;
        font-size: 1.3rem !important;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .flatpickr-current-month .cur-month, 
    .flatpickr-current-month input.cur-year {
        font-weight: 300 !important; /* Removes Bold from Year */
        color: #000 !important;
        font-size: 1.3rem !important;
    }

    /* Arrows */
    .flatpickr-prev-month, .flatpickr-next-month {
        fill: #000 !important;
        top: 15px !important;
    }

    /* Weekday Headers */
    span.flatpickr-weekday {
        color: #999 !important;
        font-weight: 500;
        text-transform: uppercase;
        font-size: 12px;
    }

    /* DAY STYLING: Circles & Black Text */
    .flatpickr-day {
        color: #000 !important; /* Force all text black */
        font-weight: 400;
        border-radius: 50% !important; /* Round circles */
        height: 40px;
        line-height: 40px;
        margin: 2px auto;
        border: none !important;
    }

    /* DISABLED / MAINTENANCE DATES (Gray Circle + Black Text) */
    .flatpickr-day.flatpickr-disabled, 
    .flatpickr-day.flatpickr-disabled:hover {
        background: #f0f0f0 !important; /* The visible gray circle */
        color: #000 !important;          /* Black text even when grayed out */
        text-decoration: line-through;   /* Keeps the "taken" visual */
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* SELECTED PICKUP DATE */
    .flatpickr-day.selected {
        background: #111 !important; /* Professional Black/Dark selected state */
        color: #fff !important;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }

    /* --- TERMS MODAL STYLES --- */
    .modal-overlay {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0; top: 0; width: 100%; height: 100%;
        background-color: rgba(0,0,0,0.6);
    }
    .terms-modal {
        background-color: #fff;
        margin: 10% auto;
        padding: 25px;
        border-radius: 12px;
        width: 85%;
        max-width: 500px;
        color: #000;
    }
    .terms-body {
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid #eee;
        padding: 15px;
        font-size: 14px;
        background: #fafafa;
        color: #000;
    }
    .close-modal-btn {
        background-color: #000;
        color: white;
        padding: 12px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        width: 100%;
    }
</style>

<script>
    window.addEventListener('DOMContentLoaded', () => {
        const form = document.querySelector('form');
        const submitBtn = form.querySelector('button[type="submit"]');
        
        // Match IDs from rent_form.html
        const durationInput = document.getElementById('duration'); 
        const rentalDateInput = document.getElementById('pickup'); 
        
        // From PHP logic at top of file
        const nextBookingDateStr = "<?php echo $next_booking_date; ?>"; 

        if (!form || !submitBtn) return;

        // --- FORM SUBMISSION GUARD ---
        // --- CHANGE 4: JAVASCRIPT VALIDATION ---
form.onsubmit = function(e) {
    const rentalDateValue = rentalDateInput.value;
    const duration = parseInt(durationInput.value);

    // Find the next blocked date AFTER the chosen pickup date
    const nextOccupied = DISABLED_DATES
        .filter(d => d > rentalDateValue)
        .sort()[0];

    if (nextOccupied && rentalDateValue && duration > 0) {
        const pickupDate = new Date(rentalDateValue);
        const nextDate = new Date(nextOccupied);
        const gap = Math.round((nextDate - pickupDate) / (1000 * 60 * 60 * 24));
        const maxAllowed = gap - 2;

        if (duration > maxAllowed) {
            e.preventDefault();
            const safeMax = maxAllowed > 0 ? maxAllowed : 0;
            alert("BOOKING REJECTED: This vehicle has a future reservation on " + nextOccupied + ". Including maintenance, max duration is " + safeMax + " day(s).");
            return false;
        }
    }
};

        // --- TERMS & CONDITIONS MODAL (DO NOT DELETE) ---
        const modalHTML = `
            <div id="termsOverlay" class="modal-overlay">
                <div class="terms-modal">
                    <h3>Rental Terms & Conditions</h3>
                    <div class="terms-body">
                        <p>1. The renter must be at least 21 years old and possess a valid driver's license.</p>
                        <p>2. The vehicle must be returned with the same amount of fuel as provided at pickup.</p>
                        <p>3. Any damage incurred during the rental period is the sole responsibility of the renter.</p>
                        <p>4. Late returns will incur an additional daily charge as specified in our rates.</p>
                        <p>5. The vehicle shall not be used for any illegal activities or unauthorized transport.</p>
                    </div>
                    <button type="button" class="close-modal-btn" onclick="document.getElementById('termsOverlay').style.display='none'">Close & Continue</button>
                </div>
            </div>

            <div class="terms-alignment-wrapper">
                <input type="checkbox" id="terms_checkbox" name="terms_agreement" class="terms-checkbox-input" required>
                <label for="terms_checkbox" class="terms-label-text">
                    I agree to the <span class="terms-link" onclick="event.preventDefault(); document.getElementById('termsOverlay').style.display='block'">Terms and Conditions</span>
                </label>
            </div>
        `;

        submitBtn.insertAdjacentHTML('beforebegin', modalHTML);

        submitBtn.disabled = true;
        const checkbox = document.getElementById('terms_checkbox');
        checkbox.addEventListener('change', function() {
            submitBtn.disabled = !this.checked;
        });

        window.onclick = function(event) {
            const overlay = document.getElementById('termsOverlay');
            if (event.target == overlay) {
                overlay.style.display = "none";
            }
        }
    });
</script>
<script>
    const DAILY_RATE = <?= $daily_rate ?>;
    const CAR_ID = '<?= $car_id ?>';
    const CAR_MODEL = '<?= $car_model ?>';
    var DISABLED_DATES = <?php echo json_encode($disable_dates); ?>;
</script>

<?php
include '../html/rent_form.html';
?>