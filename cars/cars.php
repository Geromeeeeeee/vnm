<?php
    include './db.php'
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../common.css">
    <link rel="stylesheet" href="cars.css">
    <title>VNM/Cars</title>
</head>
<body>
    <nav>
        <div class="logo">
            <img src="../VNM logo.png" alt="">
        </div>
        <div class="navLink">
            <a href="../index.php">Dashboard</a>
            <a href="">Cars</a>
            <a href="" id="logout">Logout</a>

        </div>
    </nav>
    <main>
        <button onclick="show()">Add Vehicle</button>
        <form action="" enctype="multipart/form-data" class="form">
            <div id="imgInput">
                <label for="">Image</label>
                <button onclick="addImage()" type="button">Add Image</button>
                <input type="file" name="" id="file" class="file">
            </div>
            <div class="inputs">
                <label for="">Vehicle Name</label>
                <input type="text" name="" id="">
            </div>
            <div class="inputs">
                <label for="">Plate Number</label>
                <input type="text" name="" id="">
            </div>
            <button type="submit" class="submit">
                Add Vehicle
            </button>
        </form>
        <h3>Vehicles</h3>
        <section>
            <div class="vehicle">
                <div class="vehicleContainer">
                    <div class="vehicleimg">
                        <img src="" alt="">
                    </div>
                    <div class="vehicleinfo">
                        <p>Vehicle Name: </p>
                        <p>Plate Number: </p>
                    </div>
                </div>

                <button class="editInfo" type="button" onclick="edit()">Edit Info</button>

                    <form action="" enctype="multipart/form-data" class="edit">
                        <div id="imgInput">
                            <label for="">Image</label>
                            <button onclick="addImage()" type="button">Add Image</button>
                            <input type="file" name="" id="file" class="file">
                        </div>
                        <div class="inputs">
                            <label for="">Vehicle Name</label>
                            <input type="text" name="" id="">
                        </div>
                        <div class="inputs">
                            <label for="">Plate Number</label>
                            <input type="text" name="" id="">
                        </div>
                        <button type="submit" class="submit">
                            Confirm Edit
                        </button>
                    </form>
            </div>
        </section>
    </main>

    <script src="cars.js"></script>
</body>
</html>