<?php
// Ensure no output before headers
ob_start();

session_start();
require_once('../includes/config.php');

// Set proper headers FIRST
header('Content-Type: application/json');

try {
    // Check session
    if (!isset($_SESSION['client_id'])) {
        throw new Exception('Unauthorized', 401);
    }

    // Validate input
    if (empty($_POST['request_id']) || empty($_POST['rating']) || empty($_POST['review'])) {
        throw new Exception('All fields are required', 400);
    }

    $client_id = $_SESSION['client_id'];
    $request_id = (int)$_POST['request_id'];
    $rating = (int)$_POST['rating'];
    $review = trim($_POST['review']);

    // Validate rating range
    if ($rating < 1 || $rating > 5) {
        throw new Exception('Rating must be between 1 and 5', 400);
    }

    // Get request details
    $stmt = $conn->prepare("SELECT professional_id FROM requests WHERE id = ? AND client_id = ?");
    $stmt->bind_param("ii", $request_id, $client_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Invalid request', 404);
    }
    
    $request = $result->fetch_assoc();
    $professional_id = $request['professional_id'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert review
        $stmt = $conn->prepare("INSERT INTO reviews (professional_id, client_id, rating, review, request_id, reviewed) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("iiisi", $professional_id, $client_id, $rating, $review, $request_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Database error: ' . $conn->error, 500);
        }

        // Alternatively, if you need to update an existing record:
        // $stmt = $conn->prepare("UPDATE reviews SET rating = ?, review = ?, reviewed = 1 WHERE request_id = ? AND client_id = ?");
        // $stmt->bind_param("isii", $rating, $review, $request_id, $client_id);
        // $stmt->execute();

        // Commit transaction
        $conn->commit();

        // Success response
        echo json_encode([
            'success' => true,
            'message' => 'Review submitted successfully'
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    // Error response
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Ensure no extra output
ob_end_flush();
?>