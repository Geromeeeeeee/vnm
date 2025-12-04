<?php


include 'db.php'; 

if (!isset($_GET['car_id']) || !is_numeric($_GET['car_id'])) {
    header("Location: login-dashboard.php");
    exit;
}

$car_id = $_GET['car_id'];

$car_sql = "SELECT model, daily_rate, image FROM cars WHERE car_id = ?";
$stmt = $conn->prepare($car_sql);
$stmt->bind_param("i", $car_id);
$stmt->execute();
$car_result = $stmt->get_result();

if ($car_result->num_rows === 0) {
    header("Location: login-dashboard.php");
    exit;
}

$car = $car_result->fetch_assoc();
$car_model = htmlspecialchars($car['model']);
$daily_rate = $car['daily_rate'];

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
</script>
<?php

include '../html/rent_form.html';
?>