<?php
session_start();
require_once '../includes/config.php';

// Check if professional is logged in
if (!isset($_SESSION['professional_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get and validate input
$data = json_decode(file_get_contents('php://input'), true);
$booking_id = isset($data['booking_id']) ? (int)$data['booking_id'] : null;
$reason = isset($data['reason']) ? trim($data['reason']) : null;

if (!$booking_id || empty($reason)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

$professional_id = $_SESSION['professional_id'];

// Start transaction
$conn->begin_transaction();



try {
    // Verify the booking belongs to this professional and is upcoming
    $check_stmt = $conn->prepare("SELECT id FROM requests WHERE id = ? AND professional_id = ? AND status = 'accepted' AND complete = 'upcoming'");
    $check_stmt->bind_param("ii", $booking_id, $professional_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        throw new Exception('Booking not found or not eligible for cancellation');
    }
    $check_stmt->close();

    // Update the booking status to cancelled
    $update_stmt = $conn->prepare("UPDATE requests SET complete = 'cancelled', reason = ?, updated_at = NOW() WHERE id = ?");
    $update_stmt->bind_param("si", $reason, $booking_id);
    $update_stmt->execute();
    
    if ($update_stmt->affected_rows === 0) {
        throw new Exception('Failed to update booking status');
    }
    $update_stmt->close();

    // Update payment status if exists
    $payment_stmt = $conn->prepare("UPDATE payments SET status = 'cancelled' WHERE request_id = ? AND professional_id = ?");
    $payment_stmt->bind_param("ii", $booking_id, $professional_id);
    $payment_stmt->execute();
    $payment_stmt->close();

    // Commit transaction
    $conn->commit();

    // Send success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Booking cancelled successfully'
    ]);

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    
    // Log error
    error_log('Cancellation error: ' . $e->getMessage());
    
    // Send error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}