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
        WHERE c.availability = 1
        GROUP BY c.car_id
        ORDER BY c.car_id DESC";

$result = $conn->query($sql);
$cars = [];
if ($result) {
    $cars = $result->fetch_all(MYSQLI_ASSOC);
}

$upload_dir = '/vnm-system1/php/cars/uploads/cars/';

$carousel_html = '';
foreach ($cars as $car) {
    $popover_images = [];
    if (!empty($car['additional_images'])) {
        $additional_images_paths = explode(',', $car['additional_images']);
        foreach ($additional_images_paths as $img) {
            $popover_images[] = $upload_dir . urlencode(trim($img)); 
        }
    }
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
                \'' . htmlspecialchars($car['model'], ENT_QUOTES) . '\',
                \'' . $description_html . '\',
                \'' . number_format($car['daily_rate'], 2) . '\',
                \'' . $images_json . '\'
        )">View Details</button>
    </div>';
}

include '../html/landing.html';
?>