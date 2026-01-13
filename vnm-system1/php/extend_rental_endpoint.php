<?php
session_start();
// --- Configuration: Adjust your database connection here ---
$servername = "127.0.0.1";
$username = "root"; 
$password = "";     
$dbname = "vnm";    

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => "Connection failed: " . $conn->connect_error]));
}
// ----------------------------------------------------------

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

// 1. Check if the user is logged in
if (!isset($_SESSION['user'])) { 
    $response['message'] = 'User not logged in.';
    echo json_encode($response);
    exit();
}
$user_id = (int) $_SESSION['user'];

// 2. Get and validate form data
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit();
}

$request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
$days_to_extend = isset($_POST['days_to_extend']) ? intval($_POST['days_to_extend']) : 0;
$new_end_date = $_POST['new_end_date'] ?? null; 
$additional_cost = isset($_POST['additional_cost']) ? floatval($_POST['additional_cost']) : 0.00;
$payment_method = $conn->real_escape_string($_POST['payment_method'] ?? '');
$payment_reference_no = $conn->real_escape_string($_POST['payment_reference_no'] ?? '');

if (!$request_id || $days_to_extend <= 0 || empty($new_end_date) || $additional_cost <= 0 || empty($payment_method) || empty($payment_reference_no) || !isset($_FILES['payment_proof_path'])) {
    $response['message'] = 'Missing or invalid required extension request fields.';
    echo json_encode($response);
    exit();
}

// ====================================================================
// VALIDATION RULES (48-Hour Deadline & Duration Cap)
// ====================================================================
$orig_stmt = $conn->prepare("SELECT rental_date, rental_duration_days FROM rental_requests WHERE request_id = ?");
$orig_stmt->bind_param("i", $request_id);
$orig_stmt->execute();
$rental_data = $orig_stmt->get_result()->fetch_assoc();
$orig_stmt->close();

if ($rental_data) {
    $original_duration = (int)$rental_data['rental_duration_days'];
    $rental_start = $rental_data['rental_date'];
    
    // Calculate current scheduled return date
    $current_return_date = date('Y-m-d', strtotime($rental_start . " + $original_duration days"));
    $now = new DateTime();
    $return_deadline = new DateTime($current_return_date);
    
    // UPDATED RULE: Must be submitted at least 48 hours (2 days) before return
    $extension_cutoff = clone $return_deadline;
    $extension_cutoff->modify('-48 hours');

    if ($now > $extension_cutoff) {
        $response['message'] = 'Extension Denied: Extensions must be requested at least 2 days (48 hours) before your scheduled return date.';
        echo json_encode($response);
        exit();
    }

    // Rule 2: Check if extension exceeds original duration
    if ($days_to_extend > $original_duration) {
        $response['message'] = "Extension Denied: You can only extend for a maximum of $original_duration more days.";
        echo json_encode($response);
        exit();
    }
}
// ====================================================================

// 3. File Upload Handling
$upload_dir = '../uploads/extension_proofs/'; 
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$file_info = $_FILES['payment_proof_path'];
$file_name = $file_info['name'];
$file_tmp = $file_info['tmp_name'];
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
$new_file_name = 'proof_' . $request_id . '_' . uniqid() . '.' . $file_ext;
$file_path = $upload_dir . $new_file_name;

if (!in_array($file_ext, ['jpg', 'jpeg', 'png', 'webp', 'avif'])) {
    $response['message'] = 'Invalid file type. Only JPG, PNG, WEBP, and AVIF are allowed.';
    echo json_encode($response);
    exit();
}

if (move_uploaded_file($file_tmp, $file_path)) {
    // 4. Database Insertion
    $status = 'Paid_Pending_Approval'; 

    $sql = "INSERT INTO rental_extension_requests 
                (request_id, user_id, days_to_extend, additional_cost, new_end_date, payment_proof_path, payment_method, payment_reference_no, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiidsssss", 
        $request_id, 
        $user_id, 
        $days_to_extend, 
        $additional_cost, 
        $new_end_date, 
        $file_path, 
        $payment_method, 
        $payment_reference_no, 
        $status
    );

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Rental extension request submitted successfully. Waiting for admin approval.';
    } else {
        @unlink($file_path);
        $response['message'] = 'Database error: ' . $stmt->error;
    }
    $stmt->close();
} else {
    $response['message'] = 'Failed to upload payment proof.';
}

$conn->close();
echo json_encode($response);
?>