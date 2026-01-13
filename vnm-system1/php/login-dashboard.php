<?php


include 'db.php'; 

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
        GROUP BY c.car_id
        ORDER BY c.car_id DESC";

$result = $conn->query($sql);
$cars = [];
if ($result) {
    $cars = $result->fetch_all(MYSQLI_ASSOC);
}

$featured_sql = "SELECT 
    c.car_id,
    c.model,
    c.image,
    COUNT(r.car_id) AS rental_count 
    FROM cars c 
    JOIN rental_requests r ON c.car_id = r.car_id
    GROUP BY c.car_id, c.model, c.image
    ORDER BY rental_count DESC";

$featured_result = $conn->query($featured_sql);
$featured_cars = [];

if($featured_result){
    while($row = mysqli_fetch_assoc($featured_result)){
        $featured_cars[] = $row;
    }
}

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

foreach ($featured_cars as $car){
    $img_path = !empty($car['image']) ? $upload_dir . urlencode($car['image']) : 'placeholder.jpg';
        $featured_html .= "
            <div class='featured-cars'>
            <img src='" . $img_path . "' alt='" . htmlspecialchars($car['model']) . "'>
                <h3>" . htmlspecialchars($car['model']) . "</h3>
                <p>Rented ".(int)$car['rental_count']." Times</p>
            </div>
        ";
    }

include '../html/dashboard.html';
?>