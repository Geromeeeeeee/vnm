<?php
    $host = "sql203.infinityfree.com";
    $user = "if0_40432101";
    $pass = "hbrUmYHRSY3v";
    $db   = "if0_40432101_vnm";

    $conn = mysqli_connect($host, $user, $pass, $db);

    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
?>
