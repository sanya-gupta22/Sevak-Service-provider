<?php
// Database configuration
$host = "localhost";
$user = "root";
$password = "";
$database = "sevak_db";

// Create connection
$conn = new mysqli($host, $user, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function process_expired_requests($conn) {
    // Start transaction for data integrity
    $conn->begin_transaction();
    
    try {
        // 1. First, get all expired requests (date passed) that are still pending or upcoming
        $expired_requests = $conn->query("
            SELECT id 
            FROM requests 
            WHERE date < CURDATE() 
              AND ((status = 'pending' AND complete = 'requested') 
                   OR (status = 'accepted' AND complete = 'upcoming'))
        ");
        
        $deleted_count = 0;
        $related_deletions = 0;
        
        if ($expired_requests->num_rows > 0) {
            while ($request = $expired_requests->fetch_assoc()) {
                $request_id = $request['id'];
                
                // 2. Delete related records first to maintain referential integrity
                
                // Delete from payments table
                $conn->query("DELETE FROM payments WHERE request_id = $request_id");
                $related_deletions += $conn->affected_rows;
                
                // Delete from reviews table
                $conn->query("DELETE FROM reviews WHERE request_id = $request_id");
                $related_deletions += $conn->affected_rows;
                
                // 3. Finally, delete the request itself
                $conn->query("DELETE FROM requests WHERE id = $request_id");
                $deleted_count += $conn->affected_rows;
            }
        }
        
        // Commit the transaction if all queries succeeded
        $conn->commit();
        
        
        
    } catch (Exception $e) {
        // Roll back the transaction if any error occurred
        $conn->rollback();
        
        return [
            'error' => $e->getMessage(),
            'deleted_requests' => 0,
            'related_records_deleted' => 0
        ];
    }
}

// Set charset to utf8mb4 for proper character encoding
$conn->set_charset("utf8mb4");

// Error reporting (for development)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Execute the function (you might want to call this from a cron job)
$result = process_expired_requests($conn);

// For testing/debugging - view the results
echo "<pre>";
print_r($result);
echo "</pre>";
?>