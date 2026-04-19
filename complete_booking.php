<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

// Check if professional is logged in
if (!isset($_SESSION['professional_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Validate booking ID
if (!isset($_POST['booking_id'])) {
    echo json_encode(['success' => false, 'message' => 'Booking ID required']);
    exit();
}

$booking_id = intval($_POST['booking_id']);
$professional_id = $_SESSION['professional_id'];

try {
    // Start transaction
    $conn->begin_transaction();
    
    // 1. Verify the booking exists and belongs to this professional
    $stmt = $conn->prepare("SELECT * FROM requests WHERE id = ? AND professional_id = ? AND status = 'accepted' AND complete = 'upcoming'");
    $stmt->bind_param("ii", $booking_id, $professional_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$booking) {
        throw new Exception('Booking not found or not eligible for completion');
    }
    
    // 2. Check if booking date is in the future
    $today = date('Y-m-d');
    if ($booking['date'] > $today) {
        throw new Exception('Cannot complete future bookings');
    }
    
    // 3. Update the booking status to completed
    $update_request = $conn->prepare("UPDATE requests SET complete = 'complete', updated_at = NOW() WHERE id = ?");
    $update_request->bind_param("i", $booking_id);
    $update_request->execute();
    $update_request->close();
    
    // 4. Update payment status to 'paid'
    $update_payment = $conn->prepare("UPDATE payments SET status = 'paid', updated_at = NOW() WHERE request_id = ? AND professional_id = ?");
    $update_payment->bind_param("ii", $booking_id, $professional_id);
    $update_payment->execute();
    
    if ($update_payment->affected_rows === 0) {
        // If no payment record exists, create one
        $insert_payment = $conn->prepare("INSERT INTO payments (professional_id, client_id, request_id, date, client_name, professional_name, service_name, payment, status, created_at) 
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'paid', NOW())");
        $insert_payment->bind_param("iiissssd", 
            $professional_id,
            $booking['client_id'],
            $booking_id,
            $booking['date'],
            $booking['client_name'],
            $booking['professional_name'],
            $booking['service_name'],
            $booking['payment']
        );
        $insert_payment->execute();
        $insert_payment->close();
    }
    $update_payment->close();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Booking marked as completed']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>