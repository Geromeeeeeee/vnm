<?php
    error_reporting(E_ALL);
	ini_set('display_errors', 1);
    include 'db.php';

    $updated_query = " SELECT 
                    YEAR(rental_date) AS y, 
                    MONTH(rental_date) AS m, 
                    SUM(final_amount_settled) 
                    AS total_sales 
                    FROM rental_summary 
                    GROUP BY YEAR(rental_date), MONTH(rental_date) 
                    ORDER BY YEAR(rental_date), MONTH(rental_date)";

    $result = mysqli_query($conn, $updated_query);

    $table_1_data = [];
    if (mysqli_num_rows($result)==0) {
        $table_1_data[] = ['No data', 0];
    }else{
        while($row = mysqli_fetch_assoc($result)){
            $monthNum = (int)$row['m'];
            $yearNum = $row['y'];

            $monthName = date("F", mktime(0, 0, 0, $monthNum, 1));

            $label = $monthName . " " . $yearNum;

            $table_1_data[] = [$label, (float)$row['total_sales']];
        }
    }

    $table_1_json = json_encode($table_1_data);
    
    $query2 = "SELECT SUM(final_amount_settled) AS total_sales FROM rental_summary";
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

    $query6 = "SELECT 
                c.model,
                c.plate_no,
                COUNT(rs.request_id) AS rental_count,
                SUM(IFNULL(rs.final_amount_settled, 0)) AS total_income
            FROM cars c
            LEFT JOIN rental_requests r
                ON c.car_id = r.car_id
            LEFT JOIN rental_summary rs
                ON r.request_id = rs.request_id
            GROUP BY c.model, c.plate_no
            ORDER BY total_income DESC;
";
    $result6 = mysqli_query($conn, $query6);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

    <title>VNM Admin</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

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
        colors: ['#666']
        };

        var chart = new google.visualization.ColumnChart(document.getElementById('columnchart'));

        chart.draw(dataTable, options);
      }
    </script>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
        <aside class="main-sidebar sidebar-light-primary elevation-4 layout-fixed">
  <a href="/vnm-system1/php/adminindex.php" class="brand-link">
    <img src="/vnm-system1/photos/VNM logo.png" 
         alt="VNM Logo" 
         class="brand-image img-square "
         style="opacity: .8">
    <span class="brand-text font-weight-light">VNM Admin</span>
  </a>
  <div class="sidebar">
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" 
          data-widget="treeview" role="menu" data-accordion="false">
        <li class="nav-item">
          <a href="/vnm-system1/php/adminindex.php" class="nav-link bg-gray">
            <p>Dashboard</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="/vnm-system1/php/cars/cars.php" class="nav-link">
            <p>Cars</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="/vnm-system1/php/rentals.php" class="nav-link">
            <p>Rentals</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="/vnm-system1/php/manage_accounts.php" class="nav-link">
            <p>Accounts</p>
          </a>
        </li>
      </ul>
    </nav>
  </div>
</aside>
   <div class="content-wrapper">

    <div class="content-header">
        <div class="container-fluid">
            <h1 class="m-0">Dashboard</h1>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <section id="main-top" class="row">
                <div class="col-md-4">
                    <div class="small-box bg-info bg-gray">
                        <div class="inner">
                            <h3><?= $total_users ?></h3>
                            <p>Total Users</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-users text-white"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="small-box bg-success bg-gray">
                        <div class="inner">
                            <h3><?= $total_cars ?></h3>
                            <p>Total Cars</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-car text-white"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="small-box bg-warning bg-gray">
                        <div class="inner">
                            <h3><?= $available_cars ?></h3>
                            <p>Available Cars</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-car-side text-white"></i>
                        </div>
                    </div>
                </div>
            </section>

            <h3>Total Sales</h3>
            <section class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="total-sales-value">
                                <?php
                                    if($total_sales_value == 0 || $total_sales_value === null){
                                        echo "<h3>No sales yet.</h3>";
                                    } else{
                                        echo "<h1>₱{$total_sales_value}</h1>";
                                    }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <h3>Monthly Sales</h3>
            <section class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div id="columnchart" style="height: 400px;"></div>
                        </div>
                    </div>
                </div>
            </section>

            <h3>Rental Frequency</h3>
            <section class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body table-responsive p-0">
                            <table class="table table-hover text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Model</th>
                                        <th>Plate No.</th>
                                        <th>Rental Frequency</th>
                                        <th>Total Income</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                        while($row6 = mysqli_fetch_assoc($result6)){
                                            echo "<tr>";
                                            echo "<td>{$row6['model']}</td>";
                                            echo "<td>{$row6['plate_no']}</td>";
                                            echo "<td>" . ($row6['rental_count'] < 1 ? "No rentals" : $row6['rental_count']) . "</td>";
                                            echo "<td>{$row6['total_income']}</td>";
                                            echo "</tr>";
                                        }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

        </div>
    </section>
</div>
</body>
</html>