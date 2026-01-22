<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user']) || !isset($_GET['request_id'])) {
    die("Unauthorized access.");
}

$request_id = (int)$_GET['request_id'];
$current_user_id = (int)$_SESSION['user'];

// Flexible query to handle different possible column names for User ID
// Include return details to show accurate final costs
$sql = "SELECT rr.*, c.car_brand, c.model, c.plate_no, u.*, 
        rd.final_refund_amount, rd.damage_fee, rd.late_fee,
        rrr.total_deducted_cost
        FROM rental_requests rr 
        INNER JOIN cars c ON rr.car_id = c.car_id 
        INNER JOIN users u ON rr.user_id = u.user_id 
        LEFT JOIN rental_return_details rd ON rr.request_id = rd.request_id
        LEFT JOIN rental_return_requests rrr ON rr.request_id = rrr.request_id
        WHERE rr.request_id = ? AND rr.user_id = ?";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    // Fallback if your table uses 'id' instead of 'user_id'
    $sql = "SELECT rr.*, c.car_brand, c.model, c.plate_no, u.*, 
            rd.final_refund_amount, rd.damage_fee,
            rrr.total_deducted_cost
            FROM rental_requests rr 
            INNER JOIN cars c ON rr.car_id = c.car_id 
            INNER JOIN users u ON rr.user_id = u.id 
            LEFT JOIN rental_return_details rd ON rr.request_id = rd.request_id
            LEFT JOIN rental_return_requests rrr ON rr.request_id = rrr.request_id
            WHERE rr.request_id = ? AND rr.user_id = ?";
    $stmt = $conn->prepare($sql);
}

$stmt->bind_param("ii", $request_id, $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) die("No record found.");

$display_name = $data['name'] ?? $data['full_name'] ?? $data['username'] ?? "Customer";

// Calculate the final amount charged (after early return deductions/refunds)
$original_cost = (float)$data['total_cost'];
$refund_amount = (float)($data['final_refund_amount'] ?? 0);
$damage_fee = (float)($data['damage_fee'] ?? 0);
$late_fee = (float)($data['late_fee'] ?? 0);

// Final amount is: Original Cost - Refund + Damage Fee + Late Fee
$final_amount = $original_cost - $refund_amount + $damage_fee + $late_fee;

// Check if there was an early return or late return
$is_early_return = !empty($data['final_refund_amount']) && $data['final_refund_amount'] > 0;
$is_late_return = !empty($data['late_fee']) && $data['late_fee'] > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt_<?php echo $request_id; ?></title>
    <style>
        /* PDF/Print Styling */
        body { font-family: 'Helvetica', Arial, sans-serif; background: #fff; padding: 0; margin: 0; }
        .receipt-container { 
            width: 100%; 
            max-width: 800px; 
            margin: 20px auto; 
            padding: 40px; 
            border: 1px solid #eee;
        }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; }
        .info-table { width: 100%; margin-top: 30px; border-collapse: collapse; }
        .info-table td { padding: 10px; border-bottom: 1px solid #f4f4f4; }
        .total-section { 
            margin-top: 30px; 
            padding: 20px; 
            background: #f9f9f9; 
            text-align: right; 
            font-size: 1.4em; 
            font-weight: bold; 
        }
        .footer { margin-top: 50px; text-align: center; font-size: 0.9em; color: #777; }

        /* Hide buttons when printing */
        @media print {
            .no-print { display: none; }
            .receipt-container { border: none; margin: 0; padding: 0; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="background: #333; color: white; padding: 15px; text-align: center;">
        <p style="margin:0;">Receipt Generated! <strong>Select "Save as PDF"</strong> in the print destination.</p>
        <button onclick="window.print()" style="margin-top:10px; padding: 10px 20px; cursor:pointer;">Open Print/PDF Dialog</button>
    </div>

    <div class="receipt-container">
        <div class="header">
            <h1 style="margin:0;">VNM CAR RENTAL</h1>
            <p>Official Transaction Receipt</p>
        </div>

        <table class="info-table">
            <tr>
                <td><strong>Receipt Number:</strong></td>
                <td align="right">#<?php echo $data['request_id']; ?></td>
            </tr>
            <tr>
                <td><strong>Customer Name:</strong></td>
                <td align="right"><?php echo htmlspecialchars($display_name); ?></td>
            </tr>
            <tr>
                <td><strong>Vehicle Unit:</strong></td>
                <td align="right"><?php echo $data['car_brand'] . " " . $data['model']; ?></td>
            </tr>
            <tr>
                <td><strong>Plate Number:</strong></td>
                <td align="right"><?php echo $data['plate_no']; ?></td>
            </tr>
            <tr>
                <td><strong>Rental Period:</strong></td>
                <td align="right"><?php echo $data['rental_date']; ?> (<?php echo $data['rental_duration_days']; ?> Days)</td>
            </tr>
            <tr>
                <td><strong>Status:</strong></td>
                <td align="right"><?php echo strtoupper(str_replace('_', ' ', $data['request_status'])); ?></td>
            </tr>
            <?php if ($is_early_return || $is_late_return || $damage_fee > 0): ?>
            <tr style="border-top: 2px solid #eee;">
                <td colspan="2" style="padding-top: 20px; padding-bottom: 5px;">
                    <strong style="color: #007bff;">Payment Breakdown<?php echo $is_early_return ? ' (Early Return)' : ($is_late_return ? ' (Late Return)' : ''); ?>:</strong>
                </td>
            </tr>
            <tr>
                <td>Original Total Cost:</td>
                <td align="right">₱<?php echo number_format($original_cost, 2); ?></td>
            </tr>
            <?php if ($refund_amount > 0): ?>
            <tr>
                <td>Refund (Unused Days):</td>
                <td align="right" style="color: #28a745;">-₱<?php echo number_format($refund_amount, 2); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($late_fee > 0): ?>
            <tr>
                <td>Late Return Fee:</td>
                <td align="right" style="color: #dc3545;">+₱<?php echo number_format($late_fee, 2); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($damage_fee > 0): ?>
            <tr>
                <td>Damage/Extra Fee:</td>
                <td align="right" style="color: #dc3545;">+₱<?php echo number_format($damage_fee, 2); ?></td>
            </tr>
            <?php endif; ?>
            <?php endif; ?>
        </table>

        <div class="total-section">
            <?php if ($is_early_return || $is_late_return): ?>
            FINAL AMOUNT CHARGED: ₱<?php echo number_format($final_amount, 2); ?>
            <?php else: ?>
            TOTAL PAID: ₱<?php echo number_format($final_amount, 2); ?>
            <?php endif; ?>
        </div>

        <div class="footer">
            <p>Thank you for choosing our business!</p>
             <p>VNM Car Rentals</p>
        </div>
    </div>

    <script>
        // Trigger the print dialog automatically
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>