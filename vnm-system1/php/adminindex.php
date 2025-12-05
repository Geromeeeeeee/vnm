<?php
    error_reporting(E_ALL);
	ini_set('display_errors', 1);
    include 'db.php';

    $query = "SELECT MONTH(rental_date) AS m, SUM(total_cost) AS total_sales FROM rental_requests GROUP BY MONTH(rental_date) ORDER BY MONTH(rental_date)";

    $result = mysqli_query($conn, $query);

    $table_1_data = [];
    if (mysqli_num_rows($result)==0) {
        $table_1_data[] = ['No data', 0];
    }else{
        while($row = mysqli_fetch_assoc($result)){
            $monthNum = (int)$row['m'];

            $monthName = date("F", mktime(0, 0, 0, $monthNum, 1));

            $table_1_data[] = [$monthName, (float)$row['total_sales']];
        }
    }

    $table_1_json = json_encode($table_1_data);
    
    $query2 = "SELECT SUM(total_cost) AS total_sales FROM rental_requests";
    $result2 = mysqli_query($conn, $query2);
    $sales_sum = mysqli_fetch_assoc($result2);
    $total_sales_value = $sales_sum['total_sales'];

    $query3 = "SELECT COUNT(*) AS total_users FROM users";
    $result3 = mysqli_query($conn, $query3);
    $users = mysqli_fetch_assoc($result3);
    $total_users = $users['total_users'];

    $query4 = "SELECT COUNT(*) AS total_cars FROM cars";
    $result4 = mysqli_query($conn, $query4);
    $cars = mysqli_fetch_assoc($result4);
    $total_cars = $cars['total_cars'];

    $query5 = "SELECT COUNT(*) AS available FROM cars WHERE availability = 1";
    $result5 = mysqli_query($conn, $query5);
    $available = mysqli_fetch_assoc($result5);
    $available_cars = $available['available'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/common.css ?v=1.2">
    <link rel="stylesheet" href="/vnm-system1/css/admin_panel.css ?v=1.14">
    <title>VNM Admin</title>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
      google.charts.load('current', {'packages':['corechart']});
      google.charts.setOnLoadCallback(drawChart);
      function drawChart() {

        var chartData = <?php echo $table_1_json?>;

        chartData.unshift(['month', 'total sales']);

        var dataTable = google.visualization.arrayToDataTable(chartData);

        var options = {
          hAxis: {title: 'Month'},
          vAxis: {
            title: 'Sales',
            minValue: 0
        },
        colors: ['#555']
        };

        var chart = new google.visualization.ColumnChart(document.getElementById('columnchart'));

        chart.draw(dataTable, options);
      }
    </script>
</head>
<body>
    <nav>
        <div class="logo">
            <img src="../photos/VNM logo.png" alt="">
        </div>
        <div class="navLink">
            <a href="/vnm-system1/php/adminindex.php">Dashboard</a>
            <a href="/vnm-system1/php/cars/cars.php">Cars</a>
            <a href="/vnm-system1/php/rentals.php">Rentals</a>
            <a href="/vnm-system1/php/landing.php" id="logout">Logout</a>
        </div>
    </nav>

    <main>
        <section id="main-top">
            <div class="total-users">
                <h3>Total Users:</h3>
                <?php   
                    echo"<h1>{$total_users}</h1>";
                ?>
            </div>
            <div class="total-cars">
                <h3>Total Cars:</h3>
                <?php   
                    echo"<h1>{$total_cars}</h1>";
                ?>
            </div>
            <div class="available-cars">
                <h3>Available Cars:</h3>
                <?php   
                    echo"<h1>{$available_cars}</h1>";
                ?>
            </div>
        </section>
        <h3>Total Sales</h3>
        <section class="total-sales">
            <div class="total-sales-value">
                <?php
                    if($total_sales_value == 0 || $total_sales_value === null){
                        echo "<h3>No sales yet.</h3>";
                    } else{
                        echo "<h1>₱{$total_sales_value}</h1>";
                    }
                ?>
            </div>
        </section>
        <h3>Monthly Sales</h3>
        <section class="monthly-sales">
            <div id="columnchart"></div>
        </section>
    </main>
</body>
</html>