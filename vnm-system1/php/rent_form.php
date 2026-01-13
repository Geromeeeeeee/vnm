<?php

session_start();
include 'db.php'; 

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

if ($car_result->num_rows === 0) {
    header("Location: login-dashboard.php?error=" . urlencode("The requested car does not exist."));
    exit;
}

$car = $car_result->fetch_assoc();
$car_model = htmlspecialchars($car['model']);
$daily_rate = $car['daily_rate'];

// --- NEW: FETCH BOOKED DATES TO GREY OUT ---
$disable_dates = [];
$date_query = "SELECT rental_date, rental_duration_days FROM rental_requests 
               WHERE car_id = ? AND request_status IN ('Approved', 'Picked Up', 'Early_Return_Scheduled')";
$stmt_dates = $conn->prepare($date_query);
$stmt_dates->bind_param("i", $car_id);
$stmt_dates->execute();
$res_dates = $stmt_dates->get_result();

while ($row = $res_dates->fetch_assoc()) {
    $start = new DateTime($row['rental_date']);
    $days = (int)$row['rental_duration_days'];
    for ($i = 0; $i < $days; $i++) {
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
    const DISABLED_DATES = <?php echo json_encode($disable_dates); ?>;
</script>

<style>
    /* Modal Background */
    .modal-overlay {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.6);
    }
    /* Modal Content Box */
    .terms-modal {
        background-color: #fff;
        margin: 10% auto;
        padding: 25px;
        border-radius: 12px;
        width: 85%;
        max-width: 500px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    }
    /* BLACK TEXT INSIDE MODAL */
    .terms-modal h3, 
    .terms-modal p, 
    .terms-modal div {
        color: #000 !important;
        text-align: left;
    }
    .terms-modal h3 { 
        margin-top: 0; 
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }
    .terms-body {
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid #eee;
        padding: 15px;
        margin-bottom: 20px;
        font-size: 14px;
        line-height: 1.6;
        background: #fafafa;
    }
    .close-modal-btn {
        background-color: #111;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        width: 100%;
        font-weight: bold;
    }
    
    /* BLUE LINK COLOR */
    .terms-link {
        color: #007bff; /* Standard Link Blue */
        text-decoration: underline;
        font-weight: bold;
        cursor: pointer;
    }
    
    .terms-link:hover {
        color: #0056b3;
    }

    /* Alignment Container */
    .terms-alignment-wrapper {
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 20px 0;
        padding: 0 5px;
        color: #000;
    }

    .terms-checkbox-input {
        width: 20px;
        height: 20px;
        cursor: pointer;
        margin: 0;
    }

    .terms-label-text {
        font-size: 14px;
        color: #fafafa; 
        cursor: pointer;
        user-select: none;
    }

    /* Submit Button Styling */
    form button[type="submit"]:disabled {
        background-color: #ccc !important;
        cursor: not-allowed !important;
        opacity: 0.8;
    }
</style>

<script>
    window.addEventListener('DOMContentLoaded', () => {
        const form = document.querySelector('form');
        const submitBtn = form.querySelector('button[type="submit"]');

        if (!form || !submitBtn) return;

        // Create Modal HTML
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

        // Setup Button Locking
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

<?php
include '../html/rent_form.html';
?>