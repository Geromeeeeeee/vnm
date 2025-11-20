<?php 
$host = "localhost";
$user = "root";
$pass = "";
$db   = "vnm";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}


// ================= Insert Vehicle =================
if (isset($_POST['model']) && !isset($_POST['edit_id'])) {
    $model = $_POST['model'];
    $car_brand = $_POST['brand'];
    $year = $_POST['year'];
    $daily_rate = $_POST['daily_rate'];
    $fuel_type = $_POST['fuel_type'];
    $location_id = $_POST['location_id'];
    $availability = isset($_POST['availability']) ? $_POST['availability'] : 1;

    // ---------------- Image Upload ----------------
    $image = '';
    if (!empty($_FILES['image']['name'])) {
        $uploadDir = "uploads/cars/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $image = time() . '_' . basename($_FILES['image']['name']);
        move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $image);
    }

    $sql_insert = "INSERT INTO cars(model, car_brand, year, daily_rate, fuel_type, location_id, availability, image)
                   VALUES ('$model','$car_brand','$year','$daily_rate','$fuel_type','$location_id','$availability','$image')";

    if ($conn->query($sql_insert) === TRUE) {
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Error inserting vehicle: " . $conn->error;
    }
}

// ================= Edit Vehicle =================
if (isset($_POST['edit_id'])) {
    $car_id = $_POST['edit_id'];
    $model = $_POST['model'];
    $car_brand = $_POST['brand'];
    $year = $_POST['year'];
    $daily_rate = $_POST['daily_rate'];
    $fuel_type = $_POST['fuel_type'];
    $location_id = $_POST['location_id'];
    $availability = isset($_POST['availability']) ? $_POST['availability'] : 1;

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

    $sql_update = "UPDATE cars 
                   SET model='$model', car_brand='$car_brand', year='$year', daily_rate='$daily_rate',
                       fuel_type='$fuel_type', location_id='$location_id', availability='$availability' $imageSQL
                   WHERE car_id=$car_id";

    if ($conn->query($sql_update) === TRUE) {
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Error updating vehicle: " . $conn->error;
    }
}

// ================= Delete Vehicle =================
if (isset($_POST['delete_id'])) {
    $car_id = $_POST['delete_id'];

 
    $result = $conn->query("SELECT image FROM cars WHERE car_id=$car_id");
    if ($result && $row = $result->fetch_assoc()) {
        $uploadDir = "uploads/cars/";
        if (!empty($row['image']) && file_exists($uploadDir . $row['image'])) {
            unlink($uploadDir . $row['image']);
        }
    }

    if ($conn->query("DELETE FROM cars WHERE car_id=$car_id") === TRUE) {
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Error deleting vehicle: " . $conn->error;
    }
}

// ================= Fetch Vehicles =================
$sql = "SELECT * FROM cars ORDER BY car_id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../common.css">
<link rel="stylesheet" href="cars.css">
<title>VNM/Cars</title>
<style>

.action-buttons {
    display: flex;
    flex-direction: column; 
    justify-content: center;
    align-items: center;
    gap: 5px; 
    min-height: 50px;
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
    display: none;
    max-width: 400px;
    margin: 10px auto;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background-color: #f9f9f9;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.form.show { display: block; }
.form label, .edit-form label { display:block; margin-bottom:5px; font-weight:bold; }
.form input[type="text"], .form input[type="number"], .form input[type="file"], .form select,
.edit-form input[type="text"], .edit-form input[type="number"], .edit-form input[type="file"], .edit-form select {
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

table td, table th { text-align:center; }
</style>
</head>
<body>
<nav>
    <div class="logo"><img src="../VNM logo.png" alt=""></div>
    <div class="navLink">
        <a href="../admin_panel/admin_panel.php">Dashboard</a>
        <a href="">Cars</a>
        <a href="" id="logout">Logout</a>
    </div>
</nav>

<main>
<button onclick="show()">Add Vehicle</button>

<!-- Add Vehicle Form -->
<form action="" method="POST" enctype="multipart/form-data" class="form show">
    <label>Image</label>
    <input type="file" name="image">

    <label>Model</label>
    <input type="text" name="model" required>

    <label>Brand</label>
    <input type="text" name="brand" required pattern="[A-Za-z\s]+" title="Only letters and spaces allowed">

    <label>Year</label>
    <input type="number" name="year" min="1900" max="2100" required>

    <label>Daily Rate</label>
    <input type="number" step="0.01" name="daily_rate" required>

    <label>Fuel Type</label>
    <input type="text" name="fuel_type" required pattern="[A-Za-z\s]+" title="Only letters and spaces allowed">

    <label>Location ID</label>
    <input type="number" name="location_id" required>

    <label>Availability</label>
    <select name="availability">
        <option value="1">Available</option>
        <option value="0">Unavailable</option>
        <option value="2">Maintenance</option>
    </select>

    <button type="submit" class="submit">Add Vehicle</button>
</form>

<h3>Vehicles Table</h3>
<table border="1" cellpadding="10" cellspacing="0" style="width:100%; font-family:Arial; margin-bottom:20px;">
<tr>
    <th>ID</th><th>Image</th><th>Model</th><th>Brand</th><th>Year</th><th>Daily Rate</th>
    <th>Fuel Type</th><th>Location ID</th><th>Availability</th><th>Actions</th>
</tr>

<?php while($row = $result->fetch_assoc()): ?>
<tr>
    <td><?= $row['car_id'] ?></td>
    <td>
        <?= !empty($row['image']) ? "<img src='uploads/cars/".$row['image']."' class='preview'>" : "No Image" ?>
    </td>
    <td><?= $row['model'] ?></td>
    <td><?= $row['car_brand'] ?></td>
    <td><?= $row['year'] ?></td>
    <td><?= $row['daily_rate'] ?></td>
    <td><?= $row['fuel_type'] ?></td>
    <td><?= $row['location_id'] ?></td>
    <td>
        <?php
            if($row['availability']==1) echo "Available";
            elseif($row['availability']==0) echo "Unavailable";
            else echo "Maintenance";
        ?>
    </td>
    <td>
        <div class="action-buttons">
            <button class="edit-btn" onclick="toggleEditForm(<?= $row['car_id'] ?>)">Edit Info</button>
            <form method="POST" onsubmit="return confirmDelete();">
                <input type="hidden" name="delete_id" value="<?= $row['car_id'] ?>">
                <button class="delete-btn" type="submit">Delete</button>
            </form>
        </div>

        <!-- Edit Form  -->
        <form id="editForm<?= $row['car_id'] ?>" class="edit-form" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="edit_id" value="<?= $row['car_id'] ?>">

            <input type="text" name="model" value="<?= $row['model'] ?>" required>
            <input type="text" name="brand" value="<?= $row['car_brand'] ?>" required pattern="[A-Za-z\s]+" title="Only letters and spaces allowed">
            <input type="number" name="year" value="<?= $row['year'] ?>" required>
            <input type="number" step="0.01" name="daily_rate" value="<?= $row['daily_rate'] ?>" required>
            <input type="text" name="fuel_type" value="<?= $row['fuel_type'] ?>" required pattern="[A-Za-z\s]+" title="Only letters and spaces allowed">
            <input type="number" name="location_id" value="<?= $row['location_id'] ?>" required>
            <select name="availability">
                <option value="1" <?= ($row['availability']==1)?'selected':'' ?>>Available</option>
                <option value="0" <?= ($row['availability']==0)?'selected':'' ?>>Unavailable</option>
                <option value="2" <?= ($row['availability']==2)?'selected':'' ?>>Maintenance</option>
            </select><br>

            Replace Image: <input type="file" name="image"><br>
            <button type="submit">Save</button>
        </form>
    </td>
</tr>
<?php endwhile; ?>
</table>

<img id="imagePopup" class="img-popup" src="" alt="Image Preview">

</main>

<script>
function show() {
    document.querySelector('.form').classList.toggle('show');
}

function toggleEditForm(id) {
    const form = document.getElementById('editForm' + id);
    const buttons = form.previousElementSibling; 
    if(form.style.display === 'block') {
        form.style.display = 'none';
        buttons.style.display = 'flex'; 
    } else {
        document.querySelectorAll('.edit-form').forEach(f => {
            f.style.display = 'none';
            f.previousElementSibling.style.display = 'flex';
        });
        form.style.display = 'block';
        buttons.style.display = 'none';
    }
}

function confirmDelete() {
    return confirm("Are you sure you want to delete this vehicle?");
}


const imagePopup = document.getElementById('imagePopup');
document.querySelectorAll('td img.preview').forEach(img=>{
    img.addEventListener('mouseenter',()=>{imagePopup.src=img.src; imagePopup.style.display='block';});
    img.addEventListener('mouseleave',()=>{imagePopup.style.display='none';});
});
</script>
</body>
</html>
