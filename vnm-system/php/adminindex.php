<?php
    error_reporting(E_ALL);
	ini_set('display_errors', 1);
    include 'db.php'
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/common.css ?v=1.2">
    <link rel="stylesheet" href="../admin_panel/admin_panel.css">
    <title>VNM Admin</title>
</head>
<body>
    <nav>
        <div class="logo">
            <img src="../photos/VNM logo.png" alt="">
        </div>
        <div class="navLink">
            <a href="/vnm-system/php/adminindex.php">Dashboard</a>
            <a href="/vnm-system/php/cars/cars.php">Cars</a>
            <a href="/vnm-system/php/rentals.php">Rentals</a>
            <a href="/vnm-system/php/landing.php" id="logout">Logout</a>
        </div>
    </nav>
    <main>
        
    </main>
</body>
</html>