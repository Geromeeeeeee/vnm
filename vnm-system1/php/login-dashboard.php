<?php
session_start();

//for the recommendation e2

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user'];

include 'db.php'; 

$current_user_id = $_SESSION['user'];

// Check for active rentals (Count them, limit is 3)
$active_check_sql = "SELECT COUNT(*) as active_count FROM rental_requests 
                     WHERE user_id = ? 
                     AND request_status IN ('Approved', 'Picked Up', 'Early_Return_Scheduled')";
$stmt_check = $conn->prepare($active_check_sql);
$stmt_check->bind_param("i", $current_user_id);
$stmt_check->execute();
$active_rentals = $stmt_check->get_result()->fetch_assoc()['active_count'];
$has_active_rental = $active_rentals >= 3; // Disable booking if already have 3 active rentals
$rental_limit = 3;
$active_rental_count = $active_rentals;

$sql = "SELECT 
            c.car_id, 
            c.image, 
            c.model, 
            c.year,
            c.fuel_type, 
            c.transmission, 
            c.daily_rate, 
            c.description,
            GROUP_CONCAT(ci.image_path ORDER BY ci.image_id SEPARATOR ',') AS additional_images 
        FROM cars c
        LEFT JOIN car_images ci ON c.car_id = ci.car_id
        WHERE c.availability !=2
        GROUP BY c.car_id
        ORDER BY c.car_id DESC";

$result = $conn->query($sql);
$cars = [];
if ($result) {
    $cars = $result->fetch_all(MYSQLI_ASSOC);
}

// sql query for recommendation part. sorry if magulo :/

$recommendation_sql = "SELECT c.car_brand, c.fuel_type, c.transmission
                    FROM cars c
                    JOIN rental_requests r ON c.car_id = r.car_id
                    WHERE r.user_id = $user_id
                    ORDER BY r.rental_date DESC
                    LIMIT 3";
$reco_result = mysqli_query($conn, $recommendation_sql);
$recommendation = [];

if($reco_result){
    while($reco_row = mysqli_fetch_assoc($reco_result)){
        $recommendation[] = $reco_row;
    }
}

$brand_pref = [];
$transm_pref = [];
$fuel_pref = [];

foreach($recommendation as $recommendation_row){
    $brand = $recommendation_row['car_brand'];
    if (!isset($brand_pref[$brand])) {
        $brand_pref[$brand] = 0;
    }
    $brand_pref[$brand]++;

    $trans = $recommendation_row['transmission'];
    if (!isset($transm_pref[$trans])) {
        $transm_pref[$trans] = 0;
    }
    $transm_pref[$trans]++;

    $fuel = $recommendation_row['fuel_type'];
    if (!isset($fuel_pref[$fuel])) {
        $fuel_pref[$fuel] = 0;
    }
    $fuel_pref[$fuel]++;
}

// FIX: Changed singular $recommended_car to plural $recommended_cars to match the rest of the logic
$recommended_cars = [];

foreach ($cars as $reco_cars){
    $points = 0;

    if (!empty($reco_cars['car_brand']) && isset($brand_pref[$reco_cars['car_brand']])) {
        $points += 3 * $brand_pref[$reco_cars['car_brand']];
    }

    if (!empty($reco_cars['transmission']) && isset($transm_pref[$reco_cars['transmission']])) {
        $points += 2 * $transm_pref[$reco_cars['transmission']];
    }

    if (!empty($reco_cars['fuel_type']) && isset($fuel_pref[$reco_cars['fuel_type']])) {
        $points += 1 * $fuel_pref[$reco_cars['fuel_type']];
    }

    if ($points > 0) {
        $reco_cars['score'] = $points;
        $recommended_cars[] = $reco_cars;
    }
}

if (!empty($recommended_cars)) {
    usort($recommended_cars, function($a, $b){
        return $b['score'] <=> $a['score'];
    });

    $recommended_cars = array_slice($recommended_cars, 0, 3);
}

//end of recommendation code

$upload_dir = '/vnm-system1/php/cars/uploads/cars/';

$carousel_html = '';
$featured_html = '';
foreach ($cars as $car) {
    $popover_images = [];
    
    // Add main image first
    if (!empty($car['image'])) {
         $popover_images[] = $upload_dir . urlencode($car['image']); 
    }
    
    // Add additional images
    if (!empty($car['additional_images'])) {
        $additional_images_paths = explode(',', $car['additional_images']);
        foreach ($additional_images_paths as $img) {
            $popover_images[] = $upload_dir . urlencode(trim($img)); 
        }
    }
    
    $car_id_js = $car['car_id']; 
    $images_json = htmlspecialchars(json_encode($popover_images), ENT_QUOTES, 'UTF-8');

    $description_html = htmlspecialchars(nl2br($car['description']), ENT_QUOTES);
    
    $main_image_path = !empty($car['image']) ? $upload_dir . urlencode($car['image']) : 'placeholder.jpg';

    $carousel_html .= '
    <div class="cars">
        <img src="' . $main_image_path . '" alt="' . htmlspecialchars($car['model']) . '">

        <div class="car-info-before-click">
            <h4>' . htmlspecialchars($car['model']) . ' (' . htmlspecialchars($car['year']) . ')</h4>
            <p>Fuel: ' . htmlspecialchars($car['fuel_type']) . ' | Trans: ' . htmlspecialchars($car['transmission']) . '</p>
        </div>

        <button popovertarget="view-details" 
            onclick="openDetailsModal(
                \'' . $car_id_js . '\', 
                \'' . htmlspecialchars($car['model'], ENT_QUOTES) . '\',
                \'' . $description_html . '\',
                \'' . number_format($car['daily_rate'], 2) . '\',
                \'' . $images_json . '\'
        )">View Details</button>
    </div>';
}

$recommended_html = '';

// FIX: The loop now uses $recommended_cars which is guaranteed to be an array
foreach ($recommended_cars as $car) {
    $popover_images = [];

    // Main image
    if (!empty($car['image'])) {
        $popover_images[] = $upload_dir . urlencode($car['image']);
    }

    // Additional images
    if (!empty($car['additional_images'])) {
        $additional_images_paths = explode(',', $car['additional_images']);
        foreach ($additional_images_paths as $img) {
            $popover_images[] = $upload_dir . urlencode(trim($img));
        }
    }

    $car_id_js = $car['car_id']; 
    $images_json = htmlspecialchars(json_encode($popover_images), ENT_QUOTES, 'UTF-8');
    $description_html = htmlspecialchars(nl2br($car['description']), ENT_QUOTES);

    $main_image_path = !empty($car['image']) ? $upload_dir . urlencode($car['image']) : 'placeholder.jpg';

    $recommended_html .= '
    <div class="cars">
        <img src="' . $main_image_path . '" alt="' . htmlspecialchars($car['model']) . '">
        <div class="car-info-before-click">
        <h4>' . htmlspecialchars($car['model']) . ' (' . htmlspecialchars($car['year']) . ')</h4>
        <p>Fuel: ' . htmlspecialchars($car['fuel_type']) . ' | Trans: ' . htmlspecialchars($car['transmission']) . '</p>
        </div>
        <button popovertarget="view-details" 
            onclick="openDetailsModal(
                \'' . $car_id_js . '\', 
                \'' . htmlspecialchars($car['model'], ENT_QUOTES) . '\',
                \'' . $description_html . '\',
                \'' . number_format($car['daily_rate'], 2) . '\',
                \'' . $images_json . '\'
        )">View Details</button>
    </div>';
}

include '../html/dashboard.html';
?>