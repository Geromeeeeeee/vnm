<?php
// rentalsc.php (Customer View)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'db.php'; 

// Require login - show only rentals for the logged-in user
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$current_user_id = (int) $_SESSION['user'];

// 1. Fetch UPCOMING/ACTIVE Rentals (Pending only - awaiting approval)
$upcoming_sql = "
    SELECT 
        rr.request_id, 
        rr.rental_date, 
        rr.rental_time,
        rr.rental_duration_days,
        rr.total_cost,
        rr.request_status,
        c.car_brand,
        c.model,
        c.daily_rate
    FROM rental_requests rr
    INNER JOIN cars c ON rr.car_id = c.car_id
    WHERE rr.request_status IN ('Pending')
        AND rr.user_id = ?
    ORDER BY rr.rental_date ASC, rr.rental_time ASC";

$stmt_upcoming = $conn->prepare($upcoming_sql);
$stmt_upcoming->bind_param('i', $current_user_id);
$stmt_upcoming->execute();
$upcoming_details = $stmt_upcoming->get_result();

// 2. Fetch HISTORY Rentals (Approved, Rejected, or Cancelled)
$history_sql = "
    SELECT 
        rr.request_id, 
        rr.rental_date, 
        rr.rental_time,
        rr.rental_duration_days,
        rr.total_cost,
        rr.request_status,
        c.car_brand,
        c.model
    FROM rental_requests rr
    INNER JOIN cars c ON rr.car_id = c.car_id
    WHERE rr.request_status IN ('Approved', 'Rejected', 'Cancelled')
        AND rr.user_id = ?
    ORDER BY rr.rental_date DESC, rr.rental_time DESC";

$stmt_history = $conn->prepare($history_sql);
$stmt_history->bind_param('i', $current_user_id);
$stmt_history->execute();
$history_details = $stmt_history->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/rent_form.css">
    <link rel="stylesheet" href="../css/rental.css?v=1.2"> 
    <title>My Rentals</title>
</head>
<body>
    <nav>
        <h3>VNM Car Rental</h3>
        <a href="../php/login-dashboard.php">Home</a>
        <a href="#cars">Cars</a>
        <a href="#aboutUs">About</a>
        <a href="">Contact</a>
        <a href="../php/rentalsc.php">Rentals</a>
        <button popovertarget="logout">Logout</button>
    </nav>
    <main>
        <section id="upcoming">
            <h3>Pending Rental Requests (Awaiting Approval)</h3>
            <?php if ($upcoming_details->num_rows > 0): ?>
                <?php while ($row = $upcoming_details->fetch_assoc()): 
                    $request_id = htmlspecialchars($row['request_id']);
                    $rental_datetime = date('Y-m-d', strtotime($row['rental_date']));
                    $rental_date_display = date('F j, Y', strtotime($row['rental_date']));
                    $car_display = "{$row['car_brand']} {$row['model']}";
                    $status_text = htmlspecialchars($row['request_status']);
                    $status_color = ($status_text === 'Approved') ? 'green' : (($status_text === 'Pending') ? 'orange' : 'black');
                ?>
                <div class="rental-detail">
                    <div class="detail">
                        <h4><?= $rental_date_display ?> @ <?= htmlspecialchars($row['rental_time']) ?></h4>
                        <p><?= $car_display ?></p>
                        <p>Status: <span style="font-weight: bold; color: <?= $status_color ?>;"><?= $status_text ?></span></p>
                    </div>
                    
                    <form action="cancel_action.php" method="POST">
                        <input type="hidden" name="request_id" value="<?= $request_id ?>">
                        
                        <?php if ($status_text === 'Pending'): ?>
                            <?php endif; ?>
                        
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" onclick="return confirm('Are you sure you want to cancel this rental?');">Cancel</button>
                    </form>
                </div>
                
                <?php if ($status_text === 'Pending'): ?>
                    <?php endif; ?>
                
                <?php endwhile; ?>
            <?php else: ?>
                <p>You have no upcoming or approved rentals.</p>
            <?php endif; ?>
        </section>
        
        <hr>

        <section id="history">
            <h3>Rental History (Approved, Rejected & Cancelled)</h3>
            <?php if ($history_details->num_rows > 0): ?>
                <?php while ($row = $history_details->fetch_assoc()): 
                    $rental_date_display = date('F j, Y', strtotime($row['rental_date']));
                    $car_display = "{$row['car_brand']} {$row['model']}";
                    $status_text = htmlspecialchars($row['request_status']);
                    $status_color = $status_text === 'Rejected' ? 'red' : 'grey';
                ?>
                <div class="rental-detail">
                    <div class="detail">
                        <h4><?= $rental_date_display ?> @ <?= htmlspecialchars($row['rental_time']) ?></h4>
                        <p><?= $car_display ?></p>
                        <p>Status: <span style="font-weight: bold; color: <?= $status_color ?>;"><?= $status_text ?></span></p>
                    </div>
                    <div class="action-status">
                        <p>Cost: ₱<?= number_format($row['total_cost'], 2) ?></p>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>You have no past rental history.</p>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
<?php
$stmt_upcoming->close();
$stmt_history->close();
$conn->close();
?>