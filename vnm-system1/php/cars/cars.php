<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../db.php';

// ===========================================
// NEW FUNCTION TO CHECK ACTIVE RENTAL STATUS
// ===========================================
function isCarCurrentlyRented($car_id, $conn) {
    // Check if the car is currently involved in a rental that has been 'Picked Up' (Active rental)
    $sql = "SELECT request_id FROM rental_requests WHERE car_id = ? AND request_status = 'Picked Up'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $car_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $is_rented = $result->num_rows > 0;
    $stmt->close();
    return $is_rented;
}

function handleMultipleImageUpload($car_id, $conn) {
    $uploadDir = "uploads/cars/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $uploadedCount = 0;
    if (!empty($_FILES['additional_images']['name'][0])) {
        foreach ($_FILES['additional_images']['name'] as $key => $name) {
            $tmp_name = $_FILES['additional_images']['tmp_name'][$key];
            if ($tmp_name) {
                $image = time() . '_' . basename($name);
                if (move_uploaded_file($tmp_name, $uploadDir . $image)) {
                    $sql_insert_image = "INSERT INTO car_images (car_id, image_path) VALUES ($car_id, '$image')";
                    $conn->query($sql_insert_image);
                    $uploadedCount++;
                }
            }
        }
    }
    return $uploadedCount;
}


if (isset($_POST['delete_image_id'])) {
    $image_id = $_POST['delete_image_id'];
    $result = $conn->query("SELECT image_path, car_id FROM car_images WHERE image_id=$image_id");
    if ($result && $row = $result->fetch_assoc()) {
        $uploadDir = "uploads/cars/";
        if (!empty($row['image_path']) && file_exists($uploadDir . $row['image_path'])) {
            unlink($uploadDir . $row['image_path']);
        }
        $car_id = $row['car_id'];

        if ($conn->query("DELETE FROM car_images WHERE image_id=$image_id") === TRUE) {
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error deleting additional image: " . $conn->error;
        }
    }
}

// ===========================================
// DELETE HANDLER WITH RENTAL CHECK
// ===========================================
if (isset($_POST['delete_id'])) {
    $car_id = $_POST['delete_id'];
    
    if (isCarCurrentlyRented($car_id, $conn)) {
        // Prevent deletion if the car is actively rented
        echo "<p style='color:red;'>Cannot delete car: Vehicle is currently RENTED.</p>";
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }
    
    // ... Original deletion logic continues below
    
    $result = $conn->query("SELECT image FROM cars WHERE car_id=$car_id");
    if ($result && $row = $result->fetch_assoc()) {
        $uploadDir = "uploads/cars/";
        if (!empty($row['image']) && file_exists($uploadDir . $row['image'])) {
            unlink($uploadDir . $row['image']);
        }
    }
    $result_images = $conn->query("SELECT image_path FROM car_images WHERE car_id=$car_id");
    if ($result_images) {
        $uploadDir = "uploads/cars/";
        while($img_row = $result_images->fetch_assoc()) {
            if (!empty($img_row['image_path']) && file_exists($uploadDir . $img_row['image_path'])) {
                unlink($uploadDir . $img_row['image_path']);
            }
        }
    }

    if ($conn->query("DELETE FROM cars WHERE car_id=$car_id") === TRUE) {
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Error deleting vehicle: " . $conn->error;
    }
}


if (isset($_POST['model']) && !isset($_POST['edit_id'])) {
    $model = $_POST['model'];
    $plate_no = $_POST['plate_no'];
    $car_brand = $_POST['brand'];
    $year = $_POST['year'];
    $daily_rate = isset($_POST['daily_rate']) ? $_POST['daily_rate'] : '';
    if (!is_numeric($daily_rate) || floatval($daily_rate) < 0) {
        echo "<p style='color:red;'>Invalid daily rate. It must be a non-negative number.</p>";
        exit();
    }
    $daily_rate = floatval($daily_rate);
    $owner = $_POST['owner'];
    $fuel_type = $_POST['fuel_type'];
    $transmission = $_POST['transmission']; // NEW FIELD
    $location_id = $_POST['location_id'];
    $availability = 1; 
    $description = $_POST['description'];

    $image = '';
    if (!empty($_FILES['image']['name'])) {
        $uploadDir = "uploads/cars/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $image = time() . '_' . basename($_FILES['image']['name']);
        move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $image);
    }

    // UPDATED SQL INSERT
    $sql_insert = "INSERT INTO cars(model, plate_no, car_brand, year, daily_rate, owner, fuel_type, transmission, location_id, availability, image, description)
                     VALUES ('$model','$plate_no','$car_brand','$year','$daily_rate','$owner','$fuel_type','$transmission','$location_id','$availability','$image','$description')";

    if ($conn->query($sql_insert) === TRUE) {
        $car_id = $conn->insert_id;
        handleMultipleImageUpload($car_id, $conn);
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Error inserting vehicle: " . $conn->error;
    }
}


// ===========================================
// EDIT HANDLER WITH RENTAL CHECK
// ===========================================
if (isset($_POST['edit_id'])) {
    $car_id = $_POST['edit_id'];
    $model = $_POST['model'];
    $plate_no = $_POST['plate_no'];
    $car_brand = $_POST['brand'];
    $year = $_POST['year'];
    $daily_rate = isset($_POST['daily_rate']) ? $_POST['daily_rate'] : '';
    if (!is_numeric($daily_rate) || floatval($daily_rate) < 0) {
        echo "<p style='color:red;'>Invalid daily rate. It must be a non-negative number.</p>";
        exit();
    }
    $daily_rate = floatval($daily_rate);
    $owner = $_POST['owner'];
    $fuel_type = $_POST['fuel_type'];
    $transmission = $_POST['transmission']; // NEW FIELD
    $location_id = $_POST['location_id'];
    $description = $_POST['description'];
    
    $is_rented_active = isCarCurrentlyRented($car_id, $conn); // Check rental status

    $availability_update_clause = "";
    if (!$is_rented_active) {
        // Only update availability if the car is NOT currently rented
        $availability = isset($_POST['availability']) ? $_POST['availability'] : 1; 
        $availability_update_clause = ", availability='$availability'";
    } 
    // If rented, we intentionally omit the availability update to prevent manual overrides.


    $imageSQL = '';
    if (!empty($_FILES['image']['name'])) {
        $uploadDir = "uploads/cars/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    
        $result = $conn->query("SELECT image FROM cars WHERE car_id=$car_id");
        if ($result && $row = $result->fetch_assoc()) {
            if (!empty($row['image']) && file_exists($uploadDir . $row['image'])) {
                unlink($uploadDir . $row['image']);
            }
        }

        $image = time() . '_' . basename($_FILES['image']['name']);
        move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $image);
        $imageSQL = ", image='$image'";
    }

    handleMultipleImageUpload($car_id, $conn);

    // UPDATED SQL UPDATE - Note the inclusion of $availability_update_clause
    $sql_update = "UPDATE cars 
                      SET model='$model', plate_no='$plate_no', car_brand='$car_brand', year='$year', daily_rate='$daily_rate', owner='$owner',
                          fuel_type='$fuel_type', transmission='$transmission', location_id='$location_id', description='$description' $imageSQL $availability_update_clause
                      WHERE car_id=$car_id";

    if ($conn->query($sql_update) === TRUE) {
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Error updating vehicle: " . $conn->error;
    }
}


$sql = "SELECT * FROM cars ORDER BY car_id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="/vnm-system1/css/common.css"> 
<link rel="stylesheet" href="/vnm-system1/php/cars/cars.css">
<title>VNM/Cars</title>
<style>

.action-buttons {
    box-sizing: border-box;
    display: flex;
    flex-direction: column; 
    justify-content: center;
    align-items: center;
    gap: 5px; 
    min-height: 50px;
    margin: 1.5vh;
}
.action-buttons form {
    display: inline-block;
    margin: 0;
}
.action-buttons button {
    padding: 6px 12px;
    font-size: 14px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    height: 36px;
}
.edit-btn { background-color:#4CAF50; color:white; }
.edit-btn:hover { background-color:#45a049; }
.delete-btn { background-color:#f44336; color:white; }
.delete-btn:hover { background-color:#da190b; }


.form, .edit-form {
    max-width: 75%;
    margin: 10px auto;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background-color: #f9f9f9;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

#add-vehicle-form{
    display: block;
}

div:popover-open{
    display: block;
    width: 90vw;
    height: 90vh;
    overflow: auto;
    border: transparent;
    margin: auto;
    backdrop-filter: blur(15px);
    background-color: #ffffff4d;
    box-shadow: 0 0px 8px rgba(0,0,0,0.1);
}

/* FIX: Updated CSS selector to target forms inside any popover whose ID starts with 'edit-form-popover-' 
        This is necessary because the previous fix made the popover IDs unique. */
div[id^="edit-form-popover-"] form{
    display: block;
}   

.form label, .edit-form label { 
    display:block; 
    margin-bottom:5px; 
    font-weight:bold; 
}
.form input[type="text"], .form input[type="number"], .form input[type="file"], .form select, .form textarea,
.edit-form input[type="text"], .edit-form input[type="number"], .edit-form input[type="file"], .edit-form select, .edit-form textarea {
    width:100%; padding:8px; margin-bottom:12px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box;
}
.form button.submit, .edit-form button { width:100%; padding:10px; background-color:#4CAF50; color:white; font-size:16px; border:none; border-radius:4px; cursor:pointer; }
.form button.submit:hover, .edit-form button:hover { background-color:#45a049; }


img.preview { width:80px; height:80px; object-fit:cover; border-radius:4px; cursor:pointer; }


.img-popup {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    display: none;
    max-width: 500px;
    max-height: 500px;
    border: 2px solid #333;
    border-radius: 6px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    z-index: 1000;
    background-color: white;
    object-fit: contain;
    pointer-events: none;
    transition: opacity 0.2s ease;
}
.additional-images-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
    margin-bottom: 10px;
    border: 1px solid #ddd;
    padding: 10px;
    border-radius: 4px;
    background-color: #eee;
}
.additional-image-item {
    position: relative;
}
.additional-image-item .delete-img-btn {
    position: absolute;
    top: 0;
    right: 0;
    background: #f44336;
    color: white;
    border: none;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    padding: 0;
    font-size: 12px;
    line-height: 1;
    cursor: pointer;
    text-align: center;
}


table td, table th { 
    text-align:center; 
    padding:2.5vh;
    height: 3vh;
    width: fit-content;
}

.gallery-modal-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    width: 100%;
}
.gallery-image-wrapper {
    position: relative;
    max-width: 80%;
    max-height: 80%;
    text-align: center;
}
#galleryImage {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}
.gallery-controls {
    display: flex;
    width: 100%;
    justify-content: space-between;
    align-items: center;
    margin-top: 10px;
}
.gallery-controls button {
    padding: 10px 20px;
    font-size: 18px;
    cursor: pointer;
}
#imageNumbering {
    font-size: 20px;
    font-weight: bold;
    color: #333;
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

<main>
<button popovertarget="add-vehicle">Add Vehicle</button>

 <div popover id="add-vehicle">
    <form action="" method="POST" enctype="multipart/form-data" class="form" id="add-vehicle-form">
        <label>Main Image</label>
        <input type="file" name="image">

        <label>Additional Images (Multiple)</label>
        <input type="file" name="additional_images[]" multiple>

        <label>Model</label>
        <input type="text" name="model" required>

        <label for="plate_no.">Plate No.</label>
        <input type="text" name="plate_no" required pattern="^[A-Z]{3}\s?\d{2,4}$" title="Format: ABC 123, ABC 1234, or ABC 12">

        <label>Brand</label>
        <input type="text" name="brand" required pattern="[A-Za-z\s]+" title="Only letters and spaces allowed">

        <label>Year</label>
        <input type="number" name="year" min="1900" max="2100" required>

        <label>Daily Rate</label>
        <input type="number" step="0.01" name="daily_rate" min="0" required>

        <label>Owner</label>
        <input type="text" name="owner" required>

        <label>Fuel Type</label>
        <input type="text" name="fuel_type" required pattern="[A-Za-z\s]+" title="Only letters and spaces allowed">
        
        <label>Transmission</label>
        <select name="transmission" required>
            <option value="">-- Select Transmission --</option>
            <option value="Automatic">Automatic</option>
            <option value="Manual">Manual</option>
        </select>
        <label>Location ID</label>
        <input type="number" name="location_id" required>

        <label>Description</label>
        <textarea name="description" rows="4" placeholder="Enter car description..."></textarea>
        
        <button type="submit" class="submit">Add Vehicle</button>
    </form>
 </div>

<h3>Vehicles Table</h3>
<table border="1" cellpadding="10" cellspacing="0" style="width:100%; font-family:Arial; margin-bottom:20px;">
<tr>
    <th>ID</th><th>Image</th><th>Model</th><th>Plate No.</th><th>Brand</th><th>Year</th><th>Daily Rate</th>
    <th>Owner</th><th>Fuel Type</th><th>Transmission</th><th>Description</th><th>Location ID</th><th>Availability</th><th>Actions</th>
</tr>

<?php 
    $car_images = [];
    $images_result = $conn->query("SELECT * FROM car_images");
    if ($images_result) {
        while ($img_row = $images_result->fetch_assoc()) {
            $car_images[$img_row['car_id']][] = $img_row;
        }
    }

    while($row = $result->fetch_assoc()): 
    
    // Check if car is currently rented for UI/Security checks
    $car_id = $row['car_id'];
    $is_rented_active = isCarCurrentlyRented($car_id, $conn);
    $delete_disabled = $is_rented_active ? 'disabled' : '';
    $delete_confirm = $is_rented_active ? 'return false' : 'return confirmDelete()';
    $availability_text = '';
    
    // START OF FIX: Define unique popover ID
    $popover_id = "edit-form-popover-" . $car_id; 
    
    if ($row['availability']==1) $availability_text = "Available";
    elseif($row['availability']==0) $availability_text = "Unavailable";
    else $availability_text = "Maintenance";

    $all_images = [];
    if (!empty($row['image'])) {
        $all_images[] = [ 'path' => $row['image'] ];
    }
    if (isset($car_images[$row['car_id']])) {
        foreach ($car_images[$row['car_id']] as $img) {
            $all_images[] = [ 'path' => $img['image_path'] ];
        }
    }
    $images_json = htmlspecialchars(json_encode($all_images), ENT_QUOTES, 'UTF-8');
?>
<tr>
    <td><?= $row['car_id'] ?></td>
    <td>
        <?= !empty($row['image']) ? "<img src='uploads/cars/".$row['image']."' class='preview main-car-image' data-images='{$images_json}' onclick='openImageGallery(this)'>" : "No Image" ?>
        <?php 
            $additional_count = isset($car_images[$row['car_id']]) ? count($car_images[$row['car_id']]) : 0;
            if ($additional_count > 0) {
                echo "<br><small style='color:blue;'>+" . $additional_count . " More Images</small>";
            }
        ?>
    </td>
    <td><?= $row['model'] ?></td>
    <td><?= $row['plate_no'] ?></td>
    <td><?= $row['car_brand'] ?></td>
    <td><?= $row['year'] ?></td>
    <td><?= $row['daily_rate'] ?></td>
    <td><?= $row['owner'] ?></td>
    <td><?= $row['fuel_type'] ?></td>
    <td><?= $row['transmission'] ?? 'N/A' ?></td> 
    <td style="max-width: 250px; text-align: left;"><?= nl2br(htmlspecialchars($row['description'])) ?></td>
    <td><?= $row['location_id'] ?></td>
    <td>
        <?= $availability_text ?>
        <?php if ($is_rented_active): ?>
            <p style="color: red; font-size: 0.8em; font-weight: bold;">(Currently Rented)</p>
        <?php endif; ?>
    </td>
    <td>
        <div class="action-buttons">
            <button class="edit-btn" popovertarget="<?= $popover_id ?>">Edit Info</button>
            <form method="POST" onsubmit="<?= $delete_confirm ?>">
                <input type="hidden" name="delete_id" value="<?= $row['car_id'] ?>">
                <button class="delete-btn" type="submit" <?= $delete_disabled ?>>Delete</button>
            </form>
        </div>
        
        <div id="<?= $popover_id ?>" popover="auto">
        <form id="editForm<?= $car_id ?>" class="edit-form" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="edit_id" value="<?= $car_id ?>">

            <label>Model</label>
            <input type="text" name="model" value="<?= $row['model'] ?>" required>
            
            <label>Plate No.</label>
            <input type="text" name="plate_no" value="<?= $row['plate_no'] ?>" required pattern="^[A-Z]{3}\s?\d{2,4}$" title="Format: ABC 123, ABC 1234, or ABC 12">

            <label>Brand</label>
            <input type="text" name="brand" value="<?= $row['car_brand'] ?>" required pattern="[A-Za-z\s]+" title="Only letters and spaces allowed">
            
            <label>Year</label>
            <input type="number" name="year" value="<?= $row['year'] ?>" required>
            
            <label>Daily Rate</label>
            <input type="number" step="0.01" name="daily_rate" value="<?= $row['daily_rate'] ?>" min="0" required>
            
            <label>Owner</label>
            <input type="text" name="owner" value="<?= $row['owner'] ?>" required>

            <label>Fuel Type</label>
            <input type="text" name="fuel_type" value="<?= $row['fuel_type'] ?>" required pattern="[A-Za-z\s]+" title="Only letters and spaces allowed">

            <label>Transmission</label>
            <select name="transmission" required>
                <option value="Automatic" <?= ($row['transmission']=='Automatic')?'selected':'' ?>>Automatic</option>
                <option value="Manual" <?= ($row['transmission']=='Manual')?'selected':'' ?>>Manual</option>
            </select>
            <label>Location ID</label>
            <input type="number" name="location_id" value="<?= $row['location_id'] ?>" required>
            
            <label>Description</label>
            <textarea name="description" rows="4" placeholder="Enter car description..."><?= htmlspecialchars($row['description']) ?></textarea>

            <label>Availability</label>
            <select name="availability" <?= $is_rented_active ? 'disabled' : '' ?>>
                <option value="1" <?= ($row['availability']==1)?'selected':'' ?>>Available</option>
                <option value="0" <?= ($row['availability']==0 || $is_rented_active)?'selected':'' ?>>Unavailable</option>
                <option value="2" <?= ($row['availability']==2)?'selected':'' ?>>Maintenance</option>
            </select><br>
            <?php if ($is_rented_active): ?>
                <input type="hidden" name="availability" value="0"> 
                <p style="color: red; font-weight: bold; font-size: 0.9em;">Status locked: Car is RENTED.</p>
            <?php endif; ?>

            <label>Replace Main Image:</label>
            <input type="file" name="image"><br>

            <label>Add More Images:</label>
            <input type="file" name="additional_images[]" multiple><br>

            <?php if (isset($car_images[$car_id]) && count($car_images[$car_id]) > 0): ?>
                <label>Existing Additional Images:</label>
                <div class="additional-images-container">
                    <?php foreach ($car_images[$car_id] as $img): ?>
                        <div class="additional-image-item">
                            <img src='uploads/cars/<?= $img['image_path'] ?>' class='preview'>
                            <button type="button" class="delete-img-btn" title="Delete Image" onclick="confirmAndDeleteImage(<?= $img['image_id'] ?>)">x</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <button type="submit">Save</button>
        </form>
        </div>
    </td>
</tr>
<?php endwhile; ?>
</table>

<img id="imagePopup" class="img-popup" src="" alt="Image Preview">

<form id="deleteImageForm" method="POST" style="display:none;">
    <input type="hidden" name="delete_image_id" id="hidden_delete_image_id">
</form>

<div popover id="imageGallery" style="max-width: 90vw; max-height: 90vh;">
    <div class="gallery-modal-content">
        <div class="gallery-image-wrapper">
            <img id="galleryImage" src="" alt="Car Image" style="max-width: 90vw; max-height: 70vh;">
        </div>
        <div class="gallery-controls">
            <button id="prevBtn" onclick="navigateGallery(-1)" disabled>Previous</button>
            <span id="imageNumbering"></span>
            <button id="nextBtn" onclick="navigateGallery(1)" disabled>Next</button>
        </div>
    </div>
</div>

</main>

<script>
let currentImages = [];
let currentImageIndex = 0;
const uploadPath = 'uploads/cars/'; 

function openImageGallery(imgElement) {
    const imagesJson = imgElement.getAttribute('data-images');
    currentImages = JSON.parse(imagesJson);
    currentImageIndex = 0;
    
    if (currentImages.length > 0) {
        document.getElementById('imageGallery').showPopover();
        updateGalleryImage();
    }
}

function updateGalleryImage() {
    const totalImages = currentImages.length;
    const imagePath = currentImages[currentImageIndex].path;

    document.getElementById('galleryImage').src = uploadPath + imagePath;
    document.getElementById('imageNumbering').textContent = `${currentImageIndex + 1} / ${totalImages}`;

    document.getElementById('prevBtn').disabled = currentImageIndex === 0;
    document.getElementById('nextBtn').disabled = currentImageIndex === totalImages - 1;
}

function navigateGallery(direction) {
    const newIndex = currentImageIndex + direction;
    if (newIndex >= 0 && newIndex < currentImages.length) {
        currentImageIndex = newIndex;
        updateGalleryImage();
    }
}

function show() {
    // This function is for the Add form popover, no change needed
    const addPopover = document.getElementById('add-vehicle');
    if (addPopover) {
        addPopover.togglePopover();
    }
}

function confirmDelete() {
    return confirm("Are you sure you want to delete this vehicle?");
}

function confirmAndDeleteImage(imageId) {
    if (confirm('Are you sure you want to delete this additional image?')) {
        document.getElementById('hidden_delete_image_id').value = imageId;
        document.getElementById('deleteImageForm').submit();
    }
}

const imagePopup = document.getElementById('imagePopup');
document.querySelectorAll('td img.preview, .additional-images-container img.preview').forEach(img=>{
    img.addEventListener('mouseenter',()=>{imagePopup.src=img.src; imagePopup.style.display='block';});
    img.addEventListener('mouseleave',()=>{imagePopup.style.display='none';});
});
</script>
</body>
</html>